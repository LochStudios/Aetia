<?php
// services/YouTubeOAuth.php - YouTube OAuth integration for Aetia Talent Agency

require_once '/home/aetiacom/web-config/config.php';
require_once '/home/aetiacom/vendors/Google/vendor/autoload.php';

class YouTubeOAuth {
    private $client;
    private $redirectUri;
    private $linkRedirectUri;
    public function __construct() {
        try {
            $googleConfig = Config::load('google');
            $youtubeConfig = [];
            try {
                $youtubeConfig = Config::load('youtube');
            } catch (Exception $e) {
                $youtubeConfig = [];
            }
            $this->client = new Google\Client();
            $credentialsJsonPath = $youtubeConfig['credentials_json']
                ?? '/home/aetiacom/web-config/client_secret_794642031392-90482997p725d6ckrsk6ie35d4u45uvk.apps.googleusercontent.com.json';
            if (file_exists($credentialsJsonPath)) {
                try {
                    $this->client->setAuthConfig($credentialsJsonPath);
                } catch (Exception $e) {
                    $this->setConfigCredentials($youtubeConfig, $googleConfig);
                }
            } else {
                $this->setConfigCredentials($youtubeConfig, $googleConfig);
            }
            $scopes = $youtubeConfig['scopes'] ?? [
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/youtube.readonly'
            ];
            $this->client->setScopes($scopes);
            $this->client->setAccessType('offline');
            $this->client->setPrompt('consent');
            $this->client->setIncludeGrantedScopes(true);
            $googleRedirect = $googleConfig['redirect_uri'] ?? '';
            $googleLinkRedirect = $googleConfig['link_redirect_uri'] ?? $googleRedirect;
            $this->redirectUri = $youtubeConfig['redirect_uri']
                ?? ($googleConfig['youtube_redirect_uri'] ?? str_replace('google-callback.php', 'youtube-callback.php', $googleRedirect));
            $this->linkRedirectUri = $youtubeConfig['link_redirect_uri']
                ?? ($googleConfig['youtube_link_redirect_uri'] ?? str_replace('google-link-callback.php', 'youtube-link-callback.php', $googleLinkRedirect));
            if (empty($this->redirectUri)) {
                throw new Exception('YouTube redirect URI not configured. Please update web-config/youtube.php or web-config/google.php');
            }
        } catch (Exception $e) {
            error_log('YouTubeOAuth configuration error: ' . $e->getMessage());
            throw new Exception('YouTube OAuth configuration error. Please check your configuration files.');
        }
    }

    private function setConfigCredentials($youtubeConfig, $googleConfig) {
        $clientId = $youtubeConfig['client_id'] ?? ($googleConfig['client_id'] ?? '');
        $clientSecret = $youtubeConfig['client_secret'] ?? ($googleConfig['client_secret'] ?? '');
        if (empty($clientId) || $clientId === 'YOUR_GOOGLE_CLIENT_ID_HERE.apps.googleusercontent.com') {
            throw new Exception('YouTube/Google Client ID not configured. Please update web-config/youtube.php or web-config/google.php');
        }
        if (empty($clientSecret) || $clientSecret === 'YOUR_GOOGLE_CLIENT_SECRET_HERE') {
            throw new Exception('YouTube/Google Client Secret not configured. Please update web-config/youtube.php or web-config/google.php');
        }
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
    }

    public function getAuthorizationUrl($state = null) {
        if ($state === null) {
            $state = bin2hex(random_bytes(32));
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['youtube_oauth_state'] = $state;
        $this->client->setRedirectUri($this->redirectUri);
        $this->client->setState($state);
        return $this->client->createAuthUrl();
    }

    public function getLinkAuthorizationUrl($state = null) {
        if ($state === null) {
            $state = bin2hex(random_bytes(32));
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['youtube_link_oauth_state'] = $state;
        $this->client->setRedirectUri($this->linkRedirectUri);
        $this->client->setState($state);
        return $this->client->createAuthUrl();
    }

    public function getAccessToken($code, $state = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $isLinking = false;
        if (isset($_SESSION['youtube_link_oauth_state']) && $state === $_SESSION['youtube_link_oauth_state']) {
            $isLinking = true;
            $this->client->setRedirectUri($this->linkRedirectUri);
            unset($_SESSION['youtube_link_oauth_state']);
        } elseif (isset($_SESSION['youtube_oauth_state']) && $state === $_SESSION['youtube_oauth_state']) {
            $this->client->setRedirectUri($this->redirectUri);
            unset($_SESSION['youtube_oauth_state']);
        } else {
            throw new Exception('Invalid state parameter');
        }
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($code);
            if (isset($accessToken['error'])) {
                throw new Exception('Failed to exchange authorization code');
            }
            return [
                'access_token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'] ?? null,
                'expires_in' => $accessToken['expires_in'] ?? 3600,
                'is_linking' => $isLinking
            ];
        } catch (Exception $e) {
            error_log('YouTube token exchange failed: ' . $e->getMessage());
            throw new Exception('Failed to exchange authorization code');
        }
    }

    public function getUserInfo($accessToken) {
        try {
            $this->client->setAccessToken($accessToken);
            $oauth2 = new Google\Service\Oauth2($this->client);
            $userInfo = $oauth2->userinfo->get();
            if (!$userInfo->getEmail()) {
                throw new Exception('Email not provided by YouTube/Google OAuth');
            }
            $channelId = null;
            $channelTitle = null;
            $channelThumbnail = null;
            try {
                $youtube = new Google\Service\YouTube($this->client);
                $channelResponse = $youtube->channels->listChannels('snippet', [
                    'mine' => true,
                    'maxResults' => 1
                ]);
                if (!empty($channelResponse->items)) {
                    $channel = $channelResponse->items[0];
                    $channelId = $channel->id;
                    $channelTitle = $channel->snippet->title ?? null;
                    $channelThumbnail = $channel->snippet->thumbnails->default->url
                        ?? $channel->snippet->thumbnails->medium->url
                        ?? null;
                }
            } catch (Exception $e) {
                error_log('YouTube channel info request failed, using Google profile fallback: ' . $e->getMessage());
            }
            return [
                'id' => $channelId ?: $userInfo->getId(),
                'username' => $channelTitle ?: ($userInfo->getName() ?? $userInfo->getEmail()),
                'email' => $userInfo->getEmail(),
                'profile_image' => $channelThumbnail ?: $userInfo->getPicture(),
                'google_user_id' => $userInfo->getId(),
                'channel_id' => $channelId,
                'channel_title' => $channelTitle,
                'channel_thumbnail' => $channelThumbnail,
                'verified' => $userInfo->getVerifiedEmail()
            ];
        } catch (Exception $e) {
            error_log('YouTube user info request failed: ' . $e->getMessage());
            throw new Exception('Failed to fetch YouTube user information');
        }
    }
}
?>
