<?php
require_once '../config.php';
require_once 'log_admin_action.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Check admin session
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only superadmin can access
if ($_SESSION['admin_role'] !== 'superadmin') {
    sendJSONResponse(['success' => false, 'message' => 'Access denied. Superadmin only.'], 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Admin ID is required'], 400);
}

$adminId = (int)$data['admin_id'];

// Cannot delete yourself
if ($adminId == $_SESSION['admin_id']) {
    sendJSONResponse(['success' => false, 'message' => 'Cannot delete your own account'], 400);
}

$conn = getDBConnection();

// Check if admin exists and get info
$checkStmt = $conn->prepare("SELECT role, name, email FROM admin WHERE id = ?");
$checkStmt->bind_param("i", $adminId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $checkStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Admin not found'], 404);
}

$targetAdmin = $checkResult->fetch_assoc();
$checkStmt->close();

// Cannot delete other superadmins
if ($targetAdmin['role'] === 'superadmin') {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Cannot delete other super admins'], 403);
}

// Delete admin
$deleteStmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
$deleteStmt->bind_param("i", $adminId);

if ($deleteStmt->execute()) {
    $deleteStmt->close();
    $conn->close();
    
    // Log action
    logAdminAction('delete', 'admin', $adminId, $targetAdmin['name'] ?? 'Admin #' . $adminId, json_encode(['email' => $targetAdmin['email'] ?? '', 'role' => $targetAdmin['role'] ?? '']));
    
    sendJSONResponse(['success' => true, 'message' => 'Admin deleted successfully']);
} else {
    $error = $deleteStmt->error;
    $deleteStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to delete admin: ' . $error], 500);
}
?>

