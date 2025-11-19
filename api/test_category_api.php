<?php
require_once '../config.php';
require_once '../api/cors.php';
require_once '../api/check_admin.php';

header('Content-Type: application/json');

// Test endpoint to check category API setup
$conn = getDBConnection();

$checks = [
    'database_connected' => $conn ? true : false,
    'table_exists' => false,
    'admin_logged_in' => isset($_SESSION['admin_id']),
    'admin_id' => $_SESSION['admin_id'] ?? null
];

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'module_categories'");
$checks['table_exists'] = $tableCheck->num_rows > 0;

if ($checks['table_exists']) {
    // Check table structure
    $structure = $conn->query("DESCRIBE module_categories");
    $checks['table_structure'] = [];
    while ($row = $structure->fetch_assoc()) {
        $checks['table_structure'][] = $row;
    }
    
    // Check if admins table exists (for foreign key)
    $adminsCheck = $conn->query("SHOW TABLES LIKE 'admin'");
    $checks['admins_table_exists'] = $adminsCheck->num_rows > 0;
    
    if ($checks['admins_table_exists'] && isset($_SESSION['admin_id'])) {
        $adminId = $_SESSION['admin_id'];
        $adminCheck = $conn->prepare("SELECT id FROM admin WHERE id = ?");
        $adminCheck->bind_param("i", $adminId);
        $adminCheck->execute();
        $checks['admin_exists'] = $adminCheck->get_result()->num_rows > 0;
    }
}

$conn->close();

sendJSONResponse([
    'success' => true,
    'checks' => $checks,
    'message' => $checks['table_exists'] ? 'Table exists' : 'Table does not exist. Please run database/module_categories.sql'
]);
?>

