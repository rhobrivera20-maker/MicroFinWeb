<?php
require_once '../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once '../../microfin_backend/config/db_connect.php';
require_once '../../microfin_backend/auth/totp.php';
require_once '../../microfin_backend/auth/login_activity.php';
require_once __DIR__ . '/super_admin_auth.php';

$pending = $_SESSION['pending_2fa'] ?? null;
if (!is_array($pending) || ($pending['context'] ?? '') !== 'super_admin' || empty($pending['user_id'])) {
    header('Location: login.php');
    exit;
}

if ((time() - (int) $pending['created_at']) > 600) {
    unset($_SESSION['pending_2fa']);
    header('Location: login.php?expired=1');
    exit;
}

$pendingUserId = (int) $pending['user_id'];
$error = '';

$stmt = $pdo->prepare("SELECT user_id, username, ui_theme, force_password_change, status, two_fa_enabled, two_fa_secret, two_fa_recovery_codes FROM users WHERE user_id = ? AND user_type = 'Super Admin' AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$pendingUserId]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || (int) ($admin['two_fa_enabled'] ?? 0) !== 1) {
    unset($_SESSION['pending_2fa']);
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string) ($_POST['code'] ?? ''));
    $isRecovery = isset($_POST['use_recovery']) && $_POST['use_recovery'] === '1';

    $verified = false;
    if (!$isRecovery && preg_match('/^\d{6}$/', $code)) {
        $verified = mf_totp_verify((string) $admin['two_fa_secret'], $code, 1);
    } elseif ($isRecovery && $code !== '') {
        $newJson = mf_totp_consume_recovery_code((string) $admin['two_fa_recovery_codes'], $code);
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
        mf_update_user_last_login($pdo, $pendingUserId);

        unset(
            $_SESSION['user_logged_in'],
            $_SESSION['user_id'],
            $_SESSION['username'],
            $_SESSION['tenant_id'],
            $_SESSION['tenant_name'],
            $_SESSION['tenant_slug'],
            $_SESSION['role'],
            $_SESSION['user_type'],
            $_SESSION['theme'],
            $_SESSION['pending_2fa']
        );

        $_SESSION['super_admin_logged_in'] = true;
        $_SESSION['super_admin_id'] = $pendingUserId;
        $_SESSION['super_admin_username'] = $admin['username'];
        $_SESSION['ui_theme'] = sa_super_admin_theme($admin);
        $_SESSION['super_admin_force_password_change'] = (bool) ($admin['force_password_change'] ?? false);
        $_SESSION['super_admin_onboarding_required'] = ($admin['status'] === 'Inactive' && empty($admin['force_password_change']));

        mf_create_backend_session($pdo, $pendingUserId, null, 'super_admin');

        $destination = !empty($_SESSION['super_admin_force_password_change'])
            ? 'force_change_password.php'
            : (!empty($_SESSION['super_admin_onboarding_required']) ? 'onboarding_profile.php' : 'super_admin.php');
        header('Location: ' . $destination);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars((string) ($_SESSION['ui_theme'] ?? 'light'), ENT_QUOTES, 'UTF-8'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin 2FA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; font-family:'Outfit',sans-serif; background:#f1f5f9; color:#0f172a; }
        [data-theme="dark"] body, body[data-theme="dark"] { background:#0f172a; color:#f8fafc; }
        .card { background:#fff; border-radius:18px; max-width:420px; width:100%; padding:36px 32px; box-shadow:0 16px 48px rgba(15,23,42,0.12); }
        [data-theme="dark"] .card { background:#1e293b; }
        .icon-shell { width:56px;height:56px;border-radius:14px;background:rgba(220,38,38,0.1);color:#dc2626;display:flex;align-items:center;justify-content:center;margin-bottom:18px;}
        h1 { font-size:1.45rem;margin:0 0 6px;}
        p.muted { color:#64748b;font-size:0.9rem;margin:0 0 22px;}
        label { font-size:0.78rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;display:block;margin-bottom:8px;}
        input[type="text"]{width:100%;padding:14px 16px;font-size:1.1rem;letter-spacing:0.3em;text-align:center;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#0f172a;box-sizing:border-box;font-family:'JetBrains Mono','Courier New',monospace;}
        input[type="text"]:focus{outline:none;border-color:#dc2626;box-shadow:0 0 0 3px rgba(220,38,38,0.15);}
        .btn{width:100%;padding:13px;background:#dc2626;color:#fff;border:0;border-radius:10px;font-weight:600;font-size:0.95rem;cursor:pointer;margin-top:16px;}
        .alert{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;padding:12px 14px;border-radius:10px;font-size:0.85rem;font-weight:500;margin-bottom:16px;}
        .toggle-link{background:none;border:0;color:#dc2626;font-weight:600;font-size:0.85rem;cursor:pointer;padding:0;margin-top:14px;}
        .meta{font-size:0.8rem;color:#64748b;margin-top:18px;text-align:center;}
        .meta a{color:#dc2626;text-decoration:none;font-weight:600;}
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-shell"><span class="material-symbols-outlined">verified_user</span></div>
        <h1>Two-Factor Verification</h1>
        <p class="muted" id="prompt-text">Enter the 6-digit code from your authenticator app to finish signing in.</p>
        <?php if ($error !== ''): ?><div class="alert"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
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
            var t=document.getElementById('toggle-recovery'),h=document.getElementById('use_recovery'),l=document.getElementById('code-label'),p=document.getElementById('prompt-text'),i=document.getElementById('code'),r=false;
            t.addEventListener('click',function(){r=!r;h.value=r?'1':'0';if(r){l.textContent='Recovery Code';p.textContent='Enter one of the recovery codes you saved when enabling 2FA.';i.placeholder='xxxx-xxxx';i.style.letterSpacing='0.15em';t.textContent='Use authenticator app instead';}else{l.textContent='Authentication Code';p.textContent='Enter the 6-digit code from your authenticator app to finish signing in.';i.placeholder='123456';i.style.letterSpacing='0.3em';t.textContent='Use a recovery code instead';}i.value='';i.focus();});
        })();
    </script>
</body>
</html>
