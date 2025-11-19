<?php
/**
 * Email sending helper function
 * Tries Python first, falls back to PHP mail() function
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML supported)
 * @param string $fromEmail Sender email (optional)
 * @param string $fromName Sender name (optional)
 * @return bool Success status
 */
function sendEmail($to, $subject, $message, $fromEmail = null, $fromName = null) {
    // Priority 1: Try PHPMailer first (pure PHP, no external dependencies)
    if (file_exists(__DIR__ . '/send_email_phpmailer.php')) {
        require_once __DIR__ . '/send_email_phpmailer.php';
        $smtpConfig = getSMTPConfigPHPMailer();
        
        // Only use PHPMailer if SMTP is configured
        if (!empty($smtpConfig['host']) && !empty($smtpConfig['user']) && !empty($smtpConfig['pass'])) {
            $result = sendEmailPHPMailer($to, $subject, $message, $fromEmail, $fromName, $smtpConfig);
            if ($result) {
                return true;
            }
            // If PHPMailer fails, try next option
        }
    }
    
    // Priority 2: Try Python if available
    if (file_exists(__DIR__ . '/send_email_python.php')) {
        require_once __DIR__ . '/send_email_python.php';
        $smtpConfig = getSMTPConfig();
        
        // Only use Python if SMTP is configured
        if (!empty($smtpConfig['user']) && !empty($smtpConfig['pass'])) {
            $result = sendEmailPython($to, $subject, $message, $fromEmail, $fromName, $smtpConfig);
            if ($result) {
                return true;
            }
            // If Python fails, fall back to PHP mail()
        }
    }
    
    // Priority 3: Fallback to PHP mail() function (least reliable)
    // Set default from email if not provided
    // TODO: Update this to your actual BITA Portal email address
    // For production, you may want to use: bita@utem.edu.my or admin@bita.utem.edu.my
    if (!$fromEmail) {
        $fromEmail = 'noreply@bita.utem.edu.my'; // Change this to your preferred sender email
    }
    if (!$fromName) {
        $fromName = 'BITA Portal'; // Change this to your preferred sender name
    }
    
    // Email headers
    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=UTF-8";
    $headers[] = "From: $fromName <$fromEmail>";
    $headers[] = "Reply-To: $fromEmail";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    
    $headerString = implode("\r\n", $headers);
    
    // Send email (suppress warnings to prevent HTML output errors)
    // If mail() fails, it will return false but won't output HTML warnings
    @error_reporting(0);
    $result = @mail($to, $subject, $message, $headerString);
    @error_reporting(E_ALL);
    
    return $result;
}

/**
 * Send registration approval email
 * Tries Python first, falls back to PHP mail()
 */
