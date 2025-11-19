<?php
require_once '../config.php';
require_once '../api/cors.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
    exit;
}

$categoryId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($categoryId === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Category ID is required'], 400);
    exit;
}

$conn = getDBConnection();

// Get category by ID
$stmt = $conn->prepare("SELECT id, name, level, parent_id, description, display_order, created_at, updated_at FROM module_categories WHERE id = ?");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Category not found'], 404);
    exit;
}

$category = $result->fetch_assoc();

// Get full path
$path = [];
$currentId = $categoryId;
$maxLevels = 5;
$level = 0;

while ($currentId && $level < $maxLevels) {
    $pathStmt = $conn->prepare("SELECT id, name, parent_id, level FROM module_categories WHERE id = ?");
    $pathStmt->bind_param("i", $currentId);
    $pathStmt->execute();
    $pathResult = $pathStmt->get_result();
    
    if ($pathResult->num_rows === 0) {
        break;
    }
    
    $pathCategory = $pathResult->fetch_assoc();
    array_unshift($path, $pathCategory);
    
    $currentId = $pathCategory['parent_id'];
    $level++;
    
    $pathStmt->close();
    
    if (!$currentId) {
        break;
    }
}

$stmt->close();
$conn->close();

// Build path string
$pathString = implode(' > ', array_column($path, 'name'));

sendJSONResponse([
    'success' => true,
    'category' => $category,
    'path' => $path,
    'path_string' => $pathString
]);
?>

