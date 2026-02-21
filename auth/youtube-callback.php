<?php
// auth/youtube-callback.php - YouTube OAuth callback handler for Aetia Talent Agency
session_start();

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/YouTubeOAuth.php';

try {
    if (!isset($_GET['code'])) {
        $error = $_GET['error'] ?? 'unknown_error';
        $errorDescription = $_GET['error_description'] ?? 'Authorization failed';
        error_log("YouTube OAuth error: $error - $errorDescription");
        $_SESSION['login_error'] = 'YouTube authorization failed: ' . $errorDescription;
        header('Location: ../login.php');
        exit;
    }

    $code = $_GET['code'];
    $state = $_GET['state'] ?? null;

    $youtubeOAuth = new YouTubeOAuth();
    $tokenData = $youtubeOAuth->getAccessToken($code, $state);
    $accessToken = $tokenData['access_token'];
    $isLinking = $tokenData['is_linking'];

    $youtubeUser = $youtubeOAuth->getUserInfo($accessToken);
    $userModel = new User();

    if ($isLinking) {
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            $_SESSION['link_error'] = 'You must be logged in to link a YouTube account.';
            header('Location: ../login.php');
            exit;
        }

        $result = $userModel->linkSocialAccount(
            $_SESSION['user_id'],
            'youtube',
            $youtubeUser['id'],
            $youtubeUser['username'],
            $youtubeUser,
            $accessToken,
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
    }

    $existingUser = $userModel->getUserBySocialAccount('youtube', $youtubeUser['id']);
    if ($existingUser) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $existingUser['id'];
        $_SESSION['username'] = $existingUser['username'];
        $_SESSION['account_type'] = 'youtube';
        $_SESSION['login_success'] = 'Welcome back, ' . htmlspecialchars($existingUser['username']) . '!';

        $userModel->updateLastLogin($existingUser['id']);

        if (!empty($youtubeUser['profile_image']) && $youtubeUser['profile_image'] !== $existingUser['profile_image']) {
            $userModel->updateProfileImage($existingUser['id'], $youtubeUser['profile_image']);
        }

        header('Location: ../index.php');
        exit;
    }

    $existingEmailUser = $userModel->getUserByEmail($youtubeUser['email']);
    if ($existingEmailUser) {
        $result = $userModel->linkSocialAccount(
            $existingEmailUser['id'],
            'youtube',
            $youtubeUser['id'],
            $youtubeUser['username'],
            $youtubeUser,
            $accessToken,
            $tokenData['refresh_token'] ?? null,
            isset($tokenData['expires_in']) ? date('Y-m-d H:i:s', time() + $tokenData['expires_in']) : null
        );

        if ($result['success']) {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $existingEmailUser['id'];
            $_SESSION['username'] = $existingEmailUser['username'];
            $_SESSION['account_type'] = 'youtube';
            $_SESSION['login_success'] = 'YouTube account linked and logged in successfully!';

            $userModel->updateLastLogin($existingEmailUser['id']);

            header('Location: ../index.php');
            exit;
        }

        $_SESSION['login_error'] = $result['message'];
        header('Location: ../login.php');
        exit;
    }

    $username = $userModel->generateUniqueUsername($youtubeUser['username']);
    $createResult = $userModel->createUser(
        $username,
        $youtubeUser['email'],
        null,
        'google',
        $youtubeUser['profile_image']
    );

    if (!$createResult['success']) {
        $_SESSION['login_error'] = $createResult['message'];
        header('Location: ../login.php');
        exit;
    }

    $userId = $createResult['user_id'];
    $linkResult = $userModel->linkSocialAccount(
        $userId,
        'youtube',
        $youtubeUser['id'],
        $youtubeUser['username'],
        $youtubeUser,
        $accessToken,
        $tokenData['refresh_token'] ?? null,
        isset($tokenData['expires_in']) ? date('Y-m-d H:i:s', time() + $tokenData['expires_in']) : null
    );

    if ($linkResult['success']) {
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['account_type'] = 'youtube';
        $_SESSION['login_success'] = 'Account created and logged in successfully!';
        header('Location: ../index.php');
        exit;
    }

    $_SESSION['login_error'] = 'Account created but failed to link YouTube: ' . $linkResult['message'];
    header('Location: ../login.php');
    exit;
} catch (Exception $e) {
    error_log('YouTube OAuth callback error: ' . $e->getMessage());
    $_SESSION['login_error'] = 'An error occurred during YouTube authentication. Please try again.';
    header('Location: ../login.php');
    exit;
}
?>
