<?php
// services/GoogleOAuth.php - Google OAuth integration for Aetia Talent Agency

require_once '/home/aetiacom/web-config/config.php';
require_once '/home/aetiacom/vendors/Google/vendor/autoload.php';

class GoogleOAuth {
    private $client;
    private $redirectUri;
    private $linkRedirectUri;
    
    public function __construct() {
        try {
            $googleConfig = Config::load('google');
            
            // Initialize Google Client
            $this->client = new Google\Client();
            
            // Try to use credentials JSON file first, fallback to config file
            $credentialsJsonPath = '/home/aetiacom/web-config/client_secret_794642031392-90482997p725d6ckrsk6ie35d4u45uvk.apps.googleusercontent.com.json';
            
            if (file_exists($credentialsJsonPath)) {
                try {
                    $this->client->setAuthConfig($credentialsJsonPath);
                    error_log('Google OAuth: Using credentials JSON file');
                } catch (Exception $e) {
                    error_log('Google OAuth: Failed to load JSON credentials, falling back to config file: ' . $e->getMessage());
                    // Fallback to config file method
                    $this->setConfigCredentials($googleConfig);
                }
            } else {
                error_log('Google OAuth: JSON credentials file not found, using config file');
                // Fallback to config file method
                $this->setConfigCredentials($googleConfig);
            }
            
            $this->client->setScopes($googleConfig['scopes'] ?? ['openid', 'email']);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');
            
            $this->redirectUri = $googleConfig['redirect_uri'];
            $this->linkRedirectUri = $googleConfig['link_redirect_uri'] ?? $googleConfig['redirect_uri'];
            
            // Validate required configuration
            if (empty($googleConfig['redirect_uri'])) {
                throw new Exception('Google redirect URI not configured. Please update web-config/google.php');
            }
            
        } catch (Exception $e) {
            error_log('GoogleOAuth configuration error: ' . $e->getMessage());
            throw new Exception('Google OAuth configuration error. Please check your configuration files.');
        }
    }
    
    // Helper method to set credentials from config file
    private function setConfigCredentials($googleConfig) {
        if (empty($googleConfig['client_id']) || $googleConfig['client_id'] === 'YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com') {
            throw new Exception('Google Client ID not configured. Please update web-config/google.php');
        }
        
        if (empty($googleConfig['client_secret']) || $googleConfig['client_secret'] === 'YOUR_GOOGLE_CLIENT_SECRET_HERE') {
            throw new Exception('Google Client Secret not configured. Please update web-config/google.php');
        }
        
        $this->client->setClientId($googleConfig['client_id']);
        $this->client->setClientSecret($googleConfig['client_secret']);
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
        
        // Set redirect URI and state
        $this->client->setRedirectUri($this->redirectUri);
        $this->client->setState($state);
        
        return $this->client->createAuthUrl();
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
        
        // Set link redirect URI and state
        $this->client->setRedirectUri($this->linkRedirectUri);
        $this->client->setState($state);
        
        return $this->client->createAuthUrl();
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
            $this->client->setRedirectUri($this->linkRedirectUri);
            unset($_SESSION['google_link_oauth_state']);
        } elseif (isset($_SESSION['google_oauth_state']) && $state === $_SESSION['google_oauth_state']) {
            $this->client->setRedirectUri($this->redirectUri);
            unset($_SESSION['google_oauth_state']);
        } else {
            throw new Exception('Invalid state parameter');
        }
        
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($accessToken['error'])) {
                error_log('Google token exchange error: ' . $accessToken['error']);
                throw new Exception('Failed to exchange authorization code');
            }
            
            return [
                'access_token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'] ?? null,
                'expires_in' => $accessToken['expires_in'] ?? 3600,
                'is_linking' => $isLinking
            ];
        } catch (Exception $e) {
            error_log('Google token exchange failed: ' . $e->getMessage());
            throw new Exception('Failed to exchange authorization code');
        }
    }
    
    // Get user information from Google
    public function getUserInfo($accessToken) {
        try {
            // Set the access token
            $this->client->setAccessToken($accessToken);
            
            // Create OAuth2 service
            $oauth2 = new Google\Service\Oauth2($this->client);
            
            // Get user info
            $userInfo = $oauth2->userinfo->get();
            
            if (!$userInfo->getEmail()) {
                throw new Exception('Email not provided by Google OAuth');
            }
            
            return [
                'id' => $userInfo->getId(),
                'username' => $userInfo->getName() ?? $userInfo->getEmail(),
                'email' => $userInfo->getEmail(),
                'profile_image' => $userInfo->getPicture(),
                'verified' => $userInfo->getVerifiedEmail()
            ];
        } catch (Exception $e) {
            error_log('Google user info request failed: ' . $e->getMessage());
            throw new Exception('Failed to fetch user information');
        }
    }
    
    // Refresh access token
    public function refreshAccessToken($refreshToken) {
        try {
            $newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            
            if (isset($newAccessToken['error'])) {
                error_log('Google token refresh error: ' . $newAccessToken['error']);
                throw new Exception('Token refresh failed: ' . $newAccessToken['error']);
            }
            
            return [
                'access_token' => $newAccessToken['access_token'],
                'expires_in' => $newAccessToken['expires_in'] ?? 3600
            ];
        } catch (Exception $e) {
            error_log('Google token refresh failed: ' . $e->getMessage());
            throw new Exception('Failed to refresh access token');
        }
    }
}
?>
