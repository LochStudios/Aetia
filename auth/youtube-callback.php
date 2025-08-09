<?php
// auth/youtube-callback.php - YouTube OAuth callback handler for Aetia Talent Agency
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/YouTubeOAuth.php';

$error_message = '';
$success_message = '';

try {
    // Check if we received an authorization code
    if (!isset($_GET['code'])) {
        $error = $_GET['error'] ?? 'unknown_error';
        $error_description = $_GET['error_description'] ?? 'Authorization failed';
        
        error_log("YouTube OAuth error: $error - $error_description");
        
        $_SESSION['login_error'] = 'YouTube authorization failed: ' . $error_description;
        header('Location: ../login.php');
        exit;
    }
    
    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;
    
    // Initialize YouTube OAuth service
    $youtubeOAuth = new YouTubeOAuth();
    
    // Exchange authorization code for access token
    $tokenData = $youtubeOAuth->getAccessToken($code, $state);
    $accessToken = $tokenData['access_token'];
    $isLinking = $tokenData['is_linking'];
    
    // Get user information from YouTube
    $youtubeUser = $youtubeOAuth->getUserInfo($accessToken);
    
    // Initialize User model
    $userModel = new User();
    
    if ($isLinking) {
        // This is an account linking request
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            $_SESSION['link_error'] = 'You must be logged in to link a YouTube account.';
            header('Location: ../login.php');
            exit;
        }
        
        // Link the YouTube account to the existing user
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
    }
    
    // Check if user already exists with this YouTube account
    $existingUser = $userModel->getUserBySocialAccount('youtube', $youtubeUser['id']);
    
    if ($existingUser) {
        // User exists, log them in
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['username'] = $existingUser['username'];
        $_SESSION['login_success'] = 'Welcome back, ' . htmlspecialchars($existingUser['username']) . '!';
        
        // Update last login
        $userModel->updateLastLogin($existingUser['id']);
        
        // Update profile image if it's changed
        if (!empty($youtubeUser['profile_image']) && $youtubeUser['profile_image'] !== $existingUser['profile_image']) {
            $userModel->updateProfileImage($existingUser['id'], $youtubeUser['profile_image']);
        }
        
        header('Location: ../index.php');
        exit;
    }
    
    // Check if a user already exists with this email address
    $existingEmailUser = $userModel->getUserByEmail($youtubeUser['email']);
    
    if ($existingEmailUser) {
        // User exists with this email but different account type
        // Link this YouTube account to the existing user
        $result = $userModel->linkSocialAccount(
            $existingEmailUser['id'],
            'youtube',
            $youtubeUser['id'],
            $youtubeUser['username'],
            $youtubeUser['email'],
            $youtubeUser['profile_image'],
            json_encode($youtubeUser)
        );
        
        if ($result['success']) {
            // Log the user in
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $existingEmailUser['id'];
            $_SESSION['username'] = $existingEmailUser['username'];
            $_SESSION['login_success'] = 'YouTube account linked and logged in successfully!';
            
            // Update last login
            $userModel->updateLastLogin($existingEmailUser['id']);
            
            header('Location: ../index.php');
            exit;
        } else {
            $_SESSION['login_error'] = $result['message'];
            header('Location: ../login.php');
            exit;
        }
    }
    
    // Create new user account
    $username = $userModel->generateUniqueUsername($youtubeUser['username']);
    
    $result = $userModel->createUser(
        $username,
        $youtubeUser['email'],
        null, // No password for social accounts
        'youtube',
        $youtubeUser['profile_image']
    );
    
    if ($result['success']) {
        $userId = $result['user_id'];
        
        // Link the YouTube account
        $linkResult = $userModel->linkSocialAccount(
            $userId,
            'youtube',
            $youtubeUser['id'],
            $youtubeUser['username'],
            $youtubeUser['email'],
            $youtubeUser['profile_image'],
            json_encode($youtubeUser)
        );
        
        if ($linkResult['success']) {
            // Log the user in
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['login_success'] = 'Account created and logged in successfully!';
            
            header('Location: ../index.php');
            exit;
        } else {
            $_SESSION['login_error'] = 'Account created but failed to link YouTube: ' . $linkResult['message'];
            header('Location: ../login.php');
            exit;
        }
    } else {
        $_SESSION['login_error'] = $result['message'];
        header('Location: ../login.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('YouTube OAuth callback error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'An error occurred during YouTube authentication. Please try again.';
    header('Location: ../login.php');
    exit;
}
?>
