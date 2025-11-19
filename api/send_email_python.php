<?php
/**
 * Python Email Sender Wrapper for PHP
 * This file provides PHP functions that call the Python email script
 */

/**
 * Send email using Python script
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body (HTML supported)
 * @param string $fromEmail Sender email (optional)
 * @param string $fromName Sender name (optional)
 * @param array $smtpConfig SMTP configuration (optional)
 * @return bool Success status
 */
function sendEmailPython($to, $subject, $message, $fromEmail = null, $fromName = null, $smtpConfig = null) {
    $scriptPath = __DIR__ . '/send_email.py';
    
    // Check if Python script exists
    if (!file_exists($scriptPath)) {
        error_log("Python email script not found: $scriptPath");
        return false;
    }
    
    // Check if Python is available
    $pythonCmd = 'python';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $pythonCmd = 'python'; // Try python first
        $output = [];
        $returnVar = 0;
        @exec('python --version 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            $pythonCmd = 'py'; // Try py launcher
        }
    } else {
        // Linux/Mac
        $pythonCmd = 'python3';
        $output = [];
        $returnVar = 0;
        @exec('python3 --version 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            $pythonCmd = 'python';
        }
    }
    
    // Build command
    $cmd = escapeshellarg($pythonCmd) . ' ' . escapeshellarg($scriptPath);
    $cmd .= ' --to ' . escapeshellarg($to);
    $cmd .= ' --subject ' . escapeshellarg($subject);
    $cmd .= ' --message ' . escapeshellarg($message);
    $cmd .= ' --type custom';
    
    if ($fromEmail) {
        $cmd .= ' --from-email ' . escapeshellarg($fromEmail);
    }
    if ($fromName) {
        $cmd .= ' --from-name ' . escapeshellarg($fromName);
    }
    
    // Add SMTP config if provided
    if ($smtpConfig) {
        if (isset($smtpConfig['host'])) {
            $cmd .= ' --smtp-host ' . escapeshellarg($smtpConfig['host']);
        }
        if (isset($smtpConfig['port'])) {
            $cmd .= ' --smtp-port ' . escapeshellarg($smtpConfig['port']);
        }
        if (isset($smtpConfig['user'])) {
            $cmd .= ' --smtp-user ' . escapeshellarg($smtpConfig['user']);
        }
        if (isset($smtpConfig['pass'])) {
            $cmd .= ' --smtp-pass ' . escapeshellarg($smtpConfig['pass']);
        }
    }
    
    // Execute command
    $output = [];
    $returnVar = 0;
    @exec($cmd . ' 2>&1', $output, $returnVar);
    
    if ($returnVar === 0) {
        // Check output for success
        $outputStr = implode("\n", $output);
        if (strpos($outputStr, 'SUCCESS:') !== false) {
            return true;
        }
    }
    
    // Log error
    $errorMsg = implode("\n", $output);
    error_log("Python email sending failed: $errorMsg");
    return false;
}

/**
 * Send approval email using Python
 */
function sendApprovalEmailPython($userEmail, $userName, $loginUrl = null, $smtpConfig = null) {
    $scriptPath = __DIR__ . '/send_email.py';
    
    if (!file_exists($scriptPath)) {
        error_log("Python email script not found: $scriptPath");
        return false;
    }
    
    // Determine Python command
    $pythonCmd = 'python';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        $returnVar = 0;
        @exec('python --version 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            $pythonCmd = 'py';
        }
    } else {
        $pythonCmd = 'python3';
        $output = [];
        $returnVar = 0;
        @exec('python3 --version 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            $pythonCmd = 'python';
        }
    }
    
    // Build command
    $cmd = escapeshellarg($pythonCmd) . ' ' . escapeshellarg($scriptPath);
    $cmd .= ' --to ' . escapeshellarg($userEmail);
    $cmd .= ' --type approval';
    $cmd .= ' --name ' . escapeshellarg($userName);
    
    if ($loginUrl) {
        $cmd .= ' --login-url ' . escapeshellarg($loginUrl);
    }
    
    // Add SMTP config if provided
    if ($smtpConfig) {
        if (isset($smtpConfig['host'])) {
            $cmd .= ' --smtp-host ' . escapeshellarg($smtpConfig['host']);
        }
        if (isset($smtpConfig['port'])) {
            $cmd .= ' --smtp-port ' . escapeshellarg($smtpConfig['port']);
        }
        if (isset($smtpConfig['user'])) {
            $cmd .= ' --smtp-user ' . escapeshellarg($smtpConfig['user']);
        }
        if (isset($smtpConfig['pass'])) {
            $cmd .= ' --smtp-pass ' . escapeshellarg($smtpConfig['pass']);
        }
    }
    
    // Execute
    $output = [];
    $returnVar = 0;
    @exec($cmd . ' 2>&1', $output, $returnVar);
    
    if ($returnVar === 0) {
        $outputStr = implode("\n", $output);
        if (strpos($outputStr, 'SUCCESS:') !== false) {
            return true;
        }
    }
    
    error_log("Python approval email failed: " . implode("\n", $output));
    return false;
}

/**
 * Send rejection email using Python
 */
function sendRejectionEmailPython($userEmail, $userName, $reason = null, $smtpConfig = null) {
    $scriptPath = __DIR__ . '/send_email.py';
    
    if (!file_exists($scriptPath)) {
        error_log("Python email script not found: $scriptPath");
        return false;
    }
    
    // Determine Python command
    $pythonCmd = 'python';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        $returnVar = 0;
        @exec('python --version 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            $pythonCmd = 'py';
        }
    } else {
        $pythonCmd = 'python3';
        $output = [];
        $returnVar = 0;
        @exec('python3 --version 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            $pythonCmd = 'python';
        }
    }
    
    // Build command
    $cmd = escapeshellarg($pythonCmd) . ' ' . escapeshellarg($scriptPath);
    $cmd .= ' --to ' . escapeshellarg($userEmail);
    $cmd .= ' --type rejection';
    $cmd .= ' --name ' . escapeshellarg($userName);
    
    if ($reason) {
        $cmd .= ' --reason ' . escapeshellarg($reason);
    }
    
    // Add SMTP config if provided
    if ($smtpConfig) {
        if (isset($smtpConfig['host'])) {
            $cmd .= ' --smtp-host ' . escapeshellarg($smtpConfig['host']);
        }
        if (isset($smtpConfig['port'])) {
            $cmd .= ' --smtp-port ' . escapeshellarg($smtpConfig['port']);
        }
        if (isset($smtpConfig['user'])) {
            $cmd .= ' --smtp-user ' . escapeshellarg($smtpConfig['user']);
        }
        if (isset($smtpConfig['pass'])) {
            $cmd .= ' --smtp-pass ' . escapeshellarg($smtpConfig['pass']);
        }
    }
    
    // Execute
    $output = [];
    $returnVar = 0;
    @exec($cmd . ' 2>&1', $output, $returnVar);
    
    if ($returnVar === 0) {
        $outputStr = implode("\n", $output);
        if (strpos($outputStr, 'SUCCESS:') !== false) {
            return true;
        }
    }
    
    error_log("Python rejection email failed: " . implode("\n", $output));
    return false;
}

/**
 * Get SMTP configuration from environment variables or config
 */
function getSMTPConfig() {
    // You can set these in config.php or environment variables
    $config = [
        'host' => getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com'),
        'port' => getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587),
        'user' => getenv('SMTP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : ''),
        'pass' => getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : '')
    ];
    
    return $config;
}
?>

