<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';

microfin_api_bootstrap();
microfin_require_post();

/** @var mysqli $conn */

$data = microfin_read_json_input();

$email = microfin_clean_string($data['email'] ?? '');
$otp = microfin_clean_string($data['otp'] ?? '');

if ($email === '' || $otp === '') {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing.'], 422);
}

try {
    // Get the latest pending OTP for this email from otp_verifications table
    $stmt = $conn->prepare("
        SELECT otp_id, otp_code, expires_at
        FROM otp_verifications
        WHERE email = ?
          AND status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $otpEntry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otpEntry) {
        microfin_json_response(['success' => false, 'message' => 'No OTP found for this email. Please request a new one.'], 404);
    }

    // Check if OTP has expired
    $now = time();
    $expiresAt = strtotime($otpEntry['expires_at']);
    if ($now > $expiresAt) {
        // Mark as expired
        $updateStmt = $conn->prepare("UPDATE otp_verifications SET status = 'Expired' WHERE otp_id = ?");
        $updateStmt->bind_param('i', $otpEntry['otp_id']);
        $updateStmt->execute();
        $updateStmt->close();
        
        microfin_json_response(['success' => false, 'message' => 'OTP has expired. Please request a new one.'], 422);
    }

    // Verify OTP
    if ($otpEntry['otp_code'] !== $otp) {
        microfin_json_response(['success' => false, 'message' => 'Invalid OTP. Please try again.'], 422);
    }

    // Mark as verified
    $updateStmt = $conn->prepare("UPDATE otp_verifications SET status = 'Verified' WHERE otp_id = ?");
    $updateStmt->bind_param('i', $otpEntry['otp_id']);
    $updateStmt->execute();
    $updateStmt->close();

    // OTP is valid - email is verified
    microfin_json_response([
        'success' => true,
        'message' => 'Email verified successfully.',
        'email_verified' => true,
    ]);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
