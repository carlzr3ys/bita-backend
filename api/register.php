<?php
// Start output buffering to prevent headers already sent error
ob_start();

require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle CORS preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    ob_end_clean();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid request method'], 405);
}

// Handle multipart/form-data (file upload)
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$matric = isset($_POST['matric']) ? trim($_POST['matric']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$program = isset($_POST['program']) ? trim($_POST['program']) : '';

// Validate required fields
if (empty($name) || empty($matric) || empty($email) || empty($password) || empty($program)) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Missing required fields'], 400);
}

// Validate file upload
if (!isset($_FILES['matricCard']) || $_FILES['matricCard']['error'] !== UPLOAD_ERR_OK) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Please upload a photo of your matric card'], 400);
}

$file = $_FILES['matricCard'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
if (!in_array($file['type'], $allowedTypes)) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid file type. Please upload PNG, JPG, or JPEG image'], 400);
}

// Validate file size (5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'File size must be less than 5MB'], 400);
}

// Set is_verified to false (pending admin approval)
$is_verified = false;

// Validate email format
if (!isValidEmail($email)) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid email format. Must be @student.utem.edu.my'], 400);
}

// Validate password
if (strlen($password) < 8) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
}

// Validate program is BITA
if (stripos($program, 'BITA') === false && stripos($program, 'BIT') === false) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Only BITA students can register'], 403);
}

// Function to extract batch from matric number
// Format: B032510017 -> Batch 2025 (positions 3-4 after "B03")
function extractBatchFromMatric($matric) {
    $matric = strtoupper(trim($matric));
    // Pattern: B03YYXXXXXX where YY is the batch year (last 2 digits)
    // Example: B032510017 -> 25 -> 2025
    if (preg_match('/^B03(\d{2})\d+$/', $matric, $matches)) {
        $yearDigits = $matches[1];
        $batch = '20' . $yearDigits; // Convert 25 to 2025
        return $batch;
    }
    return null;
}

// Validate matric format and extract batch
$matricUpper = strtoupper($matric);
if (!preg_match('/^B03\d{6,}$/', $matricUpper)) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Invalid matric number format. Must start with B03 followed by digits (e.g., B032510017)'], 400);
}

// Extract batch from matric number
$batch = extractBatchFromMatric($matricUpper);
if (!$batch) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Could not extract batch from matric number. Please check your matric number format.'], 400);
}

// User needs admin approval before they can login
// Batch is auto-extracted, but user must wait for admin verification
$is_verified = false;

$conn = getDBConnection();

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $stmt->close();
    $conn->close();
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Email already registered'], 409);
}
$stmt->close();

// Check if matric already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE matric = ?");
$stmt->bind_param("s", $matric);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $stmt->close();
    $conn->close();
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Matric number already registered'], 409);
}
$stmt->close();

// Hash password
$hashedPassword = hashPassword($password);

// Include Cloudinary helper
require_once __DIR__ . '/cloudinary_helper.php';

// Upload matric card to Cloudinary
$uploadResult = uploadImageToCloudinary($file['tmp_name'], 'matric_cards', [
    'public_id' => $matricUpper . '_' . time(), // Custom public ID
    'folder' => 'matric_cards',
]);

if (!$uploadResult['success']) {
    ob_end_clean();
    sendJSONResponse(['success' => false, 'message' => 'Failed to upload file to Cloudinary: ' . ($uploadResult['error'] ?? 'Unknown error')], 500);
}

// Store Cloudinary URL in database
$matricCardPath = $uploadResult['url'];

// Insert new user with matric_card path, batch, and is_verified = true (auto-verified)
$stmt = $conn->prepare("INSERT INTO users (name, matric, email, password, program, matric_card, batch, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssssi", $name, $matricUpper, $email, $hashedPassword, $program, $matricCardPath, $batch, $is_verified);

if ($stmt->execute()) {
    $userId = $conn->insert_id;
    $stmt->close();
    
    // Note: File is saved at $filePath for admin review
    // Admin can access files from uploads/matric_cards/ folder
    
    $conn->close();
    
    // User needs admin approval before they can login
    // Batch is auto-extracted from matric, but admin must verify the account
    // Session will be set after admin approves and user logs in
    
    // Clean output buffer before sending response
    ob_end_clean();
    
    sendJSONResponse([
        'success' => true, 
        'message' => 'Registration submitted successfully. Your account is pending admin approval before you can login.',
        'user' => [
            'id' => $userId,
            'name' => $name,
            'matric' => $matricUpper,
            'email' => $email,
            'program' => $program,
            'batch' => $batch
        ]
    ], 201);
} else {
    // Delete from Cloudinary if database insert fails
    if (isset($uploadResult['public_id'])) {
        require_once __DIR__ . '/cloudinary_helper.php';
        deleteFromCloudinary($uploadResult['public_id'], 'image');
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    
    // Clean output buffer before sending response
    ob_end_clean();
    
    sendJSONResponse(['success' => false, 'message' => 'Registration failed: ' . $error], 500);
}

