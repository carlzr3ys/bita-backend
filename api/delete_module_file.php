<?php
require_once '../config.php';
require_once '../api/cors.php';
require_once '../api/check_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get ID from query string or body
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($_GET['id']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $data = json_decode(file_get_contents('php://input'), true);
    $fileId = intval($data['id'] ?? 0);
}

if ($fileId === 0) {
    sendJSONResponse(['success' => false, 'message' => 'File ID is required']);
    exit;
}

// Check if file exists and user owns it
$check = $conn->prepare("SELECT id, file_path, uploaded_by FROM module_files WHERE id = ?");
$check->bind_param("i", $fileId);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'File not found']);
    exit;
}

$file = $result->fetch_assoc();

// Only allow user to delete their own files (unless admin)
$isAdmin = isset($_SESSION['admin_id']);

if (!$isAdmin && $file['uploaded_by'] != $userId) {
    sendJSONResponse(['success' => false, 'message' => 'You can only delete your own files']);
    exit;
}

// Delete file from filesystem
$filePath = __DIR__ . '/../' . $file['file_path'];
if (file_exists($filePath)) {
    @unlink($filePath);
}

// Delete record from database
$stmt = $conn->prepare("DELETE FROM module_files WHERE id = ?");
$stmt->bind_param("i", $fileId);

if ($stmt->execute()) {
    sendJSONResponse([
        'success' => true,
        'message' => 'File deleted successfully'
    ]);
} else {
    sendJSONResponse(['success' => false, 'message' => 'Failed to delete file: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>

