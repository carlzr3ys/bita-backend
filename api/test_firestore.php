<?php
/**
 * Test Firestore Connection
 * 
 * Usage:
 * 1. Enable Firebase dalam config.php atau set FIREBASE_ENABLED=true
 * 2. Open in browser: http://localhost/bita/api/test_firestore.php
 */

require_once '../config.php';

// Note: We directly use getFirestoreClient() instead of getDBConnection()
// to ensure we get FirestoreClient, not MySQLi (regardless of FIREBASE_ENABLED setting)

header('Content-Type: application/json');

// Check prerequisites
$serviceAccountExists = file_exists(__DIR__ . '/../config/serviceAccountKey.json');
$composerExists = file_exists(__DIR__ . '/../vendor/autoload.php');

if (!$serviceAccountExists) {
    sendJSONResponse([
        'success' => false,
        'message' => 'Service Account Key not found',
        'error' => 'Please ensure serviceAccountKey.json exists in config/ folder',
        'check' => [
            'service_account_key_exists' => false,
            'composer_installed' => $composerExists,
            'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined',
            'path_checked' => __DIR__ . '/../config/serviceAccountKey.json'
        ]
    ], 500);
}

if (!$composerExists) {
    sendJSONResponse([
        'success' => false,
        'message' => 'Composer dependencies not installed',
        'error' => 'Please run: composer install',
        'check' => [
            'service_account_key_exists' => $serviceAccountExists,
            'composer_installed' => false,
            'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined'
        ]
    ], 500);
}

try {
    // Directly use getFirestoreClient() instead of getDBConnection()
    // to ensure we get FirestoreClient, not MySQLi
    // Check if function already exists (already loaded by config.php)
    if (!function_exists('getFirestoreClient')) {
        require_once __DIR__ . '/../config/firebase_config.php';
    }
    
    // Capture any errors
    $lastError = error_get_last();
    try {
        $db = getFirestoreClient();
    } catch (\Google\Cloud\Core\Exception\GoogleException $e) {
        // Catch GoogleException specifically (e.g., missing gRPC extension)
        sendJSONResponse([
            'success' => false,
            'message' => 'Firestore requires gRPC extension',
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
            'solution' => 'FirestoreClient v1.54+ requires gRPC extension. Please install it or use an older version.',
            'check' => [
                'service_account_key_exists' => $serviceAccountExists,
                'composer_installed' => $composerExists,
                'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined',
                'project_id' => (function() use ($serviceAccountExists) {
                    if ($serviceAccountExists) {
                        $key = json_decode(file_get_contents(__DIR__ . '/../config/serviceAccountKey.json'), true);
                        return $key['project_id'] ?? 'not found';
                    }
                    return 'service account key not found';
                })(),
                'grpc_loaded' => extension_loaded('grpc'),
                'php_version' => PHP_VERSION,
                'help_url' => 'https://cloud.google.com/php/grpc'
            ]
        ], 500);
    } catch (\Exception $e) {
        sendJSONResponse([
            'success' => false,
            'message' => 'Firestore connection failed',
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
            'trace' => $e->getTraceAsString(),
            'check' => [
                'service_account_key_exists' => $serviceAccountExists,
                'composer_installed' => $composerExists,
                'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined',
                'project_id' => (function() use ($serviceAccountExists) {
                    if ($serviceAccountExists) {
                        $key = json_decode(file_get_contents(__DIR__ . '/../config/serviceAccountKey.json'), true);
                        return $key['project_id'] ?? 'not found';
                    }
                    return 'service account key not found';
                })(),
                'grpc_loaded' => extension_loaded('grpc'),
                'php_version' => PHP_VERSION
            ]
        ], 500);
    }
    
    $errorAfterCall = error_get_last();
    
    if (!$db) {
        // Try to get more detailed error information
        $errorDetails = [];
        
        // Check PHP error logs location
        $errorLog = ini_get('error_log');
        if ($errorLog && file_exists($errorLog)) {
            $errorDetails['php_error_log'] = $errorLog;
            $errorDetails['last_10_lines'] = implode("\n", array_slice(file($errorLog), -10));
        }
        
        // Check if error changed after getFirestoreClient call
        if ($errorAfterCall && $errorAfterCall !== $lastError) {
            $errorDetails['php_error'] = $errorAfterCall;
        }
        
        sendJSONResponse([
            'success' => false,
            'message' => 'Failed to connect to Firestore',
            'error' => 'getFirestoreClient() returned null. Check service account key and Firebase project configuration.',
            'error_details' => $errorDetails,
            'check' => [
                'service_account_key_exists' => $serviceAccountExists,
                'service_account_key_path' => __DIR__ . '/../config/serviceAccountKey.json',
                'composer_installed' => $composerExists,
                'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined',
                'project_id' => (function() use ($serviceAccountExists) {
                    if ($serviceAccountExists) {
                        $key = json_decode(file_get_contents(__DIR__ . '/../config/serviceAccountKey.json'), true);
                        return $key['project_id'] ?? 'not found';
                    }
                    return 'service account key not found';
                })(),
                'db_type' => 'null (Firestore connection failed)',
                'grpc_disabled' => getenv('GOOGLE_CLOUD_DISABLE_GRPC') ?: 'not set',
                'php_version' => PHP_VERSION
            ]
        ], 500);
    }
    
    // Verify it's a FirestoreClient instance
    if (!($db instanceof \Google\Cloud\Firestore\FirestoreClient)) {
        sendJSONResponse([
            'success' => false,
            'message' => 'Wrong database type returned',
            'error' => 'Expected FirestoreClient but got: ' . get_class($db),
            'check' => [
                'service_account_key_exists' => $serviceAccountExists,
                'composer_installed' => $composerExists,
                'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined',
                'db_type' => get_class($db)
            ]
        ], 500);
    }
    
    // Test: Get collections list
    $collections = $db->collections();
    $collectionNames = [];
    
    foreach ($collections as $collection) {
        $collectionNames[] = $collection->id();
    }
    
    sendJSONResponse([
        'success' => true,
        'message' => 'Firestore connection successful!',
        'collections' => $collectionNames,
        'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined',
        'db_type' => get_class($db)
    ]);
    
} catch (Exception $e) {
    sendJSONResponse([
        'success' => false,
        'message' => 'Firestore error: ' . $e->getMessage(),
        'error_type' => get_class($e),
        'check' => [
            'service_account_key_exists' => $serviceAccountExists,
            'composer_installed' => $composerExists,
            'firebase_enabled' => FIREBASE_ENABLED ?? 'not defined'
        ],
        'trace' => $e->getTraceAsString()
    ], 500);
}
?>

