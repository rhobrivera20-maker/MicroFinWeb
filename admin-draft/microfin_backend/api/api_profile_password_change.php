<?php
require_once '../auth/session_auth.php';
mf_start_backend_session();
require_once '../config/db_connect.php';
require_once '../auth/password_validation.php';

/** @var PDO $pdo */
header('Content-Type: application/json');

function profile_password_change_respond(string $status, string $message, int $httpCode = 200, array $extra = []): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message,
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    profile_password_change_respond('error', 'Method not allowed.', 405);
}

mf_require_tenant_session($pdo, [
    'response' => 'json',
    'message' => 'Unauthorized.',
]);

$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = trim((string)($payload['action'] ?? ''));
$userId = (int)($_SESSION['user_id'] ?? 0);
$tenantId = trim((string)($_SESSION['tenant_id'] ?? ''));

if ($userId <= 0 || $tenantId === '') {
    profile_password_change_respond('error', 'Unauthorized.', 401);
}

if ($action === 'change_password') {
    $currentPassword = trim((string)($payload['current_password'] ?? ''));
    $newPassword = trim((string)($payload['new_password'] ?? ''));

    if ($currentPassword === '') {
        profile_password_change_respond('error', 'Please enter your current password.', 422);
    }

    if ($newPassword === '') {
        profile_password_change_respond('error', 'Please enter a new password.', 422);
    }

    // Get current password hash
    $userStmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $userStmt->execute([$userId, $tenantId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        profile_password_change_respond('error', 'User not found.', 404);
    }

    // Validate password change using reusable validation function
    $validation = validate_password_change($currentPassword, $newPassword, $user['password_hash'], 8);
    if (!$validation['valid']) {
        profile_password_change_respond('error', $validation['message'], 422);
    }

    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password
    $updateStmt = $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?');
    $updateStmt->execute([$newPasswordHash, $userId, $tenantId]);

    profile_password_change_respond('success', 'Password changed successfully.');
}

profile_password_change_respond('error', 'Invalid request.', 422);
