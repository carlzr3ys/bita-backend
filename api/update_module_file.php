<?php
// Turn off error display to prevent HTML output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config.php';
require_once '../api/cors.php';
// DO NOT require 'check_session.php' - it will return response and exit!
// We check session manually below

// Ensure no output before JSON
ob_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Check user authentication - REQUIRED (manual check, no include check_session.php)
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. Please login first.'], 401);
    exit;
}

$userId = $_SESSION['user_id'];
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid JSON data'], 400);
    exit;
}

if (!isset($data['file_id'])) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'File ID is required'], 400);
    exit;
}

$fileId = intval($data['file_id']);
$description = isset($data['description']) ? trim($data['description']) : '';
$visibility = isset($data['visibility']) ? trim($data['visibility']) : 'Public';

// Validate visibility
$allowedVisibility = ['Public', 'Private', 'Admin Only'];
if (!in_array($visibility, $allowedVisibility)) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid visibility setting'], 400);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

// Check if visibility column exists FIRST (before any SELECT queries)
$checkColumn = $conn->query("SHOW COLUMNS FROM module_files LIKE 'visibility'");
$hasVisibilityColumn = $checkColumn->num_rows > 0;

// Check if file exists and belongs to user
// Don't select 'visibility' if column doesn't exist
if ($hasVisibilityColumn) {
    $checkStmt = $conn->prepare("SELECT id, uploaded_by, visibility FROM module_files WHERE id = ?");
} else {
    $checkStmt = $conn->prepare("SELECT id, uploaded_by FROM module_files WHERE id = ?");
}

if (!$checkStmt) {
    ob_end_clean();
    $conn->close();
    error_log("Update File API - Check prepare failed: " . $conn->error);
    sendJSONResponse(['success' => false, 'message' => 'Failed to prepare check statement: ' . $conn->error], 500);
    exit;
}

$checkStmt->bind_param("i", $fileId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    ob_end_clean();
    $checkStmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'File not found'], 404);
    exit;
}

$file = $checkResult->fetch_assoc();
$checkStmt->close();

// Check if user owns the file or is admin
$isAdmin = isset($_SESSION['admin_id']);
if ($file['uploaded_by'] != $userId && !$isAdmin) {
    ob_end_clean();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. You can only edit your own files.'], 403);
    exit;
}

// Check if updated_at column exists
$checkUpdatedColumn = $conn->query("SHOW COLUMNS FROM module_files LIKE 'updated_at'");
$hasUpdatedColumn = $checkUpdatedColumn->num_rows > 0;

error_log("Update File API - File ID: " . $fileId . ", User ID: " . $userId);
error_log("Update File API - Has visibility column: " . ($hasVisibilityColumn ? 'yes' : 'no'));
error_log("Update File API - Has updated_at column: " . ($hasUpdatedColumn ? 'yes' : 'no'));

// Update file record
if ($hasVisibilityColumn && $hasUpdatedColumn) {
    $updateStmt = $conn->prepare("UPDATE module_files SET description = ?, visibility = ?, updated_at = NOW() WHERE id = ?");
    if (!$updateStmt) {
        ob_end_clean();
        $conn->close();
        error_log("Update File API - Prepare failed: " . $conn->error);
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
        exit;
    }
    $updateStmt->bind_param("ssi", $description, $visibility, $fileId);
} else if ($hasVisibilityColumn) {
    // Has visibility but not updated_at
    $updateStmt = $conn->prepare("UPDATE module_files SET description = ?, visibility = ? WHERE id = ?");
    if (!$updateStmt) {
        ob_end_clean();
        $conn->close();
        error_log("Update File API - Prepare failed: " . $conn->error);
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
        exit;
    }
    $updateStmt->bind_param("ssi", $description, $visibility, $fileId);
} else if ($hasUpdatedColumn) {
    // Has updated_at but not visibility
    $updateStmt = $conn->prepare("UPDATE module_files SET description = ?, updated_at = NOW() WHERE id = ?");
    if (!$updateStmt) {
        ob_end_clean();
        $conn->close();
        error_log("Update File API - Prepare failed: " . $conn->error);
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
        exit;
    }
    $updateStmt->bind_param("si", $description, $fileId);
} else {
    // Fallback: no visibility and no updated_at
    $updateStmt = $conn->prepare("UPDATE module_files SET description = ? WHERE id = ?");
    if (!$updateStmt) {
        ob_end_clean();
        $conn->close();
        error_log("Update File API - Prepare failed: " . $conn->error);
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
        exit;
    }
    $updateStmt->bind_param("si", $description, $fileId);
}

$executeResult = $updateStmt->execute();

if (!$executeResult) {
    ob_end_clean();
    $error = $updateStmt->error;
    $errno = $updateStmt->errno;
    $updateStmt->close();
    $conn->close();
    
    error_log("Update File API - Execute failed: " . $error . " (Code: " . $errno . ")");
    sendJSONResponse([
        'success' => false, 
        'message' => 'Failed to update file: ' . $error,
        'error_code' => $errno
    ], 500);
    exit;
}

$affectedRows = $updateStmt->affected_rows;
$updateStmt->close();
$conn->close();

error_log("Update File API - Success. Affected rows: " . $affectedRows);

ob_end_clean();
sendJSONResponse([
    'success' => true,
    'message' => 'File updated successfully',
    'affected_rows' => $affectedRows
]);
?>

