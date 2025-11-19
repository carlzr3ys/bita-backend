<?php
require_once '../config.php';
require_once '../api/cors.php';
require_once '../api/check_admin.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check admin authentication
checkAdminAuth();
$adminId = getAdminId();

$conn = getDBConnection();

// Get ID from query string or body
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($_GET['id']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
}

if ($id === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Category ID is required']);
    exit;
}

// Check if category exists
$check = $conn->prepare("SELECT id, name, level FROM module_categories WHERE id = ?");
$check->bind_param("i", $id);
$check->execute();
$result = $check->get_result();

if ($result->num_rows === 0) {
    sendJSONResponse(['success' => false, 'message' => 'Category not found']);
    exit;
}

$category = $result->fetch_assoc();

// Check if category has children or files
$checkChildren = $conn->prepare("SELECT COUNT(*) as count FROM module_categories WHERE parent_id = ?");
$checkChildren->bind_param("i", $id);
$checkChildren->execute();
$childrenResult = $checkChildren->get_result();
$childrenCount = $childrenResult->fetch_assoc()['count'];

if ($category['level'] === 5) {
    // Check if week has files
    $checkFiles = $conn->prepare("SELECT COUNT(*) as count FROM module_files WHERE week_id = ?");
    $checkFiles->bind_param("i", $id);
    $checkFiles->execute();
    $filesResult = $checkFiles->get_result();
    $filesCount = $filesResult->fetch_assoc()['count'];
    
    if ($filesCount > 0) {
        sendJSONResponse(['success' => false, 'message' => "Cannot delete week with $filesCount file(s). Please delete files first."]);
        exit;
    }
}

if ($childrenCount > 0) {
    sendJSONResponse(['success' => false, 'message' => "Cannot delete category with $childrenCount child category(ies). Please delete children first."]);
    exit;
}

// Delete category (CASCADE will handle children if any)
$stmt = $conn->prepare("DELETE FROM module_categories WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    logAdminAction('delete', 'category', $id, $category['name'], json_encode(['level' => $category['level']]));
    
    sendJSONResponse([
        'success' => true,
        'message' => 'Category deleted successfully'
    ]);
} else {
    sendJSONResponse(['success' => false, 'message' => 'Failed to delete category: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>

