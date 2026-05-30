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

$client_id = (int) ($_GET['client_id'] ?? 0);
$tenant_id = $_SESSION['tenant_id'];

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
    
    $funcPath = __DIR__ . '/../functions/db_clients.php';
    if (!file_exists($funcPath)) {
        echo json_encode(['success' => false, 'message' => 'Functions file not found']);
        exit;
    }
    require_once $funcPath;
    
    $documents = get_client_documents_by_file_path($pdo, $client_id, $tenant_id);
    $id_number = get_client_id_number($pdo, $client_id, $tenant_id);
    
    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'id_number' => $id_number
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