function sendApprovalEmail($userEmail, $userName) {
    // Priority 1: Try PHPMailer first (pure PHP, recommended)
    if (file_exists(__DIR__ . '/send_email_phpmailer.php')) {
        require_once __DIR__ . '/send_email_phpmailer.php';
        $smtpConfig = getSMTPConfigPHPMailer();
        
        if (!empty($smtpConfig['host']) && !empty($smtpConfig['user']) && !empty($smtpConfig['pass'])) {
            $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/login';
            $result = sendApprovalEmailPHPMailer($userEmail, $userName, $loginUrl, $smtpConfig);
            if ($result) {
                return true;
            }
        }
    }
    
    // Priority 2: Try Python if available
    if (file_exists(__DIR__ . '/send_email_python.php')) {
        require_once __DIR__ . '/send_email_python.php';
        $smtpConfig = getSMTPConfig();
        
        if (!empty($smtpConfig['user']) && !empty($smtpConfig['pass'])) {
            $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/login';
            $result = sendApprovalEmailPython($userEmail, $userName, $loginUrl, $smtpConfig);
            if ($result) {
                return true;
            }
        }
    }
    
    // Priority 3: Fallback to PHP mail()
    $subject = "BITA Portal - Registration Approved";
    
    $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/login';
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
                line-height: 1.6; 
                color: #1f2937; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
                background-size: 400% 400%;
                padding: 20px;
                min-height: 100vh;
            }
            .email-wrapper {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            .header {
                background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 50%, #93c5fd 100%) !important;
                background-color: #3b82f6 !important;
                color: #ffffff !important;
                padding: 50px 30px 60px;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            .cloud-decoration {
                position: absolute;
                font-size: 80px;
                opacity: 0.3;
                animation: float 6s ease-in-out infinite;
            }
            .cloud-1 { top: 20px; left: 10%; animation-delay: 0s; }
            .cloud-2 { top: 30px; right: 15%; animation-delay: 2s; }
            .cloud-3 { bottom: 20px; left: 20%; animation-delay: 4s; }
            .cloud-4 { bottom: 10px; right: 10%; animation-delay: 1s; }
            @keyframes float {
                0%, 100% { transform: translateY(0px) translateX(0px); }
                50% { transform: translateY(-20px) translateX(10px); }
            }
            .header-content {
                position: relative;
                z-index: 2;
            }
            .header h1 {
                font-size: 32px;
                font-weight: 700;
                margin-bottom: 10px;
                color: #ffffff !important;
                text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
                letter-spacing: -0.5px;
            }
            .header p {
                color: #ffffff !important;
            }
            .header .icon {
                font-size: 64px;
                margin-bottom: 15px;
                display: inline-block;
                animation: bounce 2s ease-in-out infinite;
            }
            @keyframes bounce {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-10px); }
            }
            .content {
                background: #ffffff;
                padding: 40px 30px;
            }
            .greeting {
                font-size: 18px;
                color: #374151;
                margin-bottom: 25px;
            }
            .greeting strong {
                color: #1f2937;
                font-weight: 700;
                font-size: 20px;
            }
            .message-box {
                background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
                border-left: 4px solid #10b981;
                padding: 20px;
                border-radius: 12px;
                margin: 25px 0;
            }
            .message-box p {
                color: #065f46;
                font-size: 16px;
                line-height: 1.7;
                margin: 0;
            }
            .message-box .approved-text {
                color: #059669;
                font-weight: 700;
                font-size: 18px;
            }
            .button-container {
                text-align: center;
                margin: 35px 0;
            }
            .button {
                display: inline-block;
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
                background-color: #3b82f6 !important;
                color: #ffffff !important;
                padding: 16px 40px;
                text-decoration: none !important;
                border-radius: 50px;
                font-weight: 600;
                font-size: 16px;
                box-shadow: 0 4px 15px rgba(59, 130, 246, 0.4);
                transition: all 0.3s ease;
                letter-spacing: 0.5px;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
                border: 2px solid #2563eb !important;
            }
            a.button, a.button:link, a.button:visited, a.button:active {
                color: #ffffff !important;
                text-decoration: none !important;
            }
            .info-section {
                background: #f9fafb;
                border-radius: 12px;
                padding: 20px;
                margin: 25px 0;
            }
            .info-section p {
                color: #6b7280;
                font-size: 15px;
                line-height: 1.7;
                margin: 0;
            }
            .cloud-divider {
                text-align: center;
                font-size: 40px;
                color: #dbeafe;
                margin: 30px 0;
                letter-spacing: 20px;
            }
            .signature {
                margin-top: 30px;
                padding-top: 25px;
                border-top: 2px solid #e5e7eb;
            }
            .signature p {
                color: #374151;
                font-size: 15px;
                line-height: 1.7;
            }
            .signature strong {
                color: #1f2937;
                font-weight: 700;
                font-size: 16px;
            }
            .footer {
                background: #f3f4f6;
                text-align: center;
                padding: 25px 30px;
                border-top: 1px solid #e5e7eb;
            }
            .footer p {
                color: #9ca3af;
                font-size: 13px;
                line-height: 1.6;
                margin: 0;
            }
            .cloud-footer {
                font-size: 24px;
                color: #dbeafe;
                margin: 15px 0;
                letter-spacing: 15px;
            }
            @media only screen and (max-width: 600px) {
                body { padding: 10px; }
                .email-wrapper { border-radius: 15px; }
                .header { padding: 40px 25px 50px; }
                .header h1 { font-size: 26px; }
                .header .icon { font-size: 48px; }
                .content { padding: 30px 25px; }
                .cloud-decoration { font-size: 50px; }
                .button { padding: 14px 30px; font-size: 15px; }
            }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            <div class='header' style='background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 50%, #93c5fd 100%) !important; background-color: #3b82f6 !important; color: #ffffff !important;'>
                <div class='cloud-decoration cloud-1'>☁️</div>
                <div class='cloud-decoration cloud-2'>☁️</div>
                <div class='cloud-decoration cloud-3'>☁️</div>
                <div class='cloud-decoration cloud-4'>☁️</div>
                <div class='header-content'>
                    <div class='icon'>🎉</div>
                    <h1 style='color: #ffffff !important; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);'>Registration Approved!</h1>
                    <p style='font-size: 16px; margin-top: 10px; opacity: 0.95; color: #ffffff !important;'>Welcome to BITA Portal</p>
                </div>
            </div>
            
            <div class='content'>
                <div class='greeting'>
                    Dear <strong>" . htmlspecialchars($userName) . "</strong>,
                </div>
                
                <div class='message-box'>
                    <p>
                        Good news! Your registration for the <strong>BITA Portal</strong> has been 
                        <span class='approved-text'>approved</span> by our admin team. ☁️
                    </p>
                </div>
                
                <p style='color: #374151; font-size: 16px; line-height: 1.7; margin: 20px 0;'>
                    You can now login to your account and start exploring all the amazing features of the BITA Portal. 
                    Access modules, connect with fellow students, and make the most of your cloud computing journey!
                </p>
                
                <div class='cloud-divider'>☁️ ☁️ ☁️</div>
                
                <div class='button-container'>
                    <a href='" . htmlspecialchars($loginUrl) . "' class='button' style='color: #ffffff !important; background-color: #3b82f6 !important; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important; text-decoration: none !important; border: 2px solid #2563eb !important;'>
                        ✨ Login to Portal ✨
                    </a>
                </div>
                
                <div class='info-section'>
                    <p>
                        <strong>📧 Need Help?</strong><br>
                        If you have any questions or need assistance, please don't hesitate to contact our admin team. 
                        We're here to help you succeed!
                    </p>
                </div>
                
                <div class='signature'>
                    <p>
                        Best regards,<br>
                        <strong>BITA Portal Team</strong><br>
                        <span style='color: #6b7280; font-size: 14px;'>Cloud Computing & Application Program</span>
                    </p>
                </div>
            </div>
            
            <div class='footer'>
                <div class='cloud-footer'>☁️ ☁️ ☁️</div>
                <p>This is an automated email. Please do not reply to this message.</p>
                <p style='margin-top: 10px; font-size: 12px;'>© 2025 BITA Portal. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($userEmail, $subject, $message);
}

