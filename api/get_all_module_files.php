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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

// Check admin authentication - REQUIRED (manual check, no include check_session.php)
if (!isset($_SESSION['admin_id'])) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 401);
    exit;
}

$adminId = $_SESSION['admin_id'];
$isSuperAdmin = isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin';

error_log("Get All Module Files API - Admin ID: " . $adminId . ", Is Super Admin: " . ($isSuperAdmin ? 'yes' : 'no'));

$conn = getDBConnection();

if (!$conn) {
    ob_end_clean();
    error_log("Get All Module Files API - Database connection failed");
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

// Check if visibility column exists (for backwards compatibility)
$checkVisibilityColumn = $conn->query("SHOW COLUMNS FROM module_files LIKE 'visibility'");
$hasVisibilityColumn = $checkVisibilityColumn->num_rows > 0;

// Check if is_pinned column exists (for backwards compatibility)
$checkPinnedColumn = $conn->query("SHOW COLUMNS FROM module_files LIKE 'is_pinned'");
$hasPinnedColumn = $checkPinnedColumn->num_rows > 0;

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

// Build SQL query based on available columns
$sql = "SELECT mf.id, mf.week_id, mf.file_name, mf.file_path, mf.file_size, mf.file_type, 
               mf.uploaded_by, mf.description, mf.views, mf.downloads, mf.created_at";

// Add optional columns if they exist
if ($hasPinnedColumn) {
    $sql .= ", mf.is_pinned";
} else {
    $sql .= ", 0 as is_pinned";
}

if ($hasVisibilityColumn) {
    $sql .= ", mf.visibility";
} else {
    $sql .= ", 'Public' as visibility";
}

$sql .= ", mc.name as week_name, mc.level as week_level,
               u.name as uploader_name, u.matric as uploader_matric, u.email as uploader_email
        FROM module_files mf
        LEFT JOIN module_categories mc ON mf.week_id = mc.id
        LEFT JOIN users u ON mf.uploaded_by = u.id";

// Filter by visibility: Super admins can see all files, regular admins cannot see Private files
if ($hasVisibilityColumn && !$isSuperAdmin) {
    $sql .= " WHERE (mf.visibility != 'Private' OR mf.visibility IS NULL)";
} else {
    $sql .= " WHERE 1=1"; // Always true condition if no filter needed
}

$sql .= " ORDER BY " . ($hasPinnedColumn ? "mf.is_pinned DESC, " : "") . "mf.created_at DESC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    ob_end_clean();
    $conn->close();
    error_log("Get All Module Files API - Prepare failed: " . $conn->error);
    sendJSONResponse(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error], 500);
    exit;
}

$executeResult = $stmt->execute();

if (!$executeResult) {
    ob_end_clean();
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    error_log("Get All Module Files API - Execute failed: " . $error);
    sendJSONResponse(['success' => false, 'message' => 'Failed to execute query: ' . $error], 500);
    exit;
}

$result = $stmt->get_result();

$files = [];
$rowCount = 0;
while ($row = $result->fetch_assoc()) {
    $rowCount++;
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

error_log("Get All Module Files API - Rows found: " . $rowCount);
error_log("Get All Module Files API - Files array count: " . count($files));

$stmt->close();
$conn->close();

error_log("Get All Module Files API - Returning " . count($files) . " files");

// Clear any output buffer to prevent HTML output
ob_end_clean();

sendJSONResponse([
    'success' => true,
    'files' => $files,
    'count' => count($files),
    'debug' => [
        'admin_id' => $adminId,
        'is_super_admin' => $isSuperAdmin,
        'rows_found' => $rowCount,
        'files_count' => count($files),
        'has_visibility_column' => $hasVisibilityColumn,
        'has_pinned_column' => $hasPinnedColumn
    ]
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

