<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check admin session
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

$conn = getDBConnection();

// Get pending count
$pendingStmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE is_verified = 0");
$pendingStmt->execute();
$pendingResult = $pendingStmt->get_result();
$pendingCount = $pendingResult->fetch_assoc()['count'];
$pendingStmt->close();

// Get total users count
$totalStmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE is_verified = 1");
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalUsers = $totalResult->fetch_assoc()['count'];
$totalStmt->close();

$conn->close();

sendJSONResponse([
    'success' => true,
    'pending_count' => (int)$pendingCount,
    'pending_users' => (int)$pendingCount, // For compatibility
    'total_users' => (int)$totalUsers
]);
?>

