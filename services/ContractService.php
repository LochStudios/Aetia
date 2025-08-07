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
a) One (1) professional email address in a format determined by the Provider (e.g., [user.name]@aetia.com.au). This email address is intended for public-facing business and company communications.
b) Access credentials to the Provider's custom-built management system (\"the Platform\") via manual login or via social connections including but not limited to Twitch, Discord, and any other OAuth system Aetia Talent Agency wishes to use.
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
4.1. Disclaimer of Agency: For the avoidance of doubt, this is a technology and communications service agreement only. While the Provider may respond to external communications on behalf of the User using pre-approved information and responses provided by the User, the Provider is not the User's agent, manager, or representative for business, employment, or contractual matters. The Provider has no authority to enter into agreements, contracts, or commitments on behalf of the User and has no obligation to seek, solicit, negotiate, or secure employment or engagements for the User.
4.2. No Commission: The Provider is not entitled to any commission, fee, or percentage of any income or compensation earned by the User, regardless of whether the Services were used to facilitate the opportunity.

5. FEES AND BILLING
5.1. Service Fee: In consideration for the Services, the User agrees to pay the Provider a fee of One United States Dollar (US\$1.00) for each Qualifying Communication.
5.2. Qualifying Communication: A \"Qualifying Communication\" is defined as an email conversation thread. The Service Fee is charged only once per thread. A thread is initiated by the first email received from an external source. All subsequent replies and forwards that are part of the same conversation (identified by the email's subject line) are included in that single thread and will not incur additional charges. An email from an external source with a new subject line will constitute a new Qualifying Communication.
5.3. Billing Cycle: The Provider will track all Qualifying Communications received within a calendar month.
5.4. Invoicing: On or around the first (1st) day of each month, the Provider will issue an itemised invoice to the User for all Qualifying Communications from the preceding month.
5.5. Payment Terms: The User agrees to pay the full invoice amount within fourteen (14) days of the invoice date.
5.6. Manual Review Fee: Manual review services requested outside standard processing hours incur an additional fee of One United States Dollar (US$1.00) per email processed. These fees will be included in the monthly invoice along with standard service fees.
5.7. Currency: All fees are denominated in United States Dollars (USD). Payments may be made in any currency equivalent to the USD amount due at the time of payment, using prevailing exchange rates.

6. DATA ACCESS AND PRIVACY
6.1. The User acknowledges and agrees that all communications sent to the provided email address will pass through and be stored on the Provider's Platform.
6.2. The User consents to the Provider having the ability to access, view, and manage these communications as necessary for the technical administration and maintenance of the Platform and Services. The Provider agrees to handle all data in accordance with its Privacy Policy.

7. LIMITATION OF LIABILITY
7.1. The Provider offers the Services on an \"as-is\" basis and does not guarantee uninterrupted or error-free operation. The Provider is not liable for any lost data or missed business opportunities resulting from the use or inability to use the Services.
7.2. Message Processing Schedule: Incoming message communications are processed and added to the User's message platform between the hours of 12:00 PM - 1:00 PM AEST/AEDT Monday through Friday. Messages received outside these hours will be processed during the next scheduled processing window.
7.3. Manual Review Service: The User may request a manual review of messages if they are expecting an incoming message outside the standard processing hours. This service incurs an additional fee of One United States Dollar (US$1.00) per email processed outside of the standard schedule (see clause 5.6 for billing details).

8. GOVERNING LAW
8.1. This Agreement shall be governed by and construed in accordance with the laws of the state of New South Wales, Australia.

9. ENTIRE AGREEMENT & ELECTRONIC ACCEPTANCE
9.1. This document constitutes the entire agreement between the parties and supersedes all prior communications, negotiations, and agreements, whether oral or written.
9.2. Electronic Acceptance: The parties agree that this Agreement may be executed electronically. By clicking \"I Accept,\" typing their name into a signature field, or by applying any other form of electronic signature, the User indicates their full understanding and acceptance of these terms and agrees to be legally bound by them as if they had signed a physical document.

SIGNED for and on behalf of LochStudios (trading as Aetia Talent Agency):

Name: Lachlan Murdoch
Position: CEO/Founder LochStudios and subsidiaries
Date of Acceptance: {{COMPANY_ACCEPTANCE_DATE}}

