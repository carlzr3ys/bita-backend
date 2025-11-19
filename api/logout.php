<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Session already started in config.php
session_destroy();

sendJSONResponse(['success' => true, 'message' => 'Logged out successfully']);
?>

