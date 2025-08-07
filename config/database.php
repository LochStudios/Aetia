<?php
// config/database.php - Database configuration for Aetia Talent Agency

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
                public_email VARCHAR(100) NULL,
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
                signup_email_sent BOOLEAN DEFAULT FALSE,
                public_email VARCHAR(100) NULL,
                abn_acn VARCHAR(20) NULL,
                address TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            $this->mysqli->query($createUsersTable);
            
            // Create social_connections table
            $createSocialTable = "
            CREATE TABLE IF NOT EXISTS social_connections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                platform ENUM('twitch', 'youtube', 'twitter', 'instagram', 'discord') NOT NULL,
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

            // Create messages table
            $createMessagesTable = "
            CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                subject VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
                status ENUM('unread', 'read', 'responded', 'closed', 'archived') DEFAULT 'unread',
                tags VARCHAR(255) DEFAULT NULL,
                manual_review BOOLEAN DEFAULT FALSE,
                manual_review_by INT NULL,
                manual_review_at TIMESTAMP NULL,
                manual_review_reason TEXT NULL,
                created_by INT NOT NULL,
                archived_by INT NULL,
                archived_at TIMESTAMP NULL,
                archive_reason TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (archived_by) REFERENCES users(id),
                FOREIGN KEY (manual_review_by) REFERENCES users(id),
                INDEX idx_user_status (user_id, status),
                INDEX idx_tags (tags),
                INDEX idx_created_at (created_at),
                INDEX idx_manual_review (manual_review, manual_review_at)
            )";
            
            $this->mysqli->query($createMessagesTable);

            // Create message_comments table
            $createCommentsTable = "
            CREATE TABLE IF NOT EXISTS message_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                user_id INT NOT NULL,
                comment TEXT NOT NULL,
                is_admin_comment BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_message_created (message_id, created_at)
            )";
            
            $this->mysqli->query($createCommentsTable);

            // Create message_attachments table
            $createAttachmentsTable = "
            CREATE TABLE IF NOT EXISTS message_attachments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                user_id INT NOT NULL,
                filename VARCHAR(255) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                file_size INT NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                file_path VARCHAR(500) NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id),
                INDEX idx_message_attachments (message_id),
                INDEX idx_user_attachments (user_id)
            )";
            
            $this->mysqli->query($createAttachmentsTable);

            // Create contact_submissions table
            $createContactTable = "
            CREATE TABLE IF NOT EXISTS contact_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(255) DEFAULT NULL,
                message TEXT NOT NULL,
                status ENUM('new', 'read', 'responded', 'closed') DEFAULT 'new',
                priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
                ip_address VARCHAR(45),
                user_agent TEXT,
                geo_data JSON,
                responded_by INT NULL,
                responded_at TIMESTAMP NULL,
                response_notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (responded_by) REFERENCES users(id),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at),
                INDEX idx_email (email)
            )";
            
            $this->mysqli->query($createContactTable);

            // Create email_logs table
            $createEmailLogsTable = "
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_user_id INT NULL,
                recipient_email VARCHAR(100) NOT NULL,
                recipient_name VARCHAR(100),
                sender_user_id INT NULL,
                email_type VARCHAR(50) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                body_content TEXT NOT NULL,
                html_content TEXT,
                status ENUM('sent', 'failed', 'queued') DEFAULT 'sent',
                error_message TEXT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                delivery_attempts INT DEFAULT 1,
                email_service_response TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (recipient_user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_recipient_email (recipient_email),
                INDEX idx_recipient_user (recipient_user_id),
                INDEX idx_email_type (email_type),
                INDEX idx_status (status),
                INDEX idx_sent_at (sent_at),
                INDEX idx_created_at (created_at)
            )";
            
            $this->mysqli->query($createEmailLogsTable);

            // Create user_documents table
            $createUserDocumentsTable = "
            CREATE TABLE IF NOT EXISTS user_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                document_type VARCHAR(50) NOT NULL,
                original_filename VARCHAR(255) NOT NULL,
                s3_key VARCHAR(500) NOT NULL,
                s3_url VARCHAR(500) NOT NULL,
                file_size BIGINT NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                description TEXT DEFAULT NULL,
                archived BOOLEAN DEFAULT FALSE,
                archived_reason VARCHAR(255) DEFAULT NULL,
                uploaded_by INT NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_document_type (document_type),
                INDEX idx_uploaded_by (uploaded_by),
                INDEX idx_uploaded_at (uploaded_at)
            )";
            
            $this->mysqli->query($createUserDocumentsTable);

            // Create user_contracts table
            $createUserContractsTable = "
            CREATE TABLE IF NOT EXISTS user_contracts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                contract_type VARCHAR(100) NOT NULL DEFAULT 'Communications Services Agreement',
                contract_title VARCHAR(255) NOT NULL,
                client_name VARCHAR(255) NOT NULL,
                client_email VARCHAR(255) NOT NULL,
                client_phone VARCHAR(50),
                client_address TEXT,
                service_description TEXT NOT NULL,
                contract_duration VARCHAR(100),
                monthly_fee DECIMAL(10,2),
                setup_fee DECIMAL(10,2) DEFAULT 0.00,
                additional_terms TEXT,
                contract_content LONGTEXT NOT NULL,
                pdf_s3_key VARCHAR(500),
                pdf_url VARCHAR(500),
                status ENUM('draft', 'sent', 'signed', 'completed', 'cancelled') DEFAULT 'draft',
                generated_by INT NOT NULL,
                company_accepted_date TIMESTAMP NULL,
                user_accepted_date TIMESTAMP NULL,
                signed_date TIMESTAMP NULL,
                signature_data TEXT,
                signature_ip VARCHAR(45),
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status),
                INDEX idx_contract_type (contract_type),
                INDEX idx_generated_by (generated_by),
                INDEX idx_created_at (created_at)
            )";
            
            $this->mysqli->query($createUserContractsTable);

            // Add new columns to existing tables (for existing databases)
            $this->addMissingColumns();
            $this->addMissingMessageColumns();
            $this->addMissingContactColumns();
            $this->updateSocialConnectionsPlatforms();

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
                'deactivation_date' => 'TIMESTAMP NULL',
                'signup_email_sent' => 'BOOLEAN DEFAULT FALSE',
                'public_email' => 'VARCHAR(100) NULL',
                'abn_acn' => 'VARCHAR(20) NULL',
                'address' => 'TEXT NULL'
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
            
            // Add missing columns to user_contracts table
            $contractColumnsToAdd = [
                'company_accepted_date' => 'TIMESTAMP NULL',
                'user_accepted_date' => 'TIMESTAMP NULL'
            ];
            
            foreach ($contractColumnsToAdd as $columnName => $columnDefinition) {
                // Check if column exists
                $checkColumn = "SHOW COLUMNS FROM user_contracts LIKE '{$columnName}'";
                $result = $this->mysqli->query($checkColumn);
                
                if ($result && $result->num_rows == 0) {
                    // Column doesn't exist, add it
                    $alterQuery = "ALTER TABLE user_contracts ADD COLUMN {$columnName} {$columnDefinition}";
                    $this->mysqli->query($alterQuery);
                    error_log("Added column '{$columnName}' to user_contracts table");
                }
            }
            
            // Add missing columns to user_documents table
            $documentColumnsToAdd = [
                'archived' => 'BOOLEAN DEFAULT FALSE',
                'archived_reason' => 'VARCHAR(255) DEFAULT NULL'
            ];
            
            foreach ($documentColumnsToAdd as $columnName => $columnDefinition) {
                // Check if column exists
                $checkColumn = "SHOW COLUMNS FROM user_documents LIKE '{$columnName}'";
                $result = $this->mysqli->query($checkColumn);
                
                if ($result && $result->num_rows == 0) {
                    // Column doesn't exist, add it
                    $alterQuery = "ALTER TABLE user_documents ADD COLUMN {$columnName} {$columnDefinition}";
                    $this->mysqli->query($alterQuery);
                    error_log("Added column '{$columnName}' to user_documents table");
                }
            }
        } catch (Exception $e) {
            error_log("Add missing columns error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Add missing columns to existing messages table
    private function addMissingMessageColumns() {
        try {
            // Check if columns exist and add them if they don't
            $columnsToAdd = [
                'tags' => 'VARCHAR(255) DEFAULT NULL',
                'manual_review' => 'BOOLEAN DEFAULT FALSE',
                'manual_review_by' => 'INT NULL',
                'manual_review_at' => 'TIMESTAMP NULL',
                'manual_review_reason' => 'TEXT NULL',
                'archived_by' => 'INT NULL',
                'archived_at' => 'TIMESTAMP NULL',
                'archive_reason' => 'TEXT NULL'
            ];
            
            // First, add all missing columns
            foreach ($columnsToAdd as $columnName => $columnDefinition) {
                // Check if column exists
                $checkColumn = "SHOW COLUMNS FROM messages LIKE '{$columnName}'";
                $result = $this->mysqli->query($checkColumn);
                
                if ($result && $result->num_rows == 0) {
                    // Column doesn't exist, add it
                    $alterQuery = "ALTER TABLE messages ADD COLUMN {$columnName} {$columnDefinition}";
                    if ($this->mysqli->query($alterQuery)) {
                        error_log("Added column '{$columnName}' to messages table");
                    }
                }
            }
            
            // After all columns are added, add indexes and foreign keys
            $this->addMessageIndexesAndForeignKeys();
            
        } catch (Exception $e) {
            error_log("Add missing message columns error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Add indexes and foreign keys for messages table after columns exist
    private function addMessageIndexesAndForeignKeys() {
        try {
            // Add tags index if it doesn't exist
            $checkTagsIndex = "SHOW INDEX FROM messages WHERE Key_name = 'idx_tags'";
            $result = $this->mysqli->query($checkTagsIndex);
            if ($result && $result->num_rows == 0) {
                $indexQuery = "ALTER TABLE messages ADD INDEX idx_tags (tags)";
                if ($this->mysqli->query($indexQuery)) {
                    error_log("Added index 'idx_tags' to messages table");
                }
            }
            
            // Add manual review index if both columns exist and index doesn't exist
            $checkManualReviewIndex = "SHOW INDEX FROM messages WHERE Key_name = 'idx_manual_review'";
            $result = $this->mysqli->query($checkManualReviewIndex);
            if ($result && $result->num_rows == 0) {
                // Check if both manual_review columns exist
                $checkManualReview = "SHOW COLUMNS FROM messages LIKE 'manual_review'";
                $checkManualReviewAt = "SHOW COLUMNS FROM messages LIKE 'manual_review_at'";
                $result1 = $this->mysqli->query($checkManualReview);
                $result2 = $this->mysqli->query($checkManualReviewAt);
                
                if ($result1 && $result1->num_rows > 0 && $result2 && $result2->num_rows > 0) {
                    $indexQuery = "ALTER TABLE messages ADD INDEX idx_manual_review (manual_review, manual_review_at)";
                    if ($this->mysqli->query($indexQuery)) {
                        error_log("Added index 'idx_manual_review' to messages table");
                    }
                }
            }
            
            // Add foreign key for manual_review_by if column exists and FK doesn't exist
            $checkManualReviewByFK = "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = 'messages' AND CONSTRAINT_SCHEMA = DATABASE()";
            $result = $this->mysqli->query($checkManualReviewByFK);
            $hasManualReviewFK = false;
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Check if this FK involves manual_review_by column
                    $checkFKColumn = "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_NAME = '{$row['CONSTRAINT_NAME']}' AND COLUMN_NAME = 'manual_review_by'";
                    $fkColumnResult = $this->mysqli->query($checkFKColumn);
                    if ($fkColumnResult && $fkColumnResult->num_rows > 0) {
                        $hasManualReviewFK = true;
                        break;
                    }
                }
            }
            
            if (!$hasManualReviewFK) {
                $checkColumn = "SHOW COLUMNS FROM messages LIKE 'manual_review_by'";
                $columnResult = $this->mysqli->query($checkColumn);
                if ($columnResult && $columnResult->num_rows > 0) {
                    $fkQuery = "ALTER TABLE messages ADD FOREIGN KEY (manual_review_by) REFERENCES users(id)";
                    if ($this->mysqli->query($fkQuery)) {
                        error_log("Added foreign key 'manual_review_by' to messages table");
                    }
                }
            }
            
            // Add foreign key for archived_by if column exists and FK doesn't exist
            $checkArchivedByFK = "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = 'messages' AND CONSTRAINT_SCHEMA = DATABASE()";
            $result = $this->mysqli->query($checkArchivedByFK);
            $hasArchivedByFK = false;
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    // Check if this FK involves archived_by column
                    $checkFKColumn = "SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_NAME = '{$row['CONSTRAINT_NAME']}' AND COLUMN_NAME = 'archived_by'";
                    $fkColumnResult = $this->mysqli->query($checkFKColumn);
                    if ($fkColumnResult && $fkColumnResult->num_rows > 0) {
                        $hasArchivedByFK = true;
                        break;
                    }
                }
            }
            
            if (!$hasArchivedByFK) {
                $checkColumn = "SHOW COLUMNS FROM messages LIKE 'archived_by'";
                $columnResult = $this->mysqli->query($checkColumn);
                if ($columnResult && $columnResult->num_rows > 0) {
                    $fkQuery = "ALTER TABLE messages ADD FOREIGN KEY (archived_by) REFERENCES users(id)";
                    if ($this->mysqli->query($fkQuery)) {
                        error_log("Added foreign key 'archived_by' to messages table");
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Add message indexes and foreign keys error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Add missing columns to existing contact_submissions table
    private function addMissingContactColumns() {
        try {
            // Check if columns exist and add them if they don't
            $columnsToAdd = [
                'geo_data' => 'JSON'
            ];
            foreach ($columnsToAdd as $columnName => $columnDefinition) {
                // Check if column exists
                $checkColumn = "SHOW COLUMNS FROM contact_submissions LIKE '{$columnName}'";
                $result = $this->mysqli->query($checkColumn);
                if ($result && $result->num_rows == 0) {
                    // Column doesn't exist, add it
                    $alterQuery = "ALTER TABLE contact_submissions ADD COLUMN {$columnName} {$columnDefinition}";
                    if ($this->mysqli->query($alterQuery)) {
                        error_log("Added column '{$columnName}' to contact_submissions table");
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Add missing contact columns error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Update social_connections platform ENUM to include Discord
    private function updateSocialConnectionsPlatforms() {
        try {
            // Check if the social_connections table exists first
            $checkTable = "SHOW TABLES LIKE 'social_connections'";
            $result = $this->mysqli->query($checkTable);
            if ($result && $result->num_rows > 0) {
                // Check current ENUM values
                $checkEnumQuery = "SHOW COLUMNS FROM social_connections WHERE Field = 'platform'";
                $result = $this->mysqli->query($checkEnumQuery);
                if ($result && $result->num_rows > 0) {
                    $column = $result->fetch_assoc();
                    $type = $column['Type'];
                    // Check if 'discord' is already in the ENUM
                    if (strpos($type, "'discord'") === false) {
                        // Add discord to the ENUM
                        $alterQuery = "ALTER TABLE social_connections MODIFY COLUMN platform ENUM('twitch', 'youtube', 'twitter', 'instagram', 'discord') NOT NULL";
                        if ($this->mysqli->query($alterQuery)) {
                            error_log("Successfully added 'discord' to social_connections platform ENUM");
                        } else {
                            error_log("Failed to add 'discord' to platform ENUM: " . $this->mysqli->error);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Update social connections platforms error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
}
?>
