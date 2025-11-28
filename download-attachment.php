<?php
// download-attachment.php - Handle secure file downloads for message attachments
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/models/Message.php';
require_once __DIR__ . '/includes/FileUploader.php';
require_once __DIR__ . '/models/User.php';

// Get attachment ID from URL
$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Invalid attachment ID');
}

$messageModel = new Message();
$userId = $_SESSION['user_id'];

// Block suspended users from downloading message attachments
$userModel = new User();
$currentUser = $userModel->getUserById($userId);
if ($currentUser && !empty($currentUser['is_suspended'])) {
    http_response_code(403);
    exit('Access denied: account suspended');
}

// Get attachment details with permission check
$attachment = $messageModel->getAttachment($attachmentId, $userId);

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found or access denied');
}

// Handle S3 files vs local files
if (strpos($attachment['file_path'], 's3_document_') === 0) {
    // This is an S3 file - redirect to signed URL with download disposition
    $fileUploader = new FileUploader();
    $signedUrl = $fileUploader->getSignedUrl($attachment['file_path'], 60);
    if ($signedUrl) {
        // For S3, we need to add response-content-disposition parameter for download
        $downloadUrl = $signedUrl . (strpos($signedUrl, '?') !== false ? '&' : '?') . 
                      'response-content-disposition=' . urlencode('attachment; filename="' . $attachment['original_filename'] . '"');
        // Redirect to the signed URL with download parameters
        header('Location: ' . $downloadUrl);
        exit();
    } else {
        http_response_code(404);
        exit('Unable to generate signed URL for S3 file');
    }
}

// Handle local files (fallback for non-image files or legacy files)
$filePath = $attachment['file_path'];

// Check if local file exists
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

// Get file info
$filename = $attachment['original_filename'];
$mimeType = $attachment['mime_type'];
$fileSize = $attachment['file_size'];

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Set appropriate headers for file download
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
header('Cache-Control: private, max-age=0, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
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
    exit('Error reading file');
}
?>
