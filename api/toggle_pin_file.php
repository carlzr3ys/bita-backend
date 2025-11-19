<?php
require_once '../config.php';
require_once '../api/cors.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Check admin authentication
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 401);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid JSON data'], 400);
    exit;
}

if (!isset($data['file_id']) || !isset($data['is_pinned'])) {
    sendJSONResponse(['success' => false, 'message' => 'File ID and pin status are required'], 400);
    exit;
}

$fileId = intval($data['file_id']);
$isPinned = $data['is_pinned'] ? 1 : 0;

$conn = getDBConnection();

if (!$conn) {
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

// Verify file exists
$checkStmt = $conn->prepare("SELECT id FROM module_files WHERE id = ?");
$checkStmt->bind_param("i", $fileId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    $checkStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'File not found'], 404);
    exit;
}

$checkStmt->close();

// Update pin status
$updateStmt = $conn->prepare("UPDATE module_files SET is_pinned = ? WHERE id = ?");
$updateStmt->bind_param("ii", $isPinned, $fileId);

if ($updateStmt->execute()) {
    $updateStmt->close();
    $conn->close();
    sendJSONResponse([
        'success' => true,
        'message' => $isPinned ? 'File pinned successfully' : 'File unpinned successfully',
        'is_pinned' => $isPinned
    ]);
} else {
    $error = $updateStmt->error;
    $updateStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to update pin status: ' . $error], 500);
}
?>

