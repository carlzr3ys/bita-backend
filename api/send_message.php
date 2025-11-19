<?php
require_once '../config.php';
require_once 'check_admin_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$adminId = $_SESSION['admin_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['conversation_id']) || !isset($data['message'])) {
    sendJSONResponse(['success' => false, 'message' => 'Conversation ID and message are required'], 400);
}

$conversationId = intval($data['conversation_id']);
$message = trim($data['message']);

if (empty($message)) {
    sendJSONResponse(['success' => false, 'message' => 'Message cannot be empty'], 400);
}

$conn = getDBConnection();

// Verify admin has access to this conversation
$verifyStmt = $conn->prepare("SELECT admin_id, status FROM conversations WHERE id = ?");
$verifyStmt->bind_param("i", $conversationId);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Conversation not found'], 404);
    exit;
}

$conversation = $verifyResult->fetch_assoc();
if ($conversation['admin_id'] !== null && $conversation['admin_id'] != $adminId) {
    sendJSONResponse(['success' => false, 'message' => 'Access denied'], 403);
    exit;
}

// If conversation is pending and admin is sending first message, accept it
if ($conversation['status'] === 'pending' && $conversation['admin_id'] === null) {
    $acceptStmt = $conn->prepare("UPDATE conversations SET admin_id = ?, status = 'active' WHERE id = ?");
    $acceptStmt->bind_param("ii", $adminId, $conversationId);
    $acceptStmt->execute();
    $acceptStmt->close();
}

$verifyStmt->close();

// Insert message
$msgStmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, 'admin', ?)");
$msgStmt->bind_param("iis", $conversationId, $adminId, $message);

if (!$msgStmt->execute()) {
    $error = $msgStmt->error;
    $msgStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to send message: ' . $error], 500);
}

$messageId = $conn->insert_id;
$msgStmt->close();

// Update conversation last_message_at
$updateStmt = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
$updateStmt->bind_param("i", $conversationId);
$updateStmt->execute();
$updateStmt->close();

$conn->close();

sendJSONResponse([
    'success' => true,
    'message' => 'Message sent successfully',
    'message_id' => $messageId,
    'conversation_id' => $conversationId
]);
?>

