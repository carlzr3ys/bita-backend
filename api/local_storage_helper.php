<?php
/**
 * Local File Storage Helper Functions for cPanel
 * Replaces Cloudinary functionality
 * 
 * This file supports TWO modes:
 * 1. Direct local storage (when backend is on cPanel)
 * 2. FTP upload to cPanel (when backend is on Render/external server)
 */

require_once '../config.php';

/**
 * Upload file to local storage (cPanel)
 * 
 * @param string $filePath - Temporary file path (from $_FILES['file']['tmp_name'])
 * @param string $folder - Folder path within uploads directory (e.g., 'matric_cards', 'modules/1')
 * @param string $fileName - Original file name
 * @return array - ['success' => bool, 'url' => string|null, 'path' => string|null, 'error' => string|null]
 */
function uploadToLocalStorage($filePath, $folder, $fileName) {
    // Check if FTP mode is enabled (backend on Render, storage on cPanel)
    $useFTP = defined('USE_FTP_STORAGE') ? USE_FTP_STORAGE : false;
    
    if ($useFTP) {
        // Use FTP to upload to cPanel
        require_once __DIR__ . '/ftp_storage_helper.php';
        return uploadToCPanelViaFTP($filePath, $folder, $fileName);
    }
    
    // Direct local storage (backend and storage on same server - cPanel)
    try {
        // Get upload directory from config
        $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../uploads/';
        
        // Ensure upload directory exists
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create upload directory: ' . $uploadDir
                ];
            }
        }
        
        // Create folder path if it doesn't exist
        $folderPath = $uploadDir . trim($folder, '/') . '/';
        if (!file_exists($folderPath)) {
            if (!mkdir($folderPath, 0755, true)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create folder: ' . $folderPath
                ];
            }
        }
        
        // Generate unique file name
        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $fileBaseName = pathinfo($fileName, PATHINFO_FILENAME);
        $uniqueFileName = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileBaseName) . '.' . $fileExt;
        
        // Full file path
        $fullPath = $folderPath . $uniqueFileName;
        
        // Move uploaded file
        if (!move_uploaded_file($filePath, $fullPath)) {
            return [
                'success' => false,
                'error' => 'Failed to move uploaded file'
            ];
        }
        
        // Return relative path (for database storage)
        $relativePath = trim($folder, '/') . '/' . $uniqueFileName;
        
        // Return absolute URL (for accessing files)
        // For cPanel, you'll need to adjust this to your domain
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $uploadUrl = $baseUrl . '/uploads/' . $relativePath;
        
        return [
            'success' => true,
            'url' => $uploadUrl,
            'path' => $relativePath,
            'file_name' => $uniqueFileName,
            'full_path' => $fullPath
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Upload error: ' . $e->getMessage()
        ];
    }
}

/**
 * Delete file from local storage
 * 
 * @param string $filePath - Relative path from uploads directory (e.g., 'matric_cards/123_image.jpg')
 * @return array - ['success' => bool, 'error' => string|null]
 */
function deleteFromLocalStorage($filePath) {
    // Check if FTP mode is enabled
    $useFTP = defined('USE_FTP_STORAGE') ? USE_FTP_STORAGE : false;
    
    if ($useFTP) {
        // Use FTP to delete from cPanel
        require_once __DIR__ . '/ftp_storage_helper.php';
        return deleteFromCPanelViaFTP($filePath);
    }
    
    // Direct local storage deletion
    try {
        $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../uploads/';
        $fullPath = $uploadDir . $filePath;
        
        // Security: Ensure file is within upload directory
        $realUploadDir = realpath($uploadDir);
        $realFilePath = realpath($fullPath);
        
        if (!$realFilePath || strpos($realFilePath, $realUploadDir) !== 0) {
            return [
                'success' => false,
                'error' => 'Invalid file path (outside upload directory)'
            ];
        }
        
        if (file_exists($fullPath)) {
            if (unlink($fullPath)) {
                return ['success' => true];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to delete file'
                ];
            }
        } else {
            // File doesn't exist, but return success (idempotent)
            return ['success' => true];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Delete error: ' . $e->getMessage()
        ];
    }
}

/**
 * Get file URL from relative path
 * 
 * @param string $filePath - Relative path from uploads directory
 * @return string - Full URL to access the file
 */
function getFileUrl($filePath) {
    if (empty($filePath)) {
        return null;
    }
    
    // If already a full URL, return as is
    if (filter_var($filePath, FILTER_VALIDATE_URL)) {
        return $filePath;
    }
    
    // Construct URL from relative path
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    // Remove 'uploads/' prefix if present (some paths might already include it)
    $cleanPath = ltrim(str_replace('uploads/', '', $filePath), '/');
    
    return $baseUrl . '/uploads/' . $cleanPath;
}

/**
 * Check if file exists in local storage
 * 
 * @param string $filePath - Relative path from uploads directory
 * @return bool
 */
function fileExistsInLocalStorage($filePath) {
    $uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/../uploads/';
    $fullPath = $uploadDir . $filePath;
    
    return file_exists($fullPath);
}

