<?php
/**
 * CORS Configuration for Production
 * 
 * Add this file to handle Cross-Origin Resource Sharing (CORS)
 * Include this at the top of your API files or in config.php
 * 
 * Usage: require_once 'cors.php';
 */

// Get allowed origin from environment variable or use default
$allowedOrigin = getenv('ALLOWED_ORIGIN') ?: '*';

// Allow from specific origin (recommended for production)
// Replace with your Netlify app URL
$netlifyUrl = getenv('NETLIFY_URL') ?: 'https://your-app.netlify.app';

// Set CORS headers
header('Access-Control-Allow-Origin: ' . $netlifyUrl);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

