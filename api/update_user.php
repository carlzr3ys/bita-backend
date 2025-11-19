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

if (!isset($data['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'User ID required'], 400);
}

$userId = (int)$data['user_id'];
$name = isset($data['name']) ? trim($data['name']) : null;
$matric = isset($data['matric']) ? trim($data['matric']) : null;
$email = isset($data['email']) ? trim($data['email']) : null;
$program = isset($data['program']) ? trim($data['program']) : null;
$year = isset($data['year']) ? trim($data['year']) : null;
$batch = isset($data['batch']) ? trim($data['batch']) : null;
$phone = isset($data['phone']) ? trim($data['phone']) : null;
$email_alt = isset($data['email_alt']) ? trim($data['email_alt']) : null;
$bio = isset($data['bio']) ? trim($data['bio']) : null;
$description = isset($data['description']) ? trim($data['description']) : null;
$instagram = isset($data['instagram']) ? trim($data['instagram']) : null;
$facebook = isset($data['facebook']) ? trim($data['facebook']) : null;
$twitter = isset($data['twitter']) ? trim($data['twitter']) : null;
$linkedin = isset($data['linkedin']) ? trim($data['linkedin']) : null;
$tiktok = isset($data['tiktok']) ? trim($data['tiktok']) : null;
$password = isset($data['password']) && !empty($data['password']) ? $data['password'] : null;

$conn = getDBConnection();

// Check if user exists
$checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
$checkStmt->bind_param("i", $userId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $checkStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'User not found'], 404);
}

$checkStmt->close();

// Build update query dynamically
$updateFields = [];
$params = [];
$types = '';

if ($name !== null) {
    $updateFields[] = "name = ?";
    $params[] = $name;
    $types .= 's';
}

if ($matric !== null) {
    $updateFields[] = "matric = ?";
    $params[] = $matric;
    $types .= 's';
}

if ($email !== null) {
    // Validate email format
    if (!isValidEmail($email)) {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }
    // Check if email already exists for another user
    $emailCheckStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $emailCheckStmt->bind_param("si", $email, $userId);
    $emailCheckStmt->execute();
    $emailCheckResult = $emailCheckStmt->get_result();
    if ($emailCheckResult->num_rows > 0) {
        $emailCheckStmt->close();
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Email already exists'], 400);
    }
    $emailCheckStmt->close();
    
    $updateFields[] = "email = ?";
    $params[] = $email;
    $types .= 's';
}

if ($program !== null) {
    $updateFields[] = "program = ?";
    $params[] = $program;
    $types .= 's';
}

if ($batch !== null) {
    $updateFields[] = "batch = ?";
    $params[] = $batch === '' ? null : $batch;
    $types .= 's';
}

if ($year !== null) {
    $updateFields[] = "year = ?";
    $params[] = $year === '' ? null : $year;
    $types .= 's';
}

if ($phone !== null) {
    $updateFields[] = "phone = ?";
    $params[] = $phone === '' ? null : $phone;
    $types .= 's';
}

if ($email_alt !== null) {
    $updateFields[] = "email_alt = ?";
    $params[] = $email_alt === '' ? null : $email_alt;
    $types .= 's';
}

if ($bio !== null) {
    $updateFields[] = "bio = ?";
    $params[] = $bio === '' ? null : $bio;
    $types .= 's';
}

if ($description !== null) {
    $updateFields[] = "description = ?";
    $params[] = $description === '' ? null : $description;
    $types .= 's';
}

// Process social media URLs - add https:// if not present
$socialFields = ['instagram', 'facebook', 'twitter', 'linkedin', 'tiktok'];
foreach ($socialFields as $field) {
    $fieldValue = $$field;
    if ($fieldValue !== null) {
        $url = trim($fieldValue);
        if ($url === '') {
            $updateFields[] = "$field = ?";
            $params[] = null;
            $types .= 's';
        } else {
            // If it doesn't start with http:// or https://, add https://
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'https://' . $url;
            }
            $updateFields[] = "$field = ?";
            $params[] = $url;
            $types .= 's';
        }
    }
}

if ($password !== null) {
    if (strlen($password) < 8) {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
    }
    $hashedPassword = hashPassword($password);
    $updateFields[] = "password = ?";
    $params[] = $hashedPassword;
    $types .= 's';
}

if (empty($updateFields)) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'No fields to update'], 400);
}

$updateFields[] = "updated_at = NOW()";

// Build and execute update query
$sql = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
$types .= 'i'; // Add 'i' for user_id at the end
$params[] = $userId; // Add user_id to params array

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    
    // Get updated user info for logging
    $getUserStmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $getUserStmt->bind_param("i", $userId);
    $getUserStmt->execute();
    $userResult = $getUserStmt->get_result();
    $updatedUser = $userResult->fetch_assoc();
    $getUserStmt->close();
    $conn->close();
    
    // Log action
    $updateDetails = [];
    if ($name !== null) $updateDetails['name'] = $name;
    if ($matric !== null) $updateDetails['matric'] = $matric;
    if ($email !== null) $updateDetails['email'] = $email;
    if ($program !== null) $updateDetails['program'] = $program;
    if ($year !== null) $updateDetails['year'] = $year;
    if ($batch !== null) $updateDetails['batch'] = $batch;
    if ($phone !== null) $updateDetails['phone'] = $phone;
    if ($email_alt !== null) $updateDetails['email_alt'] = $email_alt;
    if ($bio !== null) $updateDetails['bio'] = $bio;
    if ($description !== null) $updateDetails['description'] = $description;
    if ($instagram !== null) $updateDetails['instagram'] = $instagram;
    if ($facebook !== null) $updateDetails['facebook'] = $facebook;
    if ($twitter !== null) $updateDetails['twitter'] = $twitter;
    if ($linkedin !== null) $updateDetails['linkedin'] = $linkedin;
    if ($tiktok !== null) $updateDetails['tiktok'] = $tiktok;
    if ($password !== null) $updateDetails['password_changed'] = true;
    
    logAdminAction('update', 'user', $userId, $updatedUser['name'] ?? 'User #' . $userId, json_encode($updateDetails));
    
    sendJSONResponse([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to update user: ' . $error], 500);
}
?>

