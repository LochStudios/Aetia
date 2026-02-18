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

// Get document ID from URL - accept both 'id' and 'document_id' parameters
$documentId = intval($_GET['id'] ?? $_GET['document_id'] ?? 0);
$isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';
$forceDownload = isset($_GET['force']) && $_GET['force'] == '1';

if ($documentId <= 0) {
    $requestedDocumentId = $_GET['id'] ?? $_GET['document_id'] ?? 'none';
    error_log("Download error: Invalid document ID provided: " . $requestedDocumentId);
    http_response_code(400);
    exit('Invalid document ID');
}

$document = $documentService->getDocument($documentId);

if (!$document) {
    error_log("Download error: Document not found for ID: $documentId by user ID: " . $_SESSION['user_id']);
    http_response_code(404);
    exit('Document not found');
}

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

// Security check: Admin can access all documents, users can only access their own
if (!$isAdmin && $document['user_id'] != $_SESSION['user_id']) {
    error_log("Download error: Access denied for document ID: $documentId. User ID: " . $_SESSION['user_id'] . ", Document User ID: " . $document['user_id'] . ", Is Admin: " . ($isAdmin ? 'yes' : 'no'));
    http_response_code(403);
    exit('Access denied');
}

$safeFilename = str_replace(["\r", "\n", '"'], ['', '', ''], basename($document['original_filename'] ?? 'download'));

// For local files (development), serve the file directly
if (file_exists($document['s3_url'])) {
    // Special handling for forced downloads to bypass browser plugins
    if ($forceDownload && !$isPreview) {
        // Use HTML page with JavaScript to force download
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Downloading...</title>
        </head>
        <body>
            <script>
                // Create a blob and download it to bypass browser plugins
                fetch('<?php echo $_SERVER['REQUEST_URI']; ?>&direct=1')
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = '<?php echo addslashes($safeFilename); ?>';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        // Close the window after download starts
                        setTimeout(() => {
                            window.close();
                        }, 100);
                    });
            </script>
            <p>Your download should start automatically. If it doesn't, <a href="<?php echo $_SERVER['REQUEST_URI']; ?>&direct=1" download="<?php echo htmlspecialchars($safeFilename); ?>">click here</a>.</p>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Direct file serving (for direct=1 parameter or normal downloads)
    if (isset($_GET['direct']) || !$forceDownload) {
        // Set headers for file download or preview
        if ($isPreview) {
            // For preview (images), display inline
            header('Content-Type: ' . $document['mime_type']);
            header('Content-Disposition: inline; filename="' . $safeFilename . '"');
        } else {
            // For PDF files, use proper MIME type to prevent corruption
            if ($document['mime_type'] === 'application/pdf') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            } else {
                // For other files, force download with octet-stream to bypass browser plugins
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            }
            header('Content-Transfer-Encoding: binary');
            header('Content-Description: File Transfer');
            header('X-Content-Type-Options: nosniff');
            header('Accept-Ranges: bytes');
        }
        
        header('Content-Length: ' . filesize($document['s3_url']));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0, public');
        header('Pragma: public');
        header('Expires: 0');
        
        // Log the download
        error_log("Document download: Document ID {$documentId} accessed by user ID {$_SESSION['user_id']} (" . ($_SESSION['username'] ?? 'Unknown') . ")");
        
        // Output the file
        readfile($document['s3_url']);
        exit;
    }
} else {
    // For S3 files, redirect to signed URL
    $signedUrl = $documentService->getSignedUrl($document['s3_key']);
    if (empty($signedUrl)) {
        error_log("Download error: Failed to generate signed URL for document ID: $documentId");
        http_response_code(500);
        exit('Unable to prepare document download');
    }
    
    // Log the download
    error_log("Document download: Document ID {$documentId} S3 access by user ID {$_SESSION['user_id']} (" . ($_SESSION['username'] ?? 'Unknown') . ")");
    
    header('Location: ' . $signedUrl);
    exit;
}
?>
