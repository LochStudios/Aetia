<?php
// auth/youtube-link-callback.php - YouTube OAuth link callback handler for Aetia Talent Agency
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/YouTubeOAuth.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    $_SESSION['link_error'] = 'You must be logged in to link a YouTube account.';
    header('Location: ../login.php');
    exit;
}

try {
    // Check if we received an authorization code
    if (!isset($_GET['code'])) {
        $error = $_GET['error'] ?? 'unknown_error';
        $error_description = $_GET['error_description'] ?? 'Authorization failed';
        
        error_log("YouTube link OAuth error: $error - $error_description");
        
        $_SESSION['link_error'] = 'YouTube authorization failed: ' . $error_description;
        header('Location: ../profile.php');
        exit;
    }
    
    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;
    
    // Initialize YouTube OAuth service
    $youtubeOAuth = new YouTubeOAuth();
    
    // Exchange authorization code for access token
    $tokenData = $youtubeOAuth->getAccessToken($code, $state);
    $accessToken = $tokenData['access_token'];
    
    // Get user information from YouTube
    $youtubeUser = $youtubeOAuth->getUserInfo($accessToken);
    
    // Initialize User model
    $userModel = new User();
    
    // Link the YouTube account to the current user
    $result = $userModel->linkSocialAccount(
        $_SESSION['user_id'],
        'youtube',
        $youtubeUser['id'],
        $youtubeUser['username'],
        $youtubeUser['email'],
        $youtubeUser['profile_image'],
        json_encode($youtubeUser)
    );
    
    if ($result['success']) {
        $_SESSION['link_success'] = 'YouTube account linked successfully!';
    } else {
        $_SESSION['link_error'] = $result['message'];
    }
    
    header('Location: ../profile.php');
    exit;
    
} catch (Exception $e) {
    error_log('YouTube link callback error: ' . $e->getMessage());
    $_SESSION['link_error'] = 'An error occurred while linking your YouTube account. Please try again.';
    header('Location: ../profile.php');
    exit;
}
?>
