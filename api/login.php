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
    sendJSONResponse(['success' => false, 'message' => 'Email and password required'], 400);
}

$email = trim($data['email']);
$password = $data['password'];

$conn = getDBConnection();

// Get user by email
$stmt = $conn->prepare("SELECT id, name, matric, email, password, program, is_verified FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!verifyPassword($password, $user['password'])) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Invalid email or password'], 401);
}

// Check if user is verified by admin
if (!$user['is_verified']) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Your account is pending admin approval. Please wait for verification.'], 403);
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['name'];

$conn->close();

// Clean output buffer before sending response
ob_end_clean();

sendJSONResponse([
    'success' => true,
    'message' => 'Login successful',
    'user' => [
        'id' => $user['id'],
        'name' => $user['name'],
        'matric' => $user['matric'],
        'email' => $user['email'],
        'program' => $user['program']
    ]
]);

