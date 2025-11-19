<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Session already started in config.php, no need to start again

if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, name, matric, email, program, matric_card, is_verified, verification_comment, year, batch, phone, email_alt, bio, description, instagram, facebook, twitter, linkedin, tiktok, created_at, updated_at FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'User not found'], 404);
}

$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'user' => $user
]);
?>