ACCEPTED AND AGREED TO by the User:

Name: [User's Full Legal Name]
Date of Acceptance: {{USER_ACCEPTANCE_DATE}}";
    }
    
    /**
     * Create a personalized contract for a user
     */
    public function generatePersonalizedContract($userId, $customTemplate = null) {
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
            
            // Validate that user has an address
            if (empty($user['address'])) {
                return [
                    'success' => false, 
                    'message' => 'Cannot generate contract: User must have an address filled out in their profile. Please ask the user to complete their profile first.'
                ];
            }
            
            // Build full legal name from database
            $talentName = trim($user['first_name'] . ' ' . $user['last_name']);
            
            // Get address from user profile
            $talentAddress = trim($user['address']);
            
            // Get ABN/ACN from user profile if available
            $userAbn = !empty($user['abn_acn']) ? trim($user['abn_acn']) : '';
            
            // Use custom template or default
            $template = $customTemplate ?? $this->getDefaultContractTemplate();
            
            // Handle ABN/ACN section dynamically
            if (empty($userAbn)) {
                // Remove the entire ABN/ACN line if user doesn't have one, but keep proper spacing
                $template = preg_replace('/\s*ABN\/ACN \(if applicable\):\s*\[Talent\'s ABN\/ACN\]\s*\n/', "\n", $template);
            }
            
            // Replace placeholders
            $personalizedContract = str_replace(
                ['[Date]', '[Talent\'s Full Legal Name]', '[Talent\'s Address]', '[Talent\'s ABN/ACN]', '[User\'s Full Legal Name]', '{{COMPANY_ACCEPTANCE_DATE}}', '{{USER_ACCEPTANCE_DATE}}'],
                [date('F j, Y'), $talentName, $talentAddress, $userAbn, $talentName, '', '_____________________'],
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
     * Mark contract as accepted by company and update PDF
     */
    public function markCompanyAccepted($contractId) {
        try {
            $this->ensureConnection();
            
            // Get the contract
            $stmt = $this->mysqli->prepare("SELECT * FROM user_contracts WHERE id = ?");
            $stmt->bind_param("i", $contractId);
            $stmt->execute();
            $result = $stmt->get_result();
            $contract = $result->fetch_assoc();
            $stmt->close();
            
            if (!$contract) {
                return ['success' => false, 'message' => 'Contract not found.'];
            }
            
            // Update contract with company acceptance date
            $companyAcceptanceDate = date('F j, Y');
            $updatedContent = str_replace(
                '{{COMPANY_ACCEPTANCE_DATE}}',
                $companyAcceptanceDate,
                $contract['contract_content']
            );
            
            // Update database
            $stmt = $this->mysqli->prepare("
                UPDATE user_contracts 
                SET contract_content = ?, company_accepted_date = NOW(), status = 'sent', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("si", $updatedContent, $contractId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                // Generate and store PDF document
                $pdfResult = $this->generateAndStoreContractPDF(
                    $contractId, 
                    $contract['user_id'], 
                    $updatedContent, 
                    'contract'
                );
                
                if (!$pdfResult['success']) {
                    error_log("Failed to generate company accepted PDF: " . $pdfResult['message']);
                }
                
                return ['success' => true, 'message' => 'Contract marked as accepted by company and PDF generated.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update contract.'];
            }
            
        } catch (Exception $e) {
            error_log("Company acceptance error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while processing company acceptance.'];
        }
    }
    
    /**
     * Mark contract as accepted by user and update PDF
     */
    public function markUserAccepted($contractId, $userId) {
        try {
            $this->ensureConnection();
            
            // Get the contract and verify user ownership
            $stmt = $this->mysqli->prepare("SELECT * FROM user_contracts WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $contractId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $contract = $result->fetch_assoc();
            $stmt->close();
            
            if (!$contract) {
                return ['success' => false, 'message' => 'Contract not found or access denied.'];
            }
            
            // Check if company has already accepted
            if (empty($contract['company_accepted_date'])) {
                return ['success' => false, 'message' => 'Contract must be accepted by company first.'];
            }
            
            // Check if user already accepted
            if (!empty($contract['user_accepted_date'])) {
                return ['success' => false, 'message' => 'Contract has already been accepted by user.'];
            }
            
            // Update contract with user acceptance date
            $userAcceptanceDate = date('F j, Y');
            $updatedContent = str_replace(
                '{{USER_ACCEPTANCE_DATE}}',
                $userAcceptanceDate,
                $contract['contract_content']
            );
            
            // Update database
            $stmt = $this->mysqli->prepare("
                UPDATE user_contracts 
                SET contract_content = ?, user_accepted_date = NOW(), status = 'signed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("si", $updatedContent, $contractId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                // Generate and store PDF document, archiving the previous one
                $pdfResult = $this->generateAndStoreContractPDF(
                    $contractId, 
                    $userId, 
                    $updatedContent, 
                    'contract',
                    'User signed contract - superseded by fully executed version'
                );
                
                if (!$pdfResult['success']) {
                    error_log("Failed to generate user accepted PDF: " . $pdfResult['message']);
                }
                
                return ['success' => true, 'message' => 'Contract accepted successfully and PDF generated.'];
            } else {
                return ['success' => false, 'message' => 'Failed to update contract.'];
            }
            
        } catch (Exception $e) {
            error_log("User acceptance error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while processing user acceptance.'];
        }
    }
    
    /**
     * Generate PDF from contract content and store as document
     */
    private function generateAndStoreContractPDF($contractId, $userId, $contractContent, $documentType = 'contract', $archiveReason = null, $sendNotification = true) {
        try {
            // Create a temporary file for the PDF
            $tempFile = tempnam(sys_get_temp_dir(), 'contract_');
            
            // Generate PDF content using basic HTML to PDF conversion
            $htmlContent = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Contract</title>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        line-height: 1.6; 
                        margin: 40px; 
                        color: #333;
                    }
                    h1, h2, h3 { 
                        color: #2c3e50; 
                        page-break-after: avoid;
                    }
                    h1 { 
                        text-align: center; 
                        margin-bottom: 30px;
                        border-bottom: 2px solid #3498db;
                        padding-bottom: 10px;
                    }
                    p { 
                        margin-bottom: 15px; 
                        text-align: justify;
                    }
                    .signature-section {
                        margin-top: 40px;
                        border-top: 1px solid #bdc3c7;
                        padding-top: 20px;
                    }
                    .page-break { 
                        page-break-before: always; 
                    }
                </style>
            </head>
            <body>
                " . nl2br(htmlspecialchars($contractContent)) . "
            </body>
            </html>";
            
            // Generate PDF using PDFlib
            $tempPdfFile = tempnam(sys_get_temp_dir(), 'contract_') . '.pdf';
            
            // Generate PDF using PDFlib - this is the only option now
            if (!extension_loaded('pdf')) {
                error_log("PDFlib extension not loaded");
                return ['success' => false, 'message' => 'PDF generation service not available.'];
            }
            
            try {
                // Create PDF document
                $pdf = pdf_new();
                
                if (!$pdf) {
                    error_log("PDFlib: Failed to create PDF object");
                    return ['success' => false, 'message' => 'Failed to initialize PDF generator.'];
                }
                
                // Set PDF options for proper output
                pdf_set_parameter($pdf, "compatibility", "1.4");
                pdf_set_parameter($pdf, "license", "");
                
                // Begin PDF document
                if (pdf_begin_document($pdf, $tempPdfFile, "") == 0) {
                    error_log("PDFlib: Failed to begin document - " . pdf_get_errmsg($pdf));
                    pdf_delete($pdf);
                    return ['success' => false, 'message' => 'Failed to create PDF document.'];
                }
                
                // Set document info
                pdf_set_info($pdf, "Creator", "Aetia Talent Agency");
                pdf_set_info($pdf, "Author", "LochStudios");
                pdf_set_info($pdf, "Title", "Communications Services Agreement");
                pdf_set_info($pdf, "Subject", "Professional Services Contract");
                
                // Begin page
                pdf_begin_page_ext($pdf, 595, 842, ""); // A4 size
                
                // Load font
                $font = pdf_load_font($pdf, "Helvetica", "unicode", "");
                if ($font == 0) {
                    error_log("PDFlib: Failed to load font - " . pdf_get_errmsg($pdf));
                    pdf_end_page_ext($pdf, "");
                    pdf_end_document($pdf, "");
                    pdf_delete($pdf);
                    return ['success' => false, 'message' => 'Failed to load PDF font.'];
                }
                
                // Convert HTML content to plain text
                $textContent = $this->htmlToText($contractContent);
                
                // Ensure we have content to add
                if (empty(trim($textContent))) {
                    error_log("PDFlib: No content to add to PDF");
                    pdf_end_page_ext($pdf, "");
                    pdf_end_document($pdf, "");
                    pdf_delete($pdf);
                    return ['success' => false, 'message' => 'No contract content available to generate PDF.'];
                }
                
                // Add content to PDF
                $this->addTextToPdf($pdf, $font, $textContent);
                
                // Close PDF properly
                pdf_end_page_ext($pdf, "");
                pdf_end_document($pdf, "");
                pdf_delete($pdf);
                
                // Validate that the file was created and has content
                if (!file_exists($tempPdfFile) || filesize($tempPdfFile) == 0) {
                    error_log("PDF file was not created or is empty");
                    return ['success' => false, 'message' => 'PDF generation failed - no output file created.'];
                }
                
                // Validate PDF header
                $handle = fopen($tempPdfFile, 'rb');
                if ($handle) {
                    $header = fread($handle, 4);
                    fclose($handle);
                    
                    if ($header !== '%PDF') {
                        error_log("Generated PDF file does not have correct header: " . bin2hex($header));
                        unlink($tempPdfFile);
                        return ['success' => false, 'message' => 'Generated PDF file is corrupted.'];
                    }
                } else {
                    return ['success' => false, 'message' => 'Cannot validate generated PDF file.'];
                }
                
                error_log("PDFlib: Successfully generated and validated PDF for contract");
                
            } catch (Exception $e) {
                error_log("PDFlib generation error: " . $e->getMessage());
                return ['success' => false, 'message' => 'PDF generation failed: ' . $e->getMessage()];
            }
            
            $tempFile = $tempPdfFile;
            
            // Get user info for filename
            $user = $this->userModel->getUserById($userId);
            $userName = $user ? ($user['first_name'] . '_' . $user['last_name']) : 'User_' . $userId;
            $userName = preg_replace('/[^a-zA-Z0-9_]/', '', $userName);
            
            // Create filename - always PDF now
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "Contract_{$userName}_{$timestamp}.pdf";
            
            // Create file array for document service
            $fileArray = [
                'name' => $filename,
                'tmp_name' => $tempFile,
                'size' => filesize($tempFile),
                'type' => 'application/pdf',
                'error' => UPLOAD_ERR_OK
            ];
            
            // Upload to document service
            $description = $documentType === 'contract' ? 'Company accepted contract' : 'Fully signed contract';
            $result = $this->documentService->uploadUserDocument(
                $userId, 
                $fileArray, 
                $documentType, 
                $description,
                $_SESSION['user_id'] ?? 1,
                $sendNotification  // Pass the notification parameter
            );
            
            // Archive previous contract document if this is a user acceptance
            if ($result['success'] && $archiveReason) {
                $this->archivePreviousContractDocuments($userId, $archiveReason);
            }
            
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate contract PDF.'];
        }
    }
    
    /**
     * Archive previous contract documents
     */
    private function archivePreviousContractDocuments($userId, $reason) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                UPDATE user_documents 
                SET archived = TRUE, archived_reason = ? 
                WHERE user_id = ? AND document_type = 'contract' AND archived = FALSE
            ");
            $stmt->bind_param("si", $reason, $userId);
            $stmt->execute();
            $stmt->close();
            
        } catch (Exception $e) {
            error_log("Archive documents error: " . $e->getMessage());
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
     * Refresh contract with latest user profile data
     */
    public function refreshContractWithUserData($contractId) {
        try {
            $this->ensureConnection();
            
            // Get the contract and user data
            $stmt = $this->mysqli->prepare("
                SELECT uc.*, u.first_name, u.last_name, u.address, u.abn_acn, u.username
                FROM user_contracts uc
                JOIN users u ON uc.user_id = u.id
                WHERE uc.id = ?
            ");
            $stmt->bind_param("i", $contractId);
            $stmt->execute();
            $result = $stmt->get_result();
            $contractData = $result->fetch_assoc();
            $stmt->close();
            
            if (!$contractData) {
                return ['success' => false, 'message' => 'Contract not found.'];
            }
            
            // Get current user profile data
            $talentName = trim(($contractData['first_name'] ?? '') . ' ' . ($contractData['last_name'] ?? ''));
            $talentAddress = trim($contractData['address'] ?? '');
            $userAbn = !empty($contractData['abn_acn']) ? trim($contractData['abn_acn']) : '';
            
            // Validate required fields
            if (empty($talentName) || trim($talentName) === '') {
                return ['success' => false, 'message' => 'User profile is incomplete. Please ensure the user has a first and last name.'];
            }
            
            if (empty($talentAddress)) {
                return ['success' => false, 'message' => 'User profile is incomplete. Please ensure the user has an address.'];
            }
            
            // Generate new contract content with updated user data
            $template = $this->getDefaultContractTemplate();
            
            // Handle ABN/ACN section dynamically
            if (empty($userAbn)) {
                // Remove the entire ABN/ACN line if user doesn't have one, but keep proper spacing
                $template = preg_replace('/\s*ABN\/ACN \(if applicable\):\s*\[Talent\'s ABN\/ACN\]\s*\n/', "\n", $template);
            }
            
            // Replace placeholders
            $personalizedContract = str_replace(
                ['[Date]', '[Talent\'s Full Legal Name]', '[Talent\'s Address]', '[Talent\'s ABN/ACN]', '[User\'s Full Legal Name]'],
                [date('F j, Y'), $talentName, $talentAddress, $userAbn, $talentName],
                $template
            );
            
            // Update the contract
            $stmt = $this->mysqli->prepare("
                UPDATE user_contracts 
                SET contract_content = ?, client_name = ?, client_address = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->bind_param("sssi", $personalizedContract, $talentName, $talentAddress, $contractId);
            $result = $stmt->execute();
            $stmt->close();
            
            if ($result) {
                return ['success' => true, 'message' => 'Contract refreshed successfully with latest user profile data.'];
            } else {
                return ['success' => false, 'message' => 'Failed to refresh contract.'];
            }
            
        } catch (Exception $e) {
            error_log("Refresh contract error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while refreshing the contract.'];
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
    /** Convert contract to PDF and upload as document **/
    public function generateContractPDF($contractId) {
        return $this->generateContractPDFInternal($contractId, true); // true = send email notification
    }
    /** Internal method to generate contract PDF with notification control **/
    private function generateContractPDFInternal($contractId, $sendNotification = true) {
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
            $filename = "Contract_{$contract['client_name']}_{$contract['id']}_" . date('Y-m-d') . ".pdf";
            $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
            
            // Create temporary PDF file
            $tempPdfFile = tempnam(sys_get_temp_dir(), 'contract_') . '.pdf';
            
            // Convert HTML to PDF using PDFlib (if available) or create a simple text-based PDF
            $pdfGenerated = $this->htmlToPdf($tempHtmlFile, $tempPdfFile);
            
            if (!$pdfGenerated) {
                // Clean up temp files on failure
                if (file_exists($tempHtmlFile)) unlink($tempHtmlFile);
                if (file_exists($tempPdfFile)) unlink($tempPdfFile);
                
                return ['success' => false, 'message' => 'Failed to generate PDF. Please check server configuration.'];
            }
            
            // Create a mock $_FILES array for DocumentService
            $mockFile = [
                'name' => $filename,
                'tmp_name' => $tempPdfFile,
                'size' => filesize($tempPdfFile),
                'type' => 'application/pdf',
                'error' => UPLOAD_ERR_OK
            ];
            
            // Upload using DocumentService
            $uploadResult = $this->documentService->uploadUserDocument(
                $contract['user_id'],
                $mockFile,
                'contract',
                "Communications Services Agreement for {$contract['client_name']}",
                $_SESSION['user_id'] ?? 1,
                $sendNotification  // Pass the notification parameter
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
     * Convert HTML to PDF using mPDF
     */
    private function htmlToPdf($htmlFile, $outputFile) {
        try {
            error_log("Creating robust PDF with proper structure");
            
            // Read HTML content and convert to text
            $htmlContent = file_get_contents($htmlFile);
            $textContent = $this->htmlToText($htmlContent);
            
            if (empty(trim($textContent))) {
                error_log("No content available for PDF generation");
                return false;
            }
            
            // Create PDF with proper structure and cross-references
            $this->createRobustPDF($textContent, $outputFile);
            
            // Validate the generated file
            if (!file_exists($outputFile) || filesize($outputFile) == 0) {
                error_log("PDF file was not created or is empty");
                return false;
            }
            
            error_log("Robust PDF generated successfully: " . filesize($outputFile) . " bytes");
            return true;
            
        } catch (Exception $e) {
            error_log("PDF generation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a robust PDF with proper cross-reference table and multi-page support
     */
    private function createRobustPDF($textContent, $outputFile) {
        // Prepare text content
        $lines = explode("\n", $textContent);
        $contentLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) > 0) {
                // Wrap long lines
                while (strlen($line) > 70) {
                    $contentLines[] = substr($line, 0, 70);
                    $line = substr($line, 70);
                }
                if (strlen($line) > 0) {
                    $contentLines[] = $line;
                }
            } else {
                $contentLines[] = ""; // Preserve empty lines
            }
        }
        
        // Split content into pages (about 55 lines per page to fill better)
        $linesPerPage = 55;
        $pages = array_chunk($contentLines, $linesPerPage);
        $pageCount = count($pages);
        
        // Build PDF structure
        $pdf = "%PDF-1.4\n";
        $objOffsets = [];
        $objNum = 1;
        
        // Object 1: Catalog
        $objOffsets[$objNum] = strlen($pdf);
        $pdf .= "{$objNum} 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Catalog\n";
        $pdf .= "/Pages 2 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";
        $objNum++;
        
        // Object 2: Pages
        $objOffsets[$objNum] = strlen($pdf);
        $pdf .= "{$objNum} 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Pages\n";
        $pdf .= "/Kids [";
        for ($i = 0; $i < $pageCount; $i++) {
            $pdf .= ($i + 3) . " 0 R";
            if ($i < $pageCount - 1) $pdf .= " ";
        }
        $pdf .= "]\n";
        $pdf .= "/Count {$pageCount}\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";
        $objNum++;
        
        // Page objects and content streams
        $pageObjectNums = [];
        $contentObjectNums = [];
        
        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            // Page object
            $pageObjectNums[$pageIndex] = $objNum;
            $objOffsets[$objNum] = strlen($pdf);
            $pdf .= "{$objNum} 0 obj\n";
            $pdf .= "<<\n";
            $pdf .= "/Type /Page\n";
            $pdf .= "/Parent 2 0 R\n";
            $pdf .= "/MediaBox [0 0 612 792]\n";
            $pdf .= "/Contents " . ($objNum + $pageCount) . " 0 R\n";
            $pdf .= "/Resources <<\n";
            $pdf .= "  /Font <<\n";
            $pdf .= "    /F1 " . ($objNum + $pageCount * 2) . " 0 R\n";
            $pdf .= "  >>\n";
            $pdf .= ">>\n";
            $pdf .= ">>\n";
            $pdf .= "endobj\n\n";
            $objNum++;
        }
        
        // Content stream objects
        for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
            $pageLines = $pages[$pageIndex];
            
            // Build content stream for this page
            $yPosition = 780;
            $stream = "BT\n/F1 11 Tf\n50 {$yPosition} Td\n";
            
            foreach ($pageLines as $line) {
                if (empty($line)) {
                    // Empty line - just move down
                    $stream .= "0 -13 Td\n";
                    $yPosition -= 13;
                    continue;
                }
                
                // Escape PDF special characters
                $line = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
                $stream .= "({$line}) Tj\n0 -13 Td\n";
                $yPosition -= 13;
            }
            
            $stream .= "ET";
            $streamLength = strlen($stream);
            
            // Content stream object
            $contentObjectNums[$pageIndex] = $objNum;
            $objOffsets[$objNum] = strlen($pdf);
            $pdf .= "{$objNum} 0 obj\n";
            $pdf .= "<<\n";
            $pdf .= "/Length {$streamLength}\n";
            $pdf .= ">>\n";
            $pdf .= "stream\n";
            $pdf .= $stream . "\n";
            $pdf .= "endstream\n";
            $pdf .= "endobj\n\n";
            $objNum++;
        }
        
        // Font object (shared by all pages)
        $objOffsets[$objNum] = strlen($pdf);
        $pdf .= "{$objNum} 0 obj\n";
        $pdf .= "<<\n";
        $pdf .= "/Type /Font\n";
        $pdf .= "/Subtype /Type1\n";
        $pdf .= "/BaseFont /Helvetica\n";
        $pdf .= ">>\n";
        $pdf .= "endobj\n\n";
        
        // Cross-reference table
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= "0 " . ($objNum + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        
        for ($i = 1; $i <= $objNum; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $objOffsets[$i]);
        }
        
        // Trailer
        $pdf .= "trailer\n";
        $pdf .= "<<\n";
        $pdf .= "/Size " . ($objNum + 1) . "\n";
        $pdf .= "/Root 1 0 R\n";
        $pdf .= ">>\n";
        $pdf .= "startxref\n";
        $pdf .= $xrefOffset . "\n";
        $pdf .= "%%EOF";
        
        // Write to file
        file_put_contents($outputFile, $pdf);
    }
    
    /**
     * Convert HTML content to plain text
     */
    private function htmlToText($html) {
        // Remove style blocks completely
        $text = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        // Remove script blocks completely
        $text = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $text);
        // Handle line breaks and paragraphs
        $text = str_replace(['<br>', '<br/>', '<br />'], "\n", $text);
        $text = str_replace(['</p>', '</div>', '</h1>', '</h2>', '</h3>', '</li>'], "\n\n", $text);
        // Remove all other HTML tags
        $text = strip_tags($text);
        // Convert HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Clean up excessive whitespace while preserving line breaks
        $text = preg_replace('/[ \t]+/', ' ', $text); // Replace multiple spaces/tabs with single space
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text); // Replace multiple line breaks with double line break
        $text = trim($text);
        return $text;
    }
    
    /**
     * Regenerate PDF for an existing contract
     */
    public function regenerateContractPDF($contractId) {
        try {
            $contract = $this->getContract($contractId);
            if (!$contract) {
                return ['success' => false, 'message' => 'Contract not found.'];
            }
            
            // Delete any existing PDF documents for this contract (since we're fixing issues)
            $this->deleteContractDocuments($contract['user_id']);
            
            // Generate new PDF using the same logic as generateContractPDF, but skip notifications
            $result = $this->generateContractPDFInternal($contractId, false); // false = no email notifications
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'PDF regenerated successfully and replaced the previous version.',
                    'document_id' => $result['document_id'] ?? null
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to regenerate PDF: ' . $result['message']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Regenerate contract PDF error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while regenerating the contract PDF.'];
        }
    }
    
    /**
     * Delete existing contract documents (for regeneration)
     */
    private function deleteContractDocuments($userId) {
        try {
            $this->ensureConnection();
            
            // First get the documents we need to delete (to clean up files)
            $stmt = $this->mysqli->prepare("
                SELECT id, s3_key, s3_url 
                FROM user_documents 
                WHERE user_id = ? AND document_type = 'contract' AND archived = FALSE
            ");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $documents = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            // Delete each document using DocumentService to properly clean up files
            if (!empty($documents)) {
                foreach ($documents as $document) {
                    // Delete the database record and file
                    $deleteStmt = $this->mysqli->prepare("DELETE FROM user_documents WHERE id = ?");
                    $deleteStmt->bind_param("i", $document['id']);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                    
                    // Clean up the actual file if it exists locally
                    if (!empty($document['s3_url']) && file_exists($document['s3_url'])) {
                        unlink($document['s3_url']);
                    }
                }
                
                error_log("Deleted " . count($documents) . " existing contract documents for user $userId");
            }
            
        } catch (Exception $e) {
            error_log("Delete contract documents error: " . $e->getMessage());
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
