<?php
require_once '../config.php';
require_once '../api/cors.php';

header('Content-Type: application/json');

$conn = getDBConnection();

// Get all categories with their hierarchy
$sql = "SELECT id, name, level, parent_id, description, display_order, created_at, updated_at
        FROM module_categories
        ORDER BY level ASC, parent_id ASC, display_order ASC, name ASC";

$result = $conn->query($sql);

$categories = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Build hierarchical structure
function buildTree($categories, $parentId = null) {
    $tree = [];
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parentId) {
            $children = buildTree($categories, $category['id']);
            if (!empty($children)) {
                $category['children'] = $children;
            }
            $tree[] = $category;
        }
    }
    return $tree;
}

$tree = buildTree($categories);

$conn->close();

sendJSONResponse([
    'success' => true,
    'categories' => $categories,
    'tree' => $tree
]);
?>

