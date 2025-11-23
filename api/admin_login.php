<?php
// Start output buffering to prevent headers already sent error
ob_start();

require_once '../config.php';

header('Content-Type: application/json');
// CORS headers for production (Netlify frontend)
header('Access-Control-Allow-Origin: https://bitaportal.netlify.app');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle CORS preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    error_log("Admin Login API: Invalid request method - " . $_SERVER['REQUEST_METHOD']);
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$input = file_get_contents('php://input');
error_log("Admin Login API: Received POST request - Input length: " . strlen($input));

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    error_log("Admin Login API: JSON decode error - " . json_last_error_msg());
    sendJSONResponse(['success' => false, 'message' => 'Invalid JSON data: ' . json_last_error_msg()], 400);
}

if (!isset($data['email']) || !isset($data['password'])) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Email and password required'], 400);
}

$email = trim($data['email']);
$password = $data['password'];

// Get database connection with error handling
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    ob_end_clean();
    sendJSONResponse([
        'success' => false, 
        'message' => 'Database connection failed. Please try again later.'
    ], 500);
}

// Check if connection is valid
if (!$conn || $conn->connect_error) {
    ob_end_clean();
    sendJSONResponse([
        'success' => false, 
        'message' => 'Database connection failed. Please try again later.'
    ], 500);
}

// Get admin by email with error handling
try {
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM admin WHERE email = ?");
    if (!$stmt) {
        $conn->close();
        ob_end_clean();
        sendJSONResponse([
            'success' => false, 
            'message' => 'Database query failed. Please try again later.'
        ], 500);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    if (isset($stmt)) $stmt->close();
    $conn->close();
    ob_end_clean();
    sendJSONResponse([
        'success' => false, 
        'message' => 'Database query failed. Please try again later.'
    ], 500);
}

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
}

$admin = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!verifyPassword($password, $admin['password'])) {
    $conn->close();
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
}

// Generate session token
$sessionToken = bin2hex(random_bytes(32));

// Update admin session token and last login with error handling
try {
    $updateStmt = $conn->prepare("UPDATE admin SET session_token = ?, last_login = NOW() WHERE id = ?");
    if (!$updateStmt) {
        $conn->close();
        ob_end_clean();
        sendJSONResponse([
            'success' => false, 
            'message' => 'Failed to update session. Please try again.'
        ], 500);
    }
    
    $updateStmt->bind_param("si", $sessionToken, $admin['id']);
    $updateStmt->execute();
    $updateStmt->close();
} catch (Exception $e) {
    if (isset($updateStmt)) $updateStmt->close();
    $conn->close();
    ob_end_clean();
    sendJSONResponse([
        'success' => false, 
        'message' => 'Failed to update session. Please try again.'
    ], 500);
}

// Set session
$_SESSION['admin_id'] = $admin['id'];
$_SESSION['admin_email'] = $admin['email'];
$_SESSION['admin_name'] = $admin['name'];
$_SESSION['admin_role'] = $admin['role'];
$_SESSION['admin_session_token'] = $sessionToken;

$conn->close();

// Clean output buffer before sending response
ob_end_clean();

sendJSONResponse([
    'success' => true,
    'message' => 'Login successful',
    'admin' => [
        'id' => $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role']
    ]
]);

