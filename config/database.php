<?php
// config/database.php - Database configuration for Aetia Talant Agency

class Database {
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset;
    private $mysqli;

    public function __construct() {
        try {
            $configFile = '/home/aetiacom/web-config/database.php';
            
            if (!file_exists($configFile)) {
                throw new Exception("Database configuration file not found at: {$configFile}");
            }
            
            // Include the configuration file to load variables
            include $configFile;
            
            // Check that required variables are defined
            if (!isset($serverhost) || !isset($username) || !isset($password) || !isset($databasename)) {
                throw new Exception("Database configuration file must define \$serverhost, \$username, \$password, and \$databasename variables");
            }
            
            $this->host = $serverhost;
            $this->database = $databasename;
            $this->username = $username;
            $this->password = $password;
            $this->charset = 'utf8mb4';
            
            // Create MySQLi connection
            $this->mysqli = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            // Check connection
            if ($this->mysqli->connect_error) {
                throw new Exception('Connection failed: ' . $this->mysqli->connect_error);
            }
            
            // Set charset
            $this->mysqli->set_charset($this->charset);
            
            // Auto-initialize tables on first connection
            $this->initializeTables();
            
        } catch (Exception $e) {
            error_log('Database configuration error: ' . $e->getMessage());
            throw new Exception('Database connection failed. Please check your configuration.');
        }
    }

    public function getConnection() {
        return $this->mysqli;
    }
    
    public function close() {
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }
    
    public function __destruct() {}
    
    // Auto-initialize tables if they don't exist
    private function initializeTables() {
        try {
            // Create users table
            $createUsersTable = "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password_hash VARCHAR(255),
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                profile_image VARCHAR(255),
                account_type ENUM('manual', 'twitch', 'youtube', 'twitter', 'instagram') DEFAULT 'manual',
                social_id VARCHAR(100),
                social_username VARCHAR(100),
                social_data JSON,
                approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                approval_date TIMESTAMP NULL,
                approved_by VARCHAR(100),
                rejection_reason TEXT,
                contact_attempted BOOLEAN DEFAULT FALSE,
                contact_date TIMESTAMP NULL,
                contact_notes TEXT,
                is_admin BOOLEAN DEFAULT FALSE,
                is_verified BOOLEAN DEFAULT FALSE,
                verified_date TIMESTAMP NULL,
                verified_by VARCHAR(100),
                is_active BOOLEAN DEFAULT TRUE,
                deactivation_reason TEXT,
                deactivated_by VARCHAR(100),
                deactivation_date TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            $this->mysqli->query($createUsersTable);
            
            // Create social_connections table
            $createSocialTable = "
            CREATE TABLE IF NOT EXISTS social_connections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                platform ENUM('twitch', 'youtube', 'twitter', 'instagram') NOT NULL,
                social_id VARCHAR(100) NOT NULL,
                social_username VARCHAR(100),
                access_token TEXT,
                refresh_token TEXT,
                expires_at TIMESTAMP NULL,
                social_data JSON,
                is_primary BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_platform_social (platform, social_id)
            )";
            
            $this->mysqli->query($createSocialTable);
            
            // Create password_reset_tokens table
            $createPasswordResetTable = "
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_token (user_id)
            )";
            
            $this->mysqli->query($createPasswordResetTable);

            // Add new columns to existing users table (for existing databases)
            $this->addMissingColumns();

            // Create initial admin user if no users exist
            $this->createInitialAdmin();
            
        } catch (Exception $e) {
            error_log("Auto table initialization error: " . $e->getMessage());
            // Don't throw exception - let the app continue even if tables exist
        }
    }
    
    // Create initial admin user on first setup
    private function createInitialAdmin() {
        try {
            // Check if any users exist
            $result = $this->mysqli->query("SELECT COUNT(*) as count FROM users");
            $count = $result->fetch_assoc()['count'];
            
            if ($count == 0) {
                // Generate random 16 character password
                $adminPassword = $this->generateRandomPassword(16);
                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                
                // Create admin user
                $stmt = $this->mysqli->prepare("
                    INSERT INTO users (username, email, password_hash, account_type, approval_status, is_admin, is_verified, is_active, first_name, last_name, approved_by) 
                    VALUES ('admin', 'admin@aetia.com.au', ?, 'manual', 'approved', 1, 1, 1, 'System', 'Administrator', 'Auto-Generated')
                ");
                
                $stmt->bind_param("s", $passwordHash);
                $result = $stmt->execute();
                $stmt->close();
                
                if ($result) {
                    // Store the password in a temporary file for display on login page
                    $tempPasswordFile = '/tmp/aetia_admin_initial_password.txt';
                    file_put_contents($tempPasswordFile, $adminPassword);
                    chmod($tempPasswordFile, 0600); // Secure permissions
                    
                    error_log("Initial admin user created - Username: admin, Password stored in temp file");
                }
            }
        } catch (Exception $e) {
            error_log("Initial admin creation error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Generate random password
    private function generateRandomPassword($length = 16) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $charactersLength = strlen($characters);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $password;
    }
    
    // Add missing columns to existing users table
    private function addMissingColumns() {
        try {
            // Check if columns exist and add them if they don't
            $columnsToAdd = [
                'verified_date' => 'TIMESTAMP NULL',
                'verified_by' => 'VARCHAR(100)',
                'deactivation_reason' => 'TEXT',
                'deactivated_by' => 'VARCHAR(100)',
                'deactivation_date' => 'TIMESTAMP NULL'
            ];
            
            foreach ($columnsToAdd as $columnName => $columnDefinition) {
                // Check if column exists
                $checkColumn = "SHOW COLUMNS FROM users LIKE '{$columnName}'";
                $result = $this->mysqli->query($checkColumn);
                
                if ($result->num_rows == 0) {
                    // Column doesn't exist, add it
                    $alterQuery = "ALTER TABLE users ADD COLUMN {$columnName} {$columnDefinition}";
                    $this->mysqli->query($alterQuery);
                    error_log("Added column '{$columnName}' to users table");
                }
            }
        } catch (Exception $e) {
            error_log("Add missing columns error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
}
?>
