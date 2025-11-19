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

$conn = getDBConnection();

// Get pending conversations with user info
$stmt = $conn->prepare("
    SELECT 
        c.id as conversation_id,
        c.user_id,
        c.status,
        c.last_message_at,
        c.created_at,
        u.name as user_name,
        u.matric as user_matric,
        u.email as user_email,
        u.program as user_program,
        (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND is_read = 0 AND sender_type = 'user') as unread_count
    FROM conversations c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.status = 'pending'
    ORDER BY c.last_message_at DESC, c.created_at DESC
");

$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = [
        'id' => $row['conversation_id'],
        'user_id' => $row['user_id'],
        'user_name' => $row['user_name'],
        'user_matric' => $row['user_matric'],
        'user_email' => $row['user_email'],
        'user_program' => $row['user_program'],
        'status' => $row['status'],
        'last_message' => $row['last_message'],
        'last_message_time' => $row['last_message_time'],
        'created_at' => $row['created_at'],
        'unread_count' => (int)$row['unread_count']
    ];
}

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'conversations' => $conversations,
    'count' => count($conversations)
]);
?>

