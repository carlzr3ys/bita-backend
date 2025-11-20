<?php
/**
 * FTP/SFTP Storage Helper Functions for cPanel
 * Allows backend on Render to upload files to cPanel storage
 */

require_once '../config.php';

/**
 * Upload file to cPanel via FTP/SFTP
 * 
 * @param string $filePath - Temporary file path (from $_FILES['file']['tmp_name'])
 * @param string $folder - Folder path within uploads directory (e.g., 'matric_cards', 'modules/6')
 * @param string $fileName - Original file name
 * @return array - ['success' => bool, 'url' => string|null, 'path' => string|null, 'error' => string|null]
 */
function uploadToCPanelViaFTP($filePath, $folder, $fileName) {
    try {
        // Get FTP configuration from config.php
        $ftpHost = defined('FTP_HOST') ? FTP_HOST : '';
        $ftpUser = defined('FTP_USER') ? FTP_USER : '';
        $ftpPass = defined('FTP_PASS') ? FTP_PASS : '';
        $ftpPort = defined('FTP_PORT') ? FTP_PORT : 21;
        $ftpUseSSL = defined('FTP_USE_SSL') ? FTP_USE_SSL : false;
        $ftpBaseDir = defined('FTP_BASE_DIR') ? FTP_BASE_DIR : '/public_html/';
        $cPanelDomain = defined('CPANEL_DOMAIN') ? CPANEL_DOMAIN : '';
        
        if (empty($ftpHost) || empty($ftpUser) || empty($ftpPass)) {
            return [
                'success' => false,
                'error' => 'FTP configuration not set. Please set FTP_HOST, FTP_USER, FTP_PASS in config.php'
            ];
        }
        
        // Generate unique file name
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
        $uniqueFileName = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileBaseName) . '.' . $fileExt;
        
        // Remote file path on cPanel
        $remoteFolder = trim($ftpBaseDir, '/') . '/uploads/' . trim($folder, '/') . '/';
        $remoteFilePath = $remoteFolder . $uniqueFileName;
        
        // Connect to FTP server
        if ($ftpUseSSL) {
            $connection = @ftp_ssl_connect($ftpHost, $ftpPort, 30);
        } else {
            $connection = @ftp_connect($ftpHost, $ftpPort, 30);
        }
        
        if (!$connection) {
            return [
                'success' => false,
                'error' => 'Failed to connect to FTP server: ' . $ftpHost
            ];
        }
        
        // Login
        if (!@ftp_login($connection, $ftpUser, $ftpPass)) {
            @ftp_close($connection);
            return [
                'success' => false,
                'error' => 'FTP authentication failed. Check FTP_USER and FTP_PASS.'
            ];
        }
        
        // Enable passive mode (important for most servers)
        @ftp_pasv($connection, true);
        
        // Create folder structure if it doesn't exist
        $currentDir = '';
        $folders = explode('/', $remoteFolder);
        foreach ($folders as $dir) {
            if (empty($dir)) continue;
            $currentDir .= '/' . $dir;
            // Try to change to directory, if fails, create it
            if (!@ftp_chdir($connection, $currentDir)) {
                if (!@ftp_mkdir($connection, $currentDir)) {
                    @ftp_close($connection);
                    return [
                        'success' => false,
                        'error' => 'Failed to create directory: ' . $currentDir
                    ];
                }
                @ftp_chdir($connection, $currentDir);
            }
        }
        
        // Upload file
        $uploadResult = @ftp_put($connection, $remoteFilePath, $filePath, FTP_BINARY);
        
        if (!$uploadResult) {
            @ftp_close($connection);
            return [
                'success' => false,
                'error' => 'Failed to upload file to FTP server'
            ];
        }
        
        // Close connection
        @ftp_close($connection);
        
        // Generate URL
        $relativePath = trim($folder, '/') . '/' . $uniqueFileName;
        $uploadUrl = ($cPanelDomain ? 'https://' . $cPanelDomain : '') . '/uploads/' . $relativePath;
        
        return [
            'success' => true,
            'url' => $uploadUrl,
            'path' => $relativePath,
            'file_name' => $uniqueFileName,
            'remote_path' => $remoteFilePath
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'FTP upload error: ' . $e->getMessage()
        ];
    }
}

/**
 * Delete file from cPanel via FTP/SFTP
 * 
 * @param string $filePath - Relative path from uploads directory (e.g., 'matric_cards/123_image.jpg')
 * @return array - ['success' => bool, 'error' => string|null]
 */
function deleteFromCPanelViaFTP($filePath) {
    try {
        $ftpHost = defined('FTP_HOST') ? FTP_HOST : '';
        $ftpUser = defined('FTP_USER') ? FTP_USER : '';
        $ftpPass = defined('FTP_PASS') ? FTP_PASS : '';
        $ftpPort = defined('FTP_PORT') ? FTP_PORT : 21;
        $ftpUseSSL = defined('FTP_USE_SSL') ? FTP_USE_SSL : false;
        $ftpBaseDir = defined('FTP_BASE_DIR') ? FTP_BASE_DIR : '/public_html/';
        
        if (empty($ftpHost) || empty($ftpUser) || empty($ftpPass)) {
            return [
                'success' => false,
                'error' => 'FTP configuration not set'
            ];
        }
        
        $remoteFilePath = trim($ftpBaseDir, '/') . '/uploads/' . $filePath;
        
        // Connect to FTP
        if ($ftpUseSSL) {
            $connection = @ftp_ssl_connect($ftpHost, $ftpPort, 30);
        } else {
            $connection = @ftp_connect($ftpHost, $ftpPort, 30);
        }
        
        if (!$connection) {
            return ['success' => false, 'error' => 'Failed to connect to FTP server'];
        }
        
        if (!@ftp_login($connection, $ftpUser, $ftpPass)) {
            @ftp_close($connection);
            return ['success' => false, 'error' => 'FTP authentication failed'];
        }
        
        @ftp_pasv($connection, true);
        
        // Delete file
        $deleteResult = @ftp_delete($connection, $remoteFilePath);
        
        @ftp_close($connection);
        
        if (!$deleteResult) {
            // File might not exist, return success anyway (idempotent)
            return ['success' => true];
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'FTP delete error: ' . $e->getMessage()
        ];
    }
}

/**
 * Check if FTP extension is available
 */
function isFTPAvailable() {
    return function_exists('ftp_connect') || function_exists('ftp_ssl_connect');
}

