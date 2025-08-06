<?php
// includes/LinkConverter.php - Utility for converting URLs to clickable links

class LinkConverter {
    
    /**
     * Convert URLs in text to clickable links that open in new tabs
     * 
     * @param string $text The text to process
     * @return string Text with URLs converted to links
     */
    public static function convertLinksToClickable($text) {
        // First escape HTML to prevent XSS, but preserve line breaks
        $text = htmlspecialchars($text);
        
        // Pattern to match URLs (http, https, www)
        $urlPattern = '/\b(?:(?:https?:\/\/|www\.)[^\s<>"{}|\\^`\[\]]*)/i';
        
        // Replace URLs with clickable links
        $text = preg_replace_callback($urlPattern, function($matches) {
            $url = $matches[0];
            $displayUrl = $url;
            
            // Add https:// if URL starts with www.
            $linkUrl = (strpos($url, 'www.') === 0) ? 'https://' . $url : $url;
            
            // Truncate display URL if it's too long
            if (strlen($displayUrl) > 60) {
                $displayUrl = substr($displayUrl, 0, 57) . '...';
            }
            
            return '<a href="' . htmlspecialchars($linkUrl) . '" target="_blank" rel="noopener noreferrer" class="message-link">' . htmlspecialchars($displayUrl) . '</a>';
        }, $text);
        
        // Convert line breaks to <br> tags
        $text = nl2br($text);
        
        return $text;
    }
    
    /**
     * Convert email addresses to clickable mailto links
     * 
     * @param string $text The text to process
     * @return string Text with email addresses converted to mailto links
     */
    public static function convertEmailsToClickable($text) {
        // Pattern to match email addresses
        $emailPattern = '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/';
        
        return preg_replace_callback($emailPattern, function($matches) {
            $email = $matches[0];
            return '<a href="mailto:' . htmlspecialchars($email) . '" class="message-email-link">' . htmlspecialchars($email) . '</a>';
        }, $text);
    }
    
    /**
     * Process message text to convert both URLs and emails to clickable links
     * 
     * @param string $text The message text to process
     * @return string Processed text with clickable links
     */
    public static function processMessageText($text) {
        if (empty($text)) {
            return '';
        }
        
        // Convert URLs to clickable links (this also handles HTML escaping and nl2br)
        $text = self::convertLinksToClickable($text);
        
        // Convert email addresses to clickable links
        $text = self::convertEmailsToClickable($text);
        
        return $text;
    }
}
?>
