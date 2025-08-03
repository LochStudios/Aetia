<?php
// view-profile-image.php - Secure profile image viewer with access control
session_start();

// Security check - user must be logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/services/ImageUploadService.php';

// Get parameters
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
$currentUserId = $_SESSION['user_id'];

if (!$userId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    // Get current user info to check admin status
    $userModel = new User();
    $currentUser = $userModel->getUserById($currentUserId);
    $isAdmin = $currentUser['is_admin'] ?? false;
    
    // Check permissions: user can view their own image, or admin can view any image
    if ($userId !== $currentUserId && !$isAdmin) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Access denied']);
        exit;
    }
    
    // Get the target user's info to check account type
    $targetUser = $userModel->getUserById($userId);
    if (!$targetUser) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Only serve S3 images for manual accounts
    if ($targetUser['account_type'] !== 'manual') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'This endpoint is only for manual account profile images']);
        exit;
    }
    
    // Get the image
    $imageUploadService = new ImageUploadService();
    $presignedUrl = $imageUploadService->getUserProfileImageUrl($userId, $currentUserId, $isAdmin, 30); // 30 minute expiration
    
    if (!$presignedUrl) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Profile image not found']);
        exit;
    }
    
    // Return the presigned URL as JSON for AJAX requests
    if (isset($_GET['json']) && $_GET['json'] === '1') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'image_url' => $presignedUrl,
            'expires_in' => 1800 // 30 minutes in seconds
        ]);
        exit;
    }
    
    // Redirect to the presigned URL for direct image access
    header('Location: ' . $presignedUrl);
    exit;
    
} catch (Exception $e) {
    error_log("Profile image view error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
    exit;
}
?>
