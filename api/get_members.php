<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = getDBConnection();

// Get verified members from users table (exclude password and sensitive info)
$sql = "SELECT id, name, matric, email, program, year, batch, phone, email_alt, bio, description,
               instagram, facebook, twitter, linkedin, tiktok, created_at 
        FROM users 
        WHERE is_verified = 1
        ORDER BY year DESC, name ASC";

$result = $conn->query($sql);

$members = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $members[] = $row;
    }
}

$conn->close();

sendJSONResponse([
    'success' => true,
    'members' => $members
]);
?>

