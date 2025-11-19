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

$conn = getDBConnection();

// Get user info before update for logging
$getUserStmt = $conn->prepare("SELECT name, email, matric FROM users WHERE id = ?");
$getUserStmt->bind_param("i", $userId);
$getUserStmt->execute();
$userResult = $getUserStmt->get_result();
$user = $userResult->fetch_assoc();
$getUserStmt->close();

// Update user to verified
$stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_comment = NULL, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    
    // Log action
    logAdminAction('approve', 'user', $userId, $user['name'] ?? 'User #' . $userId, json_encode(['email' => $user['email'] ?? '', 'matric' => $user['matric'] ?? '']));
    
    // Send approval email to user (suppress any output/errors from mail function)
    $emailSent = false;
    if (!empty($user['email'])) {
        try {
            // Suppress warnings from mail() function to prevent HTML output
            ob_start();
            $emailSent = sendApprovalEmail($user['email'], $user['name'] ?? 'User');
            $output = ob_get_clean();
            // If there's any unexpected output, log it but don't let it affect the JSON response
            if ($output && trim($output) !== '') {
                error_log('Unexpected output from sendApprovalEmail: ' . $output);
            }
        } catch (Exception $e) {
            // Clean any output buffer
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log('Failed to send approval email: ' . $e->getMessage());
        }
    }
    
    sendJSONResponse([
        'success' => true,
        'message' => 'User approved successfully' . ($emailSent ? '. Notification email sent.' : ''),
        'email_sent' => $emailSent
    ]);
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Failed to approve user: ' . $error], 500);
}
?>

