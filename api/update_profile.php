<?php
require_once '../config.php';
require_once 'log_admin_action.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check user session
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$userId = $_SESSION['user_id'];

// Allowed fields for user to update
$allowedFields = [
    'name', 'phone', 'email_alt', 'year',
    'bio', 'description',
    'instagram', 'facebook', 'twitter', 'linkedin', 'tiktok'
];

// Users cannot change: matric, email, program, password, batch (these need admin approval)
// Batch is auto-extracted from matric and cannot be changed by user

$conn = getDBConnection();

// Get current user data
$getStmt = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
$getStmt->bind_param("i", $userId);
$getStmt->execute();
$userResult = $getStmt->get_result();

if ($userResult->num_rows === 0) {
    $getStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'User not found'], 404);
}

$getStmt->close();

// Validate name if provided
if (isset($data['name']) && empty(trim($data['name']))) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Name cannot be empty'], 400);
}

// Process and validate URLs for social media
$urlFields = ['instagram', 'facebook', 'twitter', 'linkedin', 'tiktok'];
foreach ($urlFields as $field) {
    if (isset($data[$field]) && !empty(trim($data[$field]))) {
        $url = trim($data[$field]);
        // If it doesn't start with http:// or https://, add https://
        if (!preg_match('/^https?:\/\//', $url)) {
            $data[$field] = 'https://' . $url;
        }
    }
}

// Build update query dynamically
$updateFields = [];
$params = [];
$types = '';

foreach ($allowedFields as $field) {
    if (isset($data[$field])) {
        $value = trim($data[$field]);
        // Allow empty strings to clear fields
        $updateFields[] = "$field = ?";
        $params[] = $value === '' ? null : $value;
        $types .= 's'; // All fields are strings
    }
}

if (empty($updateFields)) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'No fields to update'], 400);
}

$updateFields[] = "updated_at = NOW()";

// Build and execute update query
$sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
$types .= 'i'; // For user_id at the end
$params[] = $userId;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    
    sendJSONResponse([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to update profile: ' . $error], 500);
}
?>

