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
            // Try different possible paths for the database configuration
            $possiblePaths = [
                '/home/aetiacom/web-config/database.php',
                __DIR__ . '/../web-config/database.php',
                realpath(__DIR__ . '/../web-config/database.php')
            ];
            
            $configFile = null;
            foreach ($possiblePaths as $path) {
                if ($path && file_exists($path)) {
                    $configFile = $path;
                    break;
                }
            }
            
            if (!$configFile) {
                throw new Exception("Database configuration file 'database.php' not found in any of the expected locations");
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
    
    public function __destruct() {
        $this->close();
    }
}

// Initialize database tables
function initializeDatabase() {
    try {
        $db = new Database();
        $mysqli = $db->getConnection();
        
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
            is_verified BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (!$mysqli->query($createUsersTable)) {
            throw new Exception("Error creating users table: " . $mysqli->error);
        }
        
        // Create social_connections table for multiple social accounts
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
        
        if (!$mysqli->query($createSocialTable)) {
            throw new Exception("Error creating social_connections table: " . $mysqli->error);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
        return false;
    }
}
?>
