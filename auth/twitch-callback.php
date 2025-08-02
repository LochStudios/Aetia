<?php
// auth/twitch-callback.php - Twitch OAuth callback handler
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/TwitchOAuth.php';

$error_message = '';
$success_message = '';

try {
    // Check for authorization code
    if (!isset($_GET['code'])) {
        throw new Exception('Authorization code not received');
    }
    
    // Check for state parameter (CSRF protection)
    if (!isset($_GET['state'])) {
        throw new Exception('State parameter missing - potential CSRF attack');
    }
    
    // Validate state parameter against session
    if (!isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        // Clear the state from session
        unset($_SESSION['oauth_state']);
        throw new Exception('Invalid state parameter - potential CSRF attack');
    }
    
    // Clear the state from session after successful validation
    unset($_SESSION['oauth_state']);
    
    $code = $_GET['code'];
    $twitchOAuth = new TwitchOAuth();
    $userModel = new User();
    
    // Exchange code for access token
    $tokenData = $twitchOAuth->getAccessToken($code);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to obtain access token');
    }
    
    // Get user information from Twitch
    $twitchUserData = $twitchOAuth->getUserInfo($tokenData['access_token']);
    
    if (!$twitchUserData) {
        throw new Exception('Failed to get user information from Twitch');
    }
    
    // Calculate token expiration time
    $expiresAt = null;
    if (isset($tokenData['expires_in'])) {
        $expiresAt = date('Y-m-d H:i:s', time() + $tokenData['expires_in']);
    }
    
    // Create or update user account
    $result = $userModel->createOrUpdateSocialUser(
        'twitch',
        $twitchUserData['id'],
        $twitchUserData['login'],
        $twitchUserData,
        $tokenData['access_token'],
        $tokenData['refresh_token'] ?? null,
        $expiresAt
    );
    
    if ($result['success']) {
        // Set session variables
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['username'] = $result['user']['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['account_type'] = 'twitch';
        $_SESSION['social_data'] = $twitchUserData;
        
        // Redirect to homepage with success message
        $_SESSION['login_success'] = 'Successfully logged in with Twitch!';
        header('Location: ../index.php');
        exit;
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    error_log('Twitch OAuth error: ' . $e->getMessage());
    $error_message = 'Login failed: ' . $e->getMessage();
    
    // Redirect back to login page with error
    $_SESSION['login_error'] = $error_message;
    header('Location: ../login.php');
    exit;
}
?>
