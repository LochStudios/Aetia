<?php
// services/SmsService.php - SMS service using Twilio API

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../includes/SecurityException.php';

class SmsService {
    private $db;
    private $mysqli;
    private $config;
    private $securityManager;
    
    public function __construct() {
        // Initialize security first
        $this->securityManager = new SecurityManager();
        
        // Verify server-only access for SMS operations
        $this->verifyServerAccess();
        
        // Initialize database connection
        $this->db = new Database();
        $this->mysqli = $this->db->getConnection();
        
        // Load configuration
        $this->loadConfig();
    }
    
    /**
     * Verify that SMS requests are coming from the server itself only
     * This prevents external abuse of the SMS functionality
     */
    private function verifyServerAccess() {
        $allowedIPs = [
            '127.0.0.1',
            '::1',
            'localhost',
            '43.250.142.45'
        ];
        
        // Get server IP if available
        if (isset($_SERVER['SERVER_ADDR'])) {
            $allowedIPs[] = $_SERVER['SERVER_ADDR'];
        }
        
        $clientIP = $this->getRealClientIP();
        
        // For security, only allow local server requests or your specific server IP
        if (!in_array($clientIP, $allowedIPs) && !$this->isLocalhost($clientIP)) {
            $this->securityManager->logSecurityEventPublic(
                'SMS_ACCESS_DENIED_EXTERNAL_IP', 
                $_SESSION['user_id'] ?? 'unknown', 
                'sms_service_access',
                ['client_ip' => $clientIP]
            );
            throw new SecurityException("SMS service access denied: External requests not allowed");
        }
    }
    
    /**
     * Get real client IP address
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
     * Check if IP is localhost/local network
     */
    private function isLocalhost($ip) {
        $localRanges = [
            '127.', // 127.0.0.0/8
            '192.168.', // 192.168.0.0/16
            '10.', // 10.0.0.0/8
            '172.16.', '172.17.', '172.18.', '172.19.', // 172.16.0.0/12
            '172.20.', '172.21.', '172.22.', '172.23.',
            '172.24.', '172.25.', '172.26.', '172.27.',
            '172.28.', '172.29.', '172.30.', '172.31.'
        ];
        
        foreach ($localRanges as $range) {
            if (strpos($ip, $range) === 0) {
                return true;
            }
        }
        
        return $ip === 'localhost' || $ip === '::1';
    }
    
    /**
     * Load SMS configuration from external config file
     */
    private function loadConfig() {
        try {
            $configFile = '/home/aetiacom/web-config/sms.php';
            
            if (!file_exists($configFile)) {
                throw new Exception("SMS configuration file not found at: {$configFile}. Please ensure the sms.php configuration file exists.");
            }
            
            // Load the configuration array
            $config = include $configFile;
            
            // Validate that we have a proper array structure
            if (!is_array($config)) {
                throw new Exception("SMS configuration file must return an array.");
            }
            
            // Validate required configuration for Twilio
            if (!isset($config['twilio'])) {
                throw new Exception("Twilio configuration not found in SMS configuration file.");
            }
            
            $this->config = $config['twilio'];
            
            // Validate required fields
            $this->validateConfig();
            
        } catch (Exception $e) {
            error_log('SMS Service Config Error: ' . $e->getMessage());
            throw new Exception('SMS service configuration error: ' . $e->getMessage());
        }
    }
    
    /**
     * Validate Twilio configuration
     */
    private function validateConfig() {
        $required = ['account_sid', 'auth_token'];
        
        foreach ($required as $field) {
            if (empty($this->config[$field])) {
                throw new Exception("Required Twilio configuration field '{$field}' is missing.");
            }
        }
    }
    
