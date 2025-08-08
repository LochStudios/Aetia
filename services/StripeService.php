<?php
// services/StripeService.php - Secure Stripe billing integration for Aetia

require_once '/home/aetiacom/vendors/stripe/init.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../includes/SecurityException.php';

use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Exception\ApiErrorException;

class StripeService {
    private $secretKey;
    private $publishableKey;
    private $webhookSecret;
    private $companyInfo;
    private $defaultCurrency;
    private $invoiceTerms;
    private $serviceFeeDescription;
    private $manualReviewFeeDescription;
    private $securityManager;

    public function __construct() {
        $this->securityManager = new SecurityManager();
        $this->loadConfiguration();
        Stripe::setApiKey($this->secretKey);
        $keyPrefix = substr($this->secretKey, 0, 8) . '...';
        error_log("Stripe API key set: {$keyPrefix} (length: " . strlen($this->secretKey) . ")");
    }

    /**
     * Load Stripe configuration from external config file
     */
    private function loadConfiguration() {
        $configFile = '/home/aetiacom/web-config/stripe.php';
        
        if (!file_exists($configFile)) {
            throw new Exception("Stripe configuration file not found at: {$configFile}");
        }
        
        // Include the configuration file
        include $configFile;
        
        // Check that required variables are defined
        if (!isset($stripeSecretKey) || !isset($stripePublishableKey)) {
            throw new Exception("Stripe configuration file must define \$stripeSecretKey and \$stripePublishableKey variables");
        }
        
        // Validate the secret key format
        if (empty(trim($stripeSecretKey))) {
            throw new Exception("Stripe secret key is empty or contains only whitespace");
        }
        
        // Check if it looks like a valid Stripe secret key
        if (!preg_match('/^sk_(test_|live_)?[a-zA-Z0-9]{24,}$/', trim($stripeSecretKey))) {
            throw new Exception("Stripe secret key format appears invalid. Should start with 'sk_test_' or 'sk_live_' followed by alphanumeric characters");
        }
        
        // Validate publishable key format
        if (empty(trim($stripePublishableKey))) {
            throw new Exception("Stripe publishable key is empty or contains only whitespace");
        }
        
        if (!preg_match('/^pk_(test_|live_)?[a-zA-Z0-9]{24,}$/', trim($stripePublishableKey))) {
            throw new Exception("Stripe publishable key format appears invalid. Should start with 'pk_test_' or 'pk_live_' followed by alphanumeric characters");
        }

        $this->secretKey = trim($stripeSecretKey);
        $this->publishableKey = trim($stripePublishableKey);
        $this->webhookSecret = $stripeWebhookSecret ?? '';
        $this->companyInfo = $companyInfo ?? [];
        $this->defaultCurrency = $defaultCurrency ?? 'usd';
        $this->invoiceTerms = $invoiceTerms ?? 'Net 30';
        $this->serviceFeeDescription = $serviceFeeDescription ?? 'Monthly Communication Service Fee';
        $this->manualReviewFeeDescription = $manualReviewFeeDescription ?? 'Manual Review Processing Fee';
    }

