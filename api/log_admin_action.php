<?php
require_once '../config.php';

// Function to log admin actions
function logAdminAction($action, $targetType, $targetId = null, $targetName = null, $details = null) {
    if (!isset($_SESSION['admin_id'])) {
        return false; // No admin session, skip logging
    }

    $adminId = $_SESSION['admin_id'];
    $adminName = $_SESSION['admin_name'] ?? 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $conn = getDBConnection();

    try {
        // Check if table exists, if not create it
        $checkTable = $conn->query("SHOW TABLES LIKE 'admin_logs'");
        if ($checkTable->num_rows == 0) {
            // Create table
            $createTable = "CREATE TABLE IF NOT EXISTS admin_logs (
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
                INDEX idx_admin_id (admin_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            if (!$conn->query($createTable)) {
                error_log("Failed to create admin_logs table: " . $conn->error);
                $conn->close();
                return false;
            }
        }

        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, admin_name, action, target_type, target_id, target_name, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("isssissss", 
            $adminId, 
            $adminName, 
            $action, 
            $targetType, 
            $targetId, 
            $targetName, 
            $details, 
            $ipAddress, 
            $userAgent
        );

        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        if (isset($conn)) {
            $conn->close();
        }
        return false;
    }
}
?>

