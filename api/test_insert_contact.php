<?php
// Set correct path
$configPath = dirname(__DIR__) . '/config.php';
if (!file_exists($configPath)) {
    die(json_encode(['success' => false, 'message' => 'Config file not found at: ' . $configPath]));
}
require_once $configPath;

header('Content-Type: application/json');

$conn = getDBConnection();

if (!$conn) {
    sendJSONResponse(['success' => false, 'message' => 'Database connection failed'], 500);
    exit;
}

// Check if table exists
$tablesCheck = $conn->query("SHOW TABLES LIKE 'admin_contact_requests'");
if ($tablesCheck->num_rows === 0) {
    $conn->close();
    sendJSONResponse(['success' => false, 'message' => 'Table admin_contact_requests does not exist'], 500);
    exit;
}

// Show table structure
$desc = $conn->query("DESCRIBE admin_contact_requests");
$structure = [];
while ($row = $desc->fetch_assoc()) {
    $structure[] = $row;
}

// Test data
$testData = [
    'name' => 'TEST USER',
    'matric' => 'B032410999',
    'phone' => '0123456789',
    'message' => 'This is a test message to check if data can be inserted into the database.'
];

// Test 1: Insert with phone
echo "=== Test 1: Insert with phone ===\n";
$stmt1 = $conn->prepare("INSERT INTO admin_contact_requests (name, matric, phone, message, status) VALUES (?, ?, ?, ?, 'Pending')");
if (!$stmt1) {
    echo "Error preparing statement: " . $conn->error . "\n";
} else {
    $stmt1->bind_param("ssss", $testData['name'], $testData['matric'], $testData['phone'], $testData['message']);
    if ($stmt1->execute()) {
        $insertId1 = $conn->insert_id;
        $affectedRows1 = $stmt1->affected_rows;
        echo "✓ Success! Insert ID: $insertId1, Affected Rows: $affectedRows1\n";
        
        // Verify
        $verify = $conn->prepare("SELECT * FROM admin_contact_requests WHERE id = ?");
        $verify->bind_param("i", $insertId1);
        $verify->execute();
        $result = $verify->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "✓ Verified: " . json_encode($row, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "✗ Verification failed: Record not found\n";
        }
        $verify->close();
    } else {
        echo "✗ Error executing: " . $stmt1->error . "\n";
    }
    $stmt1->close();
}

echo "\n=== Test 2: Insert without phone (NULL) ===\n";
$testData2 = [
    'name' => 'TEST USER 2',
    'matric' => 'B032410998',
    'phone' => '',
    'message' => 'This is a test message without phone number.'
];

// Method 1: Using NULL directly in SQL
$stmt2 = $conn->prepare("INSERT INTO admin_contact_requests (name, matric, phone, message, status) VALUES (?, ?, NULL, ?, 'Pending')");
if (!$stmt2) {
    echo "Error preparing statement: " . $conn->error . "\n";
} else {
    $stmt2->bind_param("sss", $testData2['name'], $testData2['matric'], $testData2['message']);
    if ($stmt2->execute()) {
        $insertId2 = $conn->insert_id;
        $affectedRows2 = $stmt2->affected_rows;
        echo "✓ Success! Insert ID: $insertId2, Affected Rows: $affectedRows2\n";
        
        // Verify
        $verify = $conn->prepare("SELECT * FROM admin_contact_requests WHERE id = ?");
        $verify->bind_param("i", $insertId2);
        $verify->execute();
        $result = $verify->get_result();
        if ($row = $result->fetch_assoc()) {
            echo "✓ Verified: " . json_encode($row, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "✗ Verification failed: Record not found\n";
        }
        $verify->close();
    } else {
        echo "✗ Error executing: " . $stmt2->error . "\n";
    }
    $stmt2->close();
}

// Count total records
$countResult = $conn->query("SELECT COUNT(*) as total FROM admin_contact_requests");
$count = $countResult->fetch_assoc();
echo "\n=== Total records in table: " . $count['total'] . " ===\n";

// Show all records
echo "\n=== All records in table ===\n";
$allResult = $conn->query("SELECT * FROM admin_contact_requests ORDER BY id DESC LIMIT 10");
if ($allResult && $allResult->num_rows > 0) {
    while ($row = $allResult->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . " | Matric: " . $row['matric'] . " | Phone: " . ($row['phone'] ?? 'NULL') . " | Status: " . $row['status'] . " | Created: " . $row['created_at'] . "\n";
    }
} else {
    echo "No records found\n";
}

$conn->close();

// JSON Response
sendJSONResponse([
    'success' => true,
    'message' => 'Test data inserted successfully',
    'table_structure' => $structure,
    'total_records' => $count['total'],
    'test_results' => [
        'test1_insert_id' => isset($insertId1) ? $insertId1 : null,
        'test1_affected_rows' => isset($affectedRows1) ? $affectedRows1 : null,
        'test2_insert_id' => isset($insertId2) ? $insertId2 : null,
        'test2_affected_rows' => isset($affectedRows2) ? $affectedRows2 : null
    ]
], 200);
?>

