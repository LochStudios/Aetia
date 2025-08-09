<?php
// auth/google-callback.php - Google OAuth callback handler for Aetia Talent Agency
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/GoogleOAuth.php';

$error_message = '';
$success_message = '';

try {
    // Check if we received an authorization code
    if (!isset($_GET['code'])) {
        $error = $_GET['error'] ?? 'unknown_error';
        $error_description = $_GET['error_description'] ?? 'Authorization failed';
        
        error_log("Google OAuth error: $error - $error_description");
        
        $_SESSION['login_error'] = 'Google authorization failed: ' . $error_description;
        header('Location: ../login.php');
        exit;
    }
    
    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;
    
    // Initialize Google OAuth service
    $googleOAuth = new GoogleOAuth();
    
    // Exchange authorization code for access token
    $tokenData = $googleOAuth->getAccessToken($code, $state);
    $accessToken = $tokenData['access_token'];
    $isLinking = $tokenData['is_linking'];
    
    // Get user information from Google
    $googleUser = $googleOAuth->getUserInfo($accessToken);
    
    // Initialize User model
    $userModel = new User();
    
    if ($isLinking) {
        // This is an account linking request
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            $_SESSION['link_error'] = 'You must be logged in to link a Google account.';
            header('Location: ../login.php');
            exit;
        }
        
        // Link the Google account to the existing user
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
    }
    
    // Check if user already exists with this Google account
    $existingUser = $userModel->getUserBySocialAccount('google', $googleUser['id']);
    
    if ($existingUser) {
        // User exists, log them in
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['username'] = $existingUser['username'];
        $_SESSION['login_success'] = 'Welcome back, ' . htmlspecialchars($existingUser['username']) . '!';
        
        // Update last login
        $userModel->updateLastLogin($existingUser['id']);
        
        // Update profile image if it's changed
        if (!empty($googleUser['profile_image']) && $googleUser['profile_image'] !== $existingUser['profile_image']) {
            $userModel->updateProfileImage($existingUser['id'], $googleUser['profile_image']);
        }
        
        header('Location: ../index.php');
        exit;
    }
    
    // Check if a user already exists with this email address
    $existingEmailUser = $userModel->getUserByEmail($googleUser['email']);
    
    if ($existingEmailUser) {
        // User exists with this email but different account type
        // Link this Google account to the existing user
        $result = $userModel->linkSocialAccount(
            $existingEmailUser['id'],
            'google',
            $googleUser['id'],
            $googleUser['username'],
            $googleUser['email'],
            $googleUser['profile_image'],
            json_encode($googleUser)
        );
        
        if ($result['success']) {
            // Log the user in
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $existingEmailUser['id'];
            $_SESSION['username'] = $existingEmailUser['username'];
            $_SESSION['login_success'] = 'Google account linked and logged in successfully!';
            
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
    $username = $userModel->generateUniqueUsername($googleUser['username']);
    
    $result = $userModel->createUser(
        $username,
        $googleUser['email'],
        null, // No password for social accounts
        'google',
        $googleUser['profile_image']
    );
    
    if ($result['success']) {
        $userId = $result['user_id'];
        
        // Link the Google account
        $linkResult = $userModel->linkSocialAccount(
            $userId,
            'google',
            $googleUser['id'],
            $googleUser['username'],
            $googleUser['email'],
            $googleUser['profile_image'],
            json_encode($googleUser)
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
            $_SESSION['login_error'] = 'Account created but failed to link Google: ' . $linkResult['message'];
            header('Location: ../login.php');
            exit;
        }
    } else {
        $_SESSION['login_error'] = $result['message'];
        header('Location: ../login.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Google OAuth callback error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'An error occurred during Google authentication. Please try again.';
    header('Location: ../login.php');
    exit;
}
?>