/**
 * Send registration rejection email
 * Tries Python first, falls back to PHP mail()
 */
function sendRejectionEmail($userEmail, $userName, $reason = null) {
    // Priority 1: Try PHPMailer first (pure PHP, recommended)
    if (file_exists(__DIR__ . '/send_email_phpmailer.php')) {
        require_once __DIR__ . '/send_email_phpmailer.php';
        $smtpConfig = getSMTPConfigPHPMailer();
        
        if (!empty($smtpConfig['host']) && !empty($smtpConfig['user']) && !empty($smtpConfig['pass'])) {
            $result = sendRejectionEmailPHPMailer($userEmail, $userName, $reason, $smtpConfig);
            if ($result) {
                return true;
            }
        }
    }
    
    // Priority 2: Try Python if available
    if (file_exists(__DIR__ . '/send_email_python.php')) {
        require_once __DIR__ . '/send_email_python.php';
        $smtpConfig = getSMTPConfig();
        
        if (!empty($smtpConfig['user']) && !empty($smtpConfig['pass'])) {
            $result = sendRejectionEmailPython($userEmail, $userName, $reason, $smtpConfig);
            if ($result) {
                return true;
            }
        }
    }
    
    // Priority 3: Fallback to PHP mail()
    $subject = "BITA Portal - Registration Not Approved";
    
    $reasonText = $reason && trim($reason) !== '' 
        ? "<p style='margin: 0;'><strong>Reason:</strong> " . htmlspecialchars($reason) . "</p>" 
        : "<p style='margin: 0;'>Unfortunately, your registration could not be approved at this time.</p>";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
                line-height: 1.6; 
                color: #1f2937; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #4facfe 75%, #00f2fe 100%);
                background-size: 400% 400%;
                padding: 20px;
                min-height: 100vh;
            }
            .email-wrapper {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            .header {
                background: linear-gradient(135deg, #ef4444 0%, #f87171 50%, #fca5a5 100%) !important;
                background-color: #ef4444 !important;
                color: #ffffff !important;
                padding: 50px 30px 60px;
                text-align: center;
                position: relative;
                overflow: hidden;
            }
            .cloud-decoration {
                position: absolute;
                font-size: 80px;
                opacity: 0.2;
                animation: float 6s ease-in-out infinite;
            }
            .cloud-1 { top: 20px; left: 10%; animation-delay: 0s; }
            .cloud-2 { top: 30px; right: 15%; animation-delay: 2s; }
            .cloud-3 { bottom: 20px; left: 20%; animation-delay: 4s; }
            .cloud-4 { bottom: 10px; right: 10%; animation-delay: 1s; }
            @keyframes float {
                0%, 100% { transform: translateY(0px) translateX(0px); }
                50% { transform: translateY(-20px) translateX(10px); }
            }
            .header-content {
                position: relative;
                z-index: 2;
            }
            .header h1 {
                font-size: 32px;
                font-weight: 700;
                margin-bottom: 10px;
                text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                letter-spacing: -0.5px;
            }
            .header .icon {
                font-size: 64px;
                margin-bottom: 15px;
                display: inline-block;
                animation: shake 3s ease-in-out infinite;
            }
            @keyframes shake {
                0%, 100% { transform: translateX(0) rotate(0deg); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-5px) rotate(-2deg); }
                20%, 40%, 60%, 80% { transform: translateX(5px) rotate(2deg); }
            }
            .content {
                background: #ffffff;
                padding: 40px 30px;
            }
            .greeting {
                font-size: 18px;
                color: #374151;
                margin-bottom: 25px;
            }
            .greeting strong {
                color: #1f2937;
                font-weight: 700;
                font-size: 20px;
            }
            .message-box {
                background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                border-left: 4px solid #ef4444;
                padding: 20px;
                border-radius: 12px;
                margin: 25px 0;
            }
            .message-box p {
                color: #991b1b;
                font-size: 16px;
                line-height: 1.7;
                margin: 0;
            }
            .reason-box {
                background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
                border: 2px solid #fecaca;
                border-left: 4px solid #ef4444;
                padding: 20px;
                border-radius: 12px;
                margin: 25px 0;
            }
            .reason-box p {
                color: #991b1b;
                font-size: 15px;
                line-height: 1.7;
                margin: 0;
            }
            .reason-box strong {
                color: #dc2626;
                font-weight: 700;
            }
            .cloud-divider {
                text-align: center;
                font-size: 40px;
                color: #fecaca;
                margin: 30px 0;
                letter-spacing: 20px;
            }
            .info-section {
                background: #f9fafb;
                border-radius: 12px;
                padding: 20px;
                margin: 25px 0;
            }
            .info-section p {
                color: #6b7280;
                font-size: 15px;
                line-height: 1.7;
                margin: 0;
            }
            .signature {
                margin-top: 30px;
                padding-top: 25px;
                border-top: 2px solid #e5e7eb;
            }
            .signature p {
                color: #374151;
                font-size: 15px;
                line-height: 1.7;
            }
            .signature strong {
                color: #1f2937;
                font-weight: 700;
                font-size: 16px;
            }
            .footer {
                background: #f3f4f6;
                text-align: center;
                padding: 25px 30px;
                border-top: 1px solid #e5e7eb;
            }
            .footer p {
                color: #9ca3af;
                font-size: 13px;
                line-height: 1.6;
                margin: 0;
            }
            .cloud-footer {
                font-size: 24px;
                color: #fecaca;
                margin: 15px 0;
                letter-spacing: 15px;
            }
            @media only screen and (max-width: 600px) {
                body { padding: 10px; }
                .email-wrapper { border-radius: 15px; }
                .header { padding: 40px 25px 50px; }
                .header h1 { font-size: 26px; }
                .header .icon { font-size: 48px; }
                .content { padding: 30px 25px; }
                .cloud-decoration { font-size: 50px; }
            }
        </style>
    </head>
    <body>
        <div class='email-wrapper'>
            <div class='header' style='background: linear-gradient(135deg, #ef4444 0%, #f87171 50%, #fca5a5 100%) !important; background-color: #ef4444 !important; color: #ffffff !important;'>
                <div class='cloud-decoration cloud-1'>☁️</div>
                <div class='cloud-decoration cloud-2'>☁️</div>
                <div class='cloud-decoration cloud-3'>☁️</div>
                <div class='cloud-decoration cloud-4'>☁️</div>
                <div class='header-content'>
                    <div class='icon'>📧</div>
                    <h1 style='color: #ffffff !important; text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);'>Registration Not Approved</h1>
                    <p style='font-size: 16px; margin-top: 10px; opacity: 0.95; color: #ffffff !important;'>BITA Portal</p>
                </div>
            </div>
            
            <div class='content'>
                <div class='greeting'>
                    Dear <strong>" . htmlspecialchars($userName) . "</strong>,
                </div>
                
                <div class='message-box'>
                    <p>
                        We regret to inform you that your registration for the <strong>BITA Portal</strong> has not been approved at this time. ☁️
                    </p>
                </div>
                
                <div class='reason-box'>
                    $reasonText
                </div>
                
                <p style='color: #374151; font-size: 16px; line-height: 1.7; margin: 20px 0;'>
                    If you believe this is an error or would like to resubmit your registration with additional information, 
                    please don't hesitate to contact our admin team for assistance.
                </p>
                
                <div class='cloud-divider'>☁️ ☁️ ☁️</div>
                
                <div class='info-section'>
                    <p>
                        <strong>📧 Need Assistance?</strong><br>
                        Our admin team is here to help. If you have any questions or concerns, please contact us. 
                        We're committed to assisting you through this process.
                    </p>
                </div>
                
                <div class='signature'>
                    <p>
                        Best regards,<br>
                        <strong>BITA Portal Team</strong><br>
                        <span style='color: #6b7280; font-size: 14px;'>Cloud Computing & Application Program</span>
                    </p>
                </div>
            </div>
            
            <div class='footer'>
                <div class='cloud-footer'>☁️ ☁️ ☁️</div>
                <p>This is an automated email. Please do not reply to this message.</p>
                <p style='margin-top: 10px; font-size: 12px;'>© 2025 BITA Portal. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($userEmail, $subject, $message);
}

/**
 * Get SMTP configuration (for backward compatibility)
 */
function getSMTPConfig() {
    // This is for Python email support
    // PHPMailer uses getSMTPConfigPHPMailer()
    return [
        'host' => defined('SMTP_HOST') ? SMTP_HOST : (getenv('SMTP_HOST') ?: ''),
        'port' => defined('SMTP_PORT') ? SMTP_PORT : (getenv('SMTP_PORT') ?: 587),
        'user' => defined('SMTP_USER') ? SMTP_USER : (getenv('SMTP_USER') ?: ''),
        'pass' => defined('SMTP_PASS') ? SMTP_PASS : (getenv('SMTP_PASS') ?: '')
    ];
}
?>

