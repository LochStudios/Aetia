<?php
// logout.php - Logout functionality for Aetia Talent Agency
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Start a new session for the logout message
session_start();
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to login page
header('Location: login.php');
exit;
?>
