<?php
// models/User.php - User model for authentication and user management

require_once __DIR__ . '/../config/database.php';

class User {
    private $database;
    private $mysqli;
    
    public function __construct() {
        $this->database = new Database();
        $this->mysqli = $this->database->getConnection();
    }
    
    // Ensure database connection is active
    private function ensureConnection() {
        if (!$this->mysqli || $this->mysqli->ping() === false) {
            $this->database = new Database();
            $this->mysqli = $this->database->getConnection();
        }
    }
    
    // Create a new manual user account
    public function createManualUser($username, $email, $password, $firstName = '', $lastName = '') {
        try {
            $this->ensureConnection();
            
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
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, password_hash, first_name, last_name, profile_image, approval_status, is_admin, is_active 
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
                
                // Check approval status
                if ($user['approval_status'] === 'pending') {
                    return ['success' => false, 'message' => 'Your account is pending approval. Aetia Talant Agency will contact you with critical platform information and business terms.'];
                } elseif ($user['approval_status'] === 'rejected') {
                    return ['success' => false, 'message' => 'Your account application has been declined. Please contact talant@aetia.com.au for more information.'];
                }
                
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
                // Check approval status for existing user
                if ($existingUser['approval_status'] === 'pending') {
                    return ['success' => false, 'message' => 'Your account is pending approval. Aetia Talant Agency will contact you with critical platform information and business terms.'];
                } elseif ($existingUser['approval_status'] === 'rejected') {
                    return ['success' => false, 'message' => 'Your account application has been declined. Please contact talant@aetia.com.au for more information.'];
                }
                
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
        $stmt->bind_param("isssssssi", $userId, $platform, $socialId, $socialUsername, $accessToken, $refreshToken, $expiresAt, $socialDataJson, $isPrimaryInt);
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
        $this->ensureConnection();
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
    
    // Get all pending users for admin review
    public function getPendingUsers() {
        $stmt = $this->mysqli->prepare("
            SELECT id, username, email, first_name, last_name, account_type, social_username, 
                   created_at, contact_attempted, contact_date 
            FROM users 
            WHERE approval_status = 'pending' 
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $pendingUsers = [];
        while ($row = $result->fetch_assoc()) {
            $pendingUsers[] = $row;
        }
        
        $stmt->close();
        return $pendingUsers;
    }
    
    // Approve a user
    public function approveUser($userId, $approvedBy) {
        $stmt = $this->mysqli->prepare("
            UPDATE users 
            SET approval_status = 'approved', approval_date = CURRENT_TIMESTAMP, approved_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("si", $approvedBy, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    // Reject a user
    public function rejectUser($userId, $rejectionReason, $approvedBy) {
        $stmt = $this->mysqli->prepare("
            UPDATE users 
            SET approval_status = 'rejected', approval_date = CURRENT_TIMESTAMP, 
                rejection_reason = ?, approved_by = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $rejectionReason, $approvedBy, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    // Mark contact attempt
    public function markContactAttempt($userId, $contactNotes = '') {
        $stmt = $this->mysqli->prepare("
            UPDATE users 
            SET contact_attempted = TRUE, contact_date = CURRENT_TIMESTAMP, contact_notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("si", $contactNotes, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    // Check if user is admin
    public function isUserAdmin($userId) {
        $this->ensureConnection();
        $stmt = $this->mysqli->prepare("SELECT is_admin FROM users WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        return $user && $user['is_admin'] == 1;
    }
    
    // Get user admin status along with other details
    public function getUserWithAdminStatus($userId) {
        $this->ensureConnection();
        $stmt = $this->mysqli->prepare("SELECT id, username, email, first_name, last_name, is_admin, approval_status FROM users WHERE id = ? AND is_active = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }
    
    // Change user password
    public function changePassword($userId, $newPassword) {
        try {
            $this->ensureConnection();
            
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET password_hash = ?
                WHERE id = ? AND is_active = 1
            ");
            
            $stmt->bind_param("si", $passwordHash, $userId);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return false;
        }
    }
    
    // Generate password reset token
    public function generatePasswordResetToken($email) {
        try {
            $this->ensureConnection();
            
            // Check if user exists and is active
            $stmt = $this->mysqli->prepare("
                SELECT id, username FROM users 
                WHERE email = ? AND is_active = 1 AND account_type = 'manual'
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                return ['success' => false, 'message' => 'No account found with that email address.'];
            }
            
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store the token
            $stmt = $this->mysqli->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), 
                expires_at = VALUES(expires_at), 
                created_at = NOW()
            ");
            $stmt->bind_param("iss", $user['id'], $token, $expiresAt);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return [
                    'success' => true, 
                    'token' => $token,
                    'username' => $user['username'],
                    'user_id' => $user['id']
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to generate reset token.'];
            }
            
        } catch (Exception $e) {
            error_log("Password reset token generation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    // Validate password reset token
    public function validatePasswordResetToken($token) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT u.id, u.username, u.email, prt.expires_at
                FROM password_reset_tokens prt
                JOIN users u ON prt.user_id = u.id
                WHERE prt.token = ? AND prt.expires_at > NOW() AND u.is_active = 1
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $stmt->close();
                return ['success' => true, 'user' => $user];
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'Invalid or expired reset token.'];
            }
            
        } catch (Exception $e) {
            error_log("Password reset token validation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    // Reset password using token
    public function resetPasswordWithToken($token, $newPassword) {
        try {
            $this->ensureConnection();
            
            // Validate token first
            $tokenValidation = $this->validatePasswordResetToken($token);
            if (!$tokenValidation['success']) {
                return $tokenValidation;
            }
            
            $userId = $tokenValidation['user']['id'];
            
            // Change password
            $passwordChangeResult = $this->changePassword($userId, $newPassword);
            
            if ($passwordChangeResult) {
                // Delete the used token
                $stmt = $this->mysqli->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $stmt->close();
                
                return ['success' => true, 'message' => 'Password reset successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to reset password.'];
            }
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
    
    // Mark admin setup as complete
    public function markAdminSetupComplete($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET approved_by = 'Setup Complete'
                WHERE id = ? AND username = 'admin' AND approved_by = 'Auto-Generated'
            ");
            
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();
            
            return $result && $affectedRows > 0;
        } catch (Exception $e) {
            error_log("Mark admin setup complete error: " . $e->getMessage());
            return false;
        }
    }
}
?>
