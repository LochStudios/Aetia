<?php

class FormTokenManager {
    private static $sessionKey = 'form_tokens';
    
    // Set to false to disable token validation (for debugging)
    private static $enableValidation = true;
    
    /**
     * Generate a new form token
     * @param string $formName The name/identifier of the form
     * @return string The generated token
     */
    public static function generateToken($formName) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialize tokens array if not exists
        if (!isset($_SESSION[self::$sessionKey])) {
            $_SESSION[self::$sessionKey] = [];
        }
        
        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        $timestamp = time();
        
        // Store token with timestamp
        $_SESSION[self::$sessionKey][$formName] = [
            'token' => $token,
            'timestamp' => $timestamp,
            'used' => false
        ];
        
        // Clean up old tokens
        self::cleanupTokens();
        
        return $token;
    }
    
    /**
     * Validate and consume a form token
     * @param string $formName The name/identifier of the form
     * @param string $token The token to validate
     * @return bool True if token is valid and unused
     */
    public static function validateToken($formName, $token) {
        // If validation is disabled, always return true
        if (!self::$enableValidation) {
            return true;
        }
        
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists
        if (!isset($_SESSION[self::$sessionKey][$formName])) {
            return false;
        }
        
        $tokenData = $_SESSION[self::$sessionKey][$formName];
        
        // Check if token matches and hasn't been used
        if ($tokenData['token'] === $token && !$tokenData['used']) {
            // Check if token is not expired (1 hour)
            if ((time() - $tokenData['timestamp']) < 3600) {
                // Mark token as used
                $_SESSION[self::$sessionKey][$formName]['used'] = true;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clean up expired tokens
     */
    private static function cleanupTokens() {
        if (!isset($_SESSION[self::$sessionKey])) {
            return;
        }
        
        $currentTime = time();
        foreach ($_SESSION[self::$sessionKey] as $formName => $tokenData) {
            // Remove tokens older than 1 hour
            if (($currentTime - $tokenData['timestamp']) > 3600) {
                unset($_SESSION[self::$sessionKey][$formName]);
            }
        }
    }
    
    /**
     * Check if a form has been recently submitted (within last 5 seconds)
     * This helps prevent rapid successive submissions
     * @param string $formName The name/identifier of the form
     * @return bool True if form was recently submitted
     */
    public static function isRecentSubmission($formName) {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $recentKey = 'recent_submissions';
        if (!isset($_SESSION[$recentKey])) {
            $_SESSION[$recentKey] = [];
        }
        
        $currentTime = time();
        
        // Clean up old submissions (older than 10 seconds)
        foreach ($_SESSION[$recentKey] as $form => $timestamp) {
            if (($currentTime - $timestamp) > 10) {
                unset($_SESSION[$recentKey][$form]);
            }
        }
        
        // Check if this form was submitted recently (within 5 seconds)
        if (isset($_SESSION[$recentKey][$formName])) {
            if (($currentTime - $_SESSION[$recentKey][$formName]) < 5) {
                return true;
            }
        }
        
        // Record this submission
        $_SESSION[$recentKey][$formName] = $currentTime;
        return false;
    }
    
    /**
     * Generate a form token field for HTML forms
     * @param string $formName The name/identifier of the form
     * @return string HTML input field with the token
     */
    public static function getTokenField($formName) {
        try {
            $token = self::generateToken($formName);
            return '<input type="hidden" name="form_token" value="' . htmlspecialchars($token) . '">' . 
                   '<input type="hidden" name="form_name" value="' . htmlspecialchars($formName) . '">';
        } catch (Exception $e) {
            // Fallback: return empty fields with error logging
            error_log("FormTokenManager: Failed to generate token for form '$formName': " . $e->getMessage());
            return '<input type="hidden" name="form_token" value="">' . 
                   '<input type="hidden" name="form_name" value="">';
        }
    }
}
