<?php
require_once '../config.php';
require_once 'log_admin_action.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Check admin session
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only superadmin can access
if ($_SESSION['admin_role'] !== 'superadmin') {
    sendJSONResponse(['success' => false, 'message' => 'Access denied. Superadmin only.'], 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || !isset($data['email']) || !isset($data['role'])) {
    sendJSONResponse(['success' => false, 'message' => 'Name, email, and role are required'], 400);
}

$adminId = isset($data['admin_id']) && $data['admin_id'] ? (int)$data['admin_id'] : null;
$name = trim($data['name']);
$email = trim($data['email']);
$password = isset($data['password']) && $data['password'] ? $data['password'] : null;
$role = trim($data['role']);

// Validation
if (empty($name) || empty($email)) {
    sendJSONResponse(['success' => false, 'message' => 'Name and email are required'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid email format'], 400);
}

if (!in_array($role, ['superadmin', 'moderator'])) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid role'], 400);
}

if ($adminId === null && empty($password)) {
    sendJSONResponse(['success' => false, 'message' => 'Password is required for new admin'], 400);
}

if ($password && strlen($password) < 8) {
    sendJSONResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
}

$conn = getDBConnection();

// If updating, check if target admin is another superadmin
if ($adminId !== null) {
    $checkStmt = $conn->prepare("SELECT role FROM admin WHERE id = ?");
    $checkStmt->bind_param("i", $adminId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        $checkStmt->close();
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Admin not found'], 404);
    }
    
    $targetAdmin = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Cannot edit other superadmins
    if ($targetAdmin['role'] === 'superadmin' && $adminId != $_SESSION['admin_id']) {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Cannot edit other super admins'], 403);
    }
    
    // Superadmin cannot change their own role (prevent lockout)
    if ($adminId == $_SESSION['admin_id'] && $_SESSION['admin_role'] === 'superadmin' && $role !== 'superadmin') {
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Cannot change your own role from superadmin'], 400);
    }
    
    // Check if email already exists (excluding current admin)
    $emailCheckStmt = $conn->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
    $emailCheckStmt->bind_param("si", $email, $adminId);
    $emailCheckStmt->execute();
    $emailCheckResult = $emailCheckStmt->get_result();
    
    if ($emailCheckResult->num_rows > 0) {
        $emailCheckStmt->close();
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Email already exists'], 400);
    }
    $emailCheckStmt->close();
    
    // Update admin
    if ($password) {
        $hashedPassword = hashPassword($password);
        $updateStmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
        $updateStmt->bind_param("ssssi", $name, $email, $hashedPassword, $role, $adminId);
    } else {
        $updateStmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, role = ? WHERE id = ?");
        $updateStmt->bind_param("sssi", $name, $email, $role, $adminId);
    }
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        $conn->close();
        
        // Log action
        $actionDetails = ['name' => $name, 'email' => $email, 'role' => $role];
        if ($password) $actionDetails['password_changed'] = true;
        logAdminAction('update', 'admin', $adminId, $name, json_encode($actionDetails));
        
        sendJSONResponse(['success' => true, 'message' => 'Admin updated successfully']);
    } else {
        $error = $updateStmt->error;
        $updateStmt->close();
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Failed to update admin: ' . $error], 500);
    }
} else {
    // Create new admin
    // Check if email already exists
    $emailCheckStmt = $conn->prepare("SELECT id FROM admin WHERE email = ?");
    $emailCheckStmt->bind_param("s", $email);
    $emailCheckStmt->execute();
    $emailCheckResult = $emailCheckStmt->get_result();
    
    if ($emailCheckResult->num_rows > 0) {
        $emailCheckStmt->close();
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Email already exists'], 400);
    }
    $emailCheckStmt->close();
    
    $hashedPassword = hashPassword($password);
    $insertStmt = $conn->prepare("INSERT INTO admin (name, email, password, role) VALUES (?, ?, ?, ?)");
    $insertStmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
    
    if ($insertStmt->execute()) {
        $newAdminId = $conn->insert_id;
        $insertStmt->close();
        $conn->close();
        
        // Log action
        logAdminAction('create', 'admin', $newAdminId, $name, json_encode(['email' => $email, 'role' => $role]));
        
        sendJSONResponse(['success' => true, 'message' => 'Admin created successfully']);
    } else {
        $error = $insertStmt->error;
        $insertStmt->close();
        $conn->close();
        sendJSONResponse(['success' => false, 'message' => 'Failed to create admin: ' . $error], 500);
    }
}
?>

