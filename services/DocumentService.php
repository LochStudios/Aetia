<?php
// services/DocumentService.php - Service for managing user documents in S3

require_once __DIR__ . '/../config/database.php';
require_once '/home/aetiacom/vendors/aws-autoloader.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class DocumentService {
    private $database;
    private $mysqli;
    private $s3Client;
    private $bucketName;
    private $region;
    private $endpoint;
    
    public function __construct() {
        $this->database = new Database();
        $this->mysqli = $this->database->getConnection();
        
        // Object Storage Configuration
        $this->bucketName = $this->getBucketName();
        $this->region = $this->getRegion();
        $this->endpoint = $this->getEndpoint();
        
        try {
            // Debug: Log configuration being used
            error_log("S3 Config (DocumentService) - Region: " . $this->region . ", Endpoint: " . $this->endpoint . ", Bucket: " . $this->bucketName);
            
            $this->s3Client = new S3Client([
                'version' => 'latest',
                'region' => $this->region,
                'endpoint' => $this->endpoint,
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $this->getAccessKey(),
                    'secret' => $this->getSecretKey(),
                ],
            ]);
            
            error_log("DocumentService: S3 client initialized successfully");
        } catch (Exception $e) {
            error_log("DocumentService: S3 initialization failed - this is a critical error: " . $e->getMessage());
            throw new Exception("Document service initialization failed - S3 configuration required: " . $e->getMessage());
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
        return ''; // Empty bucket name - files go directly to endpoint root
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
        return 'https://aetia.au-mel-1.linodeobjects.com'; // Bucket endpoint
    }
    
    // Ensure database connection is active
    private function ensureConnection() {
        if (!$this->mysqli || $this->mysqli->ping() === false) {
            $this->database = new Database();
            $this->mysqli = $this->database->getConnection();
        }
    }
    
    /**
     * Upload a document for a user to S3 and store metadata in database
     */
    public function uploadUserDocument($userId, $file, $documentType, $description, $uploadedBy) {
        try {
            $this->ensureConnection();
            
            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'File upload error.'];
            }
            
            // Check file size (10MB limit)
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $maxSize) {
                return ['success' => false, 'message' => 'File size exceeds 10MB limit.'];
            }
            
            // Validate file type
            $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($fileExtension, $allowedTypes)) {
                return ['success' => false, 'message' => 'File type not allowed.'];
            }
            
            // Generate unique filename
            $uniqueId = uniqid();
            $s3Key = "user-documents/{$userId}/{$documentType}/{$uniqueId}.{$fileExtension}";
            
            // Upload to S3 (simplified - you'll need AWS SDK for production)
            $s3Url = $this->uploadToS3($file['tmp_name'], $s3Key, $file['type']);
            
            if (!$s3Url) {
                return ['success' => false, 'message' => 'Failed to upload to S3.'];
            }
            
            // Store metadata in database
            $stmt = $this->mysqli->prepare("
                INSERT INTO user_documents (
                    user_id, document_type, original_filename, s3_key, s3_url, 
                    file_size, mime_type, description, uploaded_by, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param(
                "issssissi",
                $userId,
                $documentType,
                $file['name'],
                $s3Key,
                $s3Url,
                $file['size'],
                $file['type'],
                $description,
                $uploadedBy
            );
            
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Document uploaded successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to save document metadata.'];
            }
            
        } catch (Exception $e) {
            error_log("Upload document error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while uploading the document.'];
        }
    }
    
    /**
     * Get all documents for a user
     */
    public function getUserDocuments($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    d.id,
                    d.document_type,
                    d.original_filename,
                    d.s3_key,
                    d.s3_url,
                    d.file_size,
                    d.mime_type,
                    d.description,
                    d.uploaded_at,
                    u.username as uploaded_by_username
                FROM user_documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.user_id = ?
                ORDER BY d.uploaded_at DESC
            ");
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $documents = [];
            while ($row = $result->fetch_assoc()) {
                $documents[] = $row;
            }
            
            $stmt->close();
            return $documents;
            
        } catch (Exception $e) {
            error_log("Get user documents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete a document
     */
    public function deleteUserDocument($documentId, $deletedBy) {
        try {
            $this->ensureConnection();
            
            // Get document details first
            $stmt = $this->mysqli->prepare("SELECT s3_key FROM user_documents WHERE id = ?");
            $stmt->bind_param("i", $documentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $document = $result->fetch_assoc();
            $stmt->close();
            
            if (!$document) {
                return false;
            }
            
            // Delete from S3
            $this->deleteFromS3($document['s3_key']);
            
            // Delete from database
            $stmt = $this->mysqli->prepare("DELETE FROM user_documents WHERE id = ?");
            $stmt->bind_param("i", $documentId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                error_log("Document deleted by user ID {$deletedBy}: Document ID {$documentId}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Delete document error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get a single document by ID
     */
    public function getDocument($documentId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    d.*,
                    u.username as uploaded_by_username
                FROM user_documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?
            ");
            
            $stmt->bind_param("i", $documentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $document = $result->fetch_assoc();
            $stmt->close();
            
            return $document;
            
        } catch (Exception $e) {
            error_log("Get document error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Upload file to S3
     */
    private function uploadToS3($filePath, $s3Key, $mimeType) {
        try {
            error_log("DocumentService: Starting S3 upload for key: " . $s3Key);
            
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucketName,
                'Key' => $s3Key,
                'SourceFile' => $filePath,
                'ContentType' => $mimeType,
                'ACL' => 'private',
            ]);
            
            error_log("DocumentService: S3 upload successful. Object URL: " . $result['ObjectURL']);
            return $result['ObjectURL'];
            
        } catch (AwsException $e) {
            error_log("DocumentService: S3 Upload error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("DocumentService: General upload error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete file from S3
     */
    private function deleteFromS3($s3Key) {
        try {
            error_log("DocumentService: Starting S3 deletion for key: " . $s3Key);
            
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key' => $s3Key,
            ]);
            
            error_log("DocumentService: S3 deletion successful for key: " . $s3Key);
            return true;
            
        } catch (AwsException $e) {
            error_log("DocumentService: S3 Delete error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("DocumentService: General deletion error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate a signed URL for secure document access
     */
    public function getSignedUrl($s3Key, $expirationMinutes = 60) {
        try {
            error_log("DocumentService: Generating signed URL for key: " . $s3Key);
            
            $cmd = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->bucketName,
                'Key' => $s3Key
            ]);
            
            $request = $this->s3Client->createPresignedRequest($cmd, "+{$expirationMinutes} minutes");
            $signedUrl = (string) $request->getUri();
            
            error_log("DocumentService: Signed URL generated successfully");
            return $signedUrl;
            
        } catch (AwsException $e) {
            error_log("DocumentService: S3 Signed URL error: " . $e->getMessage());
            return false;
        } catch (Exception $e) {
            error_log("DocumentService: General signed URL error: " . $e->getMessage());
            return false;
        }
    }
}
?>
