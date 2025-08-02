<?php
// auth/discord-callback.php - Discord OAuth callback handler
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/DiscordOAuth.php';

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
    if (!isset($_SESSION['discord_oauth_state']) || $_GET['state'] !== $_SESSION['discord_oauth_state']) {
        // Clear the state from session
        unset($_SESSION['discord_oauth_state']);
        throw new Exception('Invalid state parameter - potential CSRF attack');
    }
    
    // Clear the state from session after successful validation
    unset($_SESSION['discord_oauth_state']);
    
    $code = $_GET['code'];
    $discordOAuth = new DiscordOAuth();
    $userModel = new User();
    
    // Exchange code for access token
    $tokenData = $discordOAuth->getAccessToken($code);
    
    if (!isset($tokenData['access_token'])) {
        throw new Exception('Failed to obtain access token');
    }
    
    // Get user information from Discord
    $discordUserData = $discordOAuth->getUserInfo($tokenData['access_token']);
    
    if (!$discordUserData) {
        throw new Exception('Failed to get user information from Discord');
    }
    
    // Calculate token expiration time
    $expiresAt = null;
    if (isset($tokenData['expires_in'])) {
        $expiresAt = date('Y-m-d H:i:s', time() + intval($tokenData['expires_in']));
    }
    
    // Create or update user account
    $result = $userModel->createOrUpdateSocialUser(
        'discord', 
        $discordUserData['id'], 
        $discordUserData['username'], 
        $discordUserData,
        $tokenData['access_token'],
        $tokenData['refresh_token'] ?? null,
        $expiresAt
    );
    
    if ($result['success']) {
        // Set session variables for successful login
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['username'] = $result['user']['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['account_type'] = 'discord';
        
        // Set success message based on user status
        if ($result['status'] === 'pending') {
            $_SESSION['login_success'] = 'Discord login successful! Your account is pending approval. Aetia Talant Agency will contact you before you can access all features.';
        } elseif ($result['status'] === 'approved') {
            $_SESSION['login_success'] = 'Welcome back! Successfully logged in with Discord.';
        } else {
            $_SESSION['login_success'] = 'Discord login successful!';
        }
        
        // Redirect to homepage
        header('Location: ../index.php');
        exit;
    } else {
        throw new Exception($result['message']);
    }
    
} catch (Exception $e) {
    error_log('Discord OAuth error: ' . $e->getMessage());
    $error_message = 'Login failed: ' . $e->getMessage();
    
    // Redirect back to login page with error
    $_SESSION['login_error'] = $error_message;
    header('Location: ../login.php');
    exit;
}
?>
