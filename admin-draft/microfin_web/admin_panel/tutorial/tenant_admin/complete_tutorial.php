<?php
/**
 * Tutorial Completion Endpoint
 * Marks the tutorial as completed for the current user
 * Since we use audit_logs for first-login detection, this is optional
 * but can be used for analytics or future features
 */

session_start();

require_once '../../../microfin_backend/config/db_connect.php';

// Verify user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];

// Optional: Log tutorial completion in audit_logs
try {
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, tenant_id, action_type, entity_type, description, ip_address)
        VALUES (?, ?, 'TUTORIAL_COMPLETED', 'user', ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $tenant_id,
        'Admin completed the onboarding tutorial',
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to log tutorial completion']);
}
