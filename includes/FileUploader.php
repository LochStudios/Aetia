<?php
// includes/FileUploader.php - File upload utility for message attachments

require_once __DIR__ . '/../services/DocumentService.php';

class FileUploader {
    private $baseUploadPath;
    private $allowedTypes;
    private $maxFileSize;
    
    public function __construct() {
        $this->baseUploadPath = '/home/aetiacom/message-attachments';
        
        // Allowed MIME types for security
        $this->allowedTypes = [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'text/plain', 'text/csv',
            'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip', 'application/x-zip-compressed',
            'video/mp4', 'video/avi', 'video/mov', 'video/wmv',
            'audio/mp3', 'audio/wav', 'audio/ogg'
        ];
        
        // Maximum file size: 1GB
        $this->maxFileSize = 1024 * 1024 * 1024;
    }
    
    /**
     * Upload file for a specific message
     * @param array $file - $_FILES array element
     * @param int $userId - User ID
     * @param int $messageId - Message ID
     * @return array - Result array with success/error information
     */
    public function uploadMessageAttachment($file, $userId, $messageId) {
        try {
            // Validate file upload
            $validation = $this->validateFile($file);
            if (!$validation['success']) {
                return $validation;
            }
            // Use DocumentService for image uploads to S3
            $mimeType = $this->getMimeType($file['tmp_name'], $file['name']);
            if (self::isImage($mimeType)) {
                $documentService = new DocumentService();
                $documentType = 'message-image';
                $description = 'Message image attachment';
                $result = $documentService->uploadUserDocument($userId, $file, $documentType, $description, $userId, false);
                if ($result['success']) {
                    // Get the most recently uploaded document by document_id
                    $documentId = $result['document_id'];
                    $document = $documentService->getDocument($documentId);
                    $s3Key = $document ? $document['s3_key'] : null;
                    // Generate signed URL for secure access
                    $signedUrl = $s3Key ? $documentService->getSignedUrl($s3Key, 60) : null;
                    return [
                        'success' => true,
                        'filename' => $file['name'],
                        'original_filename' => $file['name'],
                        'file_path' => 's3_document_' . $result['document_id'],
                        'file_size' => $file['size'],
                        'mime_type' => $mimeType,
                        's3_url' => $signedUrl,
                        's3_key' => $s3Key,
                        'document_id' => $result['document_id']
                    ];
                } else {
                    return ['success' => false, 'message' => $result['message']];
                }
            }
            // For non-image files, fallback to local storage
            $userDir = $this->baseUploadPath . '/' . $userId;
            $messageDir = $userDir . '/' . $messageId;
            
            if (!$this->createDirectoryStructure($messageDir)) {
                return ['success' => false, 'message' => 'Failed to create upload directory'];
            }
            
            // Generate unique filename to prevent conflicts
            $originalFilename = $file['name'];
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $filename = uniqid('attachment_') . '_' . time() . '.' . $extension;
            $fullPath = $messageDir . '/' . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                // Set appropriate permissions
                chmod($fullPath, 0644);
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'original_filename' => $originalFilename,
                    'file_path' => $fullPath,
                    'file_size' => $file['size'],
                    'mime_type' => $file['type']
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to move uploaded file'];
            }
            
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return ['success' => false, 'message' => 'File upload failed'];
        }
    }
    
    /**
     * Validate uploaded file
     * @param array $file - $_FILES array element
     * @return array - Validation result
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    return ['success' => false, 'message' => 'File is too large'];
                case UPLOAD_ERR_PARTIAL:
                    return ['success' => false, 'message' => 'File upload was interrupted'];
                case UPLOAD_ERR_NO_FILE:
                    return ['success' => false, 'message' => 'No file was uploaded'];
                default:
                    return ['success' => false, 'message' => 'File upload error'];
            }
        }
        
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'message' => 'File size exceeds 1GB limit'];
        }
        
        // Check if file is empty
        if ($file['size'] == 0) {
            return ['success' => false, 'message' => 'File is empty'];
        }
        
        // Check MIME type
        $mimeType = $this->getMimeType($file['tmp_name'], $file['name']);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['success' => false, 'message' => 'File type not allowed'];
        }
        
        // Additional security check for executable files
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $dangerousExtensions = ['exe', 'bat', 'cmd', 'scr', 'pif', 'com', 'vbs', 'js', 'jar', 'php', 'asp', 'jsp'];
        
        if (in_array($extension, $dangerousExtensions)) {
            return ['success' => false, 'message' => 'File type not allowed for security reasons'];
        }
        
        return ['success' => true];
    }
    
    /**
     * Create directory structure recursively
     * @param string $path - Directory path to create
     * @return bool - Success status
     */
    private function createDirectoryStructure($path) {
        if (!file_exists($path)) {
            if (!mkdir($path, 0755, true)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Delete attachment file
     * @param string $filePath - Full path to file or S3 document ID
     * @return bool - Success status
     */
    public function deleteAttachment($filePath) {
        // Check if this is an S3 document (indicated by s3_document_ prefix)
        if (strpos($filePath, 's3_document_') === 0) {
            $documentId = str_replace('s3_document_', '', $filePath);
            try {
                $documentService = new DocumentService();
                return $documentService->deleteUserDocument($documentId, 0); // 0 as system deletion
            } catch (Exception $e) {
                error_log("S3 attachment deletion error: " . $e->getMessage());
                return false;
            }
        }
        // Handle local file deletion
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true; // File doesn't exist, consider it deleted
    }
    
    /**
     * Get file size in human readable format
     * @param int $bytes - File size in bytes
     * @return string - Formatted file size
     */
    public static function formatFileSize($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Get MIME type with fallback for servers without fileinfo extension
     * @param string $filePath - Path to the file
     * @param string $fileName - Original filename
     * @return string - MIME type
     */
    private function getMimeType($filePath, $fileName) {
        // Try using fileinfo extension first (preferred method)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $filePath);
                finfo_close($finfo);
                if ($mimeType) {
                    return $mimeType;
                }
            }
        }
        
        // Fallback: Use file extension mapping
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $extensionToMime = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            
            // Documents
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // Archives
            'zip' => 'application/zip',
            
            // Video
            'mp4' => 'video/mp4',
            'avi' => 'video/avi',
            'mov' => 'video/mov',
            'wmv' => 'video/wmv',
            
            // Audio
            'mp3' => 'audio/mp3',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg'
        ];
        
        // Return mapped MIME type or default
        return $extensionToMime[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Check if file type is an image
     * @param string $mimeType - MIME type
     * @return bool - True if image
     */
    public static function isImage($mimeType) {
        return strpos($mimeType, 'image/') === 0;
    }
    
    /**
     * Get signed URL for S3 attachment
     * @param string $filePath - S3 document ID or file path
     * @param int $expirationMinutes - URL expiration time in minutes
     * @return string|null - Signed URL or null if not S3 or error
     */
    public function getSignedUrl($filePath, $expirationMinutes = 60) {
        // Check if this is an S3 document (indicated by s3_document_ prefix)
        if (strpos($filePath, 's3_document_') === 0) {
            $documentId = str_replace('s3_document_', '', $filePath);
            try {
                $documentService = new DocumentService();
                $document = $documentService->getDocument($documentId);
                if ($document && isset($document['s3_key'])) {
                    return $documentService->getSignedUrl($document['s3_key'], $expirationMinutes);
                }
            } catch (Exception $e) {
                error_log("S3 signed URL generation error: " . $e->getMessage());
            }
        }
        return null; // Not an S3 document or error occurred
    }
}
?>
