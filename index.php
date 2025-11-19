<?php
/**
 * Koyeb Root Index File
 * 
 * This file is important for Koyeb to auto-detect PHP runtime.
 * Also serves as health check endpoint.
 */

// Check if request is for API
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// If accessing root, show API info
if ($requestUri === '/' || $requestUri === '/index.php') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'BITA API Backend is running',
        'version' => '1.0.0',
        'status' => 'ok'
    ]);
    exit;
}

// If request starts with /api, .htaccess will route it
// This shouldn't be reached if .htaccess is working correctly
if (strpos($requestUri, '/api/') === 0) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'API endpoint not found. Check .htaccess routing.'
    ]);
    exit;
}

// Default: return 404
http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Not Found'
]);
exit;

