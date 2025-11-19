<?php
require_once '../config.php';
// DO NOT require 'check_admin_session.php' - it will return response and exit!
// We check admin authentication manually below

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Check admin authentication manually (no include check_admin_session.php)
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. Please login as admin.'], 401);
    exit;
}

// Debug: Log admin ID
error_log("Resolve Contact Request API - Admin ID from session: " . $_SESSION['admin_id']);

$input = file_get_contents('php://input');
error_log("Resolve Contact Request API - Raw input: " . $input);

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJSONResponse([
        'success' => false, 
        'message' => 'Invalid JSON data: ' . json_last_error_msg(),
        'debug' => ['raw_input' => $input, 'json_error' => json_last_error_msg()]
    ], 400);
    exit;
}

error_log("Resolve Contact Request API - Parsed data: " . print_r($data, true));

// Validate required fields
if (!isset($data['request_id']) || empty($data['request_id'])) {
    sendJSONResponse([
        'success' => false, 
        'message' => 'Request ID is required',
        'debug' => ['received_data' => $data]
    ], 400);
    exit;
}

$requestId = intval($data['request_id']);
error_log("Resolve Contact Request API - Request ID: " . $requestId);

$conn = getDBConnection();

if (!$conn) {
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

// Update request status to Resolved
$stmt = $conn->prepare("UPDATE admin_contact_requests SET status = 'Resolved', resolved_at = NOW() WHERE id = ?");
if (!$stmt) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
    exit;
}

$stmt->bind_param("i", $requestId);

error_log("Resolve Contact Request API - Executing UPDATE statement for request ID: " . $requestId);

if (!$stmt->execute()) {
    $error = $stmt->error;
    $errno = $stmt->errno;
    error_log("Resolve Contact Request API - UPDATE failed: " . $error . " (Code: " . $errno . ")");
    $stmt->close();
    $conn->close();
    sendJSONResponse([
        'success' => false, 
        'message' => 'Failed to resolve request: ' . $error,
        'error_code' => $errno,
        'debug' => [
            'stmt_error' => $error,
            'stmt_errno' => $errno,
            'conn_error' => $conn->error,
            'conn_errno' => $conn->errno,
            'request_id' => $requestId
        ]
    ], 500);
    exit;
}

$affectedRows = $stmt->affected_rows;
$stmt->close();

error_log("Resolve Contact Request API - UPDATE successful! Affected rows: " . $affectedRows);

if ($affectedRows === 0) {
    error_log("Resolve Contact Request API - No rows affected. Request may not exist or already resolved.");
    $conn->close();
    sendJSONResponse([
        'success' => false, 
        'message' => 'Request not found or already resolved',
        'debug' => [
            'affected_rows' => $affectedRows,
            'request_id' => $requestId
        ]
    ], 404);
    exit;
}

// Verify the update
$verifyStmt = $conn->prepare("SELECT id, status, resolved_at FROM admin_contact_requests WHERE id = ?");
if (!$verifyStmt) {
    error_log("Resolve Contact Request API - Failed to prepare verification statement: " . $conn->error);
    $conn->close();
    sendJSONResponse([
        'success' => true,
        'message' => 'Request marked as resolved (verification failed)',
        'request_id' => $requestId,
        'warning' => 'Could not verify update'
    ], 200);
    exit;
}

$verifyStmt->bind_param("i", $requestId);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();
$verifyStmt->close();
$conn->close();

if ($verifyResult && $verifyResult->num_rows > 0) {
    $updatedRecord = $verifyResult->fetch_assoc();
    error_log("Resolve Contact Request API - Verification successful. Updated record: " . print_r($updatedRecord, true));
    
    sendJSONResponse([
        'success' => true,
        'message' => 'Request marked as resolved successfully',
        'request_id' => $requestId,
        'debug' => [
            'updated' => true,
            'affected_rows' => $affectedRows,
            'new_status' => $updatedRecord['status'],
            'resolved_at' => $updatedRecord['resolved_at']
        ]
    ], 200);
} else {
    error_log("Resolve Contact Request API - Verification failed: No record found for ID " . $requestId);
    sendJSONResponse([
        'success' => false,
        'message' => 'Request was updated but verification failed',
        'request_id' => $requestId,
        'debug' => [
            'affected_rows' => $affectedRows,
            'verification_failed' => true
        ]
    ], 500);
}
?>

