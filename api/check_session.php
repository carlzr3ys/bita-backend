<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Session already started in config.php

if (isset($_SESSION['user_id'])) {
    sendJSONResponse([
        'success' => true,
        'authenticated' => true,
        'user_id' => $_SESSION['user_id'],
        'user_name' => $_SESSION['user_name'] ?? '',
        'user_email' => $_SESSION['user_email'] ?? ''
    ]);
} else {
    sendJSONResponse([
        'success' => true,
        'authenticated' => false
    ]);
}
?>

