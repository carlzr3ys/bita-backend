<?php
require_once '../config.php';
// DO NOT require 'check_admin_session.php' - it will return response and exit!
// We check admin authentication manually below

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Check admin authentication manually (no include check_admin_session.php)
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
    exit;
}

$conn = getDBConnection();

// Get pending contact requests
$status = isset($_GET['status']) ? $_GET['status'] : 'Pending';

$stmt = $conn->prepare("
    SELECT 
        acr.id as request_id,
        acr.name,
        acr.matric,
        acr.phone,
        acr.message,
        acr.status,
        acr.created_at,
        acr.resolved_at,
        u.id as user_id,
        u.email,
        u.program
    FROM admin_contact_requests acr
    LEFT JOIN users u ON u.matric = acr.matric
    WHERE acr.status = ?
    ORDER BY acr.created_at DESC
");

$stmt->bind_param("s", $status);
$stmt->execute();
$result = $stmt->get_result();

error_log("Get Admin Contact Requests - Status: $status");

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = [
        'id' => $row['request_id'],
        'name' => $row['name'],
        'matric' => $row['matric'],
        'phone' => $row['phone'],
        'message' => $row['message'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'resolved_at' => $row['resolved_at'],
        'user_id' => $row['user_id'], // NULL if user not found
        'user_email' => $row['email'],
        'user_program' => $row['program'],
        'has_account' => $row['user_id'] !== null
    ];
}

error_log("Get Admin Contact Requests - Found " . count($requests) . " requests with status: $status");

$stmt->close();
$conn->close();

sendJSONResponse([
    'success' => true,
    'requests' => $requests,
    'count' => count($requests),
    'debug' => [
        'status_filter' => $status,
        'total_found' => count($requests)
    ]
]);
?>

