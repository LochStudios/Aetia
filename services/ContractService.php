<?php
// services/ContractService.php - Service for managing contracts and templates

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/DocumentService.php';

class ContractService {
    private $database;
    private $mysqli;
    private $userModel;
    private $emailService;
    private $documentService;
    
    public function __construct() {
        $this->database = new Database();
        $this->mysqli = $this->database->getConnection();
        $this->userModel = new User();
        $this->emailService = new EmailService();
        $this->documentService = new DocumentService();
    }
    
    // Ensure database connection is active
    private function ensureConnection() {
        if (!$this->mysqli || $this->mysqli->ping() === false) {
            $this->database = new Database();
            $this->mysqli = $this->database->getConnection();
        }
    }
    
    /**
     * Get the default Communications Services Agreement template
     */
    public function getDefaultContractTemplate() {
        return "Communications Services Agreement
This Agreement is made on [Date]

BETWEEN:

1. The Provider:
LochStudios (trading as Aetia Talent Agency)
ABN: 20447022747
Address: Level 5, 115 Pitt Street, Sydney, NSW, 2000, Australia
(Hereinafter referred to as \"the Provider\")

AND

2. The User:
[Talent's Full Legal Name]
Address: [Talent's Address]
ABN/ACN (if applicable): [Talent's ABN/ACN]
(Hereinafter referred to as \"the User\")

BACKGROUND
A. The Provider operates a proprietary management system and provides professional communication services.
B. The User wishes to use the Provider's services to obtain a professional email address and manage business-related communications.
C. This Agreement sets out the terms and conditions upon which the Provider will grant the User access to these services. It does not create an agent, manager, or representative relationship.

AGREED TERMS
1. SERVICES
1.1. The Provider agrees to supply the User with the following services (\"the Services\"):
a) One (1) professional email address in a format determined by the Provider (e.g., [user.name]@aetia.com). This email address is intended for public-facing business and company communications.
b) Access credentials to the Provider's custom-built management system (\"the Platform\").
c) The ability for the User to view messages sent to their provided email address via the Platform.

2. TERM AND TERMINATION
2.1. This Agreement shall commence on the date it is signed and will continue on a month-to-month basis.
2.2. Either party may terminate this Agreement for any reason by providing the other party with at least thirty (30) days' written notice.
2.3. Upon termination of this Agreement, the User's access to the Platform and the provided email address will be deactivated. The User is responsible for saving any required information from the Platform prior to termination.

3. OBLIGATIONS OF THE USER
3.1. The User agrees to:
a) Use the email address and Platform in a professional manner for legitimate business communications only.
b) Keep their login credentials for the Platform confidential and secure.
c) Not use the Services for any illegal, unethical, or unauthorised purpose.

4. NO REPRESENTATION OR COMMISSION
4.1. Disclaimer of Agency: For the avoidance of doubt, this is a technology and communications service agreement only. The Provider is not the User's agent, manager, or representative. The Provider has no authority to act on behalf of the User and has no obligation to seek, solicit, negotiate, or secure employment or engagements for the User.
4.2. No Commission: The Provider is not entitled to any commission, fee, or percentage of any income or compensation earned by the User, regardless of whether the Services were used to facilitate the opportunity.

5. FEES AND BILLING
5.1. Service Fee: In consideration for the Services, the User agrees to pay the Provider a fee of One United States Dollar (US\$1.00) for each Qualifying Communication.
5.2. Qualifying Communication: A \"Qualifying Communication\" is defined as an email conversation thread. The Service Fee is charged only once per thread. A thread is initiated by the first email received from an external source. All subsequent replies and forwards that are part of the same conversation (identified by the email's subject line) are included in that single thread and will not incur additional charges. An email from an external source with a new subject line will constitute a new Qualifying Communication.
5.3. Billing Cycle: The Provider will track all Qualifying Communications received within a calendar month.
5.4. Invoicing: On or around the first (1st) day of each month, the Provider will issue an itemised invoice to the User for all Qualifying Communications from the preceding month.
5.5. Payment Terms: The User agrees to pay the full invoice amount within fourteen (14) days of the invoice date.
5.6. Currency: All fees are denominated in United States Dollars (USD) and all payments must be made in USD.

6. DATA ACCESS AND PRIVACY
6.1. The User acknowledges and agrees that all communications sent to the provided email address will pass through and be stored on the Provider's Platform.
6.2. The User consents to the Provider having the ability to access, view, and manage these communications as necessary for the technical administration and maintenance of the Platform and Services. The Provider agrees to handle all data in accordance with its Privacy Policy.

7. LIMITATION OF LIABILITY
7.1. The Provider offers the Services on an \"as-is\" basis and does not guarantee uninterrupted or error-free operation. The Provider is not liable for any lost data, missed communications, or missed business opportunities resulting from the use or inability to use the Services.

8. GOVERNING LAW
8.1. This Agreement shall be governed by and construed in accordance with the laws of the state of New South Wales, Australia.

9. ENTIRE AGREEMENT & ELECTRONIC ACCEPTANCE
9.1. This document constitutes the entire agreement between the parties and supersedes all prior communications, negotiations, and agreements, whether oral or written.
9.2. Electronic Acceptance: The parties agree that this Agreement may be executed electronically. By clicking \"I Accept,\" typing their name into a signature field, or by applying any other form of electronic signature, the User indicates their full understanding and acceptance of these terms and agrees to be legally bound by them as if they had signed a physical document.

SIGNED for and on behalf of LochStudios (trading as Aetia Talent Agency):

Name: Lachlan Murdoch
Position: CEO/Founder LochStudios and subsidiaries
Date of Acceptance:

ACCEPTED AND AGREED TO by the User:

Name: [User's Full Legal Name]
Date of Acceptance:";
    }
    
    /**
     * Create a personalized contract for a user
     */
    public function generatePersonalizedContract($userId, $talentAddress, $talentAbn = '', $customTemplate = null) {
        try {
            $this->ensureConnection();
            
            // Get user details
            $user = $this->userModel->getUserById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'User not found.'];
            }
            
            // Validate that user has first name and last name
            if (empty($user['first_name']) || empty($user['last_name'])) {
                return [
                    'success' => false, 
                    'message' => 'Cannot generate contract: User must have both first name and last name filled out in their profile. Please ask the user to complete their profile first.'
                ];
            }
            
            // Build full legal name from database
            $talentName = trim($user['first_name'] . ' ' . $user['last_name']);
            
            // Use custom template or default
            $template = $customTemplate ?? $this->getDefaultContractTemplate();
            
            // Replace placeholders
            $personalizedContract = str_replace(
                ['[Date]', '[Talent\'s Full Legal Name]', '[Talent\'s Address]', '[Talent\'s ABN/ACN]'],
                [date('F j, Y'), $talentName, $talentAddress, $talentAbn ?: 'N/A'],
                $template
            );
            
            // Store in database
            $stmt = $this->mysqli->prepare("
                INSERT INTO user_contracts (
                    user_id, client_name, client_address, contract_content, 
                    status, generated_by, created_at
                ) VALUES (?, ?, ?, ?, 'draft', ?, NOW())
            ");
            
            $status = 'draft';
            $createdBy = $_SESSION['user_id'] ?? 1; // Default to admin
            
            $stmt->bind_param(
                "isssi",
                $userId,
                $talentName,
                $talentAddress,
                $personalizedContract,
                $createdBy
            );
            
            $result = $stmt->execute();
            
            if ($result) {
                $contractId = $this->mysqli->insert_id;
                $stmt->close();
                
                return [
                    'success' => true, 
                    'message' => 'Contract generated successfully.',
                    'contract_id' => $contractId,
                    'contract_content' => $personalizedContract
                ];
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'Failed to save contract.'];
            }
            
        } catch (Exception $e) {
            error_log("Contract generation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating the contract.'];
        }
    }
    
