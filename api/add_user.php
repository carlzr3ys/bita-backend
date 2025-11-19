<?php
require_once '../config.php';
require_once 'log_admin_action.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check admin session
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['name']) || !isset($data['matric']) || !isset($data['email']) || !isset($data['password']) || !isset($data['program'])) {
    sendJSONResponse(['success' => false, 'message' => 'Missing required fields'], 400);
}

$name = trim($data['name']);
$matric = trim($data['matric']);
$email = trim($data['email']);
$password = $data['password'];
$program = trim($data['program']);
$isVerified = isset($data['is_verified']) ? (bool)$data['is_verified'] : true; // Admin can create verified users directly

// Validation
if (empty($name) || empty($matric) || empty($email) || empty($password) || empty($program)) {
    sendJSONResponse(['success' => false, 'message' => 'All fields are required'], 400);
}

// Validate email format
if (!isValidEmail($email)) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid email format. Must be @student.utem.edu.my'], 400);
}

// Validate password
if (strlen($password) < 8) {
    sendJSONResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
}

$conn = getDBConnection();

// Check if email already exists
$emailCheckStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$emailCheckStmt->bind_param("s", $email);
$emailCheckStmt->execute();
$emailCheckResult = $emailCheckStmt->get_result();

if ($emailCheckResult->num_rows > 0) {
    $emailCheckStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Email already exists'], 400);
}
$emailCheckStmt->close();

// Check if matric already exists
$matricCheckStmt = $conn->prepare("SELECT id FROM users WHERE matric = ?");
$matricCheckStmt->bind_param("s", $matric);
$matricCheckStmt->execute();
$matricCheckResult = $matricCheckStmt->get_result();

if ($matricCheckResult->num_rows > 0) {
    $matricCheckStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Matric number already exists'], 400);
}
$matricCheckStmt->close();

// Hash password
$hashedPassword = hashPassword($password);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (name, matric, email, password, program, is_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("sssssi", $name, $matric, $email, $hashedPassword, $program, $isVerified);

if ($stmt->execute()) {
    $newUserId = $conn->insert_id;
    $stmt->close();
    $conn->close();
    
    // Log action
    logAdminAction('create', 'user', $newUserId, $name, json_encode(['email' => $email, 'matric' => $matric, 'program' => $program]));
    
    sendJSONResponse([
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $newUserId
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to create user: ' . $error], 500);
}
?>

