<?php
require_once '../config.php';
require_once '../api/cors.php';
require_once '../api/check_admin.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check admin authentication
checkAdminAuth();
$adminId = getAdminId();

$conn = getDBConnection();

$data = json_decode(file_get_contents('php://input'), true);

$id = intval($data['id'] ?? 0);
$name = $data['name'] ?? '';
$description = $data['description'] ?? '';
$displayOrder = intval($data['display_order'] ?? 0);

if (empty($name) || $id === 0) {
    sendJSONResponse(['success' => false, 'message' => 'ID and name are required']);
    exit;
}

// Check if category exists
$check = $conn->prepare("SELECT id, name, level, parent_id FROM module_categories WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Category not found']);
    exit;
}

$category = $result->fetch_assoc();

// Check for duplicate name (excluding current category)
$checkDuplicate = $conn->prepare("SELECT id FROM module_categories WHERE name = ? AND level = ? AND (parent_id = ? OR (parent_id IS NULL AND ? IS NULL)) AND id != ?");
$checkDuplicate->bind_param("siiii", $name, $category['level'], $category['parent_id'], $category['parent_id'], $id);
$checkDuplicate->execute();
if ($checkDuplicate->get_result()->num_rows > 0) {
    sendJSONResponse(['success' => false, 'message' => 'Category with this name already exists at this level']);
    exit;
}

// Update category
$stmt = $conn->prepare("UPDATE module_categories SET name = ?, description = ?, display_order = ? WHERE id = ?");
$stmt->bind_param("ssii", $name, $description, $displayOrder, $id);

if ($stmt->execute()) {
    logAdminAction('update', 'category', $id, $name, json_encode(['level' => $category['level'], 'parent_id' => $category['parent_id']]));
    
    sendJSONResponse([
        'success' => true,
        'message' => 'Category updated successfully'
    ]);
} else {
    sendJSONResponse(['success' => false, 'message' => 'Failed to update category: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>

