<?php
// api/get-profile-image-url.php - API endpoint to get profile image URLs
session_start();

header('Content-Type: application/json');

// Security check - user must be logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../services/ImageUploadService.php';

// Get parameters
$profileImage = $_GET['profile_image'] ?? '';
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

if (empty($profileImage)) {
    echo json_encode(['error' => 'No profile image specified']);
    exit;
}

try {
    // If it's already a proper URL (starts with http), return as-is
    if (strpos($profileImage, 'http') === 0) {
        echo json_encode(['success' => true, 'image_url' => $profileImage]);
        exit;
    }
    
    // If it's a flag pattern like "user-X-has-image", get S3 presigned URL directly
    if (preg_match('/^user-(\d+)-has-image/', $profileImage, $matches)) {
        $imageUserId = $matches[1];
        $imageUploadService = new ImageUploadService();
        $presignedUrl = $imageUploadService->getPresignedProfileImageUrl($imageUserId, 'jpeg', 30);
        
        if ($presignedUrl) {
            echo json_encode(['success' => true, 'image_url' => $presignedUrl]);
        } else {
            echo json_encode(['error' => 'Profile image not found']);
        }
        exit;
    }
    
    // If it looks like a file path and we have a user ID, try S3
    if ($userId && !strpos($profileImage, '/')) {
        $imageUploadService = new ImageUploadService();
        $presignedUrl = $imageUploadService->getPresignedProfileImageUrl($userId, 'jpeg', 30);
        
        if ($presignedUrl) {
            echo json_encode(['success' => true, 'image_url' => $presignedUrl]);
        } else {
            echo json_encode(['error' => 'Profile image not found']);
        }
        exit;
    }
    
    // Default case - treat as relative path
    echo json_encode(['success' => true, 'image_url' => $profileImage]);
    
} catch (Exception $e) {
    error_log("Profile image URL API error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to get profile image URL']);
}
?>
