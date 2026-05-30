<?php
/**
 * Unified 2FA management endpoint.
 *
 * Works for both tenant users (admin, staff) and super admins. The acting user
 * is detected from the existing PHP session (`$_SESSION['user_id']` or
 * `$_SESSION['super_admin_id']`).
 *
 * Accepted POST `action` values:
 *   - `2fa_setup_init`     -> generates pending secret + recovery codes (kept in $_SESSION)
 *   - `2fa_setup_confirm`  -> verifies code, persists 2FA on the user's row
 *   - `2fa_disable`        -> requires password + (TOTP code or recovery code), turns 2FA off
 *
 * All responses are JSON.
 */

require_once __DIR__ . '/../../microfin_backend/auth/session_auth.php';
mf_start_backend_session();
require_once __DIR__ . '/../../microfin_backend/config/db_connect.php';
require_once __DIR__ . '/../../microfin_backend/auth/totp.php';

header('Content-Type: application/json');

function mf_2fa_json($status, $message, array $extra = []) {
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mf_2fa_json('error', 'Method not allowed.');
}

// Resolve the acting user (tenant user or super admin).
$actingUserId = 0;
$actingContext = '';
if (!empty($_SESSION['super_admin_logged_in']) && !empty($_SESSION['super_admin_id'])) {
    $actingUserId = (int) $_SESSION['super_admin_id'];
    $actingContext = 'super_admin';
} elseif (!empty($_SESSION['user_logged_in']) && !empty($_SESSION['user_id'])) {
    $actingUserId = (int) $_SESSION['user_id'];
    $actingContext = 'tenant';
}

if ($actingUserId <= 0) {
    mf_2fa_json('error', 'You must be signed in to manage two-factor authentication.');
}

$action = $_POST['action'] ?? '';
$issuerLabel = 'MicroFin';
if ($actingContext === 'tenant' && !empty($_SESSION['tenant_name'])) {
    $issuerLabel = 'MicroFin · ' . preg_replace('/[^A-Za-z0-9 _\-\.]/', '', (string) $_SESSION['tenant_name']);
}

try {
    $userStmt = $pdo->prepare('SELECT user_id, email, username, password_hash, two_fa_enabled, two_fa_secret, two_fa_recovery_codes FROM users WHERE user_id = ? LIMIT 1');
    $userStmt->execute([$actingUserId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        mf_2fa_json('error', 'Account not found.');
    }

    if ($action === '2fa_setup_init') {
        if ((int) $user['two_fa_enabled'] === 1) {
            mf_2fa_json('error', 'Two-factor authentication is already enabled. Disable it first to set up a new device.');
        }

        $secret = mf_totp_generate_secret();
        $recoveryCodes = mf_totp_generate_recovery_codes(10);

        $_SESSION['pending_2fa_setup'] = [
            'user_id' => $actingUserId,
            'context' => $actingContext,
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
            'created_at' => time(),
        ];

        $accountLabel = (string) ($user['email'] ?? $user['username'] ?? ('user-' . $actingUserId));
        $otpauth = mf_totp_otpauth_uri($secret, $accountLabel, $issuerLabel);

        mf_2fa_json('success', 'Setup started. Scan the QR code with your authenticator app.', [
            'secret' => $secret,
            'otpauth_uri' => $otpauth,
            'qr_url' => mf_totp_qr_image_url($otpauth, 220),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    if ($action === '2fa_setup_confirm') {
        $pending = $_SESSION['pending_2fa_setup'] ?? null;
        if (!is_array($pending) || (int) $pending['user_id'] !== $actingUserId) {
            mf_2fa_json('error', 'No pending 2FA setup. Please start over.');
        }
        if ((time() - (int) $pending['created_at']) > 600) {
            unset($_SESSION['pending_2fa_setup']);
            mf_2fa_json('error', 'Setup session expired. Please try again.');
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        if (!mf_totp_verify((string) $pending['secret'], $code, 1)) {
            mf_2fa_json('error', 'Invalid code. Please check the time on your device and try the latest 6-digit code.');
        }

        $hashedRecovery = mf_totp_hash_recovery_codes($pending['recovery_codes']);
        $update = $pdo->prepare('UPDATE users SET two_fa_enabled = 1, two_fa_secret = ?, two_fa_recovery_codes = ? WHERE user_id = ?');
        $update->execute([$pending['secret'], $hashedRecovery, $actingUserId]);

        // Audit log (only meaningful for tenant users; super_admin uses NULL tenant_id which is fine).
        $tenantIdForLog = $actingContext === 'tenant' ? ($_SESSION['tenant_id'] ?? null) : null;
        try {
            $audit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, entity_type, description, ip_address) VALUES (?, ?, '2FA_ENABLED', 'user', 'Two-factor authentication enabled.', ?)");
            $audit->execute([$tenantIdForLog, $actingUserId, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Throwable $e) {
            // Non-fatal.
            error_log('2FA audit log failed: ' . $e->getMessage());
        }

        unset($_SESSION['pending_2fa_setup']);
        mf_2fa_json('success', 'Two-factor authentication is now enabled.');
    }

    if ($action === '2fa_disable') {
        if ((int) $user['two_fa_enabled'] !== 1) {
            mf_2fa_json('error', 'Two-factor authentication is not currently enabled.');
        }

        $password = (string) ($_POST['password'] ?? '');
        $code = trim((string) ($_POST['code'] ?? ''));

        if ($password === '' || !password_verify($password, (string) $user['password_hash'])) {
            mf_2fa_json('error', 'Your current password is incorrect.');
        }

        $codeOk = false;
        if (preg_match('/^\d{6}$/', $code)) {
            $codeOk = mf_totp_verify((string) $user['two_fa_secret'], $code, 1);
        } else {
            $newRecoveryJson = mf_totp_consume_recovery_code((string) $user['two_fa_recovery_codes'], $code);
            $codeOk = $newRecoveryJson !== null;
        }
        if (!$codeOk) {
            mf_2fa_json('error', 'The 2FA code or recovery code is invalid.');
        }

        $update = $pdo->prepare('UPDATE users SET two_fa_enabled = 0, two_fa_secret = NULL, two_fa_recovery_codes = NULL WHERE user_id = ?');
        $update->execute([$actingUserId]);

        $tenantIdForLog = $actingContext === 'tenant' ? ($_SESSION['tenant_id'] ?? null) : null;
        try {
            $audit = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_id, action_type, entity_type, description, ip_address) VALUES (?, ?, '2FA_DISABLED', 'user', 'Two-factor authentication disabled.', ?)");
            $audit->execute([$tenantIdForLog, $actingUserId, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Throwable $e) {
            error_log('2FA audit log failed: ' . $e->getMessage());
        }

        mf_2fa_json('success', 'Two-factor authentication has been disabled.');
    }

    mf_2fa_json('error', 'Unknown action.');
} catch (Throwable $e) {
    error_log('2FA endpoint error: ' . $e->getMessage());
    mf_2fa_json('error', 'Unexpected error. Please try again.');
}
