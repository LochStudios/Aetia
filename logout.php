<?php
// logout.php - Logout functionality for Aetia Talent Agency
require_once __DIR__ . '/includes/session_bootstrap.php';
session_start();

// Destroy all session data
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        !empty($params['secure']), !empty($params['httponly'])
    );
}
session_unset();
session_destroy();

// Start a new session for the logout message
session_start();
session_regenerate_id(true);
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to login page
header('Location: login.php');
exit;
?>

