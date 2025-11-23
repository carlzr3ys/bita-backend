<?php
// Simple backend health check endpoint
ob_start();

require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://bitaportal.netlify.app');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit();
}

// Test database connection
$dbStatus = 'unknown';
$dbError = null;

try {
    $conn = getDBConnection();
    if ($conn && $conn->ping()) {
        $dbStatus = 'connected';
    } else {
        $dbStatus = 'disconnected';
        $dbError = 'Connection ping failed';
    }
} catch (Exception $e) {
    $dbStatus = 'error';
    $dbError = $e->getMessage();
}

ob_end_clean();

sendJSONResponse([
    'success' => true,
    'message' => 'Backend is running!',
    'backend_status' => 'online',
    'database_status' => $dbStatus,
    'database_error' => $dbError,
    'timestamp' => date('Y-m-d H:i:s'),
    'server_time' => time()
]);
