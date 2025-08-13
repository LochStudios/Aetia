<?php
// services/BillingService.php - Service for managing user billing and invoices

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../services/DocumentService.php';
require_once __DIR__ . '/../services/EmailService.php';

class BillingService {
    private $database;
    private $mysqli;
    private $userModel;
    private $messageModel;
    private $documentService;
    private $emailService;
    
    public function __construct() {
        $this->database = new Database();
        $this->mysqli = $this->database->getConnection();
        $this->userModel = new User();
        $this->messageModel = new Message();
        $this->documentService = new DocumentService();
        $this->emailService = new EmailService();
    }
    
    // Ensure database connection is active
    private function ensureConnection() {
        if (!$this->mysqli || $this->mysqli->ping() === false) {
            $this->database = new Database();
            $this->mysqli = $this->database->getConnection();
        }
    }
    
    /**
     * Create a bill for a user based on billing period data
     */
    public function createUserBill($userId, $billingPeriodStart, $billingPeriodEnd, $billingData, $createdBy) {
        try {
            $this->ensureConnection();
            
            // Check if bill already exists for this period
            $stmt = $this->mysqli->prepare("
                SELECT id FROM user_bills 
                WHERE user_id = ? AND billing_period_start = ? AND billing_period_end = ?
            ");
            $stmt->bind_param("iss", $userId, $billingPeriodStart, $billingPeriodEnd);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingBill = $result->fetch_assoc();
            $stmt->close();
            
            if ($existingBill) {
                return ['success' => false, 'message' => 'Bill already exists for this period.'];
            }
            
            // Calculate due date (Net 14: billing period end + 14 days)
            $dueDate = date('Y-m-d', strtotime($billingPeriodEnd . ' +14 days'));
            
            // Prepare variables for bind_param (cannot pass expressions by reference)
            $smsFee = $billingData['sms_fee'] ?? 0;
            $smsCount = $billingData['sms_count'] ?? 0;
            
            // Create the bill
            $stmt = $this->mysqli->prepare("
                INSERT INTO user_bills (
                    user_id, billing_period_start, billing_period_end, 
                    standard_fee, manual_review_fee, sms_fee, total_amount,
                    message_count, manual_review_count, sms_count,
                    bill_status, due_date, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            
            $stmt->bind_param(
                "issddddiiiis",
                $userId,
                $billingPeriodStart,
                $billingPeriodEnd,
                $billingData['standard_fee'],
                $billingData['manual_review_fee'],
                $smsFee,
                $billingData['total_fee'],
                $billingData['total_message_count'],
                $billingData['manual_review_count'],
                $smsCount,
                $dueDate,
                $createdBy
            );
            
            $result = $stmt->execute();
            $billId = $this->mysqli->insert_id;
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Bill created successfully.', 'bill_id' => $billId];
            } else {
                return ['success' => false, 'message' => 'Failed to create bill.'];
            }
            
        } catch (Exception $e) {
            error_log("Create user bill error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while creating the bill.'];
        }
    }
    
    /**
     * Get all bills for a user
     */
    public function getUserBills($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    b.*,
                    u.username as created_by_username,
                    u.first_name as created_by_first_name,
                    u.last_name as created_by_last_name
                FROM user_bills b
                LEFT JOIN users u ON b.created_by = u.id
                WHERE b.user_id = ?
                ORDER BY b.billing_period_start DESC
            ");
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $bills = [];
            while ($row = $result->fetch_assoc()) {
                // Get invoice documents for this bill
                $row['invoices'] = $this->getBillInvoices($row['id']);
                $bills[] = $row;
            }
            
            $stmt->close();
            return $bills;
            
        } catch (Exception $e) {
            error_log("Get user bills error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all bills for admin management
     */
    public function getAllBills($limit = 50, $offset = 0) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    b.*,
                    user.username,
                    user.first_name,
                    user.last_name,
                    user.email,
                    admin.username as created_by_username,
                    admin.first_name as created_by_first_name,
                    admin.last_name as created_by_last_name
                FROM user_bills b
                LEFT JOIN users user ON b.user_id = user.id
                LEFT JOIN users admin ON b.created_by = admin.id
                ORDER BY b.created_at DESC
                LIMIT ? OFFSET ?
            ");
            
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $bills = [];
            while ($row = $result->fetch_assoc()) {
                // Get invoice documents for this bill
                $row['invoices'] = $this->getBillInvoices($row['id']);
                $bills[] = $row;
            }
            
            $stmt->close();
            return $bills;
            
        } catch (Exception $e) {
            error_log("Get all bills error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get invoices linked to a bill
     */
    public function getBillInvoices($billId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    inv.*,
                    doc.original_filename,
                    doc.s3_key,
                    doc.s3_url,
                    doc.file_size,
                    doc.mime_type,
                    doc.uploaded_at as doc_uploaded_at,
                    up_user.username as uploaded_by_username
                FROM user_invoice_documents inv
                JOIN user_documents doc ON inv.document_id = doc.id
                LEFT JOIN users up_user ON inv.uploaded_by = up_user.id
                WHERE inv.user_bill_id = ?
                ORDER BY inv.is_primary_invoice DESC, inv.uploaded_at ASC
            ");
            
            $stmt->bind_param("i", $billId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $invoices = [];
            while ($row = $result->fetch_assoc()) {
                $invoices[] = $row;
            }
            
            $stmt->close();
            return $invoices;
            
        } catch (Exception $e) {
            error_log("Get bill invoices error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Link an existing document to a bill as an invoice
     */
    public function linkInvoiceToBill($billId, $documentId, $invoiceType, $invoiceNumber, $invoiceAmount, $uploadedBy, $isPrimary = false) {
        try {
            $this->ensureConnection();
            
            // If this is set as primary, unset other primary invoices for this bill
            if ($isPrimary) {
                $stmt = $this->mysqli->prepare("
                    UPDATE user_invoice_documents 
                    SET is_primary_invoice = FALSE 
                    WHERE user_bill_id = ?
                ");
                $stmt->bind_param("i", $billId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Link the document to the bill
            $stmt = $this->mysqli->prepare("
                INSERT INTO user_invoice_documents (
                    user_bill_id, document_id, invoice_type, invoice_number, 
                    invoice_amount, is_primary_invoice, uploaded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "iissdii",
                $billId,
                $documentId,
                $invoiceType,
                $invoiceNumber,
                $invoiceAmount,
                $isPrimary,
                $uploadedBy
            );
            
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Invoice linked to bill successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to link invoice to bill.'];
            }
            
        } catch (Exception $e) {
            error_log("Link invoice to bill error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while linking the invoice.'];
        }
    }
    
    /**
     * Upload an invoice document and link it to a bill
     */
    public function uploadInvoiceForBill($billId, $file, $invoiceType, $invoiceNumber, $invoiceAmount, $uploadedBy, $isPrimary = false) {
        try {
            // Get bill details
            $bill = $this->getBillById($billId);
            if (!$bill) {
                return ['success' => false, 'message' => 'Bill not found.'];
            }
            
            // Upload the document
            $description = ucfirst($invoiceType) . " for " . date('F Y', strtotime($bill['billing_period_start']));
            $uploadResult = $this->documentService->uploadUserDocument(
                $bill['user_id'],
                $file,
                'invoice',
                $description,
                $uploadedBy,
                false // Don't send notification for invoices
            );
            
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            
            // Link the uploaded document to the bill
            $linkResult = $this->linkInvoiceToBill(
                $billId,
                $uploadResult['document_id'],
                $invoiceType,
                $invoiceNumber,
                $invoiceAmount,
                $uploadedBy,
                $isPrimary
            );
            
            if ($linkResult['success']) {
                return ['success' => true, 'message' => 'Invoice uploaded and linked successfully.', 'document_id' => $uploadResult['document_id']];
            } else {
                // If linking failed, we should ideally clean up the uploaded document
                // But for now, we'll leave it as a regular document
                return ['success' => false, 'message' => 'Invoice uploaded but failed to link to bill.'];
            }
            
        } catch (Exception $e) {
            error_log("Upload invoice for bill error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while uploading the invoice.'];
        }
    }
    
    /**
     * Update bill status
     */
    public function updateBillStatus($billId, $status, $paymentDate = null, $paymentMethod = null, $paymentReference = null, $notes = null, $dueDate = null) {
        try {
            $this->ensureConnection();
            
            $updateFields = ['bill_status = ?'];
            $params = [$status];
            $types = 's';
            
            if ($status === 'paid' && $paymentDate) {
                $updateFields[] = 'payment_date = ?';
                $params[] = $paymentDate;
                $types .= 's';
            }
            
            if ($paymentMethod) {
                $updateFields[] = 'payment_method = ?';
                $params[] = $paymentMethod;
                $types .= 's';
            }
            
            if ($paymentReference) {
                $updateFields[] = 'payment_reference = ?';
                $params[] = $paymentReference;
                $types .= 's';
            }
            
            if ($notes !== null) {
                $updateFields[] = 'notes = ?';
                $params[] = $notes;
                $types .= 's';
            }
            
            if ($dueDate !== null) {
                $updateFields[] = 'due_date = ?';
                $params[] = $dueDate;
                $types .= 's';
            }
            
            $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
            $params[] = $billId;
            $types .= 'i';
            
            $sql = "UPDATE user_bills SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Bill status updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update bill status.'];
            }
            
        } catch (Exception $e) {
            error_log("Update bill status error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating the bill status.'];
        }
    }
    
    /**
     * Apply account credit to a bill
     */
    public function applyAccountCredit($billId, $creditAmount, $reason) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                UPDATE user_bills 
                SET account_credit = account_credit + ?,
                    notes = CONCAT(COALESCE(notes, ''), IF(notes IS NULL OR notes = '', '', '\n'), 
                                   'Credit applied: $', ?, ' - ', ?, ' (', NOW(), ')'),
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->bind_param("ddsi", $creditAmount, $creditAmount, $reason, $billId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Account credit applied successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to apply account credit.'];
            }
            
        } catch (Exception $e) {
            error_log("Apply account credit error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while applying account credit.'];
        }
    }
    
    /**
     * Get bill by ID
     */
    public function getBillById($billId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    b.*,
                    u.username,
                    u.first_name,
                    u.last_name,
                    u.email,
                    admin.username as created_by_username
                FROM user_bills b
                LEFT JOIN users u ON b.user_id = u.id
                LEFT JOIN users admin ON b.created_by = admin.id
                WHERE b.id = ?
            ");
            
            $stmt->bind_param("i", $billId);
            $stmt->execute();
            $result = $stmt->get_result();
            $bill = $result->fetch_assoc();
            $stmt->close();
            
            if ($bill) {
                $bill['invoices'] = $this->getBillInvoices($billId);
            }
            
            return $bill;
            
        } catch (Exception $e) {
            error_log("Get bill by ID error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Delete a bill and its invoice links
     */
    public function deleteBill($billId) {
        try {
            $this->ensureConnection();
            
            // Start transaction
            $this->mysqli->begin_transaction();
            
            // Delete invoice links (documents remain in user_documents)
            $stmt = $this->mysqli->prepare("DELETE FROM user_invoice_documents WHERE user_bill_id = ?");
            $stmt->bind_param("i", $billId);
            $result1 = $stmt->execute();
            $stmt->close();
            
            // Delete the bill
            $stmt = $this->mysqli->prepare("DELETE FROM user_bills WHERE id = ?");
            $stmt->bind_param("i", $billId);
            $result2 = $stmt->execute();
            $stmt->close();
            
            if ($result1 && $result2) {
                $this->mysqli->commit();
                return ['success' => true, 'message' => 'Bill deleted successfully.'];
            } else {
                $this->mysqli->rollback();
                return ['success' => false, 'message' => 'Failed to delete bill.'];
            }
            
        } catch (Exception $e) {
            $this->mysqli->rollback();
            error_log("Delete bill error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting the bill.'];
        }
    }
    
    /**
     * Get billing statistics for a user
     */
    public function getUserBillingStats($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    COALESCE(COUNT(*), 0) as total_bills,
                    COALESCE(SUM(total_amount), 0) as total_billed,
                    COALESCE(SUM(account_credit), 0) as total_credits,
                    COALESCE(SUM(CASE WHEN bill_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_paid,
                    COALESCE(SUM(CASE WHEN bill_status = 'overdue' THEN total_amount ELSE 0 END), 0) as total_overdue,
                    COALESCE(SUM(CASE WHEN bill_status IN ('draft', 'sent') THEN total_amount ELSE 0 END), 0) as total_pending
                FROM user_bills 
                WHERE user_id = ?
            ");
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            // Ensure all values are numeric
            $stats['total_bills'] = (int)($stats['total_bills'] ?? 0);
            $stats['total_billed'] = (float)($stats['total_billed'] ?? 0);
            $stats['total_credits'] = (float)($stats['total_credits'] ?? 0);
            $stats['total_paid'] = (float)($stats['total_paid'] ?? 0);
            $stats['total_overdue'] = (float)($stats['total_overdue'] ?? 0);
            $stats['total_pending'] = (float)($stats['total_pending'] ?? 0);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get user billing stats error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send invoice email to user
     */
    public function sendInvoiceEmail($billId) {
        try {
            $bill = $this->getBillById($billId);
            if (!$bill) {
                return ['success' => false, 'message' => 'Bill not found.'];
            }
            
            $user = $this->userModel->getUserById($bill['user_id']);
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            // Get user display name
            $userName = !empty($user['first_name']) ? 
                trim($user['first_name'] . ' ' . ($user['last_name'] ?? '')) : 
                $user['username'];
            
            $periodDisplay = date('F Y', strtotime($bill['billing_period_start']));
            
            $subject = "Invoice for {$periodDisplay} - Aetia Talent Agency";
            
            $content = "
            <h2>Invoice for {$periodDisplay}</h2>
            <div class='highlight-box'>
                <p><strong>Hello {$userName},</strong></p>
                <p>Your invoice for {$periodDisplay} is now available.</p>
            </div>
            
            <h3>Invoice Details:</h3>
            <div class='highlight-box' style='border-left-color: #209cee;'>
                <p><strong>Billing Period:</strong> " . date('F j, Y', strtotime($bill['billing_period_start'])) . " - " . date('F j, Y', strtotime($bill['billing_period_end'])) . "</p>
                <p><strong>Total Amount:</strong> $" . number_format($bill['total_amount'], 2) . "</p>
                <p><strong>Due Date:</strong> " . date('F j, Y', strtotime($bill['due_date'])) . "</p>
            </div>
            
            <h3>Service Summary:</h3>
            <div class='highlight-box'>
                <p><strong>Messages Processed:</strong> {$bill['message_count']}</p>
                <p><strong>Standard Service Fee:</strong> $" . number_format($bill['standard_fee'], 2) . "</p>
                " . ($bill['manual_review_count'] > 0 ? "<p><strong>Manual Reviews:</strong> {$bill['manual_review_count']} ($" . number_format($bill['manual_review_fee'], 2) . ")</p>" : "") . "
                " . ($bill['sms_count'] > 0 ? "<p><strong>SMS Messages:</strong> {$bill['sms_count']} ($" . number_format($bill['sms_fee'], 2) . ")</p>" : "") . "
            </div>
            
            <p style='text-align: center; margin: 30px 0;'>
                <a href='https://aetia.com.au/billing.php' class='button-primary'>View Invoice Details</a>
            </p>
            
            <div class='highlight-box' style='background-color: #2a2d2e; border-left-color: #ffdd57;'>
                <p style='color: #b0b3b5; font-size: 14px; margin: 0;'>
                    <strong>Payment Terms:</strong> Payment is due within 14 days of the billing period end date (Net 14). 
                    If you have any questions about this invoice, please contact our billing team.
                </p>
            </div>
            ";
            
            $body = $this->emailService->wrapInDarkTemplate($subject, $content);
            
            $result = $this->emailService->sendEmail(
                $user['email'],
                $subject,
                $body,
                '',
                [],
                null,
                'invoice',
                $bill['created_by']
            );
            
            if ($result) {
                // Update bill status to 'sent' and record the date
                $this->mysqli->prepare("
                    UPDATE user_bills 
                    SET bill_status = 'sent', invoice_sent_date = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ")->execute([$billId]);
                
                return ['success' => true, 'message' => 'Invoice email sent successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to send invoice email.'];
            }
            
        } catch (Exception $e) {
            error_log("Send invoice email error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while sending the invoice email.'];
        }
    }
    
    /**
     * Link an existing document from user_documents to a bill
     */
    public function linkExistingDocumentToBill($billId, $documentId, $invoiceType = 'generated', $invoiceNumber = '', $invoiceAmount = 0, $isPrimary = false) {
        try {
            $this->ensureConnection();
            
            // Start transaction
            $this->mysqli->begin_transaction();
            
            // First verify the bill exists and get its user_id
            $stmt = $this->mysqli->prepare("SELECT user_id FROM user_bills WHERE id = ?");
            $stmt->bind_param("i", $billId);
            $stmt->execute();
            $result = $stmt->get_result();
            $bill = $result->fetch_assoc();
            $stmt->close();
            
            if (!$bill) {
                throw new Exception("Bill not found");
            }
            
            // Verify the document exists and belongs to the same user
            $stmt = $this->mysqli->prepare("
                SELECT id, user_id, original_filename, s3_key, s3_url, file_size, mime_type 
                FROM user_documents 
                WHERE id = ? AND user_id = ? AND archived = 0
            ");
            $stmt->bind_param("ii", $documentId, $bill['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $document = $result->fetch_assoc();
            $stmt->close();
            
            if (!$document) {
                throw new Exception("Document not found or doesn't belong to the bill's user");
            }
            
            // Check if this document is already linked to this bill
            $stmt = $this->mysqli->prepare("
                SELECT id FROM user_invoice_documents 
                WHERE user_bill_id = ? AND document_id = ?
            ");
            $stmt->bind_param("ii", $billId, $documentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existing = $result->fetch_assoc();
            $stmt->close();
            
            if ($existing) {
                // Check if the existing record has an invalid invoice_type and fix it
                $stmt = $this->mysqli->prepare("
                    SELECT invoice_type FROM user_invoice_documents 
                    WHERE user_bill_id = ? AND document_id = ?
                ");
                $stmt->bind_param("ii", $billId, $documentId);
                $stmt->execute();
                $result = $stmt->get_result();
                $existingRecord = $result->fetch_assoc();
                $stmt->close();
                
                // If it has an invalid invoice_type, update it to 'generated'
                if ($existingRecord && !in_array($existingRecord['invoice_type'], ['generated', 'payment_receipt', 'credit_note'])) {
                    $stmt = $this->mysqli->prepare("
                        UPDATE user_invoice_documents 
                        SET invoice_type = 'generated' 
                        WHERE user_bill_id = ? AND document_id = ?
                    ");
                    $stmt->bind_param("ii", $billId, $documentId);
                    $stmt->execute();
                    $stmt->close();
                    
                    return [
                        'success' => true, 
                        'message' => 'Document link repaired successfully'
                    ];
                }
                
                throw new Exception("This document is already linked to this bill");
            }
            
            // If setting as primary, clear other primary flags for this bill
            if ($isPrimary) {
                $stmt = $this->mysqli->prepare("
                    UPDATE user_invoice_documents 
                    SET is_primary_invoice = 0 
                    WHERE user_bill_id = ?
                ");
                $stmt->bind_param("i", $billId);
                $stmt->execute();
                $stmt->close();
            }
            
            // Insert the link
            $stmt = $this->mysqli->prepare("
                INSERT INTO user_invoice_documents (
                    user_bill_id, document_id, invoice_type, invoice_number, 
                    invoice_amount, is_primary_invoice, uploaded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissdii", $billId, $documentId, $invoiceType, $invoiceNumber, $invoiceAmount, $isPrimary, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
            
            $this->mysqli->commit();
            
            return [
                'success' => true, 
                'message' => 'Document linked to bill successfully',
                'linked_document_id' => $documentId
            ];
            
        } catch (Exception $e) {
            $this->mysqli->rollback();
            error_log("Link existing document error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get total amount already invoiced for a user in a specific period
     * This is used to subtract from new billing calculations
     */
    public function getInvoicedAmountForPeriod($userId, $periodStart, $periodEnd) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT COALESCE(SUM(uid.invoice_amount), 0) as total_invoiced
                FROM user_invoice_documents uid
                INNER JOIN user_bills ub ON uid.user_bill_id = ub.id
                WHERE ub.user_id = ? 
                  AND ub.billing_period_start >= ? 
                  AND ub.billing_period_end <= ?
                  AND uid.invoice_type IN ('generated', 'generated_invoice')
                  AND uid.invoice_amount > 0
            ");
            
            $stmt->bind_param("iss", $userId, $periodStart, $periodEnd);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();
            
            return (float)($data['total_invoiced'] ?? 0);
            
        } catch (Exception $e) {
            error_log("Get invoiced amount for period error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get invoiced amounts breakdown for a user in a specific period
     */
    public function getInvoicedBreakdownForPeriod($userId, $periodStart, $periodEnd) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    ub.billing_period_start,
                    ub.billing_period_end,
                    uid.invoice_type,
                    uid.invoice_number,
                    uid.invoice_amount,
                    uid.uploaded_at,
                    ud.original_filename
                FROM user_invoice_documents uid
                INNER JOIN user_bills ub ON uid.user_bill_id = ub.id
                LEFT JOIN user_documents ud ON uid.document_id = ud.id
                WHERE ub.user_id = ? 
                  AND ub.billing_period_start >= ? 
                  AND ub.billing_period_end <= ?
                  AND uid.invoice_type IN ('generated', 'generated_invoice')
                  AND uid.invoice_amount > 0
                ORDER BY uid.uploaded_at DESC
            ");
            
            $stmt->bind_param("iss", $userId, $periodStart, $periodEnd);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $invoices = [];
            while ($row = $result->fetch_assoc()) {
                $invoices[] = $row;
            }
            
            $stmt->close();
            return $invoices;
            
        } catch (Exception $e) {
            error_log("Get invoiced breakdown for period error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all document IDs that are already linked to bills for a user
     */
    public function getLinkedDocumentIds($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT DISTINCT uid.document_id
                FROM user_invoice_documents uid
                INNER JOIN user_bills ub ON uid.user_bill_id = ub.id
                WHERE ub.user_id = ?
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $linkedIds = [];
            while ($row = $result->fetch_assoc()) {
                $linkedIds[] = $row['document_id'];
            }
            
            $stmt->close();
            return $linkedIds;
            
        } catch (Exception $e) {
            error_log("Get linked document IDs error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Repair invalid invoice types in the database
     */
    public function repairInvalidInvoiceTypes() {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                UPDATE user_invoice_documents 
                SET invoice_type = 'generated' 
                WHERE invoice_type NOT IN ('generated', 'payment_receipt', 'credit_note')
            ");
            $stmt->execute();
            $affectedRows = $stmt->affected_rows;
            $stmt->close();
            
            return [
                'success' => true, 
                'message' => "Repaired $affectedRows invalid invoice type records"
            ];
            
        } catch (Exception $e) {
            error_log("Repair invoice types error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to repair invoice types'];
        }
    }
}
?>
