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
error_log("Delete Contact Request API - Admin ID from session: " . $_SESSION['admin_id']);

$input = file_get_contents('php://input');
error_log("Delete Contact Request API - Raw input: " . $input);

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJSONResponse([
        'success' => false, 
        'message' => 'Invalid JSON data: ' . json_last_error_msg(),
        'debug' => ['raw_input' => $input, 'json_error' => json_last_error_msg()]
    ], 400);
    exit;
}

error_log("Delete Contact Request API - Parsed data: " . print_r($data, true));

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
error_log("Delete Contact Request API - Request ID: " . $requestId);

$conn = getDBConnection();

if (!$conn) {
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

// Get request info before deletion for logging
$getStmt = $conn->prepare("SELECT id, name, matric, status FROM admin_contact_requests WHERE id = ?");
if (!$getStmt) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
    exit;
}

$getStmt->bind_param("i", $requestId);
$getStmt->execute();
$getResult = $getStmt->get_result();

if ($getResult->num_rows === 0) {
    $getStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Request not found'], 404);
    exit;
}

$request = $getResult->fetch_assoc();
$getStmt->close();

// Delete request
$deleteStmt = $conn->prepare("DELETE FROM admin_contact_requests WHERE id = ?");
if (!$deleteStmt) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to prepare delete statement: ' . $conn->error], 500);
    exit;
}

$deleteStmt->bind_param("i", $requestId);

error_log("Delete Contact Request API - Executing DELETE statement for request ID: " . $requestId);

if (!$deleteStmt->execute()) {
    $error = $deleteStmt->error;
    $errno = $deleteStmt->errno;
    error_log("Delete Contact Request API - DELETE failed: " . $error . " (Code: " . $errno . ")");
    $deleteStmt->close();
    $conn->close();
    sendJSONResponse([
        'success' => false, 
        'message' => 'Failed to delete request: ' . $error,
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

$affectedRows = $deleteStmt->affected_rows;
$deleteStmt->close();
$conn->close();

error_log("Delete Contact Request API - DELETE successful! Affected rows: " . $affectedRows);

if ($affectedRows === 0) {
    error_log("Delete Contact Request API - No rows affected. Request may not exist.");
    sendJSONResponse([
        'success' => false, 
        'message' => 'Request not found or already deleted',
        'debug' => [
            'affected_rows' => $affectedRows,
            'request_id' => $requestId
        ]
    ], 404);
    exit;
}

sendJSONResponse([
    'success' => true,
    'message' => 'Contact request deleted successfully',
    'request_id' => $requestId,
    'debug' => [
        'deleted' => true,
        'affected_rows' => $affectedRows,
        'deleted_request' => [
            'id' => $request['id'],
            'name' => $request['name'],
            'matric' => $request['matric'],
            'status' => $request['status']
        ]
    ]
], 200);
?>

