<?php
// Get user's timezone (fallback to Australia/Sydney)
function getUserTimezone() {
    // Check session first
    if (isset($_SESSION['user_timezone'])) {
        return $_SESSION['user_timezone'];
    }
    
    // Check cookie as fallback
    if (isset($_COOKIE['user_timezone'])) {
        $_SESSION['user_timezone'] = $_COOKIE['user_timezone'];
        return $_COOKIE['user_timezone'];
    }
    
    // Default to Australia/Sydney
    return 'Australia/Sydney';
}

// Format date/time for user's timezone
function formatDateForUser($dateString, $format = 'M j, Y g:i A') {
    try {
        $userTimezone = getUserTimezone();
        // Handle null or empty date strings
        if (empty($dateString)) {
            $dateString = 'now';
        }
        $date = new DateTime($dateString, new DateTimeZone('Australia/Sydney')); // Server timezone
        $date->setTimezone(new DateTimeZone($userTimezone)); // Convert to user's timezone
        return $date->format($format);
    } catch (Exception $e) {
        // Fallback to original formatting if timezone conversion fails
        if (empty($dateString)) {
            return date($format);
        }
        return date($format, strtotime($dateString));
    }
}
?>
