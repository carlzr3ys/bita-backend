<?php
require_once '../config.php';
require_once '../api/cors.php';
require_once '../api/check_admin.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check admin authentication
checkAdminAuth();
$adminId = getAdminId();

if (!$adminId) {
    sendJSONResponse(['success' => false, 'message' => 'Admin ID not found']);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid JSON data']);
    exit;
}

$name = trim($data['name'] ?? '');
$level = isset($data['level']) ? intval($data['level']) : null;
$parentId = isset($data['parent_id']) && $data['parent_id'] ? intval($data['parent_id']) : null;
$description = trim($data['description'] ?? '');
$displayOrder = intval($data['display_order'] ?? 0);

// Validation
if (empty($name)) {
    sendJSONResponse(['success' => false, 'message' => 'Name is required']);
    exit;
}

// Validate level
if ($level === null || $level === false || $level === '') {
    sendJSONResponse(['success' => false, 'message' => 'Level is required and must be a number between 1 and 5']);
    exit;
}

$level = intval($level);
if ($level < 1 || $level > 5) {
    sendJSONResponse(['success' => false, 'message' => 'Level must be between 1 and 5. Received: ' . var_export($data['level'], true)]);
    exit;
}

// Validate parent exists if provided
if ($parentId) {
    $checkParent = $conn->prepare("SELECT id, level FROM module_categories WHERE id = ?");
    $checkParent->bind_param("i", $parentId);
    $checkParent->execute();
    $parentResult = $checkParent->get_result();
    
    if ($parentResult->num_rows === 0) {
        sendJSONResponse(['success' => false, 'message' => 'Parent category not found']);
        exit;
    }
    
    $parent = $parentResult->fetch_assoc();
    // Validate level is parent level + 1
    if ($parent['level'] + 1 !== $level) {
        sendJSONResponse(['success' => false, 'message' => 'Invalid level for this parent']);
        exit;
    }
}

// Check for duplicate name at same level and parent
if ($parentId) {
    $checkDuplicate = $conn->prepare("SELECT id FROM module_categories WHERE name = ? AND level = ? AND parent_id = ?");
    $checkDuplicate->bind_param("sii", $name, $level, $parentId);
} else {
    $checkDuplicate = $conn->prepare("SELECT id FROM module_categories WHERE name = ? AND level = ? AND parent_id IS NULL");
    $checkDuplicate->bind_param("si", $name, $level);
}
$checkDuplicate->execute();
if ($checkDuplicate->get_result()->num_rows > 0) {
    sendJSONResponse(['success' => false, 'message' => 'Category with this name already exists at this level']);
    exit;
}

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'module_categories'");
if ($tableCheck->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Database table not found. Please run database/module_categories.sql to create the table.']);
    exit;
}

// Insert category
// Use NULL for created_by if adminId is not valid
$finalAdminId = $adminId && $adminId > 0 ? $adminId : null;

// Build SQL with proper NULL handling
$sql = "INSERT INTO module_categories (name, level, parent_id, description, display_order, created_by) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    sendJSONResponse(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

// Bind parameters - use 'i' for integers even if NULL, mysqli will handle it
$stmt->bind_param("siisii", $name, $level, $parentId, $description, $displayOrder, $finalAdminId);

if ($stmt->execute()) {
    $categoryId = $conn->insert_id;
    
    // Log admin action (optional, don't fail if logging fails)
    try {
        logAdminAction('create', 'category', $categoryId, $name, json_encode(['level' => $level, 'parent_id' => $parentId]));
    } catch (Exception $e) {
        // Log error but don't fail the request
        error_log("Failed to log admin action: " . $e->getMessage());
    }
    
    sendJSONResponse([
        'success' => true,
        'message' => 'Category created successfully',
        'category_id' => $categoryId
    ]);
} else {
    $errorMsg = $stmt->error ?: $conn->error;
    sendJSONResponse(['success' => false, 'message' => 'Failed to create category: ' . $errorMsg]);
}

$stmt->close();
$conn->close();
?>

