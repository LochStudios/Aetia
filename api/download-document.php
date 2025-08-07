<?php
// api/download-document.php - Unified document download API
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    exit('Unauthorized');
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/DocumentService.php';

$userModel = new User();
$documentService = new DocumentService();

// Get document ID from URL
$documentId = intval($_GET['id'] ?? 0);
$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';

if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid document ID');
}

$document = $documentService->getDocument($documentId);

if (!$document) {
    http_response_code(404);
    exit('Document not found');
}

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

// Security check: Admin can access all documents, users can only access their own
if (!$isAdmin && $document['user_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    exit('Access denied');
}

// For local files (development), serve the file directly
if (file_exists($document['s3_url'])) {
    // Set headers for file download or preview
    if ($isPreview) {
        // For preview (images), display inline
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Disposition: inline; filename="' . $document['original_filename'] . '"');
    } else {
        // For download, force attachment - use generic MIME type to force download
        header('Content-Type: application/force-download');
        header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
        header('Content-Transfer-Encoding: binary');
    }
    
    header('Content-Length: ' . filesize($document['s3_url']));
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Log the download
    error_log("Document download: Document ID {$documentId} accessed by user ID {$_SESSION['user_id']} (" . ($_SESSION['username'] ?? 'Unknown') . ")");
    
    // Output the file
    readfile($document['s3_url']);
    exit;
} else {
    // For S3 files, redirect to signed URL
    $signedUrl = $documentService->getSignedUrl($document['s3_key']);
    
    // Log the download
    error_log("Document download: Document ID {$documentId} S3 access by user ID {$_SESSION['user_id']} (" . ($_SESSION['username'] ?? 'Unknown') . ")");
    
    header('Location: ' . $signedUrl);
    exit;
}
?>
