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
            throw new Exception("Image upload service is currently unavailable.");
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
        * Upload profile image to S3
        * @param array $file The uploaded file from $_FILES
        * @param int $userId The user ID for unique naming
        * @return array Success/failure result with image URL or error message
    */
    public function uploadProfileImage($file, $userId) {
        try {
            // Validate file input
            $validation = $this->validateImageFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }
            // Generate unique filename
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $fileName = "profile-images/user-{$userId}-" . time() . "." . $fileExtension;
            // Resize image if needed
            $resizedImagePath = $this->resizeImage($file['tmp_name'], $fileExtension);
            // Upload to S3
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $fileName,
                'SourceFile' => $resizedImagePath ?: $file['tmp_name'],
                'ContentType' => $file['type'],
                'ACL' => 'public-read', // Make the image publicly accessible
                'Metadata' => [
                    'user-id' => (string)$userId,
                    'upload-date' => date('Y-m-d H:i:s'),
                ]
            ]);
            // Clean up temporary resized file if created
            if ($resizedImagePath && file_exists($resizedImagePath)) {
                unlink($resizedImagePath);
            }
            // Get the public URL
            $imageUrl = $result['ObjectURL'];
            return [
                'success' => true, 
                'message' => 'Profile image uploaded successfully!',
                'image_url' => $imageUrl
            ];
            
        } catch (AwsException $e) {
            error_log("Object Storage upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to upload image to cloud storage.'];
        } catch (Exception $e) {
            error_log("Image upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while uploading your image.'];
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
}
?>
