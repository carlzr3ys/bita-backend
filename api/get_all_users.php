<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check admin session
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$conn = getDBConnection();

// Get all approved users (is_verified = 1)
$stmt = $conn->prepare("SELECT id, name, matric, email, program, is_verified, created_at, updated_at FROM users WHERE is_verified = 1 ORDER BY created_at DESC");
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

