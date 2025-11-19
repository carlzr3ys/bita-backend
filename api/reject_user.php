<?php
// Prevent any output before headers
ob_start();

require_once '../config.php';
require_once 'log_admin_action.php';
require_once 'send_email.php';

// Clean any unexpected output
if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check admin session
if (!isset($_SESSION['admin_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['user_id'])) {
    sendJSONResponse(['success' => false, 'message' => 'User ID required'], 400);
}

$userId = (int)$data['user_id'];
$comment = isset($data['comment']) ? trim($data['comment']) : (isset($data['reason']) ? trim($data['reason']) : null);

$conn = getDBConnection();

// Get user info before deletion for logging
$getStmt = $conn->prepare("SELECT name, email, matric, matric_card FROM users WHERE id = ?");
$getStmt->bind_param("i", $userId);
$getStmt->execute();
$result = $getStmt->get_result();
$user = $result->fetch_assoc();
$getStmt->close();

if (!$user) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'User not found'], 404);
}

// Delete the user
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    // Delete the uploaded matric card file if it exists
    if ($user['matric_card']) {
        $filePath = '../' . $user['matric_card'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    $stmt->close();
    $conn->close();
    
    // Log action
    logAdminAction('reject', 'user', $userId, $user['name'] ?? 'User #' . $userId, json_encode(['email' => $user['email'] ?? '', 'matric' => $user['matric'] ?? '', 'reason' => $comment]));
    
    // Send rejection email to user before account deletion (suppress any output/errors from mail function)
    $emailSent = false;
    if (!empty($user['email'])) {
        try {
            // Suppress warnings from mail() function to prevent HTML output
            ob_start();
            $emailSent = sendRejectionEmail($user['email'], $user['name'] ?? 'User', $comment);
            $output = ob_get_clean();
            // If there's any unexpected output, log it but don't let it affect the JSON response
            if ($output && trim($output) !== '') {
                error_log('Unexpected output from sendRejectionEmail: ' . $output);
            }
        } catch (Exception $e) {
            // Clean any output buffer
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Failed to send rejection email: ' . $e->getMessage());
        }
    }
    
    sendJSONResponse([
        'success' => true,
        'message' => 'User rejected and removed successfully' . ($emailSent ? '. Notification email sent.' : ''),
        'email_sent' => $emailSent
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to reject user: ' . $error], 500);
}
?>

