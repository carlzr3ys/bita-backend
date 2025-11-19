<?php
require_once '../config.php';
require_once 'check_admin_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$adminId = $_SESSION['admin_id'];

if (!isset($_GET['conversation_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Conversation ID is required'], 400);
}

$conversationId = intval($_GET['conversation_id']);

$conn = getDBConnection();

// Verify admin has access to this conversation
$verifyStmt = $conn->prepare("SELECT admin_id FROM conversations WHERE id = ?");
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

$verifyStmt->close();

// Get all messages in conversation
$stmt = $conn->prepare("
    SELECT 
        m.id,
        m.sender_id,
        m.sender_type,
        m.message,
        m.is_read,
        m.created_at
    FROM messages m
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");

$stmt->bind_param("i", $conversationId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'sender_type' => $row['sender_type'],
        'message' => $row['message'],
        'is_read' => (bool)$row['is_read'],
        'created_at' => $row['created_at']
    ];
}

// Mark user messages as read
$readStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_type = 'user' AND is_read = 0");
$readStmt->bind_param("i", $conversationId);
$readStmt->execute();
$readStmt->close();

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'messages' => $messages,
    'count' => count($messages)
]);
?>

