<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check admin session
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only superadmin can create table
if ($_SESSION['admin_role'] !== 'superadmin') {
    sendJSONResponse(['success' => false, 'message' => 'Access denied. Superadmin only.'], 403);
}

$conn = getDBConnection();

// Create admin_logs table if not exists
$sql = "CREATE TABLE IF NOT EXISTS admin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    admin_name VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_id INT,
    target_name VARCHAR(255),
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    $conn->close();
    sendJSONResponse([
        'success' => true,
        'message' => 'Admin logs table created successfully'
    ]);
} else {
    $error = $conn->error;
    $conn->close();
    sendJSONResponse([
        'success' => false,
        'message' => 'Failed to create admin logs table: ' . $error
    ], 500);
}
?>

