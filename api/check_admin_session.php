<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_session_token'])) {
    sendJSONResponse([
        'success' => true,
        'authenticated' => false
    ]);
}

$adminId = $_SESSION['admin_id'];
$sessionToken = $_SESSION['admin_session_token'];

$conn = getDBConnection();

// Verify session token
$stmt = $conn->prepare("SELECT id, name, email, role FROM admin WHERE id = ? AND session_token = ?");
$stmt->bind_param("is", $adminId, $sessionToken);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Invalid session
    session_destroy();
    $stmt->close();
    $conn->close();
    sendJSONResponse([
        'success' => true,
        'authenticated' => false
    ]);
}

$admin = $result->fetch_assoc();
$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'authenticated' => true,
    'admin' => [
        'id' => $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role']
    ]
]);
?>

