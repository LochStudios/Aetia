<?php
// view-image.php - Secure image viewing for message attachments
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(403);
    exit('Access denied');
}

require_once __DIR__ . '/models/Message.php';

// Get attachment ID from URL
$attachmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($attachmentId <= 0) {
    http_response_code(400);
    exit('Invalid attachment ID');
}

$messageModel = new Message();
$userId = $_SESSION['user_id'];

// Get attachment details with permission check
$attachment = $messageModel->getAttachment($attachmentId, $userId);

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found or access denied');
}

// Check if file is an image
if (strpos($attachment['mime_type'], 'image/') !== 0) {
    http_response_code(400);
    exit('File is not an image');
}

// Check if file exists
if (!file_exists($attachment['file_path'])) {
    http_response_code(404);
    exit('File not found');
}

// Get file info
$filePath = $attachment['file_path'];
$mimeType = $attachment['mime_type'];
$fileSize = $attachment['file_size'];

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

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
    exit('Error reading file');
}
?>
