<?php
// database/create_stripe_tables.php - Database schema for Stripe integration

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $mysqli = $database->getConnection();
    
    if (!$mysqli) {
        throw new Exception('Database connection failed');
    }
    
    // Create stripe_invoices table
    $createInvoicesTable = "
        CREATE TABLE IF NOT EXISTS stripe_invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stripe_invoice_id VARCHAR(255) UNIQUE NOT NULL,
            stripe_customer_id VARCHAR(255) NOT NULL,
            user_id INT NOT NULL,
            invoice_number VARCHAR(100),
            status ENUM('draft', 'open', 'paid', 'void', 'uncollectible') DEFAULT 'draft',
            amount_due DECIMAL(10,2) NOT NULL,
            amount_paid DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(3) DEFAULT 'usd',
            billing_period VARCHAR(50),
            payment_intent_id VARCHAR(255) NULL,
            hosted_invoice_url TEXT,
            invoice_pdf_url TEXT,
            due_date DATE NULL,
            paid_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_stripe_invoice_id (stripe_invoice_id),
            INDEX idx_status (status),
            INDEX idx_billing_period (billing_period),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    // Create stripe_customers table
    $createCustomersTable = "
        CREATE TABLE IF NOT EXISTS stripe_customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stripe_customer_id VARCHAR(255) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_stripe_customer_id (stripe_customer_id),
            INDEX idx_email (email),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    // Create stripe_webhook_events table
    $createWebhookEventsTable = "
        CREATE TABLE IF NOT EXISTS stripe_webhook_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stripe_event_id VARCHAR(255) UNIQUE NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            object_id VARCHAR(255),
            object_type VARCHAR(50),
            processed BOOLEAN DEFAULT FALSE,
            processing_attempts INT DEFAULT 0,
            last_processing_error TEXT NULL,
            raw_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            INDEX idx_stripe_event_id (stripe_event_id),
            INDEX idx_event_type (event_type),
            INDEX idx_processed (processed),
            INDEX idx_object_id (object_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    // Create stripe_payment_attempts table
    $createPaymentAttemptsTable = "
        CREATE TABLE IF NOT EXISTS stripe_payment_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stripe_invoice_id VARCHAR(255) NOT NULL,
            user_id INT NOT NULL,
            attempt_type ENUM('automatic', 'manual') DEFAULT 'automatic',
            status ENUM('pending', 'succeeded', 'failed') NOT NULL,
            failure_reason VARCHAR(255) NULL,
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'usd',
            payment_method_type VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_stripe_invoice_id (stripe_invoice_id),
            INDEX idx_user_id (user_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    // Execute table creation
    echo "Creating Stripe database tables...\n";
    
    if ($mysqli->query($createInvoicesTable)) {
        echo "✓ stripe_invoices table created successfully\n";
    } else {
        throw new Exception("Error creating stripe_invoices table: " . $mysqli->error);
    }
    
    if ($mysqli->query($createCustomersTable)) {
        echo "✓ stripe_customers table created successfully\n";
    } else {
        throw new Exception("Error creating stripe_customers table: " . $mysqli->error);
    }
    
    if ($mysqli->query($createWebhookEventsTable)) {
        echo "✓ stripe_webhook_events table created successfully\n";
    } else {
        throw new Exception("Error creating stripe_webhook_events table: " . $mysqli->error);
    }
    
    if ($mysqli->query($createPaymentAttemptsTable)) {
        echo "✓ stripe_payment_attempts table created successfully\n";
    } else {
        throw new Exception("Error creating stripe_payment_attempts table: " . $mysqli->error);
    }
    
    echo "\nAll Stripe database tables created successfully!\n";
    echo "You can now use the Stripe integration features.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
