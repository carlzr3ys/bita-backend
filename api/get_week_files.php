<?php
require_once '../config.php';
require_once '../api/cors.php';
// DO NOT require 'check_session.php' - it will return response and exit!
// We check session manually below

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Check user authentication - REQUIRED (manual check, no include check_session.php)
if (!isset($_SESSION['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. Please login first.'], 401);
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['admin_id']);

$conn = getDBConnection();

$weekId = isset($_GET['week_id']) ? intval($_GET['week_id']) : 0;

if ($weekId === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Week ID is required']);
    exit;
}

// Get files for this week with uploader info
// Filter by visibility: Public (everyone), Private (only uploader), Admin Only (uploader + admins)
// Order by is_pinned DESC (pinned files first), then by created_at DESC (newest first)

// Check if visibility column exists
$checkColumn = $conn->query("SHOW COLUMNS FROM module_files LIKE 'visibility'");
$hasVisibilityColumn = $checkColumn->num_rows > 0;

if ($hasVisibilityColumn) {
    // Filter by visibility rules
    // Public: everyone can see
    // Private: only visible to uploader
    // Admin Only: visible to uploader and admins
    $sql = "SELECT mf.id, mf.week_id, mf.file_name, mf.file_path, mf.file_size, mf.file_type, 
                   mf.uploaded_by, mf.description, mf.views, mf.downloads, mf.is_pinned, mf.visibility, mf.created_at,
                   u.name as uploader_name, u.matric as uploader_matric
            FROM module_files mf
            LEFT JOIN users u ON mf.uploaded_by = u.id
            WHERE mf.week_id = ?
            AND (
                mf.visibility = 'Public' 
                OR (mf.visibility = 'Private' AND mf.uploaded_by = ?)
                OR (mf.visibility = 'Admin Only' AND (mf.uploaded_by = ? OR ? = 1))
            )
            ORDER BY mf.is_pinned DESC, mf.created_at DESC";
    
    // Use admin check (1 = admin, 0 = not admin)
    $adminCheck = $isAdmin ? 1 : 0;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
        exit;
    }
    $stmt->bind_param("iiii", $weekId, $userId, $userId, $adminCheck);
} else {
    // Fallback: show all files if visibility column doesn't exist
    $sql = "SELECT mf.id, mf.week_id, mf.file_name, mf.file_path, mf.file_size, mf.file_type, 
                   mf.uploaded_by, mf.description, mf.views, mf.downloads, mf.is_pinned, mf.created_at,
                   u.name as uploader_name, u.matric as uploader_matric
            FROM module_files mf
            LEFT JOIN users u ON mf.uploaded_by = u.id
            WHERE mf.week_id = ?
            ORDER BY mf.is_pinned DESC, mf.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
        exit;
    }
    $stmt->bind_param("i", $weekId);
}

$stmt->execute();
$result = $stmt->get_result();

// Function to get full category path from week_id
function getCategoryPath($conn, $weekId) {
    if (!$weekId) return 'Unknown Location';
    
    $path = [];
    $currentId = $weekId;
    
    // Maximum 5 levels (Year > Sem > Subject > Type > Week)
    $maxLevels = 5;
    $level = 0;
    
    while ($currentId && $level < $maxLevels) {
        $stmt = $conn->prepare("SELECT id, name, parent_id, level FROM module_categories WHERE id = ?");
        $stmt->bind_param("i", $currentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            break;
        }
        
        $category = $result->fetch_assoc();
        array_unshift($path, $category['name']);
        
        $currentId = $category['parent_id'];
        $level++;
        
        $stmt->close();
        
        // Stop if no parent (root level)
        if (!$currentId) {
            break;
        }
    }
    
    return implode(' > ', $path);
}

$files = [];
while ($row = $result->fetch_assoc()) {
    // Format file size
    $row['file_size_formatted'] = formatFileSize($row['file_size']);
    // Default visibility to Public if not set
    if (!isset($row['visibility']) || empty($row['visibility'])) {
        $row['visibility'] = 'Public';
    }
    // Get full category path
    $row['category_path'] = getCategoryPath($conn, $row['week_id']);
    $files[] = $row;
}

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'files' => $files
]);

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

