<?php
// includes/HtmlHelper.php - Helper functions for HTML output with proper UTF-8 support

class HtmlHelper {
    
    /**
     * Escape HTML special characters with proper UTF-8 support
     * @param string $string The string to escape
     * @param int $flags The flags for htmlspecialchars (default: ENT_QUOTES)
     * @param string $encoding The encoding (default: UTF-8)
     * @return string The escaped string
     */
    public static function escape($string, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
        return htmlspecialchars($string ?? '', $flags, $encoding);
    }
    
    /**
     * Escape and display a string safely with UTF-8 support
     * Alias for escape() for convenience
     * @param string $string The string to escape and display
     * @return string The escaped string
     */
    public static function e($string) {
        return self::escape($string);
    }
    
    /**
     * Process and display message text with proper emoji support
     * @param string $text The message text to process
     * @return string The processed text with clickable links and proper emoji display
     */
    public static function messageText($text) {
        return LinkConverter::processMessageText($text);
    }
}
?>
