<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
require_once '../../microfin_backend/auth/totp.php';
require_once '../../microfin_backend/auth/login_activity.php';

$pending = $_SESSION['pending_2fa'] ?? null;
if (!is_array($pending) || ($pending['context'] ?? '') !== 'tenant' || empty($pending['user_id']) || empty($pending['tenant_id'])) {
    header('Location: login.php');
    exit;
}

// 10-minute pending window.
if ((time() - (int) $pending['created_at']) > 600) {
    unset($_SESSION['pending_2fa']);
    header('Location: login.php?expired=1');
    exit;
}

$error = '';
$pendingUserId = (int) $pending['user_id'];
$pendingTenantId = (string) $pending['tenant_id'];

$tenantStmt = $pdo->prepare('SELECT tenant_id, tenant_name, tenant_slug, setup_completed, setup_current_step, status FROM tenants WHERE tenant_id = ? LIMIT 1');
$tenantStmt->execute([$pendingTenantId]);
$tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC);

$userStmt = $pdo->prepare('SELECT u.user_id, u.username, u.email, u.role_id, u.user_type, u.status, u.ui_theme, u.force_password_change, u.two_fa_enabled, u.two_fa_secret, u.two_fa_recovery_codes, r.role_name, r.is_system_role FROM users u JOIN user_roles r ON u.role_id = r.role_id WHERE u.user_id = ? AND u.tenant_id = ? LIMIT 1');
$userStmt->execute([$pendingUserId, $pendingTenantId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$tenant || (int) ($user['two_fa_enabled'] ?? 0) !== 1) {
    unset($_SESSION['pending_2fa']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string) ($_POST['code'] ?? ''));
    $isRecovery = isset($_POST['use_recovery']) && $_POST['use_recovery'] === '1';

    $verified = false;
    if (!$isRecovery && preg_match('/^\d{6}$/', $code)) {
        $verified = mf_totp_verify((string) $user['two_fa_secret'], $code, 1);
    } elseif ($isRecovery && $code !== '') {
        $newJson = mf_totp_consume_recovery_code((string) $user['two_fa_recovery_codes'], $code);
        if ($newJson !== null) {
            $pdo->prepare('UPDATE users SET two_fa_recovery_codes = ? WHERE user_id = ?')->execute([$newJson, $pendingUserId]);
            $verified = true;
        }
    } else {
        $error = $isRecovery ? 'Enter a recovery code.' : 'Enter a 6-digit code from your authenticator app.';
    }

    if (!$verified && $error === '') {
        $error = $isRecovery ? 'That recovery code is invalid or already used.' : 'Invalid code. Try the latest 6-digit code.';
    }

    if ($verified) {
        // Complete login.
        mf_update_user_last_login($pdo, $pendingUserId);

        unset(
            $_SESSION['super_admin_logged_in'],
            $_SESSION['super_admin_id'],
            $_SESSION['super_admin_username'],
            $_SESSION['super_admin_force_password_change'],
            $_SESSION['super_admin_onboarding_required'],
            $_SESSION['pending_2fa']
        );

        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $pendingUserId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['tenant_id'] = $tenant['tenant_id'];
        $_SESSION['tenant_name'] = $tenant['tenant_name'];
        $_SESSION['tenant_slug'] = $tenant['tenant_slug'];
        $_SESSION['role'] = $user['role_name'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['theme'] = '#0f172a';
        $_SESSION['ui_theme'] = (($user['ui_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';

        mf_create_backend_session($pdo, $pendingUserId, (string) $tenant['tenant_id'], 'tenant');

        try {
            $pdo->prepare("INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, description) VALUES (?, ?, 'STAFF_LOGIN', 'user', '2FA verified, staff logged into the system')")->execute([$pendingUserId, $tenant['tenant_id']]);
        } catch (Throwable $e) { /* non-fatal */ }

        if ($user['user_type'] === 'Employee') {
            $is_admin = ((bool) $user['is_system_role'] || stripos($user['role_name'], 'Admin') !== false);
            if ($is_admin) {
                if (!empty($user['force_password_change'])) {
                    header('Location: force_change_password.php');
                    exit;
                }
                if (empty($tenant['setup_completed'])) {
                    $setup_step = (int) ($tenant['setup_current_step'] ?? 0);
                    if ($setup_step < 5) {
                        $pdo->prepare('UPDATE tenants SET setup_current_step = 5 WHERE tenant_id = ?')->execute([$tenant['tenant_id']]);
                    }
                    header('Location: setup_billing.php');
                    exit;
                }
                header('Location: ' . mf_tenant_admin_dashboard_url());
                exit;
            }
            if (!empty($user['force_password_change'])) {
                header('Location: ../admin_panel/staff/setup_wizard.php');
                exit;
            }
            header('Location: ../admin_panel/staff/dashboard.php');
            exit;
        }
        header('Location: ../admin_panel/admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string) ($_SESSION['ui_theme'] ?? 'light'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Verification</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Rounded" rel="stylesheet">
    <style>
        :root { --brand: #dc2626; --bg: #f8fafc; --text: #0f172a; --muted: #64748b; --border: #e2e8f0; --card: #fff; }
        [data-theme="dark"] { --bg: #0f172a; --text: #f8fafc; --muted: #94a3b8; --border: #1e293b; --card: #1e293b; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; max-width: 420px; width: 100%; padding: 36px 32px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); }
        .icon-shell { width: 56px; height: 56px; border-radius: 14px; background: rgba(220,38,38,0.1); color: var(--brand); display: flex; align-items: center; justify-content: center; margin-bottom: 18px; }
        h1 { font-size: 1.4rem; margin: 0 0 6px; }
        p.muted { color: var(--muted); font-size: 0.9rem; margin: 0 0 22px; }
        label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); display: block; margin-bottom: 8px; }
        input[type="text"] { width: 100%; padding: 14px 16px; font-size: 1.1rem; letter-spacing: 0.3em; text-align: center; border: 1px solid var(--border); border-radius: 10px; background: var(--bg); color: var(--text); box-sizing: border-box; font-family: 'JetBrains Mono', 'Courier New', monospace; }
        input[type="text"]:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(220,38,38,0.15); }
        .btn { width: 100%; padding: 13px; background: var(--brand); color: #fff; border: 0; border-radius: 10px; font-weight: 600; font-size: 0.95rem; cursor: pointer; margin-top: 16px; }
        .btn:hover { background: #b91c1c; }
        .alert { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 12px 14px; border-radius: 10px; font-size: 0.85rem; font-weight: 500; margin-bottom: 16px; }
        .toggle-link { background: none; border: 0; color: var(--brand); font-weight: 600; font-size: 0.85rem; cursor: pointer; padding: 0; margin-top: 14px; }
        .meta { font-size: 0.8rem; color: var(--muted); margin-top: 18px; text-align: center; }
        .meta a { color: var(--brand); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-shell"><span class="material-symbols-rounded">verified_user</span></div>
        <h1>Two-Factor Verification</h1>
        <p class="muted" id="prompt-text">Enter the 6-digit code from your authenticator app to finish signing in.</p>

        <?php if ($error !== ''): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="use_recovery" id="use_recovery" value="0">
            <label id="code-label">Authentication Code</label>
            <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code" maxlength="20" placeholder="123456" autofocus required>
            <button type="submit" class="btn">Verify &amp; Continue</button>
        </form>

        <button type="button" class="toggle-link" id="toggle-recovery">Use a recovery code instead</button>

        <div class="meta"><a href="login.php">Cancel and sign in again</a></div>
    </div>

    <script>
        (function () {
            var toggle = document.getElementById('toggle-recovery');
            var hidden = document.getElementById('use_recovery');
            var label = document.getElementById('code-label');
            var prompt = document.getElementById('prompt-text');
            var input = document.getElementById('code');
            var recovery = false;
            toggle.addEventListener('click', function () {
                recovery = !recovery;
                hidden.value = recovery ? '1' : '0';
                if (recovery) {
                    label.textContent = 'Recovery Code';
                    prompt.textContent = 'Enter one of the recovery codes you saved when enabling 2FA.';
                    input.placeholder = 'xxxx-xxxx';
                    input.style.letterSpacing = '0.15em';
                    toggle.textContent = 'Use authenticator app instead';
                } else {
                    label.textContent = 'Authentication Code';
                    prompt.textContent = 'Enter the 6-digit code from your authenticator app to finish signing in.';
                    input.placeholder = '123456';
                    input.style.letterSpacing = '0.3em';
                    toggle.textContent = 'Use a recovery code instead';
                }
                input.value = '';
                input.focus();
            });
        })();
    </script>
</body>
</html>
