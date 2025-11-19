<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['password'])) {
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
    sendJSONResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
}

$admin = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!verifyPassword($password, $admin['password'])) {
    $conn->close();
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
?>

