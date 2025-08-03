<?php
// auth/twitch-link-callback.php - Twitch OAuth callback handler for linking accounts
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    $_SESSION['link_error'] = 'You must be logged in to link social accounts.';
    header('Location: ../login.php');
    exit;
}

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
    if (!isset($_SESSION['twitch_link_state']) || $_GET['state'] !== $_SESSION['twitch_link_state']) {
        throw new Exception('Invalid state parameter - potential CSRF attack');
    }
    
    // Clear the state from session after successful validation
    unset($_SESSION['twitch_link_state']);
    
    $code = $_GET['code'];
    $twitchOAuth = new TwitchOAuth();
    $userModel = new User();
    
    // Generate the link redirect URI that was used for authorization
    $linkRedirectUri = str_replace('twitch-callback.php', 'twitch-link-callback.php', $twitchOAuth->getRedirectUri());
    
    // Exchange code for access token using the same redirect URI used for authorization
    $tokenData = $twitchOAuth->getAccessToken($code, $linkRedirectUri);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to obtain access token from Twitch');
    }
    
    // Get user information from Twitch
    $twitchUserData = $twitchOAuth->getUserInfo($tokenData['access_token']);
    
    if (!$twitchUserData) {
        throw new Exception('Failed to retrieve user information from Twitch');
    }
    
    // Calculate token expiration time
    $expiresAt = null;
    if (isset($tokenData['expires_in'])) {
        $expiresAt = date('Y-m-d H:i:s', time() + intval($tokenData['expires_in']));
    }
    
    // Link the Twitch account to the current user
    $result = $userModel->linkSocialAccount(
        $_SESSION['user_id'],
        'twitch',
        $twitchUserData['id'],
        $twitchUserData['login'],
        $twitchUserData,
        $tokenData['access_token'],
        $tokenData['refresh_token'] ?? null,
        $expiresAt
    );
    
    if ($result['success']) {
        $_SESSION['link_success'] = $result['message'];
        header('Location: ../profile.php');
        exit;
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    error_log('Twitch link error: ' . $e->getMessage());
    $error_message = 'Failed to link Twitch account: ' . $e->getMessage();
    
    // Redirect back to profile page with error
    $_SESSION['link_error'] = $error_message;
    header('Location: ../profile.php');
    exit;
}
?>
