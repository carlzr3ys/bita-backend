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

$conn = getDBConnection();

// Get user matric card path before deletion
$getStmt = $conn->prepare("SELECT matric_card FROM users WHERE id = ?");
$getStmt->bind_param("i", $userId);
$getStmt->execute();
$getResult = $getStmt->get_result();

if ($getResult->num_rows === 0) {
    $getStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'User not found'], 404);
}

$user = $getResult->fetch_assoc();
$getStmt->close();

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    $stmt->close();
    
    // Delete matric card file if exists
    if (!empty($user['matric_card']) && file_exists('../' . $user['matric_card'])) {
        unlink('../' . $user['matric_card']);
    }
    
    $conn->close();
    
    // Log action
    $userName = isset($user['name']) ? $user['name'] : ('User #' . $userId);
    logAdminAction('delete', 'user', $userId, $userName, json_encode(['email' => $user['email'] ?? '', 'matric' => $user['matric'] ?? '']));
    
    sendJSONResponse([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to delete user: ' . $error], 500);
}
?>

