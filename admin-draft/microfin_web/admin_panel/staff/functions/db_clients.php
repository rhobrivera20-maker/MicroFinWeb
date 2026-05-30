<?php
/**
 * functions/db_clients.php
 * Extracts all client-related queries so they are out of the view layer.
 */

function staff_client_effective_verification_sql(PDO $pdo, string $alias = 'c'): string {
    return "
        CASE
            WHEN {$alias}.document_verification_status = 'Approved' THEN 'Approved'
            WHEN {$alias}.document_verification_status = 'Verified' THEN 'Verified'
            WHEN {$alias}.document_verification_status = 'Rejected' THEN 'Rejected'
            WHEN {$alias}.document_verification_status = 'Pending' THEN 'Pending'
            ELSE 'Unverified'
        END
    ";
}

function get_all_tenant_clients($pdo, $tenant_id) {
    $debug = [
        'tenant_id' => $tenant_id,
        'query_error' => null,
        'row_count' => 0,
        'raw_count_check' => null,
    ];
    $clients = [];

    try {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE tenant_id = ?");
        $cnt->execute([$tenant_id]);
        $debug['raw_count_check'] = $cnt->fetchColumn();

        $effective_status_sql = staff_client_effective_verification_sql($pdo, 'c');

        $stmt = $pdo->prepare("
            SELECT c.*,
                   u.user_type,
                   ({$effective_status_sql}) as effective_status,
                   (SELECT COUNT(*) FROM loans l WHERE l.client_id = c.client_id AND l.tenant_id = c.tenant_id) as total_loans
            FROM clients c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.tenant_id = ?
            ORDER BY c.registration_date DESC
        ");
        $stmt->execute([$tenant_id]);
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug['row_count'] = count($clients);
    } catch (\Throwable $e) {
        $debug['query_error'] = $e->getMessage();
    }

    return [
        'data' => $clients,
        'debug' => $debug
    ];
}

function get_client_documents($pdo, $client_id, $tenant_id) {
    $stmt = $pdo->prepare("
        SELECT cd.*, dt.document_name
        FROM client_documents cd
        LEFT JOIN document_types dt ON cd.document_type_id = dt.document_type_id
        WHERE cd.client_id = ? AND cd.tenant_id = ? AND cd.is_active = 1
        ORDER BY cd.upload_date DESC
    ");
    $stmt->execute([$client_id, $tenant_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_client_documents_by_file_path($pdo, $client_id, $tenant_id) {
    $stmt = $pdo->prepare("
        SELECT client_document_id, file_name, file_path, verification_status, verification_notes
        FROM client_documents
        WHERE client_id = ? AND tenant_id = ? AND is_active = 1
    ");
    $stmt->execute([$client_id, $tenant_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_client_id_number($pdo, $client_id, $tenant_id) {
    $stmt = $pdo->prepare("
        SELECT cd.document_number
        FROM client_documents cd
        JOIN document_types dt ON cd.document_type_id = dt.document_type_id
        WHERE cd.client_id = ? AND cd.tenant_id = ? AND cd.is_active = 1
        AND dt.loan_purpose = 'identity'
        LIMIT 1
    ");
    $stmt->execute([$client_id, $tenant_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['document_number'] : null;
}

function detect_client_documents_from_filesystem($tenant_id, $user_id) {
    $base_path = __DIR__ . '/../../../../uploads/client_documents/' . $tenant_id;
    
    if (!is_dir($base_path)) {
        return [
            'proof_of_billing' => null,
            'proof_of_income' => null,
            'proof_of_legitimacy' => null,
            'scanned_id' => null
        ];
    }
    
    $document_types = ['proof_of_billing', 'proof_of_income', 'proof_of_legitimacy', 'scanned_id'];
    $result = array_fill_keys($document_types, null);
    
    // Scan year/month subdirectories
    $year_dirs = glob($base_path . '/*', GLOB_ONLYDIR);
    
    foreach ($year_dirs as $year_dir) {
        $month_dirs = glob($year_dir . '/*', GLOB_ONLYDIR);
        
        foreach ($month_dirs as $month_dir) {
            $files = glob($month_dir . '/*');
            
            foreach ($files as $file) {
                if (!is_file($file)) continue;
                
                $filename = basename($file);
                
                // Check if filename matches pattern: {tenant_id}_u{user_id}_{document_type}...
                $prefix = $tenant_id . '_u' . $user_id . '_';
                
                if (strpos($filename, $prefix) !== 0) {
                    continue;
                }
                
                // Extract the part after user_id
                $suffix = substr($filename, strlen($prefix));
                
                // Match against document types
                foreach ($document_types as $doc_type) {
                    if (strpos($suffix, $doc_type) === 0) {
                        // Found a match - return relative path from uploads folder
                        if ($result[$doc_type] === null) {
                            $relative_path = str_replace($base_path . '/', '', $month_dir) . '/' . $filename;
                            $result[$doc_type] = $relative_path;
                        }
                        break;
                    }
                }
            }
        }
    }
    
    return $result;
}
