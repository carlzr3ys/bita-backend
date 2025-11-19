<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    sendJSONResponse(['success' => false, 'message' => 'Missing required fields'], 400);
}

// Validate file upload
if (!isset($_FILES['matricCard']) || $_FILES['matricCard']['error'] !== UPLOAD_ERR_OK) {
    sendJSONResponse(['success' => false, 'message' => 'Please upload a photo of your matric card'], 400);
}

$file = $_FILES['matricCard'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
if (!in_array($file['type'], $allowedTypes)) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid file type. Please upload PNG, JPG, or JPEG image'], 400);
}

// Validate file size (5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    sendJSONResponse(['success' => false, 'message' => 'File size must be less than 5MB'], 400);
}

// Set is_verified to false (pending admin approval)
$is_verified = false;

// Validate email format
if (!isValidEmail($email)) {
    sendJSONResponse(['success' => false, 'message' => 'Invalid email format. Must be @student.utem.edu.my'], 400);
}

// Validate password
if (strlen($password) < 8) {
    sendJSONResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
}

// Validate program is BITA
if (stripos($program, 'BITA') === false && stripos($program, 'BIT') === false) {
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
    sendJSONResponse(['success' => false, 'message' => 'Invalid matric number format. Must start with B03 followed by digits (e.g., B032510017)'], 400);
}

// Extract batch from matric number
$batch = extractBatchFromMatric($matricUpper);
if (!$batch) {
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
    sendJSONResponse(['success' => false, 'message' => 'Matric number already registered'], 409);
}
$stmt->close();

// Hash password
$hashedPassword = hashPassword($password);

// Create uploads directory if it doesn't exist
$uploadDir = '../uploads/matric_cards/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = $matricUpper . '_' . time() . '.' . $fileExtension;
$filePath = $uploadDir . $fileName;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    sendJSONResponse(['success' => false, 'message' => 'Failed to upload file. Please try again.'], 500);
}

// Store relative path for database (from project root)
$matricCardPath = 'uploads/matric_cards/' . $fileName;

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
    // Delete uploaded file if database insert fails
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Registration failed: ' . $error], 500);
}
?>

