<?php
/**
 * Get User Profile by ID
 * Allows viewing other users' profiles
 */

require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check if user or admin is authenticated
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Not authenticated'], 401);
}

// Get user ID from query parameter
$profileUserId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($profileUserId <= 0) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid user ID'], 400);
}

$conn = getDBConnection();

// Get user profile (exclude sensitive info like password)
$stmt = $conn->prepare("SELECT id, name, matric, email, program, year, batch, phone, email_alt, bio, description, instagram, facebook, twitter, linkedin, tiktok, matric_card, is_verified, created_at, updated_at FROM users WHERE id = ? AND is_verified = 1");
$stmt->bind_param("i", $profileUserId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'User not found or not verified'], 404);
}

$user = $result->fetch_assoc();

// Don't expose email if user doesn't want to share
// For now, we'll show it, but you can add privacy settings later

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'user' => $user
]);
?>

