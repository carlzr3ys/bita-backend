<?php
// Database Configuration for BITA Website
// Simple MySQL Backend - No Firebase required!
// Supports both local (XAMPP with .env file) and production (Render via environment variables)

// Load .env file for local development (optional)
// In production (Render), environment variables are set directly, so .env is not needed
require_once __DIR__ . '/load_env.php';

// MySQL Configuration
// For production (Render): Set via environment variables in Render Dashboard (REQUIRED!)
// For local (XAMPP): Use .env file or fallback values below
// ⚠️ WARNING: Never commit real production credentials to Git!
define('DB_HOST', getenv('DB_HOST') ?: 'localhost'); // From .env or Render env vars, fallback: localhost
define('DB_USER', getenv('DB_USER') ?: 'root'); // From .env or Render env vars, fallback: root
define('DB_PASS', getenv('DB_PASS') ?: ''); // From .env or Render env vars, fallback: empty (XAMPP)
define('DB_NAME', getenv('DB_NAME') ?: 'bita_db'); // From .env or Render env vars, fallback: bita_db

// SMTP Configuration for PHPMailer/Python Email (Optional)
// Leave empty to use PHP mail() function instead
// For Gmail: Use App Password (not regular password)
// Get App Password: https://myaccount.google.com/apppasswords
// SMTP Configuration for PHPMailer
// For production: Set via environment variables (REQUIRED!)
// For local: Use fallback values or leave empty to use PHP mail()
// ⚠️ WARNING: Never commit real SMTP credentials to Git!
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com'); // Set in Render env vars for production
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587); // Set in Render env vars for production
define('SMTP_USER', getenv('SMTP_USER') ?: ''); // Set in Render env vars for production
define('SMTP_PASS', getenv('SMTP_PASS') ?: ''); // Set in Render env vars for production

// Local File Storage Configuration (cPanel)
// Direktori di mana fail akan disimpan di server cPanel anda
// Pastikan direktori ini wujud dan mempunyai kebenaran (permissions) 755 atau 777
define('UPLOAD_DIR', getenv('UPLOAD_DIR') ?: __DIR__ . '/uploads/');

// FTP Storage Configuration (Optional - for Render backend + cPanel storage)
// Set USE_FTP_STORAGE = true jika backend di Render tapi storage di cPanel
// Set USE_FTP_STORAGE = false jika backend dan storage sama (cPanel)
define('USE_FTP_STORAGE', getenv('USE_FTP_STORAGE') ?: false);

// FTP/SFTP Configuration (only needed if USE_FTP_STORAGE = true)
define('FTP_HOST', getenv('FTP_HOST') ?: ''); // cPanel FTP host (e.g., 'ftp.yourdomain.com' or IP)
define('FTP_USER', getenv('FTP_USER') ?: ''); // cPanel FTP username
define('FTP_PASS', getenv('FTP_PASS') ?: ''); // cPanel FTP password
define('FTP_PORT', getenv('FTP_PORT') ?: 21); // FTP port (21 for FTP, 22 for SFTP)
define('FTP_USE_SSL', getenv('FTP_USE_SSL') ?: false); // Use FTPS (FTP over SSL)
define('FTP_BASE_DIR', getenv('FTP_BASE_DIR') ?: '/public_html/'); // Base directory on cPanel (usually /public_html/)
define('CPANEL_DOMAIN', getenv('CPANEL_DOMAIN') ?: ''); // Your domain for generating file URLs (e.g., 'yourdomain.com')

/**
 * Get Database Connection
 * Returns: mysqli connection
 */
function getDBConnection() {
    return getMySQLConnection();
}

/**
 * Get MySQL Connection (Helper function)
 */
function getMySQLConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("MySQL Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8
    $conn->set_charset("utf8");
    
    return $conn;
}

// Helper function to send JSON response
function sendJSONResponse($data, $statusCode = 200) {
    // Clean any output buffer before sending headers
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && 
           strpos($email, '@student.utem.edu.my') !== false;
}

// Helper function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Helper function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// CORS Configuration (for production with Netlify)
// Include this if frontend and backend are on different domains
if (getenv('NETLIFY_URL')) {
    require_once __DIR__ . '/api/cors.php';
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    session_start();
}

