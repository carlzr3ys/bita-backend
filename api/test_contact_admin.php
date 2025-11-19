<?php
require_once '../config.php';
require_once 'check_session.php';

header('Content-Type: application/json');

// Check if tables exist
$conn = getDBConnection();

if (!$conn) {
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

$tables = ['conversations', 'messages', 'admin_contact_requests'];
$tableStatus = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $tableStatus[$table] = $result->num_rows > 0 ? 'EXISTS' : 'NOT EXISTS';
}

// Check if user is authenticated
$isAuthenticated = isset($_SESSION['user_id']);
$userId = $isAuthenticated ? $_SESSION['user_id'] : null;

// Check user exists
$userExists = false;
if ($userId) {
    $stmt = $conn->prepare("SELECT id, name, matric FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userExists = $result->num_rows > 0;
    $stmt->close();
}

// Count existing records
$counts = [];
if ($tableStatus['conversations'] === 'EXISTS') {
    $result = $conn->query("SELECT COUNT(*) as count FROM conversations");
    $counts['conversations'] = $result->fetch_assoc()['count'];
}

if ($tableStatus['messages'] === 'EXISTS') {
    $result = $conn->query("SELECT COUNT(*) as count FROM messages");
    $counts['messages'] = $result->fetch_assoc()['count'];
}

if ($tableStatus['admin_contact_requests'] === 'EXISTS') {
    $result = $conn->query("SELECT COUNT(*) as count FROM admin_contact_requests");
    $counts['admin_contact_requests'] = $result->fetch_assoc()['count'];
}

$conn->close();

sendJSONResponse([
    'success' => true,
    'tables' => $tableStatus,
    'counts' => $counts,
    'authenticated' => $isAuthenticated,
    'user_id' => $userId,
    'user_exists' => $userExists
], 200);
?>

