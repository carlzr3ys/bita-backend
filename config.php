<?php
// Database Configuration for BITA Website
// Simple MySQL Backend - No Firebase required!
// Supports both local (XAMPP) and production (environment variables)

// MySQL Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ''); // Default XAMPP password is empty
define('DB_NAME', getenv('DB_NAME') ?: 'bita_db');

// SMTP Configuration for PHPMailer/Python Email (Optional)
// Leave empty to use PHP mail() function instead
// For Gmail: Use App Password (not regular password)
// Get App Password: https://myaccount.google.com/apppasswords
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com'); // Gmail SMTP
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587); // Port untuk TLS
define('SMTP_USER', getenv('SMTP_USER') ?: 'bitaadm2425@gmail.com'); // Gmail address
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'zvus dklg hxvh pkdz'); // Gmail App Password

// Cloudinary Configuration for File Storage
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'dexn4m4pr');
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '914463141945499');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: 'RNW6470vuwIuzk2icz-DxWOfUZA');

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
?>

