<?php
session_start();

// Check session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tenant_id'])) {
    http_response_code(403);
    echo 'Access Denied';
    exit;
}

$path = $_GET['path'] ?? '';

if (empty($path)) {
    http_response_code(400);
    echo 'Invalid path';
    exit;
}

// Security: ensure path doesn't try to escape
if (strpos($path, '..') !== false) {
    http_response_code(403);
    echo 'Invalid path';
    exit;
}

// Build full file path
$basePath = __DIR__ . '/../../../../';
$fullPath = $basePath . $path;

// Debug: show what we're looking for
error_log("Looking for file: " . $fullPath);

if (!file_exists($fullPath)) {
    http_response_code(404);
    echo 'File not found: ' . $fullPath;
    exit;
}

// Get file info
$fileInfo = pathinfo($fullPath);
$extension = strtolower($fileInfo['extension'] ?? '');

// Set content type based on file extension
$contentTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

$contentType = $contentTypes[$extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($fullPath);
exit;
?>
