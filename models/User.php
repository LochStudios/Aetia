<?php
// models/User.php - User model for authentication and user management

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailService.php';

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
    
    // Generate a secure reset code (24-32 characters, alphanumeric)
    private function generateResetCode() {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $allCharacters = $uppercase . $lowercase . $numbers;
        
        $codeLength = random_int(24, 32); // Random length between 24-32 characters
        $resetCode = '';
        
        // Ensure we have at least one character from each required set
        $resetCode .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $resetCode .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $resetCode .= $numbers[random_int(0, strlen($numbers) - 1)];
        
        // Fill the rest with random characters from all sets
        for ($i = 3; $i < $codeLength; $i++) {
            $resetCode .= $allCharacters[random_int(0, strlen($allCharacters) - 1)];
        }
        
        // Shuffle the string to randomize the order
        return str_shuffle($resetCode);
    }
    
    // Send signup notification email if not already sent
    private function sendSignupNotificationIfNeeded($userId, $email, $name) {
        try {
            $this->ensureConnection();
            
            // Check if signup email has already been sent
            $stmt = $this->mysqli->prepare("SELECT signup_email_sent FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!$user || $user['signup_email_sent']) {
                // Email already sent or user not found
                return;
            }
            
            // Send the signup notification email
            try {
                $emailService = new EmailService();
                $emailSent = $emailService->sendSignupNotificationEmail($email, $name);
                
                if ($emailSent) {
                    // Mark email as sent
                    $stmt = $this->mysqli->prepare("UPDATE users SET signup_email_sent = TRUE WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Exception $e) {
                error_log('Failed to send signup notification email: ' . $e->getMessage());
            }
            
        } catch (Exception $e) {
            error_log('Error checking/sending signup notification: ' . $e->getMessage());
        }
    }
    
    // Public method to check and send signup notification for existing users
    public function checkAndSendSignupNotification($userId) {
        try {
            $this->ensureConnection();
            
            // Get user details
            $stmt = $this->mysqli->prepare("
                SELECT id, email, first_name, last_name, username, social_username, social_data, signup_email_sent 
                FROM users 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!$user || $user['signup_email_sent']) {
                // User not found or email already sent
                return;
            }
            
            // Determine user's display name
            $name = '';
            if (!empty($user['first_name'])) {
                $name = $user['first_name'];
                if (!empty($user['last_name'])) {
                    $name .= ' ' . $user['last_name'];
                }
            } elseif (!empty($user['social_username'])) {
                $name = $user['social_username'];
            } else {
                $name = $user['username'];
            }
            
            // If we have social_data, try to get display name from there
            if (!empty($user['social_data'])) {
                $socialData = json_decode($user['social_data'], true);
                if (isset($socialData['display_name'])) {
                    $name = $socialData['display_name'];
                } elseif (isset($socialData['name'])) {
                    $name = $socialData['name'];
                }
            }
            
            $this->sendSignupNotificationIfNeeded($userId, $user['email'], $name);
            
        } catch (Exception $e) {
            error_log('Error in checkAndSendSignupNotification: ' . $e->getMessage());
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
                
                // Send signup notification email
                $this->sendSignupNotificationIfNeeded($userId, $email, $firstName ?: $username);
                
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
                
                // Check if we need to send signup notification to this user
                $this->checkAndSendSignupNotification($user['id']);
                
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
            $this->ensureConnection();
            
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
                $updateResult = $this->updateSocialConnection($existingUser['id'], $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt);
                if (!$updateResult) {
                    return ['success' => false, 'message' => 'Failed to update social connection'];
                }
                
                // Check if we need to send signup notification to existing user
                $this->checkAndSendSignupNotification($existingUser['id']);
                
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
                    $connectionResult = $this->addSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt, true);
                    if (!$connectionResult) {
                        return ['success' => false, 'message' => 'Failed to create social connection'];
                    }
                    
                    // Get the created user
                    $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Send signup notification email
                    $userName = isset($socialData['display_name']) ? $socialData['display_name'] : 
                                (isset($socialData['name']) ? $socialData['name'] : $socialUsername);
                    $this->sendSignupNotificationIfNeeded($userId, $email, $userName);
                    
                    return ['success' => true, 'user' => $user, 'action' => 'created'];
                } else {
                    $error = $this->mysqli->error;
                    $stmt->close();
                    error_log("Failed to create social user - MySQL error: " . $error);
                    return ['success' => false, 'message' => 'Failed to create social account: ' . $error];
                }
            }
        } catch (Exception $e) {
            error_log("Social user error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
            return ['success' => false, 'message' => 'Social authentication failed: ' . $e->getMessage()];
        }
    }
    
    // Add social connection to existing user
    private function addSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt, $isPrimary = false) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                INSERT INTO social_connections (user_id, platform, social_id, social_username, access_token, refresh_token, expires_at, social_data, is_primary) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $socialDataJson = json_encode($socialData);
            $isPrimaryInt = $isPrimary ? 1 : 0;
            $stmt->bind_param("isssssssi", $userId, $platform, $socialId, $socialUsername, $accessToken, $refreshToken, $expiresAt, $socialDataJson, $isPrimaryInt);
            $result = $stmt->execute();
            
            if (!$result) {
                $error = $this->mysqli->error;
                error_log("Failed to add social connection - MySQL error: " . $error);
                $stmt->close();
                return false;
            }
            
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Add social connection error: " . $e->getMessage());
            return false;
        }
    }
    
    // Update existing social connection
    private function updateSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                UPDATE social_connections 
                SET social_username = ?, access_token = ?, refresh_token = ?, expires_at = ?, social_data = ?, updated_at = CURRENT_TIMESTAMP
                WHERE user_id = ? AND platform = ? AND social_id = ?
            ");
            
            $socialDataJson = json_encode($socialData);
            $stmt->bind_param("sssssiss", $socialUsername, $accessToken, $refreshToken, $expiresAt, $socialDataJson, $userId, $platform, $socialId);
            $result = $stmt->execute();
            
            if (!$result) {
                $error = $this->mysqli->error;
                error_log("Failed to update social connection - MySQL error: " . $error);
                $stmt->close();
                return false;
            }
            
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("Update social connection error: " . $e->getMessage());
            return false;
        }
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
            case 'discord':
                // Discord avatar URL construction
                if (isset($socialData['avatar']) && isset($socialData['id'])) {
                    $avatarHash = $socialData['avatar'];
                    $userId = $socialData['id'];
                    // Check if it's a GIF avatar (animated)
                    $extension = (strpos($avatarHash, 'a_') === 0) ? 'gif' : 'png';
                    return "https://cdn.discordapp.com/avatars/{$userId}/{$avatarHash}.{$extension}";
                }
                return null;
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
    
    // Link a social account to an existing user
    public function linkSocialAccount($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken = null, $refreshToken = null, $expiresAt = null) {
        try {
            $this->ensureConnection();
            
            // Check if this social account is already linked to another user
            $stmt = $this->mysqli->prepare("
                SELECT user_id FROM social_connections 
                WHERE platform = ? AND social_id = ?
            ");
            $stmt->bind_param("ss", $platform, $socialId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingConnection = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingConnection && $existingConnection['user_id'] != $userId) {
                return ['success' => false, 'message' => 'This ' . ucfirst($platform) . ' account is already linked to another user.'];
            }
            
            // Check if user already has this platform linked
            $stmt = $this->mysqli->prepare("
                SELECT id FROM social_connections 
                WHERE user_id = ? AND platform = ?
            ");
            $stmt->bind_param("is", $userId, $platform);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingPlatform = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingPlatform) {
                return ['success' => false, 'message' => 'You already have a ' . ucfirst($platform) . ' account linked.'];
            }
            
            // Add the social connection
            $connectionResult = $this->addSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt, false);
            
            if ($connectionResult) {
                return ['success' => true, 'message' => ucfirst($platform) . ' account linked successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to link ' . ucfirst($platform) . ' account.'];
            }
            
        } catch (Exception $e) {
            error_log("Link social account error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while linking the account.'];
        }
    }
    
    // Set password for social users (allows manual login)
    public function setPasswordForSocialUser($userId, $newPassword) {
        try {
            $this->ensureConnection();
            
            // Get user details
            $stmt = $this->mysqli->prepare("SELECT account_type, password_hash FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            // Hash the new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update the user's password
            $stmt = $this->mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $passwordHash, $userId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Password set successfully! You can now login with your username and password.'];
            } else {
                return ['success' => false, 'message' => 'Failed to set password.'];
            }
            
        } catch (Exception $e) {
            error_log("Set password for social user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while setting password.'];
        }
    }
    
    // Change password for any user (with current password verification)
    public function changeUserPassword($userId, $currentPassword, $newPassword) {
        try {
            $this->ensureConnection();
            
            // Get user details
            $stmt = $this->mysqli->prepare("SELECT username, password_hash FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            if (empty($user['password_hash'])) {
                return ['success' => false, 'message' => 'No password is currently set for this account.'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }
            
            // Hash the new password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update the user's password
            $stmt = $this->mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $passwordHash, $userId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Password changed successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to change password.'];
            }
            
        } catch (Exception $e) {
            error_log("Change user password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while changing password.'];
        }
    }
    
    // Get all pending users for admin review
    public function getPendingUsers() {
        $stmt = $this->mysqli->prepare("
            SELECT id, username, email, first_name, last_name, account_type, social_username, 
                   profile_image, created_at, contact_attempted, contact_date 
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
    
    // Get unverified users
    public function getUnverifiedUsers() {
        $this->ensureConnection();
        $stmt = $this->mysqli->prepare("
            SELECT id, username, email, first_name, last_name, account_type, social_username, 
                   profile_image, created_at, approval_status, is_verified, is_active
            FROM users 
            WHERE is_verified = 0 AND is_active = 1
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        $unverifiedUsers = [];
        while ($row = $result->fetch_assoc()) {
            $unverifiedUsers[] = $row;
        }
        
        $stmt->close();
        return $unverifiedUsers;
    }
    
    // Verify a user
    public function verifyUser($userId, $verifiedBy) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET is_verified = 1, verified_date = CURRENT_TIMESTAMP, verified_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("si", $verifiedBy, $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Verify user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Deactivate user (soft delete)
    public function deactivateUser($userId, $reason, $deactivatedBy) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET is_active = 0, deactivation_reason = ?, deactivated_by = ?, deactivation_date = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $reason, $deactivatedBy, $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Deactivate user error: " . $e->getMessage());
            return false;
        }
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
    
    // Mark user as admin (also approves them if pending)
    public function markUserAsAdmin($userId, $approvedBy) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET is_admin = 1, 
                    approval_status = 'approved', 
                    approval_date = CURRENT_TIMESTAMP, 
                    approved_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param("si", $approvedBy, $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Mark user as admin error: " . $e->getMessage());
            return false;
        }
    }

    // Make user admin (without changing approval status)
    public function makeUserAdmin($userId, $grantedBy) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET is_admin = 1, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Make user admin error: " . $e->getMessage());
            return false;
        }
    }

    // Remove admin privileges from user
    public function removeUserAdmin($userId, $removedBy) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET is_admin = 0, 
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Remove user admin error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get first admin user for receiving team messages
    public function getFirstAdmin() {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, first_name, last_name 
                FROM users 
                WHERE is_admin = 1 AND is_active = 1 
                ORDER BY id ASC 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $admin = $result->fetch_assoc();
            $stmt->close();
            return $admin;
        } catch (Exception $e) {
            error_log("Get first admin error: " . $e->getMessage());
            return null;
        }
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
            
            // Generate secure reset code (24-32 characters, alphanumeric)
            $resetCode = $this->generateResetCode();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store the reset code
            $stmt = $this->mysqli->prepare("
                INSERT INTO password_reset_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), 
                expires_at = VALUES(expires_at), 
                created_at = NOW()
            ");
            $stmt->bind_param("iss", $user['id'], $resetCode, $expiresAt);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return [
                    'success' => true, 
                    'token' => $resetCode,
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
                return ['success' => false, 'message' => 'Invalid or expired reset code.'];
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
    
    // Unlink a social account from a user
    public function unlinkSocialAccount($userId, $platform) {
        try {
            $this->ensureConnection();
            
            // Check if this is the only/primary social connection for a social-only account
            $stmt = $this->mysqli->prepare("
                SELECT account_type, password_hash FROM users WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if ($user['account_type'] !== 'manual' && empty($user['password_hash'])) {
                // Check how many social connections they have
                $stmt = $this->mysqli->prepare("
                    SELECT COUNT(*) as count FROM social_connections WHERE user_id = ?
                ");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $count = $result->fetch_assoc()['count'];
                $stmt->close();
                
                if ($count <= 1) {
                    return ['success' => false, 'message' => 'Cannot unlink your only social account. Please link another account or set a password first.'];
                }
            }
            
            // Remove the social connection
            $stmt = $this->mysqli->prepare("
                DELETE FROM social_connections 
                WHERE user_id = ? AND platform = ?
            ");
            $stmt->bind_param("is", $userId, $platform);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => ucfirst($platform) . ' account unlinked successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to unlink ' . ucfirst($platform) . ' account.'];
            }
            
        } catch (Exception $e) {
            error_log("Unlink social account error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while unlinking the account.'];
        }
    }
    
    // Get available platforms for linking (platforms not yet linked by user)
    public function getAvailablePlatformsForLinking($userId) {
        try {
            $this->ensureConnection();
            
            $allPlatforms = ['twitch', 'discord', 'youtube', 'twitter', 'instagram'];
            
            $stmt = $this->mysqli->prepare("
                SELECT platform FROM social_connections WHERE user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $linkedPlatforms = [];
            while ($row = $result->fetch_assoc()) {
                $linkedPlatforms[] = $row['platform'];
            }
            $stmt->close();
            
            return array_diff($allPlatforms, $linkedPlatforms);
            
        } catch (Exception $e) {
            error_log("Get available platforms error: " . $e->getMessage());
            return [];
        }
    }
    
    // Set a social connection as primary
    public function setPrimarySocialConnection($userId, $platform) {
        try {
            $this->ensureConnection();
            
            // Start transaction
            $this->mysqli->begin_transaction();
            
            // First, unset all primary flags for this user
            $stmt = $this->mysqli->prepare("
                UPDATE social_connections 
                SET is_primary = 0 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $result1 = $stmt->execute();
            $stmt->close();
            
            if (!$result1) {
                $this->mysqli->rollback();
                return ['success' => false, 'message' => 'Failed to update primary status.'];
            }
            
            // Set the specified platform as primary
            $stmt = $this->mysqli->prepare("
                UPDATE social_connections 
                SET is_primary = 1 
                WHERE user_id = ? AND platform = ?
            ");
            $stmt->bind_param("is", $userId, $platform);
            $result2 = $stmt->execute();
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();
            
            if ($result2 && $affectedRows > 0) {
                $this->mysqli->commit();
                return ['success' => true, 'message' => ucfirst($platform) . ' set as primary account successfully!'];
            } else {
                $this->mysqli->rollback();
                return ['success' => false, 'message' => 'Failed to set primary account or account not found.'];
            }
            
        } catch (Exception $e) {
            $this->mysqli->rollback();
            error_log("Set primary social connection error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while setting primary account.'];
        }
    }
    
    // Update user profile information (first name and last name)
    public function updateUserProfile($userId, $firstName, $lastName) {
        try {
            $this->ensureConnection();
            
            // Trim and validate input
            $firstName = trim($firstName);
            $lastName = trim($lastName);
            
            // Basic validation
            if (empty($firstName) && empty($lastName)) {
                return ['success' => false, 'message' => 'At least one name field must be provided.'];
            }
            
            if (strlen($firstName) > 50 || strlen($lastName) > 50) {
                return ['success' => false, 'message' => 'Name fields must be 50 characters or less.'];
            }
            
            // Update the user's profile
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            $stmt->bind_param("ssi", $firstName, $lastName, $userId);
            $result = $stmt->execute();
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();
            
            if ($result && $affectedRows > 0) {
                return ['success' => true, 'message' => 'Profile updated successfully!'];
            } else {
                return ['success' => false, 'message' => 'No changes were made or user not found.'];
            }
            
        } catch (Exception $e) {
            error_log("Update user profile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating your profile.'];
        }
    }
    
    /**
     * Get all users for admin email functionality
     */
    public function getAllUsers() {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, first_name, last_name, is_active, is_admin, account_type, approval_status
                FROM users 
                ORDER BY username ASC
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            
            $stmt->close();
            return $users;
            
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all active users for newsletter functionality
     */
    public function getAllActiveUsers() {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, first_name, last_name, is_active, is_admin, account_type
                FROM users 
                WHERE is_active = 1 AND approval_status = 'approved'
                ORDER BY username ASC
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            
            $stmt->close();
            return $users;
            
        } catch (Exception $e) {
            error_log("Get all active users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user by ID for email functionality
     */
    public function getUserById($userId) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("SELECT * FROM users  WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            return $user;
        } catch (Exception $e) {
            error_log("Get user by ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all users for admin management with comprehensive status information
     */
    public function getAllUsersForAdmin() {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, first_name, last_name, profile_image, 
                       account_type, social_id, social_username, social_data, approval_status, 
                       approval_date, approved_by, rejection_reason, contact_attempted, contact_date, 
                       contact_notes, is_admin, is_verified, is_active, created_at, updated_at, 
                       verified_date, verified_by, deactivation_reason, deactivated_by, 
                       deactivation_date, signup_email_sent
                FROM users 
                ORDER BY 
                    CASE 
                        WHEN approval_status = 'pending' THEN 1
                        WHEN is_verified = 0 THEN 2
                        WHEN is_active = 0 THEN 3
                        ELSE 4
                    END,
                    created_at DESC
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            $users = [];
            
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            
            $stmt->close();
            return $users;
            
        } catch (Exception $e) {
            error_log("Get all users for admin error: " . $e->getMessage());
            return [];
        }
    }
}
?>
