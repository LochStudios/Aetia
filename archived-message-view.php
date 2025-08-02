<?php
// archived-message-view.php - Redirect to main archived messages page with message ID
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($messageId) {
    // Redirect to the main archived messages page with the message ID
    header('Location: archived-messages.php?id=' . $messageId);
} else {
    // Redirect to archived messages list
    header('Location: archived-messages.php');
}
exit;
?>
