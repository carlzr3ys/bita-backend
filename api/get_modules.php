<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = getDBConnection();

$sql = "SELECT id, title, slug, year, semester, subject, category, file_url, file_type, 
               views, downloads, created_at, updated_at 
        FROM modules 
        ORDER BY year ASC, semester ASC, category ASC, title ASC";

$result = $conn->query($sql);

$modules = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $modules[] = $row;
    }
}

$conn->close();

sendJSONResponse([
    'success' => true,
    'modules' => $modules
]);
?>

