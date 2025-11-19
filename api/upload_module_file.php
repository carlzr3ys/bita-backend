<?php
require_once '../config.php';
require_once '../api/cors.php';
// DO NOT require 'check_session.php' - it will return response and exit!
// We check session manually below

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Check user authentication - REQUIRED (manual check, no include check_session.php)
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. Please login first.'], 401);
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get week_id from POST
$weekId = isset($_POST['week_id']) ? intval($_POST['week_id']) : 0;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$visibility = isset($_POST['visibility']) ? trim($_POST['visibility']) : 'Public';

// Validate visibility
$allowedVisibility = ['Public', 'Private', 'Admin Only'];
if (!in_array($visibility, $allowedVisibility)) {
    $visibility = 'Public'; // Default to Public if invalid
}

if ($weekId === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Week ID is required']);
    exit;
}

// Verify week exists and is level 5
$checkWeek = $conn->prepare("SELECT id, name, level FROM module_categories WHERE id = ? AND level = 5");
$checkWeek->bind_param("i", $weekId);
$checkWeek->execute();
$weekResult = $checkWeek->get_result();

if ($weekResult->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid week. Only weeks (level 5) can have files.']);
    exit;
}

$week = $weekResult->fetch_assoc();

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    sendJSONResponse(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileTmpName = $file['tmp_name'];
$fileSize = $file['size'];
$fileError = $file['error'];

// Validate file size (max 50MB)
$maxSize = 50 * 1024 * 1024; // 50MB
if ($fileSize > $maxSize) {
    sendJSONResponse(['success' => false, 'message' => 'File size exceeds maximum allowed size (50MB)']);
    exit;
}

// Get file extension
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// Allowed file types
$allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif'];
if (!in_array($fileExt, $allowedTypes)) {
    sendJSONResponse(['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes)]);
    exit;
}

// Create upload directory structure: uploads/modules/{week_id}/
$uploadDir = __DIR__ . '/../uploads/modules/' . $weekId . '/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        sendJSONResponse(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

// Generate unique filename
$uniqueFileName = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
$filePath = $uploadDir . $uniqueFileName;

// Move uploaded file
if (!move_uploaded_file($fileTmpName, $filePath)) {
    sendJSONResponse(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

// Store relative path in database
$relativePath = 'uploads/modules/' . $weekId . '/' . $uniqueFileName;

// Check if is_pinned column exists (for backwards compatibility)
$checkColumn = $conn->query("SHOW COLUMNS FROM module_files LIKE 'is_pinned'");
$hasPinnedColumn = $checkColumn->num_rows > 0;

// Check if visibility column exists
$checkVisibilityColumn = $conn->query("SHOW COLUMNS FROM module_files LIKE 'visibility'");
$hasVisibilityColumn = $checkVisibilityColumn->num_rows > 0;

// Insert file record
// Parameter types: i=int, s=string, d=double
// week_id=i, file_name=s, file_path=s, file_size=i, file_type=s, uploaded_by=i, description=s, visibility=s (optional), is_pinned=i (optional)
if ($hasPinnedColumn && $hasVisibilityColumn) {
    $stmt = $conn->prepare("INSERT INTO module_files (week_id, file_name, file_path, file_size, file_type, uploaded_by, description, visibility, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
    if (!$stmt) {
        @unlink($filePath);
        sendJSONResponse([
            'success' => false, 
            'message' => 'Failed to prepare statement: ' . $conn->error,
            'error_code' => $conn->errno
        ]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("issisiss", $weekId, $fileName, $relativePath, $fileSize, $fileExt, $userId, $description, $visibility);
} else if ($hasPinnedColumn) {
    // Has pinned but not visibility
    $stmt = $conn->prepare("INSERT INTO module_files (week_id, file_name, file_path, file_size, file_type, uploaded_by, description, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
    if (!$stmt) {
        @unlink($filePath);
        sendJSONResponse([
            'success' => false, 
            'message' => 'Failed to prepare statement: ' . $conn->error,
            'error_code' => $conn->errno
        ]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("issisis", $weekId, $fileName, $relativePath, $fileSize, $fileExt, $userId, $description);
} else if ($hasVisibilityColumn) {
    // Has visibility but not pinned
    $stmt = $conn->prepare("INSERT INTO module_files (week_id, file_name, file_path, file_size, file_type, uploaded_by, description, visibility) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        @unlink($filePath);
        sendJSONResponse([
            'success' => false, 
            'message' => 'Failed to prepare statement: ' . $conn->error,
            'error_code' => $conn->errno
        ]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("issisiss", $weekId, $fileName, $relativePath, $fileSize, $fileExt, $userId, $description, $visibility);
} else {
    // Fallback if neither column exists yet
    $stmt = $conn->prepare("INSERT INTO module_files (week_id, file_name, file_path, file_size, file_type, uploaded_by, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        @unlink($filePath);
        sendJSONResponse([
            'success' => false, 
            'message' => 'Failed to prepare statement: ' . $conn->error,
            'error_code' => $conn->errno
        ]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("issisis", $weekId, $fileName, $relativePath, $fileSize, $fileExt, $userId, $description);
}


// Execute INSERT statement
$executeResult = $stmt->execute();

if (!$executeResult) {
    // Delete uploaded file if database insert fails
    $error = $stmt->error;
    $errno = $stmt->errno;
    @unlink($filePath);
    $stmt->close();
    $conn->close();
    
    error_log("Upload failed - SQL Error: " . $error . " (Code: " . $errno . ")");
    error_log("Upload failed - Week ID: " . $weekId . ", File: " . $fileName . ", User: " . $userId);
    
    sendJSONResponse([
        'success' => false, 
        'message' => 'Failed to save file record: ' . $error,
        'error_code' => $errno,
        'debug' => [
            'week_id' => $weekId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'user_id' => $userId,
            'sql_error' => $error,
            'sql_errno' => $errno
        ]
    ]);
    exit;
}

$fileId = $conn->insert_id;
$affectedRows = $stmt->affected_rows;
$stmt->close();

// Verify file was actually inserted
if ($fileId > 0 && $affectedRows > 0) {
    // Verify the record exists in database
    $verifyStmt = $conn->prepare("SELECT id, file_name, file_path FROM module_files WHERE id = ?");
    $verifyStmt->bind_param("i", $fileId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $verifyStmt->close();
    
    if ($verifyResult->num_rows > 0) {
        $insertedFile = $verifyResult->fetch_assoc();
        $conn->close();
        
        error_log("Upload successful - File ID: " . $fileId . ", File: " . $fileName);
        
        sendJSONResponse([
            'success' => true,
            'message' => 'File uploaded successfully',
            'file_id' => $fileId,
            'affected_rows' => $affectedRows,
            'file' => [
                'id' => $fileId,
                'file_name' => $insertedFile['file_name'],
                'file_path' => $insertedFile['file_path'],
                'file_size' => $fileSize,
                'file_type' => $fileExt
            ]
        ]);
    } else {
        // Record not found after insert
        @unlink($filePath);
        $conn->close();
        
        error_log("Upload failed - Insert ID returned but record not found. File ID: " . $fileId);
        
        sendJSONResponse([
            'success' => false, 
            'message' => 'File uploaded but record not found in database. Please check database.',
            'file_id' => $fileId,
            'affected_rows' => $affectedRows
        ]);
    }
} else {
    // Delete uploaded file if insert ID is invalid
    @unlink($filePath);
    $conn->close();
    
    error_log("Upload failed - Invalid insert ID or affected rows. File ID: " . $fileId . ", Affected: " . $affectedRows);
    
    sendJSONResponse([
        'success' => false, 
        'message' => 'File uploaded but failed to get valid insert ID. Check database.',
        'file_id' => $fileId,
        'affected_rows' => $affectedRows
    ]);
}
?>

