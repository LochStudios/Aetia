<?php
// services/StripeDataService.php - Database operations for Stripe integration

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SecurityException.php';

class StripeDataService {
    private $mysqli;
    
    public function __construct() {
        $database = new Database();
        $this->mysqli = $database->getConnection();
        
        if (!$this->mysqli) {
            throw new Exception('Database connection failed');
        }
    }
    
    /**
     * Store or update Stripe customer information
     */
    public function saveStripeCustomer($stripeCustomerId, $userId, $email, $name = null) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO stripe_customers (stripe_customer_id, user_id, email, name) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                email = VALUES(email),
                name = VALUES(name),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare customer statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('siss', $stripeCustomerId, $userId, $email, $name);
        $result = $stmt->execute();
        $stmt->close();
        
        if (!$result) {
            throw new Exception('Failed to save Stripe customer');
        }
        
        return true;
    }
    
    /**
     * Store or update Stripe invoice information
     */
    public function saveStripeInvoice($invoiceData) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO stripe_invoices (
                stripe_invoice_id, stripe_customer_id, user_id, invoice_number,
                status, amount_due, amount_paid, currency, billing_period,
                payment_intent_id, hosted_invoice_url, invoice_pdf_url, due_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                amount_paid = VALUES(amount_paid),
                payment_intent_id = VALUES(payment_intent_id),
                hosted_invoice_url = VALUES(hosted_invoice_url),
                invoice_pdf_url = VALUES(invoice_pdf_url),
                due_date = VALUES(due_date),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare invoice statement: ' . $this->mysqli->error);
        }
        
        $dueDate = isset($invoiceData['due_date']) ? date('Y-m-d', $invoiceData['due_date']) : null;
        
        $stmt->bind_param('ssissddsssss',
            $invoiceData['stripe_invoice_id'],
            $invoiceData['stripe_customer_id'],
            $invoiceData['user_id'],
            $invoiceData['invoice_number'],
            $invoiceData['status'],
            $invoiceData['amount_due'],
            $invoiceData['amount_paid'],
            $invoiceData['currency'],
            $invoiceData['billing_period'],
            $invoiceData['payment_intent_id'],
            $invoiceData['hosted_invoice_url'],
            $invoiceData['invoice_pdf_url'],
            $dueDate
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        if (!$result) {
            throw new Exception('Failed to save Stripe invoice');
        }
        
        return true;
    }
    
    /**
     * Update invoice status and payment information
     */
    public function updateInvoicePayment($stripeInvoiceId, $status, $amountPaid = null, $paidAt = null) {
        $stmt = $this->mysqli->prepare("
            UPDATE stripe_invoices 
            SET status = ?, amount_paid = ?, paid_at = ?, updated_at = CURRENT_TIMESTAMP
            WHERE stripe_invoice_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare payment update statement: ' . $this->mysqli->error);
        }
        
        $paidAtFormatted = $paidAt ? date('Y-m-d H:i:s', $paidAt) : null;
        $stmt->bind_param('sdss', $status, $amountPaid, $paidAtFormatted, $stripeInvoiceId);
        
        $result = $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        if (!$result) {
            throw new Exception('Failed to update invoice payment');
        }
        
        return $affectedRows > 0;
    }
    
    /**
     * Log webhook event to database
     */
    public function logWebhookEvent($eventId, $eventType, $objectId, $objectType, $rawData) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO stripe_webhook_events (
                stripe_event_id, event_type, object_id, object_type, raw_data
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                processing_attempts = processing_attempts + 1,
                raw_data = VALUES(raw_data)
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare webhook event statement: ' . $this->mysqli->error);
        }
        
        $rawDataJson = json_encode($rawData);
        $stmt->bind_param('sssss', $eventId, $eventType, $objectId, $objectType, $rawDataJson);
        
        $result = $stmt->execute();
        $stmt->close();
        
        if (!$result) {
            throw new Exception('Failed to log webhook event');
        }
        
        return true;
    }
    
    /**
     * Mark webhook event as processed
     */
    public function markWebhookEventProcessed($eventId, $success = true, $error = null) {
        $stmt = $this->mysqli->prepare("
            UPDATE stripe_webhook_events 
            SET processed = ?, processed_at = CURRENT_TIMESTAMP, last_processing_error = ?
            WHERE stripe_event_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare webhook update statement: ' . $this->mysqli->error);
        }
        
        $processed = $success ? 1 : 0;
        $stmt->bind_param('iss', $processed, $error, $eventId);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Record payment attempt
     */
    public function recordPaymentAttempt($stripeInvoiceId, $userId, $status, $amount, $attemptType = 'automatic', $failureReason = null, $paymentMethodType = null) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO stripe_payment_attempts (
                stripe_invoice_id, user_id, attempt_type, status, failure_reason, 
                amount, payment_method_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare payment attempt statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('sisssds', 
            $stripeInvoiceId, $userId, $attemptType, $status, 
            $failureReason, $amount, $paymentMethodType
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get invoice by Stripe ID
     */
    public function getInvoiceByStripeId($stripeInvoiceId) {
        $stmt = $this->mysqli->prepare("
            SELECT si.*, u.username, u.email as user_email, u.first_name, u.last_name
            FROM stripe_invoices si
            LEFT JOIN users u ON si.user_id = u.user_id
            WHERE si.stripe_invoice_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare invoice select statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('s', $stripeInvoiceId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $invoice = $result->fetch_assoc();
        $stmt->close();
        
        return $invoice;
    }
    
    /**
     * Get customer by Stripe ID
     */
    public function getCustomerByStripeId($stripeCustomerId) {
        $stmt = $this->mysqli->prepare("
            SELECT sc.*, u.username, u.first_name, u.last_name
            FROM stripe_customers sc
            LEFT JOIN users u ON sc.user_id = u.user_id
            WHERE sc.stripe_customer_id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare customer select statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('s', $stripeCustomerId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();
        $stmt->close();
        
        return $customer;
    }
    
    /**
     * Get all invoices for a user
     */
    public function getUserInvoices($userId, $limit = 50, $offset = 0) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM stripe_invoices 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare user invoices statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $invoices = [];
        
        while ($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }
        
        $stmt->close();
        return $invoices;
    }
    
    /**
     * Get all invoices with user information
     */
    public function getAllInvoices($limit = 50, $offset = 0) {
        $stmt = $this->mysqli->prepare("
            SELECT si.*, u.username, u.email as user_email, u.first_name, u.last_name
            FROM stripe_invoices si
            LEFT JOIN users u ON si.user_id = u.user_id
            ORDER BY si.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare all invoices statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $invoices = [];
        
        while ($row = $result->fetch_assoc()) {
            $invoices[] = $row;
        }
        
        $stmt->close();
        return $invoices;
    }
    
    /**
     * Get payment statistics
     */
    public function getPaymentStats($startDate = null, $endDate = null) {
        $whereClause = '';
        $params = [];
        $types = '';
        
        if ($startDate && $endDate) {
            $whereClause = 'WHERE created_at BETWEEN ? AND ?';
            $params = [$startDate, $endDate];
            $types = 'ss';
        }
        
        $sql = "
            SELECT 
                COUNT(*) as total_invoices,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices,
                COUNT(CASE WHEN status = 'open' THEN 1 END) as open_invoices,
                COUNT(CASE WHEN status = 'void' THEN 1 END) as void_invoices,
                SUM(amount_due) as total_amount_due,
                SUM(amount_paid) as total_amount_paid,
                AVG(amount_due) as average_invoice_amount
            FROM stripe_invoices 
            $whereClause
        ";
        
        $stmt = $this->mysqli->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Failed to prepare stats statement: ' . $this->mysqli->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        return $stats;
    }
    
    /**
     * Get recent webhook events
     */
    public function getRecentWebhookEvents($limit = 50) {
        $stmt = $this->mysqli->prepare("
            SELECT stripe_event_id, event_type, object_id, processed, 
                   processing_attempts, last_processing_error, created_at
            FROM stripe_webhook_events 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare webhook events statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $events = [];
        
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        
        $stmt->close();
        return $events;
    }
    
    /**
     * Clean up old webhook events (keep last 1000 or 30 days)
     */
    public function cleanupOldWebhookEvents($keepDays = 30, $maxRecords = 1000) {
        // Delete events older than specified days, but keep at least maxRecords
        $stmt = $this->mysqli->prepare("
            DELETE FROM stripe_webhook_events 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM stripe_webhook_events 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ) as recent_events
            )
        ");
        
        if (!$stmt) {
            throw new Exception('Failed to prepare cleanup statement: ' . $this->mysqli->error);
        }
        
        $stmt->bind_param('ii', $keepDays, $maxRecords);
        $result = $stmt->execute();
        $deletedRows = $stmt->affected_rows;
        $stmt->close();
        
        return $deletedRows;
    }
}
