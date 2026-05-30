<?php
require_once __DIR__ . '/api_utils.php';
require_once __DIR__ . '/../config/db.php';

microfin_api_bootstrap();
microfin_require_post();

/** @var mysqli $conn */

$data = microfin_read_json_input();

$tenantId = microfin_clean_string($data['tenant_id'] ?? '');
$firstName = microfin_clean_string($data['first_name'] ?? '');
$middleName = microfin_clean_string($data['middle_name'] ?? '');
$lastName = microfin_clean_string($data['last_name'] ?? '');
$suffix = microfin_clean_string($data['suffix'] ?? '');

if ($tenantId === '' || $firstName === '' || $lastName === '') {
    microfin_json_response(['success' => false, 'message' => 'Required fields are missing.'], 422);
}

try {
    // Check for existing client with same name credentials
    $stmt = $conn->prepare("
        SELECT client_id, first_name, middle_name, last_name, suffix
        FROM clients
        WHERE tenant_id = ?
          AND first_name = ?
          AND last_name = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bind_param('sss', $tenantId, $firstName, $lastName);
    $stmt->execute();
    $result = $stmt->get_result();
    $existing = $result->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Check if middle name and suffix also match (exact duplicate)
        $existingMiddleName = trim((string)($existing['middle_name'] ?? ''));
        $existingSuffix = trim((string)($existing['suffix'] ?? ''));
        
        $middleMatch = ($middleName === '' && $existingMiddleName === '') || strtolower($middleName) === strtolower($existingMiddleName);
        $suffixMatch = ($suffix === '' && $existingSuffix === '') || strtolower($suffix) === strtolower($existingSuffix);
        
        if ($middleMatch && $suffixMatch) {
            microfin_json_response([
                'success' => true,
                'has_duplicate' => true,
                'duplicate_type' => 'exact',
                'message' => 'A user with the same name credentials already exists.',
            ]);
        } else {
            microfin_json_response([
                'success' => true,
                'has_duplicate' => true,
                'duplicate_type' => 'partial',
                'message' => 'A user with the same first and last name already exists.',
            ]);
        }
    }

    microfin_json_response([
        'success' => true,
        'has_duplicate' => false,
        'message' => 'No duplicate found.',
    ]);
} catch (Throwable $e) {
    microfin_json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
