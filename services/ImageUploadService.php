<?php
// services/ImageUploadService.php - AWS S3 Image Upload Service for Aetia

require_once '/home/aetiacom/vendors/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class ImageUploadService {
    private $s3Client;
    private $bucketName;
    private $region;
    private $endpoint;
    
    public function __construct() {
        // Object Storage Configuration
        $this->bucketName = $this->getBucketName();
        $this->region = $this->getRegion();
        $this->endpoint = $this->getEndpoint();
        
        try {
            // Debug: Log configuration being used
            error_log("S3 Config - Region: " . $this->region . ", Endpoint: " . $this->endpoint . ", Bucket: " . $this->bucketName);
            
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'endpoint' => $this->endpoint,
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $this->getAccessKey(),
                    'secret' => $this->getSecretKey(),
                ]
            ]);
        } catch (Exception $e) {
            error_log("Object Storage Client initialization failed: " . $e->getMessage());
            throw new Exception("Image upload service initialization failed: " . $e->getMessage());
        }
    }
    
    /** Get access key from configuration file */
    private function getAccessKey() {
        $configFile = '/home/aetiacom/web-config/aws.php';
        if (file_exists($configFile)) {
            include $configFile;
            if (isset($aws_access_key)) {
                return $aws_access_key;
            }
        }
        throw new Exception("Access key not found in configuration file.");
    }
    
    /** Get secret key from configuration file */
    private function getSecretKey() {
        $configFile = '/home/aetiacom/web-config/aws.php';
        if (file_exists($configFile)) {
            include $configFile;
            if (isset($aws_secret_key)) {
                return $aws_secret_key;
            }
        }
        throw new Exception("Secret key not found in configuration file.");
    }
    
    /**  Get bucket name from configuration file */
    private function getBucketName() {
        $configFile = '/home/aetiacom/web-config/aws.php';
        if (file_exists($configFile)) {
            include $configFile;
            if (isset($aws_bucket_name)) {
                return $aws_bucket_name;
            }
        }
        return 'aetia'; // Default bucket name
    }
    
    /** Get region from configuration file */
    private function getRegion() {
        $configFile = '/home/aetiacom/web-config/aws.php';
        if (file_exists($configFile)) {
            include $configFile;
            if (isset($aws_region)) {
                return $aws_region;
            }
        }
        return 'au-mel-1'; // Default region
    }
    
    /** Get endpoint from configuration file */
    private function getEndpoint() {
        $configFile = '/home/aetiacom/web-config/aws.php';
        if (file_exists($configFile)) {
            include $configFile;
            if (isset($aws_endpoint)) {
                return $aws_endpoint;
            }
        }
        return 'https://aetia.au-mel-1.linodeobjects.com'; // Default endpoint
    }
    
    /**
     * Ensure user's profile image folder exists in the bucket
     * @param int $userId The user ID
     * @return bool Success status
     */
    private function ensureUserFolderExists($userId) {
        try {
            $folderKey = "profile-images/user-{$userId}/";
            
            // Check if the folder marker already exists
            try {
                $this->s3Client->headObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $folderKey,
                ]);
                // Folder marker exists, we're good
                return true;
            } catch (Exception $e) {
                // Folder marker doesn't exist, create it
            }
            
            // Create a folder marker (empty object with trailing slash)
            $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $folderKey,
                'Body' => '',
                'ContentType' => 'application/x-directory',
                'Metadata' => [
                    'user-id' => (string)$userId,
                    'created-date' => date('Y-m-d H:i:s'),
                    'folder-type' => 'profile-images',
                ]
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Folder creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize basic bucket structure
     * This creates the main folders for organization
     * 
     * @return bool Success status
     */
    public function initializeBucketStructure() {
        try {
            $folders = [
                'profile-images/',
                'documents/',
                'temp/',
                'backups/',
            ];
            
            foreach ($folders as $folder) {
                $this->s3Client->putObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $folder,
                    'Body' => '',
                    'ContentType' => 'application/x-directory',
                    'Metadata' => [
                        'created-date' => date('Y-m-d H:i:s'),
                        'folder-type' => 'system',
                        'purpose' => 'Organization structure',
                    ]
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Bucket structure initialization error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
        * Upload profile image to S3
        * @param array $file The uploaded file from $_FILES
        * @param int $userId The user ID for unique naming
        * @return array Success/failure result with image URL or error message
    */
    public function uploadProfileImage($file, $userId) {
        try {
            error_log("Starting upload for user {$userId}");
            // Validate file input
            $validation = $this->validateImageFile($file);
            if (!$validation['valid']) {
                error_log("File validation failed: " . $validation['message']);
                return ['success' => false, 'message' => $validation['message']];
            }
            error_log("File validation passed");
            // Generate unique filename with proper folder structure
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = "profile-images/user-{$userId}/profile." . $fileExtension;
            error_log("Generated filename: " . $fileName);
            
            // Ensure the user's folder exists before uploading
            if (!$this->ensureUserFolderExists($userId)) {
                error_log("Failed to create user folder");
                return ['success' => false, 'message' => 'Failed to create user folder in storage.'];
            }
            error_log("User folder exists");
            
            // Delete any existing profile images for this user before uploading new one
            $this->deleteAllUserProfileImages($userId);
            
            // Resize image if needed
            error_log("About to resize image");
            $resizedImagePath = $this->resizeImage($file['tmp_name'], $fileExtension);
            error_log("Resized image path: " . ($resizedImagePath ?: 'null'));
            // Upload to S3 with authenticated read access
            error_log("About to upload to S3 with key: " . $fileName);
            try {
                $result = $this->s3Client->putObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $fileName,
                    'SourceFile' => $resizedImagePath ?: $file['tmp_name'],
                    'ContentType' => $file['type'],
                    'ACL' => 'authenticated-read', // Only authenticated users can access
                    'Metadata' => [
                        'user-id' => (string)$userId,
                        'upload-date' => date('Y-m-d H:i:s'),
                    ]
                ]);
                error_log("S3 upload successful. Result: " . json_encode($result));
            } catch (Exception $e) {
                error_log("S3 upload failed: " . $e->getMessage());
                throw $e;
            }
            // Clean up temporary resized file if created
            if ($resizedImagePath && file_exists($resizedImagePath)) {
                unlink($resizedImagePath);
            }
            // Get the public URL
            $imageUrl = $result['ObjectURL'];
            error_log("Image URL: " . $imageUrl);
            // Update user's profile image in database
            error_log("About to update database with image URL");
            $user = new User();
            $updateResult = $user->updateProfileImage($userId, $fileName);
            if (!$updateResult) {
                error_log("Failed to update database with image URL");
                return ['success' => false, 'message' => 'Image uploaded but failed to update database.'];
            }
            error_log("Database updated successfully");
            return [
                'success' => true, 
                'message' => 'Profile image uploaded successfully!',
                'image_url' => $imageUrl
            ];
            
        } catch (AwsException $e) {
            error_log("Object Storage upload error: " . $e->getMessage());
            // Temporarily show detailed error for debugging
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Image upload error: " . $e->getMessage());
            // Temporarily show detailed error for debugging
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
        * Validate uploaded image file
        * @param array $file The uploaded file from $_FILES
        * @return array Validation result
    */
    private function validateImageFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the maximum file size.',
                UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form maximum file size.',
                UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            ];
            
            $message = $errorMessages[$file['error']] ?? 'Unknown upload error.';
            return ['valid' => false, 'message' => $message];
        }
        // Check file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'message' => 'Image file must be smaller than 5MB.'];
        }
        // Check if file is actually an image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'message' => 'Please upload a valid image file.'];
        }
        // Check allowed image types
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['valid' => false, 'message' => 'Only JPEG, PNG, GIF, and WebP images are allowed.'];
        }
        // Check image dimensions (max 2000x2000)
        if ($imageInfo[0] > 2000 || $imageInfo[1] > 2000) {
            return ['valid' => false, 'message' => 'Image dimensions must not exceed 2000x2000 pixels.'];
        }
        return ['valid' => true, 'message' => 'Valid image file.'];
    }
    
    /**
        * Resize image to optimal profile image size (400x400)
        * @param string $sourcePath Path to the source image
        * @param string $extension File extension
        * @return string|false Path to resized image or false if resize failed
    */
    private function resizeImage($sourcePath, $extension) {
        try {
            $targetSize = 400; // 400x400 pixels for profile images
            
            // Create image resource from source
            switch (strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                case 'webp':
                    $sourceImage = imagecreatefromwebp($sourcePath);
                    break;
                default:
                    return false;
            }
            if (!$sourceImage) {
                return false;
            }
            // Get source dimensions
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            // Skip resize if image is already small enough
            if ($sourceWidth <= $targetSize && $sourceHeight <= $targetSize) {
                imagedestroy($sourceImage);
                return false; // Use original file
            }
            // Calculate new dimensions (square crop from center)
            $size = min($sourceWidth, $sourceHeight);
            $x = ($sourceWidth - $size) / 2;
            $y = ($sourceHeight - $size) / 2;
            // Create new image
            $newImage = imagecreatetruecolor($targetSize, $targetSize);
            // Preserve transparency for PNG and GIF
            if ($extension === 'png' || $extension === 'gif') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
                imagefilledrectangle($newImage, 0, 0, $targetSize, $targetSize, $transparent);
            }
            // Resample the image
            imagecopyresampled(
                $newImage, $sourceImage,
                0, 0, $x, $y,
                $targetSize, $targetSize, $size, $size
            );
            // Create temporary file
            $tempPath = tempnam(sys_get_temp_dir(), 'profile_img_');
            // Save resized image
            switch (strtolower($extension)) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($newImage, $tempPath, 90);
                    break;
                case 'png':
                    imagepng($newImage, $tempPath, 9);
                    break;
                case 'gif':
                    imagegif($newImage, $tempPath);
                    break;
                case 'webp':
                    imagewebp($newImage, $tempPath, 90);
                    break;
            }
            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($newImage);
            return $tempPath;
        } catch (Exception $e) {
            error_log("Image resize error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
        * Delete profile image from S3
        * @param string $imageUrl The full URL of the image to delete
        * @return bool Success status
    */
    public function deleteProfileImage($imageUrl) {
        try {
            // Extract the key from the URL
            $parsedUrl = parse_url($imageUrl);
            $key = ltrim($parsedUrl['path'], '/');
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key' => $key,
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Image deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete all existing profile images for a user
     * 
     * @param int $userId The user ID
     * @return bool Success status
     */
    public function deleteAllUserProfileImages($userId) {
        try {
            $prefix = "profile-images/user-{$userId}/";
            
            // List all objects with the user's prefix
            $result = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucketName,
                'Prefix' => $prefix,
            ]);
            
            if (isset($result['Contents']) && count($result['Contents']) > 0) {
                // Delete all found objects
                $deleteKeys = array_map(function($object) {
                    return ['Key' => $object['Key']];
                }, $result['Contents']);
                
                $this->s3Client->deleteObjects([
                    'Bucket' => $this->bucketName,
                    'Delete' => [
                        'Objects' => $deleteKeys,
                    ],
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("User images deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a presigned URL for secure access to a user's profile image
     * 
     * @param int $userId The user ID whose image to access
     * @param string $extension The file extension (jpg, png, etc.)
     * @param int $expirationMinutes How long the URL should be valid (default: 60 minutes)
     * @return string|false The presigned URL or false if failed
     */
    public function getPresignedProfileImageUrl($userId, $extension = 'jpg', $expirationMinutes = 60) {
        try {
            $fileName = "profile-images/user-{$userId}/profile.{$extension}";
            
            // Check if the image exists first
            try {
                $this->s3Client->headObject([
                    'Bucket' => $this->bucketName,
                    'Key' => $fileName,
                ]);
            } catch (Exception $e) {
                // Image doesn't exist, try common extensions
                $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $found = false;
                
                foreach ($extensions as $ext) {
                    if ($ext === $extension) continue; // Skip the one we already tried
                    
                    $testFileName = "profile-images/user-{$userId}/profile.{$ext}";
                    try {
                        $this->s3Client->headObject([
                            'Bucket' => $this->bucketName,
                            'Key' => $testFileName,
                        ]);
                        $fileName = $testFileName;
                        $found = true;
                        break;
                    } catch (Exception $e) {
                        continue;
                    }
                }
                
                if (!$found) {
                    return false; // No profile image found
                }
            }
            
            // Generate presigned URL
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key' => $fileName,
            ]);
            
            $request = $this->s3Client->createPresignedRequest(
                $cmd, 
                '+' . $expirationMinutes . ' minutes'
            );
            
            return (string) $request->getUri();
            
        } catch (Exception $e) {
            error_log("Presigned URL generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a user's profile image URL for display
     * This checks if the user owns the image or is an admin
     * 
     * @param int $targetUserId The user whose image to display
     * @param int $currentUserId The current logged-in user
     * @param bool $isAdmin Whether the current user is an admin
     * @param int $expirationMinutes How long the URL should be valid
     * @return string|false The image URL or false if no access
     */
    public function getUserProfileImageUrl($targetUserId, $currentUserId, $isAdmin = false, $expirationMinutes = 60) {
        // Check if user has permission to view this image
        if ($targetUserId !== $currentUserId && !$isAdmin) {
            return false; // No permission to view this image
        }
        
        return $this->getPresignedProfileImageUrl($targetUserId, 'jpg', $expirationMinutes);
    }
    
    /**
     * Get profile image info for a user (for admins)
     * 
     * @param int $userId The user ID
     * @return array Image info including URL, size, upload date, etc.
     */
    public function getProfileImageInfo($userId) {
        try {
            $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            foreach ($extensions as $ext) {
                $fileName = "profile-images/user-{$userId}/profile.{$ext}";
                
                try {
                    $result = $this->s3Client->headObject([
                        'Bucket' => $this->bucketName,
                        'Key' => $fileName,
                    ]);
                    
                    return [
                        'exists' => true,
                        'size' => $result['ContentLength'],
                        'last_modified' => $result['LastModified']->format('Y-m-d H:i:s'),
                        'content_type' => $result['ContentType'],
                        'extension' => $ext,
                        'presigned_url' => $this->getPresignedProfileImageUrl($userId, $ext, 60),
                        'metadata' => $result['Metadata'] ?? []
                    ];
                    
                } catch (Exception $e) {
                    continue;
                }
            }
            
            return [
                'exists' => false,
                'message' => 'No profile image found'
            ];
            
        } catch (Exception $e) {
            error_log("Profile image info error: " . $e->getMessage());
            return [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
