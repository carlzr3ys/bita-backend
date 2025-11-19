<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Check admin session
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Only superadmin can view admin logs
if ($_SESSION['admin_role'] !== 'superadmin') {
    sendJSONResponse(['success' => false, 'message' => 'Access denied. Superadmin only.'], 403);
}

$conn = getDBConnection();

// Check if table exists
$checkTable = $conn->query("SHOW TABLES LIKE 'admin_logs'");
if ($checkTable->num_rows == 0) {
    $conn->close();
    sendJSONResponse([
        'success' => true,
        'logs' => []
    ]);
}

// Get logs with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = ($page - 1) * $limit;

// Get total count
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM admin_logs");
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalLogs = $countResult->fetch_assoc()['total'];
$countStmt->close();

// Get logs
$stmt = $conn->prepare("SELECT id, admin_id, admin_name, action, target_type, target_id, target_name, details, ip_address, created_at FROM admin_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'logs' => $logs,
    'total' => (int)$totalLogs,
    'page' => $page,
    'limit' => $limit
]);
?>

