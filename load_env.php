<?php
/**
 * Simple .env file loader (without external dependencies)
 * Alternative to phpdotenv for simplicity
 */

if (!function_exists('loadEnvFile')) {
    function loadEnvFile($path = null) {
        if ($path === null) {
            $path = __DIR__ . '/.env';
        }
        
        // Don't load if already loaded or file doesn't exist
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                // Set environment variable if not already set
                if (!empty($key) && getenv($key) === false) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

// Auto-load .env file if exists (only for local development)
// In production (Render), environment variables are set directly, so .env file is not needed
if (file_exists(__DIR__ . '/.env')) {
    loadEnvFile(__DIR__ . '/.env');
}

