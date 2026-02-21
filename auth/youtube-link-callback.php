<?php
// auth/youtube-link-callback.php - YouTube OAuth link callback handler for Aetia Talent Agency
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/YouTubeOAuth.php';

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    $_SESSION['link_error'] = 'You must be logged in to link a YouTube account.';
    header('Location: ../login.php');
    exit;
}

try {
    if (!isset($_GET['code'])) {
        $error = $_GET['error'] ?? 'unknown_error';
        $errorDescription = $_GET['error_description'] ?? 'Authorization failed';
        error_log("YouTube link OAuth error: $error - $errorDescription");
        $_SESSION['link_error'] = 'YouTube authorization failed: ' . $errorDescription;
        header('Location: ../profile.php');
        exit;
    }

    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;

    $youtubeOAuth = new YouTubeOAuth();
    $tokenData = $youtubeOAuth->getAccessToken($code, $state);
    $youtubeUser = $youtubeOAuth->getUserInfo($tokenData['access_token']);

    $userModel = new User();
    $result = $userModel->linkSocialAccount(
        $_SESSION['user_id'],
        'youtube',
        $youtubeUser['id'],
        $youtubeUser['username'],
        $youtubeUser,
        $tokenData['access_token'],
        $tokenData['refresh_token'] ?? null,
        isset($tokenData['expires_in']) ? date('Y-m-d H:i:s', time() + $tokenData['expires_in']) : null
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
