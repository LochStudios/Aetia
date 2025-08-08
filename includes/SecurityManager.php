<?php
// includes/SecurityManager.php - Advanced security for Stripe integration

class SecurityManager {
    private $allowedIPs = [];
    private $rateLimitFile = '/home/aetiacom/tmp/aetia_rate_limits.json';
    private $auditLogFile = '/home/aetiacom/web-config/aetia_security.log';

    public function __construct() {
        // Load allowed IPs from secure config
        $this->loadSecurityConfig();
    }
    
    /**
     * Load security configuration from external file
     */
    private function loadSecurityConfig() {
        $configFile = '/home/aetiacom/web-config/security.php';
        if (file_exists($configFile)) {
            include $configFile;
            $this->allowedIPs = $allowedAdminIPs ?? [];
        }
    }
    
    /**
     * Verify admin access with multiple security checks
     */
    public function verifyAdminAccess($userId, $action = 'stripe_access') {
        // Check session validity
        if (!$this->isValidSession($userId)) {
            $this->logSecurityEvent('INVALID_SESSION', $userId, $action);
            return false;
        }
        
        // Check IP whitelist for sensitive operations
        if ($action === 'stripe_create_invoices' && !$this->isAllowedIP()) {
            $this->logSecurityEvent('IP_BLOCKED', $userId, $action);
            return false;
        }
        
        // Check rate limiting
        if (!$this->checkRateLimit($userId, $action)) {
            $this->logSecurityEvent('RATE_LIMITED', $userId, $action);
            return false;
        }
        
        // Verify CSRF token
        if (!$this->verifyCsrfToken()) {
            $this->logSecurityEvent('CSRF_VIOLATION', $userId, $action);
            return false;
        }
        
        $this->logSecurityEvent('ACCESS_GRANTED', $userId, $action);
        return true;
    }
    
    /**
     * Check if current IP is in allowed list
     */
    private function isAllowedIP() {
        if (empty($this->allowedIPs)) {
            return true; // If no restrictions set, allow all
        }
        
        $clientIP = $this->getRealClientIP();
        return in_array($clientIP, $this->allowedIPs);
    }
    
    /**
     * Get real client IP (handles proxies)
     */
    private function getRealClientIP() {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Validate session integrity
     */
    private function isValidSession($userId) {
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            return false;
        }
        
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
            return false;
        }
        
        // Check session timeout (4 hours for admin operations)
        if (!isset($_SESSION['last_activity']) || 
            (time() - $_SESSION['last_activity']) > 14400) {
            return false;
        }
        
