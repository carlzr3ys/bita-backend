<?php
// Start output buffering to prevent headers already sent error
ob_start();

require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Email and password required'], 400);
}

$email = trim($data['email']);
$password = $data['password'];

$conn = getDBConnection();

// Get admin by email
$stmt = $conn->prepare("SELECT id, name, email, password, role FROM admin WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

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

// Update admin session token and last login
$updateStmt = $conn->prepare("UPDATE admin SET session_token = ?, last_login = NOW() WHERE id = ?");
$updateStmt->bind_param("si", $sessionToken, $admin['id']);
$updateStmt->execute();
$updateStmt->close();

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

