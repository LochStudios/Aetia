<?php
// admin/view-user-profile-image.php - Admin endpoint for viewing user profile images
session_start();

// Security check - user must be logged in and be an admin
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/ImageUploadService.php';

// Check if user is admin
$userModel = new User();
$currentUser = $userModel->getUserById($_SESSION['user_id']);
if (!$currentUser || empty($currentUser['is_admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Get parameters
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
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
    $presignedUrl = $imageUploadService->getUserProfileImageUrl($userId, $_SESSION['user_id'], true, 60); // 60 minute expiration for admin
    if (!$presignedUrl) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Profile image not found']);
        exit;
    }
    // Return the presigned URL as JSON for AJAX requests
    if (isset($_GET['json']) && $_GET['json'] === '1') {
        // Also get additional image info for admin
        $imageInfo = $imageUploadService->getProfileImageInfo($userId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'image_url' => $presignedUrl,
            'expires_in' => 3600, // 60 minutes in seconds
            'image_info' => $imageInfo
        ]);
        exit;
    }
    // Redirect to the presigned URL for direct image access
    header('Location: ' . $presignedUrl);
    exit;
} catch (Exception $e) {
    error_log("Admin profile image view error: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
    exit;
}
?>