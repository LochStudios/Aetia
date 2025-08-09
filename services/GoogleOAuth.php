<?php
// services/GoogleOAuth.php - Google OAuth integration for Aetia Talent Agency

require_once '/home/aetiacom/web-config/config.php';

class GoogleOAuth {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $linkRedirectUri;
    private $scopes;
    private $baseUrl = 'https://accounts.google.com/o/oauth2/v2';
    private $apiUrl = 'https://www.googleapis.com/oauth2/v2';
    
    public function __construct() {
        try {
            $googleConfig = Config::load('google');
            
            $this->clientId = $googleConfig['client_id'];
            $this->clientSecret = $googleConfig['client_secret'];
            $this->redirectUri = $googleConfig['redirect_uri'];
            $this->linkRedirectUri = $googleConfig['link_redirect_uri'] ?? $googleConfig['redirect_uri'];
            $this->scopes = $googleConfig['scopes'] ?? [
                'openid',
                'email'
            ];
            
            // Validate required configuration
            if (empty($this->clientId) || $this->clientId === 'YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com') {
                throw new Exception('Google Client ID not configured. Please update web-config/google.php');
            }
            
            if (empty($this->clientSecret) || $this->clientSecret === 'YOUR_GOOGLE_CLIENT_SECRET_HERE') {
                throw new Exception('Google Client Secret not configured. Please update web-config/google.php');
            }
            
        } catch (Exception $e) {
            error_log('GoogleOAuth configuration error: ' . $e->getMessage());
            throw new Exception('Google OAuth configuration error. Please check your configuration files.');
        }
    }
    
    // Get authorization URL
    public function getAuthorizationUrl($state = null) {
        // Generate a secure random state if not provided
        if ($state === null) {
            $state = bin2hex(random_bytes(32)); // 64 character hex string
        }
        
        // Store state in session for later validation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['google_oauth_state'] = $state;
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return $this->baseUrl . '/auth?' . http_build_query($params);
    }
    
    // Get authorization URL for linking additional account
    public function getLinkAuthorizationUrl($state = null) {
        // Generate a secure random state if not provided
        if ($state === null) {
            $state = bin2hex(random_bytes(32)); // 64 character hex string
        }
        
        // Store state in session for later validation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['google_link_oauth_state'] = $state;
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->linkRedirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return $this->baseUrl . '/auth?' . http_build_query($params);
    }
    
    // Exchange authorization code for access token
    public function getAccessToken($code, $state = null) {
        // Validate state parameter
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $isLinking = false;
        if (isset($_SESSION['google_link_oauth_state']) && $state === $_SESSION['google_link_oauth_state']) {
            $isLinking = true;
            unset($_SESSION['google_link_oauth_state']);
        } elseif (isset($_SESSION['google_oauth_state']) && $state === $_SESSION['google_oauth_state']) {
            unset($_SESSION['google_oauth_state']);
        } else {
            throw new Exception('Invalid state parameter');
        }
        
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log('Google token exchange failed: ' . $response);
            throw new Exception('Failed to exchange authorization code for access token');
        }
        
        $tokenData = json_decode($response, true);
        
        if (isset($tokenData['error'])) {
            error_log('Google token exchange error: ' . $tokenData['error']);
            throw new Exception('Token exchange failed: ' . $tokenData['error']);
        }
        
        return [
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_in' => $tokenData['expires_in'] ?? 3600,
            'is_linking' => $isLinking
        ];
    }
    
    // Get user information from Google
    public function getUserInfo($accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/userinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log('Google user info request failed: ' . $response);
            throw new Exception('Failed to fetch user information');
        }
        
        $userData = json_decode($response, true);
        
        if (!isset($userData['email'])) {
            throw new Exception('Email not provided by Google OAuth');
        }
        
        return [
            'id' => $userData['sub'] ?? $userData['id'],
            'username' => $userData['name'] ?? $userData['email'],
            'email' => $userData['email'],
            'profile_image' => $userData['picture'] ?? null,
            'verified' => $userData['email_verified'] ?? false
        ];
    }
    
    // Refresh access token
    public function refreshAccessToken($refreshToken) {
        $params = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log('Google token refresh failed: ' . $response);
            throw new Exception('Failed to refresh access token');
        }
        
        $tokenData = json_decode($response, true);
        
        if (isset($tokenData['error'])) {
            error_log('Google token refresh error: ' . $tokenData['error']);
            throw new Exception('Token refresh failed: ' . $tokenData['error']);
        }
        
        return [
            'access_token' => $tokenData['access_token'],
            'expires_in' => $tokenData['expires_in'] ?? 3600
        ];
    }
}
?>
