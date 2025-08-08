<?php
// includes/SecurityException.php - Custom security exception

class SecurityException extends Exception {
    public function __construct($message = "Security violation detected", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        
        // Log security exceptions immediately
        error_log("SECURITY EXCEPTION: " . $message . " - " . $this->getTraceAsString());
    }
}
