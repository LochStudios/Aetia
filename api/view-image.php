<?php
// api/view-image.php - Secure image viewing API for message attachments
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../includes/FileUploader.php';

// Get attachment ID from URL
$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attachmentId <= 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid attachment ID']);
    exit;
}

$messageModel = new Message();
$userId = $_SESSION['user_id'];

// Get attachment details with permission check
$attachment = $messageModel->getAttachment($attachmentId, $userId);

if (!$attachment) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Attachment not found or access denied']);
    exit;
}

// Check if file is an image
if (strpos($attachment['mime_type'], 'image/') !== 0) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'File is not an image']);
    exit;
}

try {
    // Handle S3 images vs local images
    if (strpos($attachment['file_path'], 's3_document_') === 0) {
        // This is an S3 image - get signed URL
        $fileUploader = new FileUploader();
        $signedUrl = $fileUploader->getSignedUrl($attachment['file_path'], 60);
        
        if ($signedUrl) {
            // Return JSON response with signed URL for AJAX requests
            if (isset($_GET['json']) && $_GET['json'] === '1') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'image_url' => $signedUrl,
                    'expires_in' => 3600 // 60 minutes
                ]);
                exit;
            }
            
            // Redirect to the signed URL for direct access
            header('Location: ' . $signedUrl);
            exit;
        } else {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unable to generate signed URL for S3 image']);
            exit;
        }
    }

    // Handle local images (fallback for legacy images)
    $filePath = $attachment['file_path'];

    // Check if local file exists
    if (!file_exists($filePath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'File not found']);
        exit;
    }

    // For JSON requests, return the local file URL
    if (isset($_GET['json']) && $_GET['json'] === '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'image_url' => $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/view-image.php?id=' . $attachmentId,
            'expires_in' => 3600
        ]);
        exit;
    }

    // Get file info
    $mimeType = $attachment['mime_type'];
    $fileSize = $attachment['file_size'];

    // Set appropriate headers for image display
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: private, max-age=3600');
    header('Pragma: cache');

    // Output image
    if ($handle = fopen($filePath, 'rb')) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
        }
        fclose($handle);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error reading file']);
        exit;
    }

} catch (Exception $e) {
    error_log("Image view API error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
}
?>