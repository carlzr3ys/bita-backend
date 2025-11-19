<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (isset($_SESSION['admin_id'])) {
    $adminId = $_SESSION['admin_id'];
    
    // Clear session token from database
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE admin SET session_token = NULL WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Destroy session
session_destroy();

sendJSONResponse([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>

