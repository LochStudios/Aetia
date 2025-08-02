<?php
// services/TwitchOAuth.php - Twitch OAuth integration for Aetia Talant Agency

require_once '/home/aetiacom/web-config/config.php';

class TwitchOAuth {
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $scopes;
    private $baseUrl = 'https://id.twitch.tv/oauth2';
    private $apiUrl = 'https://api.twitch.tv/helix';
    
    public function __construct() {
        try {
            $twitchConfig = Config::load('twitch');
            
            $this->clientId = $twitchConfig['client_id'];
            $this->clientSecret = $twitchConfig['client_secret'];
            $this->redirectUri = $twitchConfig['redirect_uri'];
            $this->scopes = $twitchConfig['scopes'] ?? [
                'user:read:email',
                'user:read:follows',
                'channel:read:subscriptions'
            ];
            
            // Validate required configuration
            if (empty($this->clientId) || $this->clientId === 'YOUR_TWITCH_CLIENT_ID_HERE') {
                throw new Exception('Twitch Client ID not configured. Please update web-config/twitch.php');
            }
            
            if (empty($this->clientSecret) || $this->clientSecret === 'YOUR_TWITCH_CLIENT_SECRET_HERE') {
                throw new Exception('Twitch Client Secret not configured. Please update web-config/twitch.php');
            }
            
        } catch (Exception $e) {
            error_log('TwitchOAuth configuration error: ' . $e->getMessage());
            throw new Exception('Twitch OAuth configuration error. Please check your configuration files.');
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
        $_SESSION['oauth_state'] = $state;
        
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'state' => $state
        ];
        
        return $this->baseUrl . '/authorize?' . http_build_query($params);
    }
    
    // Exchange authorization code for access token
    public function getAccessToken($code) {
        $data = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            throw new Exception('Failed to get access token: ' . $response);
        }
    }
    
    // Get user information from Twitch
    public function getUserInfo($accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/users');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Client-Id: ' . $this->clientId
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return $data['data'][0] ?? null;
        } else {
            throw new Exception('Failed to get user info: ' . $response);
        }
    }
    
    // Refresh access token
    public function refreshToken($refreshToken) {
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            throw new Exception('Failed to refresh token: ' . $response);
        }
    }
    
    // Validate access token
    public function validateToken($accessToken) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/validate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: OAuth ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            return false;
        }
    }
}
?>