    /**
     * Get all contracts for a user
     */
    public function getUserContracts($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    c.*,
                    u.username as generated_by_username
                FROM user_contracts c
                LEFT JOIN users u ON c.generated_by = u.id
                WHERE c.user_id = ?
                ORDER BY c.created_at DESC
            ");
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $contracts = [];
            while ($row = $result->fetch_assoc()) {
                $contracts[] = $row;
            }
            
            $stmt->close();
            return $contracts;
            
        } catch (Exception $e) {
            error_log("Get user contracts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a single contract by ID
     */
    public function getContract($contractId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    c.*,
                    u.username as generated_by_username,
                    user.username as user_username,
                    user.email as user_email
                FROM user_contracts c
                LEFT JOIN users u ON c.generated_by = u.id
                LEFT JOIN users user ON c.user_id = user.id
                WHERE c.id = ?
            ");
            
            $stmt->bind_param("i", $contractId);
            $stmt->execute();
            $result = $stmt->get_result();
            $contract = $result->fetch_assoc();
            $stmt->close();
            
            return $contract;
            
        } catch (Exception $e) {
            error_log("Get contract error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update contract content
     */
    public function updateContract($contractId, $contractContent, $talentName = null, $talentAddress = null, $talentAbn = null) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                UPDATE user_contracts 
                SET contract_content = ?, client_name = COALESCE(?, client_name), 
                    client_address = COALESCE(?, client_address),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("sssi", $contractContent, $talentName, $talentAddress, $contractId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Contract updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update contract.'];
            }
            
        } catch (Exception $e) {
            error_log("Update contract error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating the contract.'];
        }
    }
    
    /**
     * Change contract status
     */
    public function updateContractStatus($contractId, $status, $signedBy = null) {
        try {
            $this->ensureConnection();
            
            $validStatuses = ['draft', 'sent', 'signed', 'completed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                return ['success' => false, 'message' => 'Invalid contract status.'];
            }
            
            $stmt = $this->mysqli->prepare("
                UPDATE user_contracts 
                SET status = ?, signed_date = CASE WHEN ? = 'signed' THEN NOW() ELSE signed_date END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("ssi", $status, $status, $contractId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Contract status updated successfully.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update contract status.'];
            }
            
        } catch (Exception $e) {
            error_log("Update contract status error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating the contract status.'];
        }
    }
    
    /**
     * Convert contract to PDF and upload as document
     */
    public function generateContractPDF($contractId) {
        try {
            $contract = $this->getContract($contractId);
            if (!$contract) {
                return ['success' => false, 'message' => 'Contract not found.'];
            }
            
            // Create a temporary HTML file
            $htmlContent = $this->convertContractToHTML($contract['contract_content']);
            $tempHtmlFile = tempnam(sys_get_temp_dir(), 'contract_') . '.html';
            file_put_contents($tempHtmlFile, $htmlContent);
            
            // Generate PDF filename
            $filename = "Contract_{$contract['talent_name']}_{$contract['id']}_" . date('Y-m-d') . ".pdf";
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            
            // Create temporary PDF file
            $tempPdfFile = tempnam(sys_get_temp_dir(), 'contract_') . '.pdf';
            
            // Convert HTML to PDF using wkhtmltopdf (if available) or create a simple text-based PDF
            $pdfGenerated = $this->htmlToPdf($tempHtmlFile, $tempPdfFile);
            
            if (!$pdfGenerated) {
                // Fallback: create a simple text file if PDF generation fails
                $tempPdfFile = tempnam(sys_get_temp_dir(), 'contract_') . '.txt';
                file_put_contents($tempPdfFile, $contract['contract_content']);
                $filename = str_replace('.pdf', '.txt', $filename);
            }
            
            // Create a mock $_FILES array for DocumentService
            $mockFile = [
                'name' => $filename,
                'tmp_name' => $tempPdfFile,
                'size' => filesize($tempPdfFile),
                'type' => $pdfGenerated ? 'application/pdf' : 'text/plain',
                'error' => UPLOAD_ERR_OK
            ];
            
            // Upload using DocumentService
            $uploadResult = $this->documentService->uploadUserDocument(
                $contract['user_id'],
                $mockFile,
                'contract',
                "Communications Services Agreement for {$contract['talent_name']}",
                $_SESSION['user_id'] ?? 1
            );
            
            // Clean up temporary files
            if (file_exists($tempHtmlFile)) unlink($tempHtmlFile);
            if (file_exists($tempPdfFile)) unlink($tempPdfFile);
            
            if ($uploadResult['success']) {
                // Update contract status
                $this->updateContractStatus($contractId, 'sent');
                
                return [
                    'success' => true,
                    'message' => 'Contract PDF generated and uploaded successfully.',
                    'document_id' => $uploadResult['document_id'] ?? null
                ];
            } else {
                return ['success' => false, 'message' => 'Failed to upload contract PDF: ' . $uploadResult['message']];
            }
            
        } catch (Exception $e) {
            error_log("Generate contract PDF error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while generating the contract PDF.'];
        }
    }
    
    /**
     * Convert contract text to HTML for PDF generation
     */
    private function convertContractToHTML($contractContent) {
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Communications Services Agreement</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            margin: 40px;
            color: #333;
        }
        h1 { 
            text-align: center; 
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .section { 
            margin: 20px 0; 
        }
        .signature-area {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .provider-signature, .user-signature {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>";
        
        // Convert line breaks and format the content
        $formatted = nl2br(htmlspecialchars($contractContent));
        
        // Add some basic formatting
        $formatted = preg_replace('/^([A-Z\s]+AGREEMENT.*?)$/m', '<h1>$1</h1>', $formatted);
        $formatted = preg_replace('/^(\d+\.\s+[A-Z\s]+)$/m', '<h3>$1</h3>', $formatted);
        
        $html .= $formatted;
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Convert HTML to PDF using native PHP PDF functions
     */
    private function htmlToPdf($htmlFile, $outputFile) {
        try {
            // Check if PDF extension is loaded
            if (!extension_loaded('pdf')) {
                error_log("PDF extension not loaded");
                return false;
            }
            
            // Read the HTML content and convert to plain text for PDF
            $htmlContent = file_get_contents($htmlFile);
            $textContent = $this->htmlToText($htmlContent);
            
            // Create PDF document
            $pdf = pdf_new();
            
            if (!$pdf) {
                error_log("Failed to create PDF object");
                return false;
            }
            
            // Open PDF file for writing
            if (pdf_open_file($pdf, $outputFile) == 0) {
                error_log("Failed to open PDF file for writing: " . pdf_get_errmsg($pdf));
                pdf_delete($pdf);
                return false;
            }
            
            // Set document info
            pdf_set_info($pdf, "Creator", "Aetia Talent Agency");
            pdf_set_info($pdf, "Author", "LochStudios");
            pdf_set_info($pdf, "Title", "Communications Services Agreement");
            pdf_set_info($pdf, "Subject", "Professional Services Contract");
            
            // Begin page
            pdf_begin_page($pdf, 595, 842); // A4 size in points
            
            // Set font
            $font = pdf_findfont($pdf, "Helvetica", "host", 0);
            if ($font == 0) {
                $font = pdf_findfont($pdf, "Times-Roman", "host", 0);
            }
            
            if ($font == 0) {
                error_log("Failed to find suitable font");
                pdf_end_page($pdf);
                pdf_close($pdf);
                pdf_delete($pdf);
                return false;
            }
            
            // Add content to PDF
            $this->addTextToPdf($pdf, $font, $textContent);
            
            // End page and close document
            pdf_end_page($pdf);
            pdf_close($pdf);
            pdf_delete($pdf);
            
            return file_exists($outputFile) && filesize($outputFile) > 0;
            
        } catch (Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Convert HTML content to plain text
     */
    private function htmlToText($html) {
        // Remove HTML tags
        $text = strip_tags($html);
        
        // Convert HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Add text content to PDF with proper formatting
     */
    private function addTextToPdf($pdf, $font, $text) {
        $fontSize = 11;
        $lineHeight = 14;
        $margin = 50;
        $pageWidth = 595 - (2 * $margin); // A4 width minus margins
        $pageHeight = 842 - (2 * $margin); // A4 height minus margins
        $currentY = 792; // Start near top of page
        
        // Set font
        pdf_setfont($pdf, $font, $fontSize);
        
        // Split text into lines that fit the page width
        $words = explode(' ', $text);
        $currentLine = '';
        
        foreach ($words as $word) {
            $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
            $textWidth = pdf_stringwidth($pdf, $testLine, $font, $fontSize);
            
            if ($textWidth > $pageWidth && $currentLine) {
                // Current line is full, write it and start new line
                pdf_show_xy($pdf, $currentLine, $margin, $currentY);
                $currentY -= $lineHeight;
                $currentLine = $word;
                
                // Check if we need a new page
                if ($currentY < $margin) {
                    pdf_end_page($pdf);
                    pdf_begin_page($pdf, 595, 842);
                    pdf_setfont($pdf, $font, $fontSize);
                    $currentY = 792;
                }
            } else {
                $currentLine = $testLine;
            }
        }
        
        // Write the last line if there is one
        if ($currentLine) {
            pdf_show_xy($pdf, $currentLine, $margin, $currentY);
        }
    }
    
    /**
     * Delete a contract
     */
    public function deleteContract($contractId, $deletedBy) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("DELETE FROM user_contracts WHERE id = ?");
            $stmt->bind_param("i", $contractId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                error_log("Contract deleted by user ID {$deletedBy}: Contract ID {$contractId}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Delete contract error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all contracts for admin view
     */
    public function getAllContracts() {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT 
                    c.*,
                    u.username as generated_by_username,
                    user.username as user_username,
                    user.email as user_email
                FROM user_contracts c
                LEFT JOIN users u ON c.generated_by = u.id
                LEFT JOIN users user ON c.user_id = user.id
                ORDER BY c.created_at DESC
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $contracts = [];
            while ($row = $result->fetch_assoc()) {
                $contracts[] = $row;
            }
            
            $stmt->close();
            return $contracts;
            
        } catch (Exception $e) {
            error_log("Get all contracts error: " . $e->getMessage());
            return [];
        }
    }
}
?>