    /**
     * Send SMS message to a phone number with security validation
     * 
     * @param string $to Phone number in international format (e.g., +1234567890)
     * @param string $message Message content
     * @param int|null $userId Optional user ID for logging
     * @param string $purpose Purpose of SMS (verification, notification, etc.)
     * @return array Result array with success status and message
     */
    public function sendSms($to, $message, $userId = null, $purpose = 'notification') {
        try {
            // Security validation first
            $this->validateSmsRequest($userId, $purpose);
            
            // Validate phone number format
            if (!$this->validatePhoneNumber($to)) {
                $this->logSecurityEvent('SMS_INVALID_PHONE', $userId, $purpose, ['phone' => $to]);
                return [
                    'success' => false,
                    'message' => 'Invalid phone number or unsupported country. Currently we only support USA (+1) and Australia (+61) phone numbers. Support for more countries coming soon!'
                ];
            }
            
            // Rate limiting check
            if (!$this->checkSmsRateLimit($userId, $purpose)) {
                $this->logSecurityEvent('SMS_RATE_LIMITED', $userId, $purpose);
                return [
                    'success' => false,
                    'message' => 'SMS rate limit exceeded. Please wait before sending another message.'
                ];
            }
            
            // Validate message content
            if (empty(trim($message))) {
                return [
                    'success' => false,
                    'message' => 'Message content cannot be empty.'
                ];
            }
            
            // Sanitize message content
            $message = $this->sanitizeMessage($message);
            
            // Check message length (most SMS providers have 160 character limit for single SMS)
            if (strlen($message) > 1600) { // Allow for longer messages that will be split
                return [
                    'success' => false,
                    'message' => 'Message is too long. Maximum 1600 characters allowed.'
                ];
            }
            
            // Send SMS using Twilio API
            $result = $this->sendSmsWithTwilio($to, $message);
            
            // Log the SMS attempt with security context
            $this->logSmsAttempt($to, $message, $result, $userId, $purpose);
            
            // Log successful send for security audit
            if ($result['success']) {
                $this->logSecurityEvent('SMS_SENT_SUCCESS', $userId, $purpose, [
                    'phone' => substr($to, 0, 4) . '****', // Partial phone for privacy
                    'message_length' => strlen($message)
                ]);
            }
            
            return $result;
            
        } catch (SecurityException $e) {
            // Security exceptions should not be retried
            $this->logSmsAttempt($to, $message, [
                'success' => false,
                'message' => 'Security violation',
                'error' => $e->getMessage()
            ], $userId, $purpose);
            
            return [
                'success' => false,
                'message' => 'SMS request denied for security reasons.'
            ];
        } catch (Exception $e) {
            error_log('SMS Service Error: ' . $e->getMessage());
            
            // Log failed attempt
            $this->logSmsAttempt($to, $message, [
                'success' => false,
                'message' => 'SMS service error',
                'error' => $e->getMessage()
            ], $userId, $purpose);
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS. Please try again later.'
            ];
        }
    }
    
    /**
     * Send SMS using Twilio API
     */
    private function sendSmsWithTwilio($to, $message) {
        $accountSid = $this->config['account_sid'];
        $authToken = $this->config['auth_token'];
        
        // Get appropriate from number based on destination country
        $fromNumber = $this->getFromNumberForDestination($to);
        
        if (empty($fromNumber)) {
            $countryCode = $this->getCountryCodeFromPhone($to);
            $supportedCountries = array_keys(self::getSupportedCountries());
            
            return [
                'success' => false,
                'message' => "SMS service not configured for {$countryCode} numbers. Please add a Twilio phone number for this country in your SMS configuration. Supported countries: " . implode(', ', $supportedCountries) . ". Visit https://console.twilio.com/us1/develop/phone-numbers/manage/incoming to get phone numbers."
            ];
        }
        
        // Prepare API request
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
        
        $postData = [
            'From' => $fromNumber,
            'To' => $to,
            'Body' => $message
        ];
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURL_HTTPAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $accountSid . ':' . $authToken);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception("cURL error: {$curlError}");
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'provider_response' => $responseData,
                'message_id' => $responseData['sid'] ?? null
            ];
        } else {
            $errorMessage = $responseData['message'] ?? 'Unknown error';
            return [
                'success' => false,
                'message' => "Twilio API error: {$errorMessage}",
                'provider_response' => $responseData
            ];
        }
    }
    
    /**
     * Get appropriate from number based on destination phone number
     */
    private function getFromNumberForDestination($phoneNumber) {
        $countryCode = $this->getCountryCodeFromPhone($phoneNumber);
        
        // Check for country-specific from numbers first
        if (isset($this->config['from_numbers']) && is_array($this->config['from_numbers'])) {
            if (isset($this->config['from_numbers'][$countryCode])) {
                return $this->config['from_numbers'][$countryCode];
            }
        }
        
        // Fall back to legacy single from_number
        if (isset($this->config['from_number']) && !empty($this->config['from_number'])) {
            return $this->config['from_number'];
        }
        
        return null;
    }
    
    /**
     * Extract country code from phone number
     */
    private function getCountryCodeFromPhone($phoneNumber) {
        // Remove all non-numeric characters except + at the beginning
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        if (preg_match('/^\+1\d{10}$/', $cleaned)) {
            return '+1'; // USA
        } elseif (preg_match('/^\+61\d{8,9}$/', $cleaned)) {
            return '+61'; // Australia
        }
        
        // Extract first 1-3 digits after +
        if (preg_match('/^\+(\d{1,3})/', $cleaned, $matches)) {
            return '+' . $matches[1];
        }
        
        return 'unknown';
    }
    
    /**
     * Validate phone number format - only supports USA (+1) and Australia (+61)
     */
    private function validatePhoneNumber($phoneNumber) {
        // Remove all non-numeric characters except + at the beginning
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // Must start with + and have 10-15 digits
        if (!preg_match('/^\+\d{10,15}$/', $cleaned)) {
            return false;
        }
        
        // Check if it's a supported country code
        // USA: +1 followed by 10 digits (total 12 characters)
        // Australia: +61 followed by 8-9 digits (total 11-12 characters)
        if (preg_match('/^\+1\d{10}$/', $cleaned)) {
            return true; // USA number
        } elseif (preg_match('/^\+61\d{8,9}$/', $cleaned)) {
            return true; // Australia number
        }
        
        return false; // Unsupported country code
    }
    
    /**
     * Get list of supported countries for SMS
     */
    public static function getSupportedCountries() {
        return [
            '+1' => [
                'code' => '+1',
                'name' => 'United States',
                'flag' => '🇺🇸',
                'pattern' => '/^\+1\d{10}$/'
            ],
            '+61' => [
                'code' => '+61',
                'name' => 'Australia', 
                'flag' => '🇦🇺',
                'pattern' => '/^\+61\d{8,9}$/'
            ]
        ];
    }
    
    /**
     * Validate SMS request for security
     */
    private function validateSmsRequest($userId, $purpose) {
        // Ensure user session exists for verification SMS
        if ($purpose === 'verification' && (!$userId || !isset($_SESSION['user_id']))) {
            throw new SecurityException('Invalid session for SMS verification request');
        }
        
        // Validate user ID matches session for security
        if ($userId && isset($_SESSION['user_id']) && $_SESSION['user_id'] != $userId) {
            throw new SecurityException('User ID mismatch in SMS request');
        }
        
        // Additional validation for test purposes
        if ($purpose === 'test') {
            if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
                throw new SecurityException('Admin authentication required for SMS testing');
            }
        }
    }
    
    /**
     * Check SMS rate limiting
     */
    private function checkSmsRateLimit($userId, $purpose) {
        $limits = [
            'verification' => ['count' => 3, 'window' => 3600], // 3 verification SMS per hour
            'notification' => ['count' => 10, 'window' => 3600], // 10 notifications per hour
            'test' => ['count' => 5, 'window' => 3600] // 5 test SMS per hour
        ];
        
        $limit = $limits[$purpose] ?? ['count' => 5, 'window' => 3600];
        
        // Use existing rate limiting from SecurityManager approach
        $rateLimitFile = '/home/aetiacom/tmp/aetia_sms_rate_limits.json';
        $rateLimits = [];
        
        if (file_exists($rateLimitFile)) {
            $data = file_get_contents($rateLimitFile);
            $rateLimits = json_decode($data, true) ?: [];
        }
        
        $key = ($userId ?: 'anonymous') . '_sms_' . $purpose;
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
        file_put_contents($rateLimitFile, json_encode($rateLimits), LOCK_EX);
        
        return true;
    }
    
    /**
     * Sanitize message content
     */
    private function sanitizeMessage($message) {
        // Remove potentially harmful content
        $message = strip_tags($message);
        $message = trim($message);
        
        // Remove excessive whitespace
        $message = preg_replace('/\s+/', ' ', $message);
        
        // Ensure message is UTF-8
        if (!mb_check_encoding($message, 'UTF-8')) {
            $message = mb_convert_encoding($message, 'UTF-8', 'auto');
        }
        
        return $message;
    }
    
    /**
     * Log security events related to SMS
     */
    private function logSecurityEvent($event, $userId, $purpose, $additional = []) {
        $logData = [
            'event' => $event,
            'user_id' => $userId,
            'purpose' => $purpose,
            'ip' => $this->getRealClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'additional' => $additional
        ];
        
        error_log("SMS_SECURITY_EVENT: " . json_encode($logData));
        
        // Critical events should be logged to security audit file
        $criticalEvents = ['SMS_ACCESS_DENIED_EXTERNAL_IP', 'SMS_RATE_LIMITED', 'SMS_INVALID_PHONE'];
        if (in_array($event, $criticalEvents)) {
            $auditFile = '/home/aetiacom/web-config/aetia_sms_security.log';
            error_log(json_encode($logData) . "\n", 3, $auditFile);
        }
    }
    
    /**
     * Log SMS attempt to database with security context
     */
    private function logSmsAttempt($to, $message, $result, $userId = null, $purpose = 'notification') {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                INSERT INTO sms_logs (
                    user_id, 
                    to_number, 
                    message_content, 
                    provider, 
                    success, 
                    response_message, 
                    provider_message_id,
                    purpose,
                    client_ip,
                    sent_at
                ) VALUES (?, ?, ?, 'twilio', ?, ?, ?, ?, ?, NOW())
            ");
            
            $success = $result['success'] ? 1 : 0;
            $responseMessage = $result['message'] ?? '';
            $providerMessageId = $result['message_id'] ?? null;
            $clientIP = $this->getRealClientIP();
            
            // Truncate message content for logging (privacy)
            $logMessage = strlen($message) > 100 ? substr($message, 0, 100) . '...' : $message;
            
            $stmt->bind_param(
                "ississss",
                $userId,
                $to,
                $logMessage,
                $success,
                $responseMessage,
                $providerMessageId,
                $purpose,
                $clientIP
            );
            
            $stmt->execute();
            $stmt->close();
            
        } catch (Exception $e) {
            error_log('Failed to log SMS attempt: ' . $e->getMessage());
        }
    }
    
    /**
     * Ensure database connection is active
     */
    private function ensureConnection() {
        if (!$this->mysqli || $this->mysqli->ping() === false) {
            $this->db = new Database();
            $this->mysqli = $this->db->getConnection();
        }
    }
    
    /**
     * Get SMS logs for a user
     * 
     * @param int $userId User ID
     * @param int $limit Number of logs to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of SMS logs
     */
    public function getUserSmsLogs($userId, $limit = 50, $offset = 0) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    to_number,
                    message_content,
                    provider,
                    success,
                    response_message,
                    provider_message_id,
                    sent_at
                FROM sms_logs 
                WHERE user_id = ? 
                ORDER BY sent_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $stmt->bind_param("iii", $userId, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            
            $stmt->close();
            return $logs;
            
        } catch (Exception $e) {
            error_log('Failed to get SMS logs: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get SMS statistics for a user
     * 
     * @param int $userId User ID
     * @return array SMS statistics
     */
    public function getUserSmsStats($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    COUNT(*) as total_sent,
                    SUM(success) as successful_sent,
                    COUNT(*) - SUM(success) as failed_sent
                FROM sms_logs 
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            return [
                'total_sent' => (int)$stats['total_sent'],
                'successful_sent' => (int)$stats['successful_sent'], 
                'failed_sent' => (int)$stats['failed_sent']
            ];
            
        } catch (Exception $e) {
            error_log('Failed to get SMS stats: ' . $e->getMessage());
            return [
                'total_sent' => 0,
                'successful_sent' => 0,
                'failed_sent' => 0
            ];
        }
    }
}
