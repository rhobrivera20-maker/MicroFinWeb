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
$client_id = (int) ($input['client_id'] ?? 0);

if ($client_id <= 0) {
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
    
    // Get client data including policy_metadata
    $stmt = $pdo->prepare("
        SELECT client_id, policy_metadata 
        FROM clients 
        WHERE client_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$client_id, $tenant_id]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        echo json_encode(['success' => false, 'message' => 'Client not found']);
        exit;
    }
    
    // Update document_verification_status to 'approved'
    $stmt = $pdo->prepare("
        UPDATE clients 
        SET document_verification_status = 'approved' 
        WHERE client_id = ? AND tenant_id = ?
    ");
    $stmt->execute([$client_id, $tenant_id]);
    
    // Parse policy_metadata to get credit limit
    $policy_metadata = json_decode($client['policy_metadata'], true);
    $credit_limit = $policy_metadata['potential_limit'] ?? null;
    
    if ($credit_limit !== null) {
        // Update credit limit in clients table
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET credit_limit = ? 
            WHERE client_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$credit_limit, $client_id, $tenant_id]);
    }
    
    echo json_encode(['success' => true, 'message' => 'Client approved successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
