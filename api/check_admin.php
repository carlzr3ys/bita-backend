<?php
require_once '../config.php';
require_once 'log_admin_action.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is authenticated
function checkAdminAuth() {
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_session_token'])) {
        sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        exit;
    }

    $adminId = $_SESSION['admin_id'];
    $sessionToken = $_SESSION['admin_session_token'];

    $conn = getDBConnection();

    // Verify session token
    $stmt = $conn->prepare("SELECT id, name, email, role FROM admin WHERE id = ? AND session_token = ?");
    $stmt->bind_param("is", $adminId, $sessionToken);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Invalid session
        session_destroy();
        $stmt->close();
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        exit;
    }

    $admin = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    return $admin;
}

// Get admin ID from session
function getAdminId() {
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }
    return intval($_SESSION['admin_id']);
}
?>

