<?php
/**
 * Test Email Functionality
 * Use this to test if email sending is working properly
 * 
 * Usage: Open in browser: http://localhost/bita/api/test_email.php?email=your@email.com
 */

// Prevent any output before headers
ob_start();

require_once '../config.php';
require_once 'send_email.php';

// Clean any unexpected output
if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/html; charset=UTF-8');

// Get test email from query parameter
$testEmail = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($testEmail)) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Test Email - BITA Portal</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .form-group { margin: 20px 0; }
            label { display: block; margin-bottom: 5px; font-weight: bold; }
            input[type='email'] { width: 100%; padding: 10px; font-size: 14px; border: 1px solid #ddd; border-radius: 4px; }
            button { background: #3b82f6; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
            button:hover { background: #2563eb; }
            .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
            .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
            .error { background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; }
            .info { background: #dbeafe; border: 1px solid #3b82f6; color: #1e40af; }
            code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        </style>
    </head>
    <body>
        <h1>📧 Test Email Functionality</h1>
        <div class='info result'>
            <strong>Instructions:</strong><br>
            1. Enter your email address below<br>
            2. Click 'Send Test Email'<br>
            3. Check your inbox (and spam folder) for the test email<br><br>
            <strong>Note:</strong> This will send a test approval email to verify the email system is working.
        </div>
        
        <form method='GET' action=''>
            <div class='form-group'>
                <label for='email'>Test Email Address:</label>
                <input type='email' id='email' name='email' placeholder='your.email@student.utem.edu.my' value='" . (isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '') . "' required>
                <small style='display: block; margin-top: 5px; color: #666;'>Enter student email (e.g., example@student.utem.edu.my) or any email for testing</small>
            </div>
            <button type='submit'>Send Test Email</button>
        </form>
        
        <div style='margin-top: 15px; padding: 10px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;'>
            <strong>⚠️ Important untuk UTeM Student Email:</strong><br>
            Jika email tidak sampai dalam inbox, <strong>sila check Spam/Junk folder!</strong> UTeM email servers kadang filter emails dari external sources.
        </div>
        
        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
            <h3>Troubleshooting:</h3>
            <ul>
                <li>If you see a <strong>success message</strong> but don't receive email:
                    <ul>
                        <li>Check your spam/junk folder</li>
                        <li>Verify email address is correct</li>
                        <li>Check server mail logs</li>
                    </ul>
                </li>
                <li>If you see an <strong>error message</strong>:
                    <ul>
                        <li>PHP mail() function may not be configured</li>
                        <li>Server may need SMTP configuration</li>
                        <li>Check PHP error logs</li>
                    </ul>
                </li>
                <li>For production, consider using:
                    <ul>
                        <li>PHPMailer with SMTP</li>
                        <li>SendGrid, Mailgun, or AWS SES</li>
                    </ul>
                </li>
            </ul>
        </div>
    </body>
    </html>";
    exit;
}

// Validate email
if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Test Email - Error</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .error { background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 15px; border-radius: 4px; }
            a { color: #3b82f6; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='error'>
            <strong>Invalid Email Address:</strong> $testEmail<br><br>
            <a href='test_email.php'>← Go Back</a>
        </div>
    </body>
    </html>";
    exit;
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Email - Result</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .result { margin: 20px 0; padding: 15px; border-radius: 4px; }
        .success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
        .error { background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; }
        .info { background: #dbeafe; border: 1px solid #3b82f6; color: #1e40af; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        a { color: #3b82f6; text-decoration: none; }
        pre { background: #f3f4f6; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>📧 Test Email Result</h1>";

// Test 1: Check if mail() function exists
echo "<div class='info result'>";
echo "<strong>Test 1: PHP mail() Function Check</strong><br>";
if (function_exists('mail')) {
    echo "✅ PHP <code>mail()</code> function is available<br>";
} else {
    echo "❌ PHP <code>mail()</code> function is NOT available<br>";
}
echo "</div>";

// Test 2: Check PHP mail configuration
echo "<div class='info result'>";
echo "<strong>Test 2: PHP Mail Configuration</strong><br>";
$sendmailPath = ini_get('sendmail_path');
if ($sendmailPath) {
    echo "✅ Sendmail path: <code>$sendmailPath</code><br>";
} else {
    echo "⚠️ Sendmail path not configured<br>";
}
echo "SMTP: " . (ini_get('SMTP') ?: 'Not set') . "<br>";
echo "smtp_port: " . (ini_get('smtp_port') ?: 'Not set') . "<br>";
echo "</div>";

// Test 3: Try to send test email
echo "<div class='info result'>";
echo "<strong>Test 3: Sending Test Email</strong><br>";
echo "To: <code>$testEmail</code><br>";
echo "Attempting to send...<br><br>";
echo "</div>";

try {
    // Capture any output/warnings from mail function
    ob_start();
    
    // Send test approval email
    $emailSent = sendApprovalEmail($testEmail, 'Test User');
    
    // Get any output/warnings
    $output = ob_get_clean();
    
    if ($emailSent) {
        echo "<div class='success result'>";
        echo "<strong>✅ Email Sent Successfully!</strong><br><br>";
        echo "The test approval email has been sent to <code>$testEmail</code><br><br>";
        echo "<strong>What to check:</strong><br>";
        echo "1. Check your inbox for the email<br>";
        echo "2. Check your spam/junk folder<br>";
        echo "3. Email subject should be: <strong>BITA Portal - Registration Approved</strong><br>";
        echo "4. If you don't receive it, the server mail() function may not be properly configured<br>";
        echo "</div>";
        
        if ($output && trim($output) !== '') {
            echo "<div class='info result'>";
            echo "<strong>Note:</strong> There was some output during email sending:<br>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
            echo "</div>";
        }
    } else {
        echo "<div class='error result'>";
        echo "<strong>❌ Email Sending Failed</strong><br><br>";
        echo "The <code>mail()</code> function returned <code>false</code>, which means:<br>";
        echo "1. The email was not sent<br>";
        echo "2. This could be due to server mail configuration issues<br>";
        echo "3. Check PHP error logs for more details<br><br>";
        
        if ($output && trim($output) !== '') {
            echo "<strong>Output/Warnings:</strong><br>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        }
        
        echo "<br><strong>Solutions:</strong><br>";
        echo "• Configure PHP mail() on your server<br>";
        echo "• Use SMTP with PHPMailer library<br>";
        echo "• Use email service (SendGrid, Mailgun, AWS SES)<br>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<div class='error result'>";
    echo "<strong>❌ Error: Exception Thrown</strong><br><br>";
    echo "Error message: <code>" . htmlspecialchars($e->getMessage()) . "</code><br>";
    echo "File: <code>" . htmlspecialchars($e->getFile()) . "</code><br>";
    echo "Line: <code>" . $e->getLine() . "</code><br>";
    echo "</div>";
}

echo "<div style='margin-top: 30px;'>";
echo "<a href='test_email.php'>← Test Another Email</a>";
echo "</div>";

echo "</body></html>";
?>

