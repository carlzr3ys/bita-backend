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

if (!isset($data['request_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Request ID is required'], 400);
}

$requestId = intval($data['request_id']);

$conn = getDBConnection();

// Get contact request details
$reqStmt = $conn->prepare("SELECT id, name, matric, message, status FROM admin_contact_requests WHERE id = ?");
$reqStmt->bind_param("i", $requestId);
$reqStmt->execute();
$reqResult = $reqStmt->get_result();

if ($reqResult->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Request not found'], 404);
    exit;
}

$request = $reqResult->fetch_assoc();
$reqStmt->close();

// Find user by matric
$userStmt = $conn->prepare("SELECT id FROM users WHERE matric = ? AND is_verified = 1 LIMIT 1");
$userStmt->bind_param("s", $request['matric']);
$userStmt->execute();
$userResult = $userStmt->get_result();

$userId = null;
if ($userResult->num_rows > 0) {
    $user = $userResult->fetch_assoc();
    $userId = $user['id'];
}
$userStmt->close();

// If user not found, cannot create conversation
if ($userId === null) {
    sendJSONResponse([
        'success' => false,
        'message' => 'User not found. User must be registered and verified to start conversation.'
    ], 404);
    exit;
}

// Check if conversation already exists for this user
$checkStmt = $conn->prepare("SELECT id FROM conversations WHERE user_id = ? AND admin_id = ? AND status = 'active' LIMIT 1");
$checkStmt->bind_param("ii", $userId, $adminId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    $existing = $checkResult->fetch_assoc();
    $checkStmt->close();
    $conn->close();
    sendJSONResponse([
        'success' => true,
        'message' => 'Conversation already exists',
        'conversation_id' => $existing['id'],
        'already_exists' => true
    ]);
    exit;
}

$checkStmt->close();

// Create conversation (status: active, admin assigned immediately)
$convStmt = $conn->prepare("INSERT INTO conversations (user_id, admin_id, status, last_message_at) VALUES (?, ?, 'active', NOW())");
$convStmt->bind_param("ii", $userId, $adminId);

if (!$convStmt->execute()) {
    $error = $convStmt->error;
    $convStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to create conversation: ' . $error], 500);
}

$conversationId = $conn->insert_id;
$convStmt->close();

// Add initial message from the contact request
$msgStmt = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, sender_type, message) VALUES (?, ?, 'user', ?)");
$msgStmt->bind_param("iis", $conversationId, $userId, $request['message']);

if (!$msgStmt->execute()) {
    // If message insert fails, conversation still created, so continue
}

$msgStmt->close();

// Update contact request status to Resolved
$updateStmt = $conn->prepare("UPDATE admin_contact_requests SET status = 'Resolved', resolved_at = NOW() WHERE id = ?");
$updateStmt->bind_param("i", $requestId);
$updateStmt->execute(); // Don't fail if this fails
$updateStmt->close();

$conn->close();

sendJSONResponse([
    'success' => true,
    'message' => 'Conversation started successfully',
    'conversation_id' => $conversationId,
    'user_id' => $userId
]);
?>

