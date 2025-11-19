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

if (!isset($data['conversation_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Conversation ID is required'], 400);
}

$conversationId = intval($data['conversation_id']);

$conn = getDBConnection();

// Check if conversation exists and is pending
$checkStmt = $conn->prepare("SELECT id, status, admin_id FROM conversations WHERE id = ?");
$checkStmt->bind_param("i", $conversationId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Conversation not found'], 404);
    exit;
}

$conversation = $checkResult->fetch_assoc();
if ($conversation['status'] !== 'pending') {
    sendJSONResponse(['success' => false, 'message' => 'Conversation is not pending'], 400);
    exit;
}

if ($conversation['admin_id'] !== null) {
    sendJSONResponse(['success' => false, 'message' => 'Conversation already accepted by another admin'], 400);
    exit;
}

$checkStmt->close();

// Update conversation: assign admin and change status to active
$updateStmt = $conn->prepare("UPDATE conversations SET admin_id = ?, status = 'active' WHERE id = ?");
$updateStmt->bind_param("ii", $adminId, $conversationId);

if (!$updateStmt->execute()) {
    $error = $updateStmt->error;
    $updateStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to accept conversation: ' . $error], 500);
}

$updateStmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'message' => 'Conversation accepted successfully',
    'conversation_id' => $conversationId
]);
?>

