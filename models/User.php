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
            if (!$user || $user['signup_email_sent']) {return;} // Email already sent or user not found
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
            if ($this->userExists($username, $email)) {return ['success' => false, 'message' => 'Username or email already exists'];}
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
                SELECT id, username, email, password_hash, first_name, last_name, profile_image, approval_status, is_admin, is_active, is_suspended, suspension_reason 
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
                    return ['success' => false, 'message' => 'Your account is pending approval. Aetia Talent Agency will contact you with critical platform information and business terms.'];
                } elseif ($user['approval_status'] === 'rejected') {
                    return ['success' => false, 'message' => 'Your account application has been declined. Please contact talent@aetia.com.au for more information.'];
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
                    return ['success' => false, 'message' => 'Your account is pending approval. Aetia Talent Agency will contact you with critical platform information and business terms.'];
                } elseif ($existingUser['approval_status'] === 'rejected') {
                    return ['success' => false, 'message' => 'Your account application has been declined. Please contact talent@aetia.com.au for more information.'];
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
                    // Auto-verify when created via Google and Google provided an email
                    if ($platform === 'google' && !empty($socialData['email'])) {
                        // If the Google email matches the created user email (case-insensitive), mark verified
                        if (strcasecmp(trim($user['email']), trim($socialData['email'])) === 0) {
                            $this->verifyUser($userId, 'Google OAuth');
                            // reflect change in returned user array
                            $user['is_verified'] = 1;
                            $user['verified_by'] = 'Google OAuth';
                        }
                    }
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
            // Start transaction to ensure data consistency
            $this->mysqli->begin_transaction();
            // If this is being set as primary, unset all other primary connections first
            if ($isPrimary) {
                $stmt = $this->mysqli->prepare("UPDATE social_connections SET is_primary = 0 WHERE user_id = ?");
                $stmt->bind_param("i", $userId);
                $result = $stmt->execute();
                $stmt->close();
                if (!$result) {
                    $this->mysqli->rollback();
                    error_log("Failed to unset existing primary connections for user $userId");
                    return false;
                }
            }
            $stmt = $this->mysqli->prepare("
                INSERT INTO social_connections (user_id, platform, social_id, social_username, access_token, refresh_token, expires_at, social_data, is_primary) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $socialDataJson = json_encode($socialData);
            $isPrimaryInt = $isPrimary ? 1 : 0;
            error_log("AddSocialConnection - About to insert: User ID: $userId, Platform: '$platform', Social ID: '$socialId', Username: '$socialUsername'");
            error_log("AddSocialConnection - Social data JSON: " . substr($socialDataJson, 0, 200) . "...");
            $stmt->bind_param("isssssssi", $userId, $platform, $socialId, $socialUsername, $accessToken, $refreshToken, $expiresAt, $socialDataJson, $isPrimaryInt);
            $result = $stmt->execute();
            if (!$result) {
                $error = $this->mysqli->error;
                error_log("Failed to add social connection - MySQL error: " . $error);
                $stmt->close();
                $this->mysqli->rollback();
                return false;
            }
            $affectedRows = $this->mysqli->affected_rows;
            error_log("AddSocialConnection - Successfully inserted, affected rows: $affectedRows");
            $stmt->close();
            $this->mysqli->commit();
            return true;
        } catch (Exception $e) {
            $this->mysqli->rollback();
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
            case 'google':
                return $socialData['picture'] ?? null;
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
            error_log("LinkSocialAccount - User ID: $userId, Platform: '$platform', Social ID: '$socialId', Username: '$socialUsername'");
            error_log("LinkSocialAccount - Access token: " . (empty($accessToken) ? 'NULL' : substr($accessToken, 0, 20) . '...'));
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
                error_log("LinkSocialAccount - Account already linked to user " . $existingConnection['user_id']);
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
            if ($existingPlatform) {return ['success' => false, 'message' => 'You already have a ' . ucfirst($platform) . ' account linked.'];}
            // Add the social connection
            $connectionResult = $this->addSocialConnection($userId, $platform, $socialId, $socialUsername, $socialData, $accessToken, $refreshToken, $expiresAt, false);
            if ($connectionResult) {
                // Auto-verify user if linking Google and Google provided an email that matches the user's email
                if ($platform === 'google' && !empty($socialData['email'])) {
                    // Fetch user's current email
                    $stmt = $this->mysqli->prepare("SELECT email, is_verified FROM users WHERE id = ?");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $existingUser = $result->fetch_assoc();
                    $stmt->close();
                    if ($existingUser && strcasecmp(trim($existingUser['email']), trim($socialData['email'])) === 0 && !$existingUser['is_verified']) {
                        $this->verifyUser($userId, 'Google OAuth (linked)');
                    }
                }
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
            if (!$user) {return ['success' => false, 'message' => 'User not found.'];}
            if (empty($user['password_hash'])) {return ['success' => false, 'message' => 'No password is currently set for this account.'];}
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {return ['success' => false, 'message' => 'Current password is incorrect.'];}
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
    
    // Suspend user (temporary restriction)
    public function suspendUser($userId, $reason, $suspendedBy) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET is_suspended = 1, suspension_reason = ?, suspended_by = ?, suspended_date = CURRENT_TIMESTAMP
                WHERE id = ? AND is_active = 1
            ");
            $stmt->bind_param("ssi", $reason, $suspendedBy, $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Suspend user error: " . $e->getMessage());
            return false;
        }
    }
    
    // Unsuspend user
    public function unsuspendUser($userId) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET is_suspended = 0, suspension_reason = NULL, suspended_by = NULL, suspended_date = NULL
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Unsuspend user error: " . $e->getMessage());
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
        // Include status fields such as suspension info when returning a subset of user fields
        $stmt = $this->mysqli->prepare("SELECT id, username, email, first_name, last_name, is_admin, approval_status, account_type, profile_image, is_suspended, suspension_reason FROM users WHERE id = ? AND is_active = 1");
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
    
    // Check if email exists and can be used for password reset
    public function checkEmailExists($email) {
        try {
            $this->ensureConnection();
            // Check if user exists with this email
            $stmt = $this->mysqli->prepare("
                SELECT id, username, account_type, is_active, approval_status 
                FROM users 
                WHERE email = ?
            ");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $stmt->close();
                return [
                    'exists' => false,
                    'canReset' => false,
                    'message' => 'No account found with that email address.'
                ];
            }
            $user = $result->fetch_assoc();
            $stmt->close();
            // Check if user can reset password
            if ($user['account_type'] !== 'manual') {
                return [
                    'exists' => true,
                    'canReset' => false,
                    'message' => 'This account is linked to a social login service. Password reset is not available for social accounts.'
                ];
            }
            if (!$user['is_active']) {
                return [
                    'exists' => true,
                    'canReset' => false,
                    'message' => 'This account is currently deactivated. Please contact support for assistance.'
                ];
            }
            if ($user['approval_status'] !== 'approved') {
                return [
                    'exists' => true,
                    'canReset' => false,
                    'message' => 'This account is pending approval. Password reset is not available until your account is approved.'
                ];
            }
            // User exists and can reset password
            return [
                'exists' => true,
                'canReset' => true,
                'message' => 'Account found and eligible for password reset.',
                'user_id' => $user['id'],
                'username' => $user['username']
            ];
        } catch (Exception $e) {
            error_log("Email check error: " . $e->getMessage());
            return [
                'exists' => false,
                'canReset' => false,
                'message' => 'An error occurred while checking the email. Please try again.'
            ];
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

    /**
     * Generate 6-digit email verification code for a user and email it.
     * Expires in 1 hour.
     */
    public function generateEmailVerificationCode($userId) {
        try {
            $this->ensureConnection();
            // Get user's email and name
            $stmt = $this->mysqli->prepare("SELECT email, first_name, last_name, username FROM users WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                $stmt->close();
                return ['success' => false, 'message' => 'User not found or inactive.'];
            }
            $user = $result->fetch_assoc();
            $stmt->close();
            $email = $user['email'];
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['username'];
            // Generate 6-digit code
            $code = sprintf('%06d', random_int(0, 999999));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            // Store or update code
            $stmt = $this->mysqli->prepare("INSERT INTO email_verification_tokens (user_id, code, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE code = VALUES(code), expires_at = VALUES(expires_at), created_at = NOW()");
            $stmt->bind_param("iss", $userId, $code, $expiresAt);
            $res = $stmt->execute();
            $stmt->close();
            if (!$res) {
                return ['success' => false, 'message' => 'Failed to store verification code.'];
            }
            // Prepare verification URL (prefill email as query param)
            $verifyUrl = sprintf('%s/verify-email.php?email=%s', rtrim($this->getSiteBaseUrl(), '/'), rawurlencode($email));
            // Send email
            $emailService = new EmailService();
            $sent = $emailService->sendEmailVerificationCode($email, $name, $code, $verifyUrl);
            if ($sent) {
                return ['success' => true, 'message' => 'Verification code sent', 'expires_at' => $expiresAt];
            } else {
                return ['success' => false, 'message' => 'Failed to send verification email'];
            }
        } catch (Exception $e) {
            error_log('Generate email verification code error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }

    /**
     * Validate email verification code for a user (by email + code).
     */
    public function validateEmailVerificationCode($email, $code) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("SELECT evt.code, evt.expires_at, u.id as user_id FROM email_verification_tokens evt JOIN users u ON evt.user_id = u.id WHERE u.email = ? AND evt.code = ? AND evt.expires_at > NOW() LIMIT 1");
            $stmt->bind_param("ss", $email, $code);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $stmt->close();
                // Mark user as verified
                $this->verifyUser($row['user_id'], 'Email Verification Code');
                // Delete verification token after successful validation
                $del = $this->mysqli->prepare("DELETE FROM email_verification_tokens WHERE user_id = ?");
                $del->bind_param("i", $row['user_id']);
                $del->execute();
                $del->close();
                return ['success' => true, 'user_id' => $row['user_id']];
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'Invalid or expired verification code.'];
            }
        } catch (Exception $e) {
            error_log('Validate email verification code error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }

    // Helper to determine site base URL for links (simple fallback)
    private function getSiteBaseUrl() {
        // Try to determine from server vars, fallback to config constant or example
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
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
            if (!$tokenValidation['success']) {return $tokenValidation;}
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
            error_log("User model unlinkSocialAccount - User ID: $userId, Platform: '$platform'");
            // Check if this is the only/primary social connection for a social-only account
            $stmt = $this->mysqli->prepare("
                SELECT account_type, password_hash FROM users WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            error_log("User account type: " . $user['account_type'] . ", Has password: " . (!empty($user['password_hash']) ? 'yes' : 'no'));
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
                error_log("Total social connections: $count");
                if ($count <= 1) {
                    error_log("Cannot unlink - only social account");
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
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();
            
            error_log("Unlink query executed - Affected rows: $affectedRows");
            
            if ($result && $affectedRows > 0) {
                error_log("Successfully unlinked $platform account for user $userId");
                return ['success' => true, 'message' => ucfirst($platform) . ' account unlinked successfully!'];
            } else {
                error_log("Failed to unlink $platform account for user $userId - no rows affected");
                return ['success' => false, 'message' => 'Failed to unlink ' . ucfirst($platform) . ' account. Account may not exist.'];
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
            
            $allPlatforms = ['twitch', 'discord', 'google', 'twitter', 'instagram'];
            
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
                // If platform is google, attempt auto-verification when set as primary
                if (strtolower($platform) === 'google') {
                    try {
                        // Get the social_data for the google connection
                        $stmt = $this->mysqli->prepare("SELECT social_data FROM social_connections WHERE user_id = ? AND platform = ? LIMIT 1");
                        $stmt->bind_param("is", $userId, $platform);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res->fetch_assoc();
                        $stmt->close();
                        if ($row && !empty($row['social_data'])) {
                            $socialData = json_decode($row['social_data'], true);
                            if (!empty($socialData['email'])) {
                                // Fetch user's email
                                $stmt2 = $this->mysqli->prepare("SELECT email, is_verified FROM users WHERE id = ?");
                                $stmt2->bind_param("i", $userId);
                                $stmt2->execute();
                                $res2 = $stmt2->get_result();
                                $u = $res2->fetch_assoc();
                                $stmt2->close();
                                if ($u && strcasecmp(trim($u['email']), trim($socialData['email'])) === 0 && !$u['is_verified']) {
                                    $this->verifyUser($userId, 'Google OAuth (primary)');
                                }
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Auto-verify (setPrimarySocialConnection) failed: ' . $e->getMessage());
                    }
                }
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
    
    // Update user profile information (supports multiple fields)
    public function updateUserProfile($userId, $profileData = []) {
        try {
            $this->ensureConnection();
            
            // Handle legacy parameters (backward compatibility)
            if (is_string($profileData)) {
                // Legacy call: updateUserProfile($userId, $firstName, $lastName)
                $firstName = func_get_arg(1);
                $lastName = func_get_arg(2);
                $profileData = ['first_name' => $firstName, 'last_name' => $lastName];
            }
            
            // Build dynamic update query based on provided fields
            $validFields = ['first_name', 'last_name', 'abn_acn', 'address'];
            $updateFields = [];
            $values = [];
            $types = '';
            
            foreach ($profileData as $field => $value) {
                if (in_array($field, $validFields)) {
                    $value = trim($value);
                    if (!empty($value)) { // Only update non-empty values
                        $updateFields[] = "$field = ?";
                        $values[] = $value;
                        $types .= 's';
                    }
                }
            }
            
            if (empty($updateFields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }
            
            // Basic validation for name fields
            if (isset($profileData['first_name']) && strlen($profileData['first_name']) > 50) {
                return ['success' => false, 'message' => 'First name must be 50 characters or less.'];
            }
            if (isset($profileData['last_name']) && strlen($profileData['last_name']) > 50) {
                return ['success' => false, 'message' => 'Last name must be 50 characters or less.'];
            }
            
            $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
            $values[] = $userId;
            $types .= 'i';
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            
            $stmt->bind_param($types, ...$values);
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
     * Update user's profile image reference
     * @param int $userId The user ID
     * @param string $imageUrl The reference/flag for the uploaded image (not the actual URL)
     * @return array Success/failure result
     */
    public function updateProfileImage($userId, $imageUrl) {
        try {
            $this->ensureConnection();
            // Validate input
            if (empty($imageUrl)) {
                return ['success' => false, 'message' => 'Image URL is required.'];
            }
            // Update the user's profile image
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET profile_image = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $imageUrl, $userId);
            $result = $stmt->execute();
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();
            if ($result && $affectedRows > 0) {
                return ['success' => true, 'message' => 'Profile image updated successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to update profile image or user not found.'];
            }
        } catch (Exception $e) {
            error_log("Update profile image error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating your profile image.'];
        }
    }
    
    /**
     * Update user's public email address
     * @param int $userId The user ID
     * @param string $publicEmail The new public email address
     * @param string $adminName The name of the admin making the change
     * @return bool Success status
     */
    public function updatePublicEmail($userId, $publicEmail, $adminName = 'Admin') {
        try {
            $this->ensureConnection();
            // Validate input
            if (empty($publicEmail)) {
                error_log("Update public email error: Public email is required.");
                return false;
            }
            // Basic email validation
            if (!filter_var($publicEmail, FILTER_VALIDATE_EMAIL)) {
                error_log("Update public email error: Invalid email format.");
                return false;
            }
            // Update the user's public email
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET public_email = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->bind_param("si", $publicEmail, $userId);
            $result = $stmt->execute();
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();
            if ($result && $affectedRows > 0) {
                // Log the admin action
                error_log("Admin action: {$adminName} updated public email for user ID {$userId} to {$publicEmail}");
                return true;
            } else {
                error_log("Update public email error: Failed to update or user not found.");
                return false;
            }
        } catch (Exception $e) {
            error_log("Update public email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove user's profile image
     * @param int $userId The user ID
     * @return array Success/failure result
     */
    public function removeProfileImage($userId) {
        try {
            $this->ensureConnection();
            
            // Update the user's profile image to NULL
            $stmt = $this->mysqli->prepare("
                UPDATE users 
                SET profile_image = NULL, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();
            if ($result && $affectedRows > 0) {
                return ['success' => true, 'message' => 'Profile image removed successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to remove profile image or user not found.'];
            }
        } catch (Exception $e) {
            error_log("Remove profile image error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while removing your profile image.'];
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
     * Get all users with complete information for admin management
     */
    public function getAllUsersForAdmin() {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, first_name, last_name, account_type, social_username, 
                       profile_image, created_at, contact_attempted, contact_date, contact_notes,
                       approval_status, approval_date, approved_by, rejection_reason,
                       verified_date, verified_by, is_verified, is_active,
                       deactivation_reason, deactivated_by, deactivation_date,
                       suspension_reason, suspended_by, suspended_date, is_suspended, is_admin,
                       public_email
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
            error_log("Get all users for admin error: " . $e->getMessage());
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
     * Get user by social account
     */
    public function getUserBySocialAccount($platform, $socialId) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("
                SELECT u.* FROM users u 
                JOIN social_connections sc ON u.id = sc.user_id 
                WHERE sc.platform = ? AND sc.social_id = ?
            ");
            $stmt->bind_param("ss", $platform, $socialId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            return $user;
        } catch (Exception $e) {
            error_log("Get user by social account error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get user by email
     */
    public function getUserByEmail($email) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            return $user;
        } catch (Exception $e) {
            error_log("Get user by email error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update user's last login timestamp
     */
    public function updateLastLogin($userId) {
        try {
            $this->ensureConnection();
            $stmt = $this->mysqli->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's SMS preferences
     * @param int $userId The user ID
     * @return array SMS preferences with success status
     */
    public function getUserSmsPreferences($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT sms_enabled, phone_number, phone_verified 
                FROM users 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $stmt->close();
                
                return [
                    'success' => true,
                    'sms_enabled' => (bool)$user['sms_enabled'],
                    'phone_number' => $user['phone_number'] ?: '',
                    'phone_verified' => (bool)$user['phone_verified']
                ];
            } else {
                $stmt->close();
                return [
                    'success' => false,
                    'message' => 'User not found or inactive.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Get user SMS preferences error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while retrieving SMS preferences.'
            ];
        }
    }

    /**
     * Update user's SMS preferences
     * @param int $userId The user ID
     * @param bool $smsEnabled Whether SMS notifications are enabled
     * @param string $phoneNumber The phone number for SMS notifications
     * @return array Success/failure result
     */
    public function updateSmsPreferences($userId, $smsEnabled, $phoneNumber) {
        try {
            $this->ensureConnection();

            // Validate phone number if SMS is enabled
            if ($smsEnabled && empty($phoneNumber)) {
                return ['success' => false, 'message' => 'Phone number is required when enabling SMS notifications.'];
            }

            // If SMS is being disabled, clear phone verification
            $phoneVerified = $smsEnabled ? 0 : 0; // Reset verification when disabled

            $stmt = $this->mysqli->prepare("
                UPDATE users
                SET sms_enabled = ?, phone_number = ?, phone_verified = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND is_active = 1
            ");
            $stmt->bind_param("issii", $smsEnabled, $phoneNumber, $phoneVerified, $userId);
            $result = $stmt->execute();
            $affectedRows = $this->mysqli->affected_rows;
            $stmt->close();

            if ($result && $affectedRows > 0) {
                return ['success' => true, 'message' => 'SMS preferences updated successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to update SMS preferences or user not found.'];
            }

        } catch (Exception $e) {
            error_log("Update SMS preferences error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating SMS preferences.'];
        }
    }

    /**
     * Send SMS verification code to user's phone number
     * @param int $userId The user ID
     * @return array Success/failure result
     */
    public function sendSmsVerificationCode($userId) {
        try {
            $this->ensureConnection();

            // Get user's phone number
            $stmt = $this->mysqli->prepare("SELECT phone_number FROM users WHERE id = ? AND is_active = 1");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user || empty($user['phone_number'])) {
                return ['success' => false, 'message' => 'No phone number found for this user.'];
            }

            // Generate 6-digit verification code
            $verificationCode = sprintf('%06d', random_int(0, 999999));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes')); // 10 minutes expiry

            // Store verification attempt
            $stmt = $this->mysqli->prepare("
                INSERT INTO sms_verification_attempts (user_id, phone_number, verification_code, expires_at)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                verification_code = VALUES(verification_code),
                expires_at = VALUES(expires_at),
                last_attempt = NOW(),
                attempts = attempts + 1
            ");
            $stmt->bind_param("isss", $userId, $user['phone_number'], $verificationCode, $expiresAt);
            $result = $stmt->execute();
            $stmt->close();

            if (!$result) {
                return ['success' => false, 'message' => 'Failed to store verification code.'];
            }

            // Send SMS using SmsService
            require_once __DIR__ . '/../services/SmsService.php';
            $smsService = new SmsService();
            $message = "Your Aetia verification code is: {$verificationCode}. This code expires in 10 minutes.";

            $smsResult = $smsService->sendSms($user['phone_number'], $message, $userId, 'verification');

            if ($smsResult['success']) {
                return ['success' => true, 'message' => 'Verification code sent to your phone!'];
            } else {
                return ['success' => false, 'message' => 'Failed to send verification code: ' . $smsResult['message']];
            }

        } catch (Exception $e) {
            error_log("Send SMS verification code error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while sending the verification code.'];
        }
    }

    /**
     * Verify SMS code entered by user
     * @param int $userId The user ID
     * @param string $verificationCode The code entered by the user
     * @return array Success/failure result
     */
    public function verifySmsCode($userId, $verificationCode) {
        try {
            $this->ensureConnection();

            // Get the latest verification attempt for this user
            $stmt = $this->mysqli->prepare("
                SELECT verification_code, expires_at, attempts
                FROM sms_verification_attempts
                WHERE user_id = ? AND verified = 0 AND expires_at > NOW()
                ORDER BY last_attempt DESC
                LIMIT 1
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $attempt = $result->fetch_assoc();
            $stmt->close();

            if (!$attempt) {
                return ['success' => false, 'message' => 'No valid verification code found. Please request a new code.'];
            }

            // Check if code matches
            if ($attempt['verification_code'] !== $verificationCode) {
                return ['success' => false, 'message' => 'Invalid verification code. Please try again.'];
            }

            // Mark as verified in sms_verification_attempts
            $stmt = $this->mysqli->prepare("
                UPDATE sms_verification_attempts
                SET verified = 1
                WHERE user_id = ? AND verification_code = ? AND verified = 0
            ");
            $stmt->bind_param("is", $userId, $verificationCode);
            $stmt->execute();
            $stmt->close();

            // Update user's phone_verified status
            $stmt = $this->mysqli->prepare("
                UPDATE users
                SET phone_verified = 1, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->bind_param("i", $userId);
            $result = $stmt->execute();
            $stmt->close();

            if ($result) {
                return ['success' => true, 'message' => 'Phone number verified successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to update verification status.'];
            }

        } catch (Exception $e) {
            error_log("Verify SMS code error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while verifying the code.'];
        }
    }
}
?>
