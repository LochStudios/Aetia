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
                account_type ENUM('manual', 'twitch', 'google', 'youtube', 'discord', 'twitter', 'instagram') DEFAULT 'manual',
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
                sms_enabled BOOLEAN DEFAULT FALSE,
                phone_number VARCHAR(20) DEFAULT NULL,
                phone_verified BOOLEAN DEFAULT FALSE,
                phone_verification_code VARCHAR(10) DEFAULT NULL,
                phone_verification_expires DATETIME DEFAULT NULL,
                is_suspended BOOLEAN DEFAULT FALSE,
                suspension_reason TEXT,
                suspended_by VARCHAR(100),
                suspended_date TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )";
            
            $this->mysqli->query($createUsersTable);
            
            // Create social_connections table
            $createSocialTable = "
            CREATE TABLE IF NOT EXISTS social_connections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                platform ENUM('twitch', 'google', 'youtube', 'twitter', 'instagram', 'discord') NOT NULL,
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

            // Create email_verification_tokens table for admin-triggered verification codes
            $createEmailVerificationTable = "
            CREATE TABLE IF NOT EXISTS email_verification_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_verification (user_id)
            )";
            
            $this->mysqli->query($createEmailVerificationTable);

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
                status ENUM('new', 'spam', 'read', 'responded', 'closed') DEFAULT 'new',
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

            // Create turnstile_verifications table to store siteverify results and prevent replay
            $createTurnstileTable = "
            CREATE TABLE IF NOT EXISTS turnstile_verifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token_hash CHAR(64) NOT NULL,
                idempotency_key VARCHAR(64) DEFAULT NULL,
                remoteip VARCHAR(45) DEFAULT NULL,
                success BOOLEAN DEFAULT FALSE,
                response_json LONGTEXT,
                action VARCHAR(100) DEFAULT NULL,
                cdata VARCHAR(255) DEFAULT NULL,
                ephemeral_id VARCHAR(255) DEFAULT NULL,
                hostname VARCHAR(255) DEFAULT NULL,
                challenge_ts TIMESTAMP NULL,
                error_codes JSON DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_token_hash (token_hash),
                INDEX idx_idempotency (idempotency_key),
                INDEX idx_created_at (created_at)
            )";
            $this->mysqli->query($createTurnstileTable);

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

            // Create billing_reports table
            $createBillingReportsTable = "
            CREATE TABLE IF NOT EXISTS billing_reports (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_period_start DATE NOT NULL,
                report_period_end DATE NOT NULL,
                report_data JSON NOT NULL,
                total_clients INT NOT NULL DEFAULT 0,
                total_messages INT NOT NULL DEFAULT 0,
                total_manual_reviews INT NOT NULL DEFAULT 0,
                total_sms INT NOT NULL DEFAULT 0,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                generated_by INT NOT NULL,
                generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY unique_period (report_period_start, report_period_end),
                INDEX idx_period_start (report_period_start),
                INDEX idx_generated_by (generated_by),
                INDEX idx_generated_at (generated_at)
            )";
            
            $this->mysqli->query($createBillingReportsTable);
            
            // Add total_sms column to existing billing_reports table if it doesn't exist
            $addSmsColumnQuery = "
            ALTER TABLE billing_reports 
            ADD COLUMN IF NOT EXISTS total_sms INT NOT NULL DEFAULT 0 
            AFTER total_manual_reviews
            ";
            $this->mysqli->query($addSmsColumnQuery);

            // Create SMS logs table
            $createSmsLogsTable = "
            CREATE TABLE IF NOT EXISTS sms_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                to_number VARCHAR(20) NOT NULL,
                message_content TEXT NOT NULL,
                provider VARCHAR(20) NOT NULL DEFAULT 'twilio',
                success BOOLEAN DEFAULT FALSE,
                response_message TEXT DEFAULT NULL,
                provider_message_id VARCHAR(100) DEFAULT NULL,
                purpose VARCHAR(50) DEFAULT 'notification',
                client_ip VARCHAR(45) DEFAULT NULL,
                sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_sent_at (sent_at),
                INDEX idx_success (success),
                INDEX idx_provider (provider),
                INDEX idx_purpose (purpose),
                INDEX idx_client_ip (client_ip),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )";
            
            $this->mysqli->query($createSmsLogsTable);

            // Create SMS verification attempts table
            $createSmsVerificationTable = "
            CREATE TABLE IF NOT EXISTS sms_verification_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                verification_code VARCHAR(10) NOT NULL,
                attempts INT DEFAULT 1,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                verified BOOLEAN DEFAULT FALSE,
                INDEX idx_user_id (user_id),
                INDEX idx_phone_number (phone_number),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )";
            
            $this->mysqli->query($createSmsVerificationTable);

            // Create user bills table for individual user billing
            $createUserBillsTable = "
            CREATE TABLE IF NOT EXISTS user_bills (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                billing_period_start DATE NOT NULL,
                billing_period_end DATE NOT NULL,
                standard_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                manual_review_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                sms_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                message_count INT NOT NULL DEFAULT 0,
                manual_review_count INT NOT NULL DEFAULT 0,
                sms_count INT NOT NULL DEFAULT 0,
                bill_status ENUM('draft', 'sent', 'overdue', 'paid', 'cancelled') DEFAULT 'draft',
                invoice_sent_date TIMESTAMP NULL,
                due_date DATE NULL,
                payment_date TIMESTAMP NULL,
                payment_method VARCHAR(100) NULL,
                payment_reference VARCHAR(255) NULL,
                account_credit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                notes TEXT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_billing_period (billing_period_start, billing_period_end),
                INDEX idx_bill_status (bill_status),
                INDEX idx_due_date (due_date),
                INDEX idx_created_at (created_at),
                UNIQUE KEY unique_user_period (user_id, billing_period_start, billing_period_end)
            )";
            
            $this->mysqli->query($createUserBillsTable);

            // Create user invoice documents table to link invoices to bills
            $createUserInvoiceDocumentsTable = "
            CREATE TABLE IF NOT EXISTS user_invoice_documents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_bill_id INT NOT NULL,
                document_id INT NOT NULL,
                invoice_type ENUM('generated', 'payment_receipt', 'credit_note') DEFAULT 'generated',
                invoice_number VARCHAR(100) NULL,
                invoice_amount DECIMAL(10,2) NULL,
                is_primary_invoice BOOLEAN DEFAULT FALSE,
                uploaded_by INT NOT NULL,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_bill_id) REFERENCES user_bills(id) ON DELETE CASCADE,
                FOREIGN KEY (document_id) REFERENCES user_documents(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_bill (user_bill_id),
                INDEX idx_document (document_id),
                INDEX idx_invoice_type (invoice_type),
                UNIQUE KEY unique_bill_document (user_bill_id, document_id)
            )";
            
            $this->mysqli->query($createUserInvoiceDocumentsTable);

            // Add new columns to existing tables (for existing databases)
            $this->addMissingColumns();
            $this->addMissingMessageColumns();
            $this->addMissingContactColumns();
            $this->addMissingSmsTables();
            $this->addMissingBillingTables();
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
                'address' => 'TEXT NULL',
                'sms_enabled' => 'BOOLEAN DEFAULT FALSE',
                'phone_number' => 'VARCHAR(20) DEFAULT NULL',
                'phone_verified' => 'BOOLEAN DEFAULT FALSE',
                'phone_verification_code' => 'VARCHAR(10) DEFAULT NULL',
                'phone_verification_expires' => 'DATETIME DEFAULT NULL',
                'is_suspended' => 'BOOLEAN DEFAULT FALSE',
                'suspension_reason' => 'TEXT',
                'suspended_by' => 'VARCHAR(100)',
                'suspended_date' => 'TIMESTAMP NULL'
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
            $checkManualReviewByFK = "SELECT COUNT(*) as count FROM information_schema.KEY_COLUMN_USAGE 
                                      WHERE TABLE_SCHEMA = DATABASE() 
                                      AND TABLE_NAME = 'messages' 
                                      AND COLUMN_NAME = 'manual_review_by' 
                                      AND REFERENCED_TABLE_NAME = 'users'";
            $result = $this->mysqli->query($checkManualReviewByFK);
            $fkExists = $result ? $result->fetch_assoc()['count'] > 0 : false;
            
            if (!$fkExists) {
                $checkColumn = "SHOW COLUMNS FROM messages LIKE 'manual_review_by'";
                $columnResult = $this->mysqli->query($checkColumn);
                if ($columnResult && $columnResult->num_rows > 0) {
                    $fkQuery = "ALTER TABLE messages ADD FOREIGN KEY (manual_review_by) REFERENCES users(id)";
                    if ($this->mysqli->query($fkQuery)) {}
                }
            }
            
            // Add foreign key for archived_by if column exists and FK doesn't exist
            $checkArchivedByFK = "SELECT COUNT(*) as count FROM information_schema.KEY_COLUMN_USAGE 
                                  WHERE TABLE_SCHEMA = DATABASE() 
                                  AND TABLE_NAME = 'messages' 
                                  AND COLUMN_NAME = 'archived_by' 
                                  AND REFERENCED_TABLE_NAME = 'users'";
            $result = $this->mysqli->query($checkArchivedByFK);
            $fkExists = $result ? $result->fetch_assoc()['count'] > 0 : false;
            
            if (!$fkExists) {
                $checkColumn = "SHOW COLUMNS FROM messages LIKE 'archived_by'";
                $columnResult = $this->mysqli->query($checkColumn);
                if ($columnResult && $columnResult->num_rows > 0) {
                    $fkQuery = "ALTER TABLE messages ADD FOREIGN KEY (archived_by) REFERENCES users(id)";
                    if ($this->mysqli->query($fkQuery)) {}
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
            // Ensure contact_submissions table exists
            $checkTable = "SHOW TABLES LIKE 'contact_submissions'";
            $tableResult = $this->mysqli->query($checkTable);
            if (!($tableResult && $tableResult->num_rows > 0)) {
                return;
            }
            // Ensure status column exists and has the expected ENUM values/default
            $expectedEnum = "ENUM('new','spam','read','responded','closed')";
            $expectedDefault = 'new';
            $checkStatus = "SHOW COLUMNS FROM contact_submissions LIKE 'status'";
            $result = $this->mysqli->query($checkStatus);
            if ($result && $result->num_rows == 0) {
                // Column doesn't exist, add it with the exact enum and default
                $alterStatus = "ALTER TABLE contact_submissions ADD COLUMN status {$expectedEnum} DEFAULT '{$expectedDefault}'";
                if ($this->mysqli->query($alterStatus)) {
                    error_log("Added 'status' column to contact_submissions with expected ENUM/default");
                }
            } else {
                // Column exists - verify ENUM values and default, modify if necessary
                $col = $result->fetch_assoc();
                $type = isset($col['Type']) ? $col['Type'] : '';
                $default = isset($col['Default']) ? $col['Default'] : null;
                $needsModify = false;
                // Check presence of all expected tokens (simple check)
                $requiredTokens = ["'new'", "'spam'", "'read'", "'responded'", "'closed'"];
                foreach ($requiredTokens as $token) {
                    if (strpos($type, $token) === false) {
                        $needsModify = true;
                        break;
                    }
                }
                if ($default !== $expectedDefault) {
                    $needsModify = true;
                }
                if ($needsModify) {
                    $modifyQuery = "ALTER TABLE contact_submissions MODIFY COLUMN status {$expectedEnum} DEFAULT '{$expectedDefault}'";
                    if ($this->mysqli->query($modifyQuery)) {
                        error_log("Modified 'status' column on contact_submissions to expected ENUM/default");
                    } else {
                        error_log("Failed to modify 'status' column on contact_submissions: " . $this->mysqli->error);
                    }
                }
            }
            // Add geo_data JSON column if missing
            $checkGeo = "SHOW COLUMNS FROM contact_submissions LIKE 'geo_data'";
            $geoResult = $this->mysqli->query($checkGeo);
            if ($geoResult && $geoResult->num_rows == 0) {
                $alterGeo = "ALTER TABLE contact_submissions ADD COLUMN geo_data JSON";
                if ($this->mysqli->query($alterGeo)) {
                    error_log("Added column 'geo_data' to contact_submissions table");
                }
            }
            // Ensure common indexes exist (idx_status, idx_created_at, idx_email)
            $indexesToEnsure = [
                'idx_status' => "ALTER TABLE contact_submissions ADD INDEX idx_status (status)",
                'idx_created_at' => "ALTER TABLE contact_submissions ADD INDEX idx_created_at (created_at)",
                'idx_email' => "ALTER TABLE contact_submissions ADD INDEX idx_email (email)"
            ];
            foreach ($indexesToEnsure as $indexName => $indexQuery) {
                $checkIndex = "SHOW INDEX FROM contact_submissions WHERE Key_name = '{$indexName}'";
                $idxResult = $this->mysqli->query($checkIndex);
                if ($idxResult && $idxResult->num_rows == 0) {
                    $this->mysqli->query($indexQuery);
                }
            }

            // Ensure turnstile_verifications extra columns exist for older DBs
            $checkTurnstileTable = "SHOW TABLES LIKE 'turnstile_verifications'";
            $ttRes = $this->mysqli->query($checkTurnstileTable);
            if ($ttRes && $ttRes->num_rows > 0) {
                $turnstileColumns = [
                    'action' => "VARCHAR(100) DEFAULT NULL",
                    'cdata' => "VARCHAR(255) DEFAULT NULL",
                    'ephemeral_id' => "VARCHAR(255) DEFAULT NULL",
                    'hostname' => "VARCHAR(255) DEFAULT NULL",
                    'challenge_ts' => "TIMESTAMP NULL",
                    'error_codes' => "JSON DEFAULT NULL"
                ];
                foreach ($turnstileColumns as $col => $def) {
                    $checkCol = "SHOW COLUMNS FROM turnstile_verifications LIKE '{$col}'";
                    $colRes = $this->mysqli->query($checkCol);
                    if ($colRes && $colRes->num_rows == 0) {
                        $alter = "ALTER TABLE turnstile_verifications ADD COLUMN {$col} {$def}";
                        $this->mysqli->query($alter);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Add missing contact columns error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Update social_connections platform ENUM to include YouTube/Discord
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
                    // Check if 'youtube' and 'discord' are in the ENUM
                    if (strpos($type, "'youtube'") === false || strpos($type, "'discord'") === false) {
                        $alterQuery = "ALTER TABLE social_connections MODIFY COLUMN platform ENUM('twitch', 'google', 'youtube', 'twitter', 'instagram', 'discord') NOT NULL";
                        if ($this->mysqli->query($alterQuery)) {
                            error_log("Successfully updated social_connections platform ENUM with YouTube/Discord");
                        } else {
                            error_log("Failed to update platform ENUM: " . $this->mysqli->error);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Update social connections platforms error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Add missing SMS tables for existing databases
    private function addMissingSmsTables() {
        try {
            // Check if sms_logs table exists
            $checkSmsLogsTable = "SHOW TABLES LIKE 'sms_logs'";
            $result = $this->mysqli->query($checkSmsLogsTable);
            if ($result && $result->num_rows == 0) {
                // Create SMS logs table
                $createSmsLogsTable = "
                CREATE TABLE sms_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT DEFAULT NULL,
                    to_number VARCHAR(20) NOT NULL,
                    message_content TEXT NOT NULL,
                    provider VARCHAR(20) NOT NULL DEFAULT 'twilio',
                    success BOOLEAN DEFAULT FALSE,
                    response_message TEXT DEFAULT NULL,
                    provider_message_id VARCHAR(100) DEFAULT NULL,
                    purpose VARCHAR(50) DEFAULT 'notification',
                    client_ip VARCHAR(45) DEFAULT NULL,
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_sent_at (sent_at),
                    INDEX idx_success (success),
                    INDEX idx_provider (provider),
                    INDEX idx_purpose (purpose),
                    INDEX idx_client_ip (client_ip),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )";
                
                if ($this->mysqli->query($createSmsLogsTable)) {
                    error_log("Created sms_logs table for existing database");
                }
            }
            
            // Check if sms_verification_attempts table exists
            $checkSmsVerificationTable = "SHOW TABLES LIKE 'sms_verification_attempts'";
            $result = $this->mysqli->query($checkSmsVerificationTable);
            if ($result && $result->num_rows == 0) {
                // Create SMS verification attempts table
                $createSmsVerificationTable = "
                CREATE TABLE sms_verification_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    phone_number VARCHAR(20) NOT NULL,
                    verification_code VARCHAR(10) NOT NULL,
                    attempts INT DEFAULT 1,
                    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    verified BOOLEAN DEFAULT FALSE,
                    INDEX idx_user_id (user_id),
                    INDEX idx_phone_number (phone_number),
                    INDEX idx_expires_at (expires_at),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )";
                
                if ($this->mysqli->query($createSmsVerificationTable)) {
                    error_log("Created sms_verification_attempts table for existing database");
                }
            }
            
        } catch (Exception $e) {
            error_log("Add missing SMS tables error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
    
    // Add missing billing tables for existing databases
    private function addMissingBillingTables() {
        try {
            // Check if user_bills table exists
            $checkUserBillsTable = "SHOW TABLES LIKE 'user_bills'";
            $result = $this->mysqli->query($checkUserBillsTable);
            if ($result && $result->num_rows == 0) {
                // Create user bills table
                $createUserBillsTable = "
                CREATE TABLE user_bills (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    billing_period_start DATE NOT NULL,
                    billing_period_end DATE NOT NULL,
                    standard_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    manual_review_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    sms_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    message_count INT NOT NULL DEFAULT 0,
                    manual_review_count INT NOT NULL DEFAULT 0,
                    sms_count INT NOT NULL DEFAULT 0,
                    bill_status ENUM('draft', 'sent', 'overdue', 'paid', 'cancelled') DEFAULT 'draft',
                    invoice_sent_date TIMESTAMP NULL,
                    due_date DATE NULL,
                    payment_date TIMESTAMP NULL,
                    payment_method VARCHAR(100) NULL,
                    payment_reference VARCHAR(255) NULL,
                    account_credit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    notes TEXT NULL,
                    created_by INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_user_id (user_id),
                    INDEX idx_billing_period (billing_period_start, billing_period_end),
                    INDEX idx_bill_status (bill_status),
                    INDEX idx_due_date (due_date),
                    INDEX idx_created_at (created_at),
                    UNIQUE KEY unique_user_period (user_id, billing_period_start, billing_period_end)
                )";
                
                if ($this->mysqli->query($createUserBillsTable)) {
                    error_log("Created user_bills table for existing database");
                }
            }
            
            // Check if user_invoice_documents table exists
            $checkUserInvoiceDocumentsTable = "SHOW TABLES LIKE 'user_invoice_documents'";
            $result = $this->mysqli->query($checkUserInvoiceDocumentsTable);
            if ($result && $result->num_rows == 0) {
                // Create user invoice documents table
                $createUserInvoiceDocumentsTable = "
                CREATE TABLE user_invoice_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_bill_id INT NOT NULL,
                    document_id INT NOT NULL,
                    invoice_type ENUM('generated', 'payment_receipt', 'credit_note') DEFAULT 'generated',
                    invoice_number VARCHAR(100) NULL,
                    invoice_amount DECIMAL(10,2) NULL,
                    is_primary_invoice BOOLEAN DEFAULT FALSE,
                    uploaded_by INT NOT NULL,
                    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_bill_id) REFERENCES user_bills(id) ON DELETE CASCADE,
                    FOREIGN KEY (document_id) REFERENCES user_documents(id) ON DELETE CASCADE,
                    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_user_bill (user_bill_id),
                    INDEX idx_document (document_id),
                    INDEX idx_invoice_type (invoice_type),
                    UNIQUE KEY unique_bill_document (user_bill_id, document_id)
                )";
                
                if ($this->mysqli->query($createUserInvoiceDocumentsTable)) {
                    error_log("Created user_invoice_documents table for existing database");
                }
            }
            
        } catch (Exception $e) {
            error_log("Add missing billing tables error: " . $e->getMessage());
            // Don't throw exception - continue normal operation
        }
    }
}
?>
