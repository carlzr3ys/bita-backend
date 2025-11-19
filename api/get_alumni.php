<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = getDBConnection();

$sql = "SELECT id, name, matric, batch, current_company, bio, description, 
               instagram, facebook, twitter, linkedin, tiktok, created_at 
        FROM alumni 
        ORDER BY created_at DESC";

$result = $conn->query($sql);

$alumni = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $alumni[] = $row;
    }
}

$conn->close();

sendJSONResponse([
    'success' => true,
    'alumni' => $alumni
]);
?>

