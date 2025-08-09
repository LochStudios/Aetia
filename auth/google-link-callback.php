<?php
// auth/google-link-callback.php - Google OAuth link callback handler for Aetia Talent Agency
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/GoogleOAuth.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    $_SESSION['link_error'] = 'You must be logged in to link a Google account.';
    header('Location: ../login.php');
    exit;
}

try {
    // Check if we received an authorization code
    if (!isset($_GET['code'])) {
        $error = $_GET['error'] ?? 'unknown_error';
        $error_description = $_GET['error_description'] ?? 'Authorization failed';
        
        error_log("Google link OAuth error: $error - $error_description");
        
        $_SESSION['link_error'] = 'Google authorization failed: ' . $error_description;
        header('Location: ../profile.php');
        exit;
    }
    
    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;
    
    // Initialize Google OAuth service
    $googleOAuth = new GoogleOAuth();
    
    // Exchange authorization code for access token
    $tokenData = $googleOAuth->getAccessToken($code, $state);
    $accessToken = $tokenData['access_token'];
    
    // Get user information from Google
    $googleUser = $googleOAuth->getUserInfo($accessToken);
    
    // Initialize User model
    $userModel = new User();
    
    // Link the Google account to the current user
    $result = $userModel->linkSocialAccount(
        $_SESSION['user_id'],
        'google',
        $googleUser['id'],
        $googleUser['username'],
        $googleUser['email'],
        $googleUser['profile_image'],
        json_encode($googleUser)
    );
    
    if ($result['success']) {
        $_SESSION['link_success'] = 'Google account linked successfully!';
    } else {
        $_SESSION['link_error'] = $result['message'];
    }
    
    header('Location: ../profile.php');
    exit;
    
} catch (Exception $e) {
    error_log('Google link callback error: ' . $e->getMessage());
    $_SESSION['link_error'] = 'An error occurred while linking your Google account. Please try again.';
    header('Location: ../profile.php');
    exit;
}
?>
