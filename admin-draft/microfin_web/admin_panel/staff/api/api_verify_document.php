<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();

header('Content-Type: application/json');

// Check session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$client_document_id = (int) ($input['client_document_id'] ?? 0);
$action = $input['action'] ?? 'verify';
$reason = $input['reason'] ?? '';

if ($client_document_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $dbPath = __DIR__ . '/../../../../microfin_backend/config/db_connect.php';
    if (!file_exists($dbPath)) {
        echo json_encode(['success' => false, 'message' => 'DB config not found']);
        exit;
    }
    require_once $dbPath;
    
    $tenant_id = $_SESSION['tenant_id'];
    
    // Check if document exists and belongs to tenant
    $stmt = $pdo->prepare("
        SELECT client_document_id, client_id 
        FROM client_documents 
        WHERE client_document_id = ? AND tenant_id = ? AND is_active = 1
    ");
    $stmt->execute([$client_document_id, $tenant_id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doc) {
        echo json_encode(['success' => false, 'message' => 'Document not found']);
        exit;
    }
    
    // Update verification status (without verified_by to avoid FK constraint)
    $status = ($action === 'reject') ? 'Rejected' : 'Verified';
    $stmt = $pdo->prepare("
        UPDATE client_documents 
        SET verification_status = ?, verification_date = NOW(), verification_notes = ?
        WHERE client_document_id = ?
    ");
    $stmt->execute([$status, $reason, $client_document_id]);
    
    // If rejected, also update clients.verification_rejection_reason
    if ($action === 'reject') {
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET verification_rejection_reason = ? 
            WHERE client_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$reason, $doc['client_id'], $tenant_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Document ' . $status . ' successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
