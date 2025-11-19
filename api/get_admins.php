<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check admin session
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only superadmin can access
if ($_SESSION['admin_role'] !== 'superadmin') {
    sendJSONResponse(['success' => false, 'message' => 'Access denied. Superadmin only.'], 403);
}

$conn = getDBConnection();

// Get all admins
$stmt = $conn->prepare("SELECT id, name, email, role, created_at, last_login FROM admin ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();

$admins = [];
while ($row = $result->fetch_assoc()) {
    $admins[] = $row;
}

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'admins' => $admins
]);
?>

