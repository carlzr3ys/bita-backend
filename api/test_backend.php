<?php
/**
 * Test Backend Connection
 * Simple endpoint untuk test backend dan database connection
 */

require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

try {
    // Test database connection
    $conn = getDBConnection();
    
    if (!$conn) {
        sendJSONResponse([
            'success' => false,
            'message' => 'Database connection failed',
            'backend' => 'working',
            'database' => 'failed'
        ], 500);
    }
    
    // Get MySQL version before closing connection
    $mysqlVersion = $conn->server_info ?? 'unknown';
    
    // Test database query
    $result = $conn->query("SELECT 1 as test");
    
    if ($result) {
        sendJSONResponse([
            'success' => true,
            'message' => 'Backend is working correctly!',
            'backend' => 'working',
            'database' => 'connected',
            'mysql_version' => $mysqlVersion,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        $conn->close();
    } else {
        $error = $conn->error;
        $conn->close();
        
        sendJSONResponse([
            'success' => false,
            'message' => 'Database query failed: ' . $error,
            'backend' => 'working',
            'database' => 'query_failed'
        ], 500);
    }
    
} catch (Exception $e) {
    sendJSONResponse([
        'success' => false,
        'message' => 'Backend error: ' . $e->getMessage(),
        'backend' => 'error',
        'error_type' => get_class($e)
    ], 500);
}
?>

