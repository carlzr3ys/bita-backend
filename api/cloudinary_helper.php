<?php
/**
 * Cloudinary Helper Functions
 * 
 * Provides helper functions for uploading files to Cloudinary
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

// Configure Cloudinary
Configuration::instance([
    'cloud' => [
        'cloud_name' => CLOUDINARY_CLOUD_NAME,
        'api_key' => CLOUDINARY_API_KEY,
        'api_secret' => CLOUDINARY_API_SECRET,
    ],
    'url' => [
        'secure' => true
    ]
]);

/**
 * Upload file to Cloudinary
 * 
 * @param string $filePath - Local file path (temporary upload location)
 * @param string $folder - Cloudinary folder path (e.g., 'matric_cards', 'modules/week_1')
 * @param array $options - Additional Cloudinary options
 * @return array - ['success' => bool, 'url' => string, 'public_id' => string, 'error' => string]
 */
function uploadToCloudinary($filePath, $folder = '', $options = []) {
    try {
        $cloudinary = new Cloudinary();
        
        // Default options
        $uploadOptions = [
            'resource_type' => 'auto', // Auto-detect image, video, raw, etc.
            'folder' => $folder,
        ];
        
        // Merge custom options
        $uploadOptions = array_merge($uploadOptions, $options);
        
        // Upload file
        $result = $cloudinary->uploadApi()->upload($filePath, $uploadOptions);
        
        return [
            'success' => true,
            'url' => $result['secure_url'], // HTTPS URL
            'public_id' => $result['public_id'],
            'format' => $result['format'] ?? null,
            'width' => $result['width'] ?? null,
            'height' => $result['height'] ?? null,
            'bytes' => $result['bytes'] ?? null
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'url' => null
        ];
    }
}

/**
 * Upload image file to Cloudinary (with image-specific options)
 * 
 * @param string $filePath - Local file path
 * @param string $folder - Cloudinary folder
 * @param array $options - Additional options (transformation, etc.)
 * @return array
 */
function uploadImageToCloudinary($filePath, $folder = '', $options = []) {
    $imageOptions = [
        'resource_type' => 'image',
        'folder' => $folder,
    ];
    
    $imageOptions = array_merge($imageOptions, $options);
    
    return uploadToCloudinary($filePath, $folder, $imageOptions);
}

/**
 * Delete file from Cloudinary
 * 
 * @param string $publicId - Cloudinary public ID
 * @param string $resourceType - Resource type ('image', 'video', 'raw', 'auto')
 * @return array - ['success' => bool, 'result' => string, 'error' => string]
 */
function deleteFromCloudinary($publicId, $resourceType = 'auto') {
    try {
        $cloudinary = new Cloudinary();
        $result = $cloudinary->uploadApi()->destroy($publicId, [
            'resource_type' => $resourceType
        ]);
        
        return [
            'success' => $result['result'] === 'ok',
            'result' => $result['result'] ?? 'unknown',
            'error' => null
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'result' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get Cloudinary URL from public_id
 * 
 * @param string $publicId - Cloudinary public ID
 * @param array $transformations - Image transformations (optional)
 * @return string - Cloudinary URL
 */
function getCloudinaryUrl($publicId, $transformations = []) {
    $cloudinary = new Cloudinary();
    $url = $cloudinary->image($publicId);
    
    if (!empty($transformations)) {
        $url->transformation($transformations);
    }
    
    return $url->secure()->toUrl();
}

?>
