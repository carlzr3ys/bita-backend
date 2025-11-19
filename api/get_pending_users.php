<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check admin session
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$conn = getDBConnection();

// Get all users where is_verified = false
$stmt = $conn->prepare("SELECT id, name, matric, email, program, matric_card, is_verified, created_at FROM users WHERE is_verified = 0 ORDER BY created_at ASC");
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'users' => $users
]);
?>