        // Verify session fingerprint
        $currentFingerprint = $this->generateSessionFingerprint();
        if (!isset($_SESSION['fingerprint']) || $_SESSION['fingerprint'] !== $currentFingerprint) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate session fingerprint to prevent session hijacking
     */
    private function generateSessionFingerprint() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        return hash('sha256', $userAgent . $acceptLanguage . $acceptEncoding . $_SERVER['REMOTE_ADDR']);
    }
    
    /**
     * Initialize session security
     */
    public function initializeSecureSession() {
        $_SESSION['fingerprint'] = $this->generateSessionFingerprint();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    /**
     * Rate limiting for API calls
     */
    private function checkRateLimit($userId, $action) {
        $limits = [
            'stripe_create_invoices' => ['count' => 5, 'window' => 3600], // 5 per hour
            'stripe_test_connection' => ['count' => 10, 'window' => 3600], // 10 per hour
            'stripe_access' => ['count' => 50, 'window' => 3600] // 50 per hour
        ];
        
        $limit = $limits[$action] ?? ['count' => 20, 'window' => 3600];
        
        $rateLimits = $this->loadRateLimits();
        $key = $userId . '_' . $action;
        $now = time();
        
        // Clean old entries
        if (isset($rateLimits[$key])) {
            $rateLimits[$key] = array_filter($rateLimits[$key], function($timestamp) use ($now, $limit) {
                return ($now - $timestamp) < $limit['window'];
            });
        } else {
            $rateLimits[$key] = [];
        }
        
        // Check if limit exceeded
        if (count($rateLimits[$key]) >= $limit['count']) {
            return false;
        }
        
        // Add current request
        $rateLimits[$key][] = $now;
        $this->saveRateLimits($rateLimits);
        
        return true;
    }
    
    /**
     * Load rate limit data
     */
    private function loadRateLimits() {
        if (!file_exists($this->rateLimitFile)) {
            return [];
        }
        
        $data = file_get_contents($this->rateLimitFile);
        return json_decode($data, true) ?: [];
    }
    
    /**
     * Save rate limit data
     */
    private function saveRateLimits($rateLimits) {
        file_put_contents($this->rateLimitFile, json_encode($rateLimits), LOCK_EX);
    }
    
    /**
     * Verify CSRF token
     */
    private function verifyCsrfToken() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true; // Only check POST requests
        }
        
        $submittedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        
        if (empty($submittedToken) || empty($sessionToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $submittedToken);
    }
    
    /**
     * Get CSRF token for forms
     */
    public function getCsrfToken() {
        return $_SESSION['csrf_token'] ?? '';
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($event, $userId, $action) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getRealClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] %s - User: %s, Action: %s, IP: %s, UA: %s\n",
            $timestamp,
            $event,
            $userId,
            $action,
            $ip,
            $userAgent
        );
        
        error_log($logEntry, 3, $this->auditLogFile);
        
        // Also log to system error log for critical events
        if (in_array($event, ['CSRF_VIOLATION', 'IP_BLOCKED', 'RATE_LIMITED'])) {
            error_log("AETIA SECURITY ALERT: $logEntry");
        }
    }
    
    /**
     * Validate and sanitize billing data before Stripe operations
     */
    public function validateBillingData($billData) {
        if (!is_array($billData) || empty($billData)) {
            throw new SecurityException('Invalid billing data structure');
        }
        
        foreach ($billData as $client) {
            // Validate required fields
            $requiredFields = ['user_id', 'email', 'total_fee', 'username'];
            foreach ($requiredFields as $field) {
                if (!isset($client[$field]) || empty($client[$field])) {
                    throw new SecurityException("Missing required field: $field");
                }
            }
            
            // Validate data types and ranges
            if (!is_numeric($client['user_id']) || $client['user_id'] <= 0) {
                throw new SecurityException('Invalid user_id');
            }
            
            if (!filter_var($client['email'], FILTER_VALIDATE_EMAIL)) {
                throw new SecurityException('Invalid email format');
            }
            
            if (!is_numeric($client['total_fee']) || $client['total_fee'] < 0 || $client['total_fee'] > 10000) {
                throw new SecurityException('Invalid fee amount');
            }
            
            // Sanitize string fields
            $client['username'] = preg_replace('/[^a-zA-Z0-9_.-]/', '', $client['username']);
            $client['first_name'] = preg_replace('/[^a-zA-Z\s-]/', '', $client['first_name'] ?? '');
            $client['last_name'] = preg_replace('/[^a-zA-Z\s-]/', '', $client['last_name'] ?? '');
        }
        
        return true;
    }
    
    /**
     * Generate secure operation token
     */
    public function generateOperationToken($action, $expiry = 1800) {
        $payload = [
            'action' => $action,
            'user_id' => $_SESSION['user_id'],
            'timestamp' => time(),
            'expiry' => time() + $expiry,
            'nonce' => bin2hex(random_bytes(16))
        ];
        
        $token = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $token, $this->getSecretKey());
        
        return $token . '.' . $signature;
    }
    
    /**
     * Verify operation token
     */
    public function verifyOperationToken($token, $action) {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($payload, $signature) = $parts;
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payload, $this->getSecretKey());
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }
        
        // Decode and validate payload
        $data = json_decode(base64_decode($payload), true);
        if (!$data) {
            return false;
        }
        
        // Check expiry
        if (time() > $data['expiry']) {
            return false;
        }
        
        // Check action and user
        if ($data['action'] !== $action || $data['user_id'] !== $_SESSION['user_id']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get secret key for token signing
     */
    private function getSecretKey() {
        // Use a combination of factors to create a secret key
        $configFile = '/home/aetiacom/web-config/security.php';
        if (file_exists($configFile)) {
            include $configFile;
            $secretKey = $tokenSigningKey ?? $secretKey;
        }
        
        return $secretKey;
    }
}