    /**
     * Check Stripe configuration status without initializing the service
     */
    public static function checkConfiguration() {
        $status = [
            'config_file_exists' => false,
            'vendor_library_exists' => false,
            'secret_key_defined' => false,
            'publishable_key_defined' => false,
            'errors' => []
        ];

        // Check vendor library
        $vendorPath = '/home/aetiacom/vendors/stripe/init.php';
        $status['vendor_library_exists'] = file_exists($vendorPath);
        if (!$status['vendor_library_exists']) {
            $status['errors'][] = "Stripe vendor library not found at: {$vendorPath}";
        }

        // Check config file
        $configFile = '/home/aetiacom/web-config/stripe.php';
        $status['config_file_exists'] = file_exists($configFile);
        
        if (!$status['config_file_exists']) {
            $status['errors'][] = "Stripe configuration file not found at: {$configFile}";
        } else {
            // Check configuration variables
            include $configFile;
            
            $status['secret_key_defined'] = isset($stripeSecretKey) && !empty(trim($stripeSecretKey));
            $status['publishable_key_defined'] = isset($stripePublishableKey) && !empty(trim($stripePublishableKey));
            
            if (!$status['secret_key_defined']) {
                $status['errors'][] = "Variable \$stripeSecretKey is not defined or empty in config file";
            } else {
                // Check secret key format
                $trimmedSecretKey = trim($stripeSecretKey);
                if (!preg_match('/^sk_(test_|live_)?[a-zA-Z0-9]{24,}$/', $trimmedSecretKey)) {
                    $status['errors'][] = "Secret key format appears invalid (should start with 'sk_test_' or 'sk_live_')";
                    $status['secret_key_defined'] = false;
                } else {
                    // Log key info for debugging
                    $keyPrefix = substr($trimmedSecretKey, 0, 8) . '...';
                    error_log("Config check - Secret key: {$keyPrefix} (length: " . strlen($trimmedSecretKey) . ")");
                }
            }
            
            if (!$status['publishable_key_defined']) {
                $status['errors'][] = "Variable \$stripePublishableKey is not defined or empty in config file";
            } else {
                // Check publishable key format
                $trimmedPublishableKey = trim($stripePublishableKey);
                if (!preg_match('/^pk_(test_|live_)?[a-zA-Z0-9]{24,}$/', $trimmedPublishableKey)) {
                    $status['errors'][] = "Publishable key format appears invalid (should start with 'pk_test_' or 'pk_live_')";
                    $status['publishable_key_defined'] = false;
                }
            }
        }

        return $status;
    }

    /**
     * Create or update a Stripe customer
     */
    public function createOrUpdateCustomer($clientData) {
        // Security validation
        if (!isset($_SESSION['user_id'])) {
            throw new SecurityException('Unauthorized access - no valid session');
        }

        // Validate client data
        $this->validateClientData($clientData);

        try {
            $customerData = [
                'email' => filter_var($clientData['email'], FILTER_SANITIZE_EMAIL),
                'name' => $this->sanitizeName($clientData['first_name'] . ' ' . $clientData['last_name']),
                'metadata' => [
                    'user_id' => (int)$clientData['user_id'],
                    'username' => preg_replace('/[^a-zA-Z0-9_.-]/', '', $clientData['username']),
                    'account_type' => preg_replace('/[^a-zA-Z]/', '', $clientData['account_type']),
                    'created_by_admin' => $_SESSION['user_id'],
                    'creation_timestamp' => time()
                ]
            ];

            // Check if customer already exists
            $existingCustomer = $this->findCustomerByEmail($clientData['email']);
            
            if ($existingCustomer) {
                // Update existing customer with security check
                $this->verifyCustomerOwnership($existingCustomer, $clientData['user_id']);
                $customer = Customer::update($existingCustomer->id, $customerData);
                error_log("Updated Stripe customer: {$customer->id} for user ID: {$clientData['user_id']}");
            } else {
                // Create new customer
                $customer = Customer::create($customerData);
                error_log("Created new Stripe customer: {$customer->id} for user ID: {$clientData['user_id']}");
            }

            return $customer;
        } catch (ApiErrorException $e) {
            error_log("Stripe customer creation/update error: " . $e->getMessage());
            throw new SecurityException("Failed to create/update Stripe customer: " . $e->getMessage());
        }
    }

