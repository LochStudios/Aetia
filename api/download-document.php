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
$forceDownload = isset($_GET['force']) && $_GET['force'] == '1';

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
                        a.download = '<?php echo addslashes($document['original_filename']); ?>';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        // Close the window after download starts
                        setTimeout(() => {
                            window.close();
                        }, 100);
                    });
            </script>
            <p>Your download should start automatically. If it doesn't, <a href="<?php echo $_SERVER['REQUEST_URI']; ?>&direct=1" download="<?php echo htmlspecialchars($document['original_filename']); ?>">click here</a>.</p>
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
            header('Content-Disposition: inline; filename="' . $document['original_filename'] . '"');
        } else {
            // For PDF files, use proper MIME type to prevent corruption
            if ($document['mime_type'] === 'application/pdf') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
            } else {
                // For other files, force download with octet-stream to bypass browser plugins
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $document['original_filename'] . '"');
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
    
    // Log the download
    error_log("Document download: Document ID {$documentId} S3 access by user ID {$_SESSION['user_id']} (" . ($_SESSION['username'] ?? 'Unknown') . ")");
    
    header('Location: ' . $signedUrl);
    exit;
}
?>
