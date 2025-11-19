<?php
require_once '../config.php';
// DO NOT require 'check_session.php' - it will return response and exit!
// We check session manually below

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Check user authentication - REQUIRED (manual check, no include check_session.php)
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. Please login first.'], 401);
    exit;
}

$userId = $_SESSION['user_id'];

// Debug: Log session and input
error_log("Contact Admin API - User ID from session: " . $userId);
error_log("Contact Admin API - Session data: " . print_r($_SESSION, true));

$input = file_get_contents('php://input');
error_log("Contact Admin API - Raw input: " . $input);

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJSONResponse([
        'success' => false, 
        'message' => 'Invalid JSON data: ' . json_last_error_msg(),
        'debug' => ['raw_input' => $input, 'json_error' => json_last_error_msg()]
    ], 400);
    exit;
}

error_log("Contact Admin API - Parsed data: " . print_r($data, true));

// Validate required fields
if (!isset($data['message']) || empty(trim($data['message']))) {
    sendJSONResponse([
        'success' => false, 
        'message' => 'Message is required',
        'debug' => ['received_data' => $data]
    ], 400);
    exit;
}

$phone = isset($data['phone']) && !empty(trim($data['phone'])) ? trim($data['phone']) : '';
$message = trim($data['message']);

// Validate phone length if provided
if ($phone !== '' && strlen($phone) > 20) {
    sendJSONResponse(['success' => false, 'message' => 'Phone number is too long. Maximum 20 characters.'], 400);
    exit;
}

// Validate message length
if (strlen($message) === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Message cannot be empty'], 400);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

// Check if table exists
$tablesCheck = $conn->query("SHOW TABLES LIKE 'admin_contact_requests'");
if ($tablesCheck->num_rows === 0) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Database table "admin_contact_requests" does not exist.'], 500);
    exit;
}

// Get user info from database (to ensure we use correct name and matric from database)
$userStmt = $conn->prepare("SELECT id, name, matric FROM users WHERE id = ?");
if (!$userStmt) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error, 'errno' => $conn->errno], 500);
    exit;
}

$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
if ($userResult->num_rows === 0) {
    $userStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'User not found'], 404);
    exit;
}
$user = $userResult->fetch_assoc();
$userStmt->close();

// Use user info from database
$name = $user['name'];
$matric = $user['matric'];

error_log("Contact Admin API - User info: name=$name, matric=$matric");
error_log("Contact Admin API - Message: " . substr($message, 0, 100));
error_log("Contact Admin API - Phone: " . ($phone === '' ? 'NULL' : $phone));

// Insert into admin_contact_requests table
// Database structure:
// id INT AUTO_INCREMENT PRIMARY KEY
// name VARCHAR(100) - from user database
// matric VARCHAR(20) - from user database
// phone VARCHAR(20) - from form (optional, can be NULL)
// message TEXT - from form
// status ENUM('Pending','Resolved') DEFAULT 'Pending'
// created_at DATETIME DEFAULT CURRENT_TIMESTAMP
// resolved_at DATETIME NULL

// Handle phone field - if empty, use NULL
if ($phone === '') {
    // Use NULL for empty phone
    $contactStmt = $conn->prepare("INSERT INTO admin_contact_requests (name, matric, phone, message, status) VALUES (?, ?, NULL, ?, 'Pending')");
    if (!$contactStmt) {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error, 'errno' => $conn->errno], 500);
        exit;
    }
    $contactStmt->bind_param("sss", $name, $matric, $message);
} else {
    // Use phone value
    $contactStmt = $conn->prepare("INSERT INTO admin_contact_requests (name, matric, phone, message, status) VALUES (?, ?, ?, ?, 'Pending')");
    if (!$contactStmt) {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error, 'errno' => $conn->errno], 500);
        exit;
    }
    $contactStmt->bind_param("ssss", $name, $matric, $phone, $message);
}

error_log("Contact Admin API - Executing INSERT statement...");

if (!$contactStmt->execute()) {
    $error = $contactStmt->error;
    $errno = $contactStmt->errno;
    error_log("Contact Admin API - INSERT failed: " . $error . " (Code: " . $errno . ")");
    $contactStmt->close();
    $conn->close();
    sendJSONResponse([
        'success' => false,
        'message' => 'Failed to submit request: ' . $error,
        'error_code' => $errno,
        'debug' => [
            'stmt_error' => $error,
            'stmt_errno' => $errno,
            'conn_error' => $conn->error,
            'conn_errno' => $conn->errno,
            'user_id' => $userId,
            'user_name' => $name,
            'user_matric' => $matric,
            'phone' => $phone === '' ? 'NULL' : $phone,
            'message_length' => strlen($message),
            'phone_empty' => $phone === ''
        ]
    ], 500);
    exit;
}

error_log("Contact Admin API - INSERT successful!");

$requestId = $conn->insert_id;
$affectedRows = $contactStmt->affected_rows;
$contactStmt->close();

error_log("Contact Admin API - Insert ID: $requestId, Affected Rows: $affectedRows");

// Check if insert was successful
if (!$requestId || $requestId <= 0) {
    error_log("Contact Admin API - Insert failed: No insert ID!");
    $conn->close();
    sendJSONResponse([
        'success' => false,
        'message' => 'Failed to get insert ID. Data may not have been inserted.',
        'debug' => [
            'insert_id' => $requestId,
            'affected_rows' => $affectedRows,
            'user_id' => $userId,
            'autocommit' => $conn->autocommit,
            'conn_error' => $conn->error,
            'conn_errno' => $conn->errno
        ]
    ], 500);
    exit;
}

if ($affectedRows === 0) {
    $conn->close();
    sendJSONResponse([
        'success' => false,
        'message' => 'No rows were affected. Data was not inserted.',
        'debug' => [
            'insert_id' => $requestId,
            'affected_rows' => $affectedRows,
            'user_id' => $userId
        ]
    ], 500);
    exit;
}

// Verify data was inserted
$verifyStmt = $conn->prepare("SELECT * FROM admin_contact_requests WHERE id = ?");
$verifyStmt->bind_param("i", $requestId);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();
$verifyStmt->close();

if (!$verifyResult || $verifyResult->num_rows === 0) {
    $conn->close();
    sendJSONResponse([
        'success' => false,
        'message' => 'Request was created but verification failed. Please check database.',
        'request_id' => $requestId,
        'debug' => [
            'affected_rows' => $affectedRows,
            'insert_id' => $requestId,
            'user_id' => $userId,
            'user_name' => $name,
            'user_matric' => $matric
        ]
    ], 500);
    exit;
}

// Get the inserted record for confirmation
$insertedRecord = $verifyResult->fetch_assoc();

$conn->close();

sendJSONResponse([
    'success' => true,
    'message' => 'Your request has been submitted successfully. Admin will contact you shortly.',
    'request_id' => $requestId,
    'debug' => [
        'inserted' => true,
        'affected_rows' => $affectedRows,
        'record' => [
            'id' => $insertedRecord['id'],
            'name' => $insertedRecord['name'],
            'matric' => $insertedRecord['matric'],
            'status' => $insertedRecord['status'],
            'created_at' => $insertedRecord['created_at']
        ]
    ]
], 201);
?>