    /**
     * Find customer by email address
     */
    private function findCustomerByEmail($email) {
        try {
            $customers = Customer::all(['email' => $email, 'limit' => 1]);
            return !empty($customers->data) ? $customers->data[0] : null;
        } catch (ApiErrorException $e) {
            error_log("Error finding Stripe customer by email: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create invoice for a client's billing period
     */
    public function createInvoice($clientData, $billingPeriod) {
        try {
            // Debug: Log the billing data being processed
            error_log("STRIPE DEBUG: Creating invoice for user {$clientData['user_id']} - Standard Fee: $" . number_format($clientData['standard_fee'], 2) . ", Manual Review Fee: $" . number_format($clientData['manual_review_fee'], 2) . ", Total: $" . number_format($clientData['total_fee'], 2));
            
            // Create or update customer first
            $customer = $this->createOrUpdateCustomer($clientData);

            // Initialize invoice items array for tracking
            $invoiceItems = [];

            // Create the invoice first (in draft state)
            $invoice = Invoice::create([
                'customer' => $customer->id,
                'collection_method' => 'send_invoice',
                'days_until_due' => 30, // Net 30 terms
                'metadata' => [
                    'user_id' => $clientData['user_id'],
                    'billing_period' => $billingPeriod,
                    'total_messages' => $clientData['total_message_count'],
                    'manual_reviews' => $clientData['manual_review_count'],
                    'generated_by' => 'aetia_billing_system'
                ],
                'custom_fields' => [
                    [
                        'name' => 'Billing Period',
                        'value' => $billingPeriod
                    ],
                    [
                        'name' => 'Total Messages',
                        'value' => (string)$clientData['total_message_count']
                    ]
                ]
            ]);
            error_log("STRIPE DEBUG: Created draft invoice with ID: " . $invoice->id);
            // Add service fee item to the specific invoice
            if ($clientData['standard_fee'] > 0) {
                error_log("STRIPE DEBUG: Creating service fee item - Amount: $" . number_format($clientData['standard_fee'], 2) . " (" . ($clientData['standard_fee'] * 100) . " cents)");
                $serviceItem = InvoiceItem::create([
                    'customer' => $customer->id,
                    'invoice' => $invoice->id, // Explicitly attach to this invoice
                    'amount' => $clientData['standard_fee'] * 100, // Convert to cents
                    'currency' => $this->defaultCurrency,
                    'description' => $this->serviceFeeDescription . " - {$billingPeriod}",
                    'metadata' => [
                        'user_id' => $clientData['user_id'],
                        'message_count' => $clientData['total_message_count'],
                        'billing_period' => $billingPeriod,
                        'fee_type' => 'service_fee'
                    ]
                ]);
                $invoiceItems[] = $serviceItem;
                error_log("STRIPE DEBUG: Service fee item created with ID: " . $serviceItem->id . " attached to invoice: " . $invoice->id);
            } else {
                error_log("STRIPE DEBUG: Skipping service fee item - amount is $0");
            }
            // Add manual review fee item to the specific invoice if applicable
            if ($clientData['manual_review_fee'] > 0) {
                error_log("STRIPE DEBUG: Creating manual review fee item - Amount: $" . number_format($clientData['manual_review_fee'], 2) . " (" . ($clientData['manual_review_fee'] * 100) . " cents)");
                $reviewItem = InvoiceItem::create([
                    'customer' => $customer->id,
                    'invoice' => $invoice->id, // Explicitly attach to this invoice
                    'amount' => $clientData['manual_review_fee'] * 100, // Convert to cents
                    'currency' => $this->defaultCurrency,
                    'description' => $this->manualReviewFeeDescription . " - {$billingPeriod}",
                    'metadata' => [
                        'user_id' => $clientData['user_id'],
                        'manual_review_count' => $clientData['manual_review_count'],
                        'billing_period' => $billingPeriod,
                        'fee_type' => 'manual_review_fee'
                    ]
                ]);
                $invoiceItems[] = $reviewItem;
                error_log("STRIPE DEBUG: Manual review fee item created with ID: " . $reviewItem->id . " attached to invoice: " . $invoice->id);
            } else {
                error_log("STRIPE DEBUG: Skipping manual review fee item - amount is $0");
            }
            // Refresh the invoice to get the latest data including the items
            $invoice = Invoice::retrieve($invoice->id);
            error_log("STRIPE DEBUG: Retrieved invoice after adding items. Total amount: " . ($invoice->total / 100) . " USD");
            // Finalize the invoice to make it ready to send
            $invoice->finalizeInvoice();
            error_log("STRIPE DEBUG: Finalized invoice. Final amount: " . ($invoice->total / 100) . " USD");
            // Send the invoice via Stripe's email system
            $invoice->sendInvoice();
            error_log("STRIPE DEBUG: Invoice email sent via Stripe for invoice: " . $invoice->id);
            error_log("Created Stripe invoice: {$invoice->id} for user ID: {$clientData['user_id']} - Amount: $" . number_format($clientData['total_fee'], 2) . " (email sent)");
            return $invoice;
        } catch (ApiErrorException $e) {
            error_log("Stripe invoice creation error for user ID {$clientData['user_id']}: " . $e->getMessage());
            throw new Exception("Failed to create Stripe invoice: " . $e->getMessage());
        }
    }

    /**
     * Send invoice via email
     */
    public function sendInvoice($invoiceId) {
        try {
            $invoice = Invoice::retrieve($invoiceId);
            $invoice->sendInvoice();
            error_log("Sent Stripe invoice: {$invoiceId}");
            return true;
        } catch (ApiErrorException $e) {
            error_log("Error sending Stripe invoice {$invoiceId}: " . $e->getMessage());
            throw new Exception("Failed to send invoice: " . $e->getMessage());
        }
    }

    /**
     * Create invoices for multiple clients (batch processing)
     */
    public function createBatchInvoices($billData, $billingPeriod) {
        // Security checks
        if (!isset($_SESSION['user_id'])) {
            throw new SecurityException('Unauthorized access - no valid session');
        }

        if (!$this->securityManager->verifyAdminAccess($_SESSION['user_id'], 'stripe_create_invoices')) {
            throw new SecurityException('Access denied for batch invoice creation');
        }

        // Validate billing data
        $this->securityManager->validateBillingData($billData);

        // Additional validation for billing period
        if (!preg_match('/^[A-Za-z]+ \d{4}$/', $billingPeriod)) {
            throw new SecurityException('Invalid billing period format');
        }

        $results = [
            'success' => [],
            'errors' => [],
            'total_processed' => 0,
            'total_amount' => 0
        ];

        // Limit batch size for security
        if (count($billData) > 100) {
            throw new SecurityException('Batch size too large - maximum 100 invoices per batch');
        }

        foreach ($billData as $clientData) {
            $results['total_processed']++;
            
            try {
                // Additional per-client validation
                $this->validateClientForInvoicing($clientData);
                
                $invoice = $this->createInvoice($clientData, $billingPeriod);
                
                $results['success'][] = [
                    'user_id' => (int)$clientData['user_id'],
                    'email' => filter_var($clientData['email'], FILTER_SANITIZE_EMAIL),
                    'invoice_id' => $invoice->id,
                    'amount' => (float)$clientData['total_fee'],
                    'invoice_url' => $invoice->hosted_invoice_url,
                    'email_sent' => true // Stripe sends email automatically when invoice is created
                ];
                
                $results['total_amount'] += (float)$clientData['total_fee'];
                
                // Log successful invoice creation
                error_log("Invoice created and sent successfully: {$invoice->id} for user {$clientData['user_id']} by admin {$_SESSION['user_id']}");
                
            } catch (Exception $e) {
                $results['errors'][] = [
                    'user_id' => (int)$clientData['user_id'],
                    'email' => filter_var($clientData['email'], FILTER_SANITIZE_EMAIL),
                    'error' => $e->getMessage()
                ];
                error_log("Failed to create invoice for user ID {$clientData['user_id']}: " . $e->getMessage());
            }
            
            // Add delay to avoid rate limiting and reduce server load
            usleep(500000); // 0.5 seconds between requests
        }

        // Log batch operation summary
        error_log("Batch invoice operation completed by admin {$_SESSION['user_id']}: {$results['total_processed']} processed, " . count($results['success']) . " successful, " . count($results['errors']) . " errors. All successful invoices automatically emailed by Stripe.");

        return $results;
    }

    /**
     * Get invoice status and payment information
     */
    public function getInvoiceStatus($invoiceId) {
        try {
            $invoice = Invoice::retrieve($invoiceId);
            return [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'amount_due' => $invoice->amount_due / 100, // Convert from cents
                'amount_paid' => $invoice->amount_paid / 100,
                'paid' => $invoice->paid,
                'hosted_invoice_url' => $invoice->hosted_invoice_url,
                'invoice_pdf' => $invoice->invoice_pdf,
                'due_date' => $invoice->due_date ? date('Y-m-d', $invoice->due_date) : null
            ];
        } catch (ApiErrorException $e) {
            error_log("Error retrieving Stripe invoice {$invoiceId}: " . $e->getMessage());
            throw new Exception("Failed to retrieve invoice status: " . $e->getMessage());
        }
    }

    /**
     * Get publishable key for frontend use
     */
    public function getPublishableKey() {
        return $this->publishableKey;
    }

    /**
     * Test the Stripe connection
     */
    public function testConnection() {
        // Security check
        if (!isset($_SESSION['user_id'])) {
            throw new SecurityException('Unauthorized access - no valid session');
        }

        if (!$this->securityManager->verifyAdminAccess($_SESSION['user_id'], 'stripe_test_connection')) {
            throw new SecurityException('Access denied for Stripe connection test');
        }

        try {
            // Log some debugging info (without exposing the actual key)
            $keyPrefix = substr($this->secretKey, 0, 8) . '...';
            error_log("Attempting Stripe connection test with key: {$keyPrefix}");
            
            // Try to retrieve account information
            $account = \Stripe\Account::retrieve();
            
            // Log the test
            error_log("Stripe connection test successful by admin {$_SESSION['user_id']}");
            
            return [
                'success' => true,
                'account_id' => substr($account->id, 0, 15) . '...', // Partial ID only
                'business_profile' => $account->business_profile->name ?? 'N/A',
                'country' => $account->country,
                'default_currency' => $account->default_currency
            ];
        } catch (ApiErrorException $e) {
            error_log("Stripe connection test failed by admin {$_SESSION['user_id']}: " . $e->getMessage());
            
            // Provide more specific error information
            $errorMessage = 'Connection failed - check configuration';
            if ($e->getStripeCode()) {
                $errorMessage .= ' (Code: ' . $e->getStripeCode() . ')';
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'details' => $e->getMessage()
            ];
        } catch (Exception $e) {
            error_log("Stripe connection test error by admin {$_SESSION['user_id']}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unexpected error occurred',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate client data for security
     */
    private function validateClientData($clientData) {
        $requiredFields = ['user_id', 'email', 'username'];
        
        foreach ($requiredFields as $field) {
            if (!isset($clientData[$field]) || empty($clientData[$field])) {
                throw new SecurityException("Missing required field: $field");
            }
        }

        // Validate email format
        if (!filter_var($clientData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new SecurityException('Invalid email format');
        }

        // Validate user_id is numeric and positive
        if (!is_numeric($clientData['user_id']) || $clientData['user_id'] <= 0) {
            throw new SecurityException('Invalid user_id');
        }

        // Validate username format
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $clientData['username'])) {
            throw new SecurityException('Invalid username format');
        }
    }

    /**
     * Sanitize name fields
     */
    private function sanitizeName($name) {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z\s\'-]/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return substr($name, 0, 100); // Limit length
    }

    /**
     * Verify customer ownership to prevent unauthorized access
     */
    private function verifyCustomerOwnership($stripeCustomer, $expectedUserId) {
        $metadata = $stripeCustomer->metadata ?? [];
        $storedUserId = $metadata['user_id'] ?? null;
        
        if ($storedUserId && $storedUserId != $expectedUserId) {
            throw new SecurityException('Customer ownership mismatch');
        }
    }

    /**
     * Validate client data specifically for invoicing
     */
    private function validateClientForInvoicing($clientData) {
        // Check for reasonable fee amounts
        $totalFee = (float)$clientData['total_fee'];
        if ($totalFee < 0 || $totalFee > 10000) {
            throw new SecurityException("Invalid fee amount: $totalFee");
        }

        // Validate message counts
        $messageCount = (int)($clientData['total_message_count'] ?? 0);
        if ($messageCount < 0 || $messageCount > 1000) {
            throw new SecurityException("Invalid message count: $messageCount");
        }

        // Check for suspicious patterns
        if (isset($clientData['manual_review_count'])) {
            $reviewCount = (int)$clientData['manual_review_count'];
            if ($reviewCount < 0 || $reviewCount > $messageCount) {
                throw new SecurityException("Invalid manual review count: $reviewCount");
            }
        }
    }
}
