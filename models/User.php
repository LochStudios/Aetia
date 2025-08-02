<?php
// models/User.php - User model for authentication and user management

require_once __DIR__ . '/../config/database.php';

class User {
    private $mysqli;
    
    public function __construct() {
        $database = new Database();
        $this->mysqli = $database->getConnection();
    }
    
    // Create a new manual user account
    public function createManualUser($username, $email, $password, $firstName = '', $lastName = '') {
        try {
            // Check if username or email already exists
            if ($this->userExists($username, $email)) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash the password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->mysqli->prepare("
                INSERT INTO users (username, email, password_hash, first_name, last_name, account_type) 
                VALUES (?, ?, ?, ?, ?, 'manual')
            ");
            
            $stmt->bind_param("sssss", $username, $email, $passwordHash, $firstName, $lastName);
            $result = $stmt->execute();
            
            if ($result) {
                $userId = $this->mysqli->insert_id;
                $stmt->close();
                return ['success' => true, 'user_id' => $userId];
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'Failed to create account'];
            }
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Authenticate manual user
    public function authenticateManualUser($username, $password) {
        try {
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, profile_image, is_active 
                FROM users 
                WHERE (username = ? OR email = ?) AND account_type = 'manual' AND is_active = 1
            ");
            
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                unset($user['password_hash']); // Remove password hash from returned data
                return ['success' => true, 'user' => $user];
            } else {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Authentication failed'];
        }
    }
    
    // Create or update social user
    public function createOrUpdateSocialUser($platform, $socialId, $socialUsername, $socialData, $accessToken = null, $refreshToken = null, $expiresAt = null) {
        try {
            // Check if user exists with this social account
            $stmt = $this->mysqli->prepare("
                SELECT u.* FROM users u 
                JOIN social_connections sc ON u.id = sc.user_id 
                WHERE sc.platform = ? AND sc.social_id = ?
            ");
            $stmt->bind_param("ss", $platform, $socialId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingUser = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingUser) {
                // Update existing social connection
                $this->updateSocialConnection($existingUser['id'], $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt);
                return ['success' => true, 'user' => $existingUser, 'action' => 'updated'];
            } else {
                // Create new user
                $username = $this->generateUniqueUsername($socialUsername);
                $email = isset($socialData['email']) ? $socialData['email'] : $username . '@' . $platform . '.temp';
                
                $stmt = $this->mysqli->prepare("
                    INSERT INTO users (username, email, account_type, social_id, social_username, social_data, profile_image) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $profileImage = $this->extractProfileImage($platform, $socialData);
                $socialDataJson = json_encode($socialData);
                $stmt->bind_param("sssssss", $username, $email, $platform, $socialId, $socialUsername, $socialDataJson, $profileImage);
                $result = $stmt->execute();
                
                if ($result) {
                    $userId = $this->mysqli->insert_id;
                    $stmt->close();
                    
                    // Add social connection
                    $this->addSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt, true);
                    
                    // Get the created user
                    $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    return ['success' => true, 'user' => $user, 'action' => 'created'];
                } else {
                    $stmt->close();
                    return ['success' => false, 'message' => 'Failed to create social account'];
                }
            }
        } catch (Exception $e) {
            error_log("Social user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Social authentication failed'];
        }
    }
    
    // Add social connection to existing user
    private function addSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt, $isPrimary = false) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO social_connections (user_id, platform, social_id, social_username, access_token, refresh_token, expires_at, social_data, is_primary) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $socialDataJson = json_encode($socialData);
        $isPrimaryInt = $isPrimary ? 1 : 0;
        $stmt->bind_param("issssssi", $userId, $platform, $socialId, $socialUsername, $accessToken, $refreshToken, $expiresAt, $socialDataJson, $isPrimaryInt);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    // Update existing social connection
    private function updateSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt) {
        $stmt = $this->mysqli->prepare("
            UPDATE social_connections 
            SET social_username = ?, access_token = ?, refresh_token = ?, expires_at = ?, social_data = ?, updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND platform = ? AND social_id = ?
        ");
        
        $socialDataJson = json_encode($socialData);
        $stmt->bind_param("sssssiss", $socialUsername, $accessToken, $refreshToken, $expiresAt, $socialDataJson, $userId, $platform, $socialId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    // Check if user exists
    private function userExists($username, $email) {
        $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    // Generate unique username
    private function generateUniqueUsername($baseUsername) {
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $baseUsername);
        $originalUsername = $username;
        $counter = 1;
        
        while ($this->usernameExists($username)) {
            $username = $originalUsername . $counter;
            $counter++;
        }
        
        return $username;
    }
    
    // Check if username exists
    private function usernameExists($username) {
        $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    
    // Extract profile image from social data
    private function extractProfileImage($platform, $socialData) {
        switch ($platform) {
            case 'twitch':
                return $socialData['profile_image_url'] ?? null;
            case 'youtube':
                return $socialData['snippet']['thumbnails']['default']['url'] ?? null;
            case 'twitter':
                return $socialData['profile_image_url'] ?? null;
            case 'instagram':
                return $socialData['profile_picture'] ?? null;
            default:
                return null;
        }
    }
    
    // Get user by ID
    public function getUserById($userId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
    
    // Get user's social connections
    public function getUserSocialConnections($userId) {
        $stmt = $this->mysqli->prepare("SELECT * FROM social_connections WHERE user_id = ? ORDER BY is_primary DESC, created_at ASC");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $connections = [];
        while ($row = $result->fetch_assoc()) {
            $connections[] = $row;
        }
        
        $stmt->close();
        return $connections;
    }
}
?>
