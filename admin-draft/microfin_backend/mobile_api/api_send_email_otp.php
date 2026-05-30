<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_service.php';

microfin_api_bootstrap();
microfin_require_post();

/** @var mysqli $conn */

$data = microfin_read_json_input();

$email = microfin_clean_string($data['email'] ?? '');
$tenantId = microfin_clean_string($data['tenant_id'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    microfin_json_response(['success' => false, 'message' => 'Invalid email address.'], 422);
}

if ($tenantId === '') {
    microfin_json_response(['success' => false, 'message' => 'Tenant ID is required.'], 422);
}

try {
    // Check if email already exists for this tenant
    $checkStmt = $conn->prepare("
        SELECT u.user_id, u.user_type, c.client_id
        FROM users u
        LEFT JOIN clients c ON c.user_id = u.user_id AND c.tenant_id = u.tenant_id AND c.deleted_at IS NULL
        WHERE u.email = ? AND u.tenant_id = ? AND u.deleted_at IS NULL
        LIMIT 1
    ");
    $checkStmt->bind_param('ss', $email, $tenantId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        // Admin cannot register as a client
        if ($existing['user_type'] === 'admin') {
            microfin_json_response(['success' => false, 'message' => 'Admin accounts cannot register as clients.'], 409);
        }
        // User already has a client record
        if ($existing['client_id'] !== null) {
            microfin_json_response(['success' => false, 'message' => 'An account with this email already exists.'], 409);
        }
    }

    // Safety check: also verify email doesn't exist in clients table directly
    $clientCheckStmt = $conn->prepare("
        SELECT client_id
        FROM clients
        WHERE email_address = ? AND tenant_id = ? AND deleted_at IS NULL
        LIMIT 1
    ");
    $clientCheckStmt->bind_param('ss', $email, $tenantId);
    $clientCheckStmt->execute();
    $clientExists = $clientCheckStmt->get_result()->fetch_assoc();
    $clientCheckStmt->close();

    if ($clientExists) {
        microfin_json_response(['success' => false, 'message' => 'An account with this email already exists.'], 409);
    }

    // Generate OTP
    $otp = microfin_generate_one_time_code();
    $expiresAt = date('Y-m-d H:i:s', time() + 180); // 3 minutes from now

    // Insert OTP into otp_verifications table
    $insertStmt = $conn->prepare("
        INSERT INTO otp_verifications (email, otp_code, status, expires_at)
        VALUES (?, ?, 'Pending', ?)
    ");
    $insertStmt->bind_param('sss', $email, $otp, $expiresAt);
    $insertStmt->execute();
    $insertStmt->close();

    // Send OTP email
    $emailResult = microfin_send_registration_otp_email($conn, [
        'tenant_id' => $tenantId,
        'tenant_name' => 'MicroFin',
        'user_id' => null,
        'to_email' => $email,
        'recipient_name' => 'User',
        'otp' => $otp,
        'ttl_minutes' => 3,
    ]);

    if (!$emailResult['success']) {
        throw new RuntimeException('Unable to send OTP email: ' . ($emailResult['message'] ?? 'Unknown email error.'));
    }

    microfin_json_response([
        'success' => true,
        'message' => 'OTP sent to your email.',
        'expires_in' => 180, // 3 minutes in seconds
    ]);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
