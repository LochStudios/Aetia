<?php
// api/stripe-webhook.php - Secure Stripe webhook handler with full database integration

error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to webhook sender

require_once '/home/aetiacom/vendors/stripe/init.php';
require_once __DIR__ . '/../services/StripeService.php';
require_once __DIR__ . '/../services/StripeDataService.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../includes/SecurityException.php';

use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

// Set response headers
header('Content-Type: application/json');
header('X-Powered-By: Aetia-Webhook-Handler');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Initialize services
$securityManager = new SecurityManager();
$stripeDataService = new StripeDataService();

// Log files
$logFile = '/var/log/stripe_webhooks.log';
$securityLogFile = '/var/log/stripe_webhook_security.log';

function logWebhook($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    error_log("[$timestamp] [$level] [IP:$ip] $message" . PHP_EOL, 3, $logFile);
}

function logSecurity($message) {
    global $securityLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    error_log("[$timestamp] [SECURITY] [IP:$ip] [UA:$userAgent] $message" . PHP_EOL, 3, $securityLogFile);
}

// Security validation
function validateWebhookSecurity() {
    global $securityManager;
    
    // Check if IP is from Stripe (basic validation)
    $allowedStripeIPs = [
        '54.187.174.169', '54.187.205.235', '54.187.216.72', '54.241.31.99', '54.241.31.102',
        '54.241.34.107', '177.71.207.51', '18.211.135.69', '3.18.12.63', '3.130.192.231',
        '13.235.14.237', '13.235.122.149', '35.154.171.200', '52.15.183.38', '54.88.130.119',
        '54.88.130.237', '54.187.174.169', '54.187.205.235', '54.187.216.72'
    ];
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Rate limiting for webhook endpoint
    if (!checkWebhookRateLimit($clientIP)) {
        logSecurity("Rate limit exceeded from IP: $clientIP");
        return false;
    }
    
    // Check User-Agent (Stripe uses specific user agents)
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!preg_match('/Stripe/', $userAgent)) {
        logSecurity("Invalid User-Agent for webhook: $userAgent");
        // Note: Don't block entirely as this could be too restrictive
    }
    
    return true;
}

function checkWebhookRateLimit($ip) {
    $rateLimitFile = '/home/aetiacom/tmp/stripe_webhook_rate_limit.json';
    $maxRequestsPerMinute = 100; // Allow up to 100 requests per minute per IP
    $now = time();
    
    // Load existing rate limit data
    $rateLimits = [];
    if (file_exists($rateLimitFile)) {
        $data = file_get_contents($rateLimitFile);
        $rateLimits = json_decode($data, true) ?: [];
    }
    
    // Clean old entries (older than 1 minute)
    if (isset($rateLimits[$ip])) {
        $rateLimits[$ip] = array_filter($rateLimits[$ip], function($timestamp) use ($now) {
            return ($now - $timestamp) < 60;
        });
    } else {
        $rateLimits[$ip] = [];
    }
    
    // Check if limit exceeded
    if (count($rateLimits[$ip]) >= $maxRequestsPerMinute) {
        return false;
    }
    
    // Add current request
    $rateLimits[$ip][] = $now;
    
    // Save rate limit data
    file_put_contents($rateLimitFile, json_encode($rateLimits), LOCK_EX);
    
    return true;
}

try {
    logWebhook("Webhook request received", 'INFO');
    
    // Validate security
    if (!validateWebhookSecurity()) {
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded']);
        exit;
    }
    
    // Get the webhook payload
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

    logWebhook("Processing webhook - Signature: " . substr($sig_header, 0, 20) . "...");

    if (empty($payload)) {
        throw new SecurityException('Empty payload received');
    }

    if (empty($sig_header)) {
        throw new SecurityException('Missing Stripe signature header');
    }

    // Load Stripe configuration to get webhook secret
    $configFile = '/home/aetiacom/web-config/stripe.php';
    if (!file_exists($configFile)) {
        throw new SecurityException('Stripe configuration not found');
    }

    include $configFile;
    if (empty($stripeWebhookSecret)) {
        throw new SecurityException('Webhook secret not configured');
    }

    // Verify the webhook signature
    try {
        $event = Webhook::constructEvent($payload, $sig_header, $stripeWebhookSecret);
    } catch (SignatureVerificationException $e) {
        logSecurity("Signature verification failed: " . $e->getMessage());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    // Log the verified event
    logWebhook("Verified webhook event: " . $event['type'] . " - ID: " . $event['id']);
    
    // Store webhook event in database
    $eventObject = $event['data']['object'];
    $objectId = $eventObject['id'] ?? null;
    $objectType = $eventObject['object'] ?? 'unknown';
    
    $stripeDataService->logWebhookEvent(
        $event['id'], 
        $event['type'], 
        $objectId, 
        $objectType, 
        $event['data']
    );

    // Process the event based on type
    $processingResult = processWebhookEvent($event, $stripeDataService);
    
    // Mark event as processed
    $stripeDataService->markWebhookEventProcessed(
        $event['id'], 
        $processingResult['success'], 
        $processingResult['error']
    );

    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success', 
        'event_type' => $event['type'],
        'event_id' => $event['id'],
        'processed' => $processingResult['success']
    ]);
    
    logWebhook("Webhook processed successfully: " . $event['type']);

} catch (SecurityException $e) {
    logSecurity("Security violation: " . $e->getMessage());
    http_response_code(403);
    echo json_encode(['error' => 'Security violation']);
} catch (Exception $e) {
    logWebhook("Webhook error: " . $e->getMessage(), 'ERROR');
    error_log("Stripe webhook error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode(['error' => 'Processing failed']);
}

/**
 * Process webhook events based on type
 */
function processWebhookEvent($event, $stripeDataService) {
    $result = ['success' => true, 'error' => null];
    
    try {
        switch ($event['type']) {
            case 'invoice.payment_succeeded':
                $result = handleInvoicePaymentSucceeded($event['data']['object'], $stripeDataService);
                break;

            case 'invoice.payment_failed':
                $result = handleInvoicePaymentFailed($event['data']['object'], $stripeDataService);
                break;

            case 'invoice.created':
                $result = handleInvoiceCreated($event['data']['object'], $stripeDataService);
                break;

            case 'invoice.finalized':
                $result = handleInvoiceFinalized($event['data']['object'], $stripeDataService);
                break;

            case 'invoice.voided':
                $result = handleInvoiceVoided($event['data']['object'], $stripeDataService);
                break;

            case 'customer.created':
                $result = handleCustomerCreated($event['data']['object'], $stripeDataService);
                break;

            case 'customer.updated':
                $result = handleCustomerUpdated($event['data']['object'], $stripeDataService);
                break;

            case 'payment_intent.succeeded':
                $result = handlePaymentIntentSucceeded($event['data']['object'], $stripeDataService);
                break;

            case 'payment_intent.payment_failed':
                $result = handlePaymentIntentFailed($event['data']['object'], $stripeDataService);
                break;

            case 'payment_intent.canceled':
                $result = handlePaymentIntentCanceled($event['data']['object'], $stripeDataService);
                break;

            case 'payment_intent.partially_funded':
                $result = handlePaymentIntentPartiallyFunded($event['data']['object'], $stripeDataService);
                break;

            default:
                logWebhook("Unhandled event type: " . $event['type'], 'WARNING');
                $result = ['success' => true, 'error' => 'Event type not handled'];
                break;
        }
    } catch (Exception $e) {
        logWebhook("Error processing event " . $event['type'] . ": " . $e->getMessage(), 'ERROR');
        $result = ['success' => false, 'error' => $e->getMessage()];
    }
    
    return $result;
}

/**
 * Handle successful invoice payment
 */
function handleInvoicePaymentSucceeded($invoice, $stripeDataService) {
    try {
        $amountPaid = $invoice['amount_paid'] / 100; // Convert from cents
        $paidAt = $invoice['status_transitions']['paid_at'] ?? time();
        
        // Update invoice status in database
        $updated = $stripeDataService->updateInvoicePayment(
            $invoice['id'], 
            'paid', 
            $amountPaid, 
            $paidAt
        );
        
        if ($updated) {
            // Get user information for notification
            $invoiceData = $stripeDataService->getInvoiceByStripeId($invoice['id']);
            
            if ($invoiceData) {
                // Record successful payment attempt
                $stripeDataService->recordPaymentAttempt(
                    $invoice['id'],
                    $invoiceData['user_id'],
                    'succeeded',
                    $amountPaid,
                    'automatic'
                );
                
                // Send success notification
                sendPaymentSuccessNotification($invoiceData, $amountPaid);
                
                logWebhook("Payment succeeded for invoice: " . $invoice['id'] . " - Amount: $" . $amountPaid . " - User: " . $invoiceData['user_id']);
            }
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle failed invoice payment
 */
function handleInvoicePaymentFailed($invoice, $stripeDataService) {
    try {
        $amountDue = $invoice['amount_due'] / 100;
        $failureCode = $invoice['last_payment_error']['code'] ?? 'unknown';
        $failureMessage = $invoice['last_payment_error']['message'] ?? 'Payment failed';
        
        // Get user information
        $invoiceData = $stripeDataService->getInvoiceByStripeId($invoice['id']);
        
        if ($invoiceData) {
            // Record failed payment attempt
            $stripeDataService->recordPaymentAttempt(
                $invoice['id'],
                $invoiceData['user_id'],
                'failed',
                $amountDue,
                'automatic',
                $failureCode . ': ' . $failureMessage
            );
            
            // Send failure notification to admin
            sendPaymentFailureNotification($invoiceData, $amountDue, $failureMessage);
            
            logWebhook("Payment failed for invoice: " . $invoice['id'] . " - User: " . $invoiceData['user_id'] . " - Reason: " . $failureMessage, 'WARNING');
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle invoice creation
 */
function handleInvoiceCreated($invoice, $stripeDataService) {
    try {
        // Extract user_id from customer metadata
        $userId = null;
        if (isset($invoice['customer_metadata']['user_id'])) {
            $userId = (int)$invoice['customer_metadata']['user_id'];
        } else {
            // Try to get from stored customer data
            $customerData = $stripeDataService->getCustomerByStripeId($invoice['customer']);
            $userId = $customerData['user_id'] ?? null;
        }
        
        if (!$userId) {
            logWebhook("Cannot determine user_id for invoice: " . $invoice['id'], 'WARNING');
            return ['success' => false, 'error' => 'Cannot determine user_id'];
        }
        
        // Prepare invoice data
        $invoiceData = [
            'stripe_invoice_id' => $invoice['id'],
            'stripe_customer_id' => $invoice['customer'],
            'user_id' => $userId,
            'invoice_number' => $invoice['number'],
            'status' => $invoice['status'],
            'amount_due' => $invoice['amount_due'] / 100,
            'amount_paid' => $invoice['amount_paid'] / 100,
            'currency' => $invoice['currency'],
            'billing_period' => $invoice['metadata']['billing_period'] ?? null,
            'payment_intent_id' => $invoice['payment_intent'],
            'hosted_invoice_url' => $invoice['hosted_invoice_url'],
            'invoice_pdf_url' => $invoice['invoice_pdf'],
            'due_date' => $invoice['due_date']
        ];
        
        // Save to database
        $stripeDataService->saveStripeInvoice($invoiceData);
        
        logWebhook("Invoice created and saved: " . $invoice['id'] . " - Amount: $" . ($invoice['amount_due'] / 100) . " - User: " . $userId);
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle invoice finalization
 */
function handleInvoiceFinalized($invoice, $stripeDataService) {
    try {
        // Update invoice status
        $updated = $stripeDataService->updateInvoicePayment($invoice['id'], 'open');
        
        if ($updated) {
            $invoiceData = $stripeDataService->getInvoiceByStripeId($invoice['id']);
            
            if ($invoiceData) {
                // Send invoice ready notification
                sendInvoiceFinalizedNotification($invoiceData, $invoice);
                
                logWebhook("Invoice finalized: " . $invoice['id'] . " - Number: " . $invoice['number'] . " - User: " . $invoiceData['user_id']);
            }
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle invoice voiding
 */
function handleInvoiceVoided($invoice, $stripeDataService) {
    try {
        // Update invoice status
        $updated = $stripeDataService->updateInvoicePayment($invoice['id'], 'void');
        
        if ($updated) {
            logWebhook("Invoice voided: " . $invoice['id'] . " - Reason: Admin action");
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle customer creation
 */
function handleCustomerCreated($customer, $stripeDataService) {
    try {
        $userId = $customer['metadata']['user_id'] ?? null;
        
        if ($userId) {
            $stripeDataService->saveStripeCustomer(
                $customer['id'],
                (int)$userId,
                $customer['email'],
                $customer['name']
            );
            
            logWebhook("Customer created and saved: " . $customer['id'] . " - Email: " . $customer['email'] . " - User: " . $userId);
        } else {
            logWebhook("Customer created without user_id metadata: " . $customer['id'], 'WARNING');
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle customer updates
 */
function handleCustomerUpdated($customer, $stripeDataService) {
    try {
        $userId = $customer['metadata']['user_id'] ?? null;
        
        if ($userId) {
            $stripeDataService->saveStripeCustomer(
                $customer['id'],
                (int)$userId,
                $customer['email'],
                $customer['name']
            );
            
            logWebhook("Customer updated: " . $customer['id'] . " - User: " . $userId);
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle successful payment intent
 */
function handlePaymentIntentSucceeded($paymentIntent, $stripeDataService) {
    try {
        $amount = $paymentIntent['amount'] / 100;
        
        logWebhook("Payment intent succeeded: " . $paymentIntent['id'] . " - Amount: $" . $amount);
        
        // Additional processing if needed
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle failed payment intent
 */
function handlePaymentIntentFailed($paymentIntent, $stripeDataService) {
    try {
        $amount = $paymentIntent['amount'] / 100;
        $failureCode = $paymentIntent['last_payment_error']['code'] ?? 'unknown';
        $failureMessage = $paymentIntent['last_payment_error']['message'] ?? 'Payment failed';
        
        logWebhook("Payment intent failed: " . $paymentIntent['id'] . " - Amount: $" . $amount . " - Reason: " . $failureMessage, 'WARNING');
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle canceled payment intent
 */
function handlePaymentIntentCanceled($paymentIntent, $stripeDataService) {
    try {
        $amount = $paymentIntent['amount'] / 100;
        $cancellationReason = $paymentIntent['cancellation_reason'] ?? 'unknown';
        
        logWebhook("Payment intent canceled: " . $paymentIntent['id'] . " - Amount: $" . $amount . " - Reason: " . $cancellationReason, 'WARNING');
        
        // Check if this payment intent was linked to an invoice
        if (isset($paymentIntent['invoice'])) {
            $invoiceData = $stripeDataService->getInvoiceByStripeId($paymentIntent['invoice']);
            
            if ($invoiceData) {
                // Record canceled payment attempt
                $stripeDataService->recordPaymentAttempt(
                    $paymentIntent['invoice'],
                    $invoiceData['user_id'],
                    'canceled',
                    $amount,
                    'automatic',
                    'Payment intent canceled: ' . $cancellationReason
                );
                
                // Notify admin of cancellation
                sendPaymentCancellationNotification($invoiceData, $amount, $cancellationReason);
                
                logWebhook("Payment intent cancellation recorded for invoice: " . $paymentIntent['invoice'] . " - User: " . $invoiceData['user_id']);
            }
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Handle partially funded payment intent
 */
function handlePaymentIntentPartiallyFunded($paymentIntent, $stripeDataService) {
    try {
        $totalAmount = $paymentIntent['amount'] / 100;
        $amountReceived = $paymentIntent['amount_received'] / 100;
        $amountRemaining = ($paymentIntent['amount'] - $paymentIntent['amount_received']) / 100;
        
        logWebhook("Payment intent partially funded: " . $paymentIntent['id'] . " - Received: $" . $amountReceived . " of $" . $totalAmount . " (Remaining: $" . $amountRemaining . ")", 'INFO');
        
        // Check if this payment intent was linked to an invoice
        if (isset($paymentIntent['invoice'])) {
            $invoiceData = $stripeDataService->getInvoiceByStripeId($paymentIntent['invoice']);
            
            if ($invoiceData) {
                // Record partial payment attempt
                $stripeDataService->recordPaymentAttempt(
                    $paymentIntent['invoice'],
                    $invoiceData['user_id'],
                    'partially_funded',
                    $amountReceived,
                    'automatic',
                    "Partial payment: $" . $amountReceived . " of $" . $totalAmount . " received. Remaining: $" . $amountRemaining
                );
                
                // Notify admin of partial payment
                sendPartialPaymentNotification($invoiceData, $amountReceived, $totalAmount, $amountRemaining);
                
                logWebhook("Partial payment recorded for invoice: " . $paymentIntent['invoice'] . " - User: " . $invoiceData['user_id'] . " - Amount received: $" . $amountReceived);
            }
        }
        
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Send payment success notification
 */
function sendPaymentSuccessNotification($invoiceData, $amount) {
    try {
        require_once __DIR__ . '/../services/EmailService.php';
        
        $emailService = new EmailService();
        $subject = "Payment Received - Invoice " . $invoiceData['invoice_number'];
        
        $message = "Dear " . $invoiceData['first_name'] . " " . $invoiceData['last_name'] . ",\n\n";
        $message .= "We have received your payment of $" . number_format($amount, 2) . " for invoice " . $invoiceData['invoice_number'] . ".\n\n";
        $message .= "Billing Period: " . $invoiceData['billing_period'] . "\n";
        $message .= "Payment Date: " . date('F j, Y') . "\n\n";
        $message .= "Thank you for your business!\n\n";
        $message .= "Best regards,\n";
        $message .= "Aetia Talent Agency";
        
        $emailService->sendEmail($invoiceData['user_email'], $subject, $message);
        
        // Also notify admin
        $adminSubject = "Payment Received - " . $invoiceData['username'];
        $adminMessage = "Payment received from " . $invoiceData['username'] . " (" . $invoiceData['user_email'] . ")\n";
        $adminMessage .= "Amount: $" . number_format($amount, 2) . "\n";
        $adminMessage .= "Invoice: " . $invoiceData['invoice_number'] . "\n";
        $adminMessage .= "Billing Period: " . $invoiceData['billing_period'];
        
        $emailService->sendEmail('admin@aetia.com', $adminSubject, $adminMessage);
        
    } catch (Exception $e) {
        logWebhook("Failed to send payment success notification: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send payment failure notification
 */
function sendPaymentFailureNotification($invoiceData, $amount, $failureReason) {
    try {
        require_once __DIR__ . '/../services/EmailService.php';
        
        $emailService = new EmailService();
        
        // Notify admin of failed payment
        $subject = "Payment Failed - " . $invoiceData['username'];
        $message = "Payment failed for " . $invoiceData['username'] . " (" . $invoiceData['user_email'] . ")\n\n";
        $message .= "Invoice: " . $invoiceData['invoice_number'] . "\n";
        $message .= "Amount: $" . number_format($amount, 2) . "\n";
        $message .= "Billing Period: " . $invoiceData['billing_period'] . "\n";
        $message .= "Failure Reason: " . $failureReason . "\n\n";
        $message .= "Please follow up with the client.\n\n";
        $message .= "View invoice: " . $invoiceData['hosted_invoice_url'];
        
        $emailService->sendEmail('admin@aetia.com', $subject, $message);
        
    } catch (Exception $e) {
        logWebhook("Failed to send payment failure notification: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send invoice finalized notification
 */
function sendInvoiceFinalizedNotification($invoiceData, $invoice) {
    try {
        require_once __DIR__ . '/../services/EmailService.php';
        
        $emailService = new EmailService();
        
        // Notify admin that invoice is ready
        $subject = "Invoice Ready - " . $invoiceData['username'];
        $message = "Invoice " . $invoice['number'] . " has been finalized and is ready for " . $invoiceData['username'] . "\n\n";
        $message .= "Client: " . $invoiceData['first_name'] . " " . $invoiceData['last_name'] . " (" . $invoiceData['user_email'] . ")\n";
        $message .= "Amount: $" . number_format($invoice['amount_due'] / 100, 2) . "\n";
        $message .= "Due Date: " . ($invoice['due_date'] ? date('F j, Y', $invoice['due_date']) : 'Net 30') . "\n";
        $message .= "Billing Period: " . $invoiceData['billing_period'] . "\n\n";
        $message .= "Client invoice URL: " . $invoice['hosted_invoice_url'] . "\n";
        $message .= "Admin view: https://dashboard.stripe.com/invoices/" . $invoice['id'];
        
        $emailService->sendEmail('admin@aetia.com', $subject, $message);
        
    } catch (Exception $e) {
        logWebhook("Failed to send invoice finalized notification: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send payment cancellation notification
 */
function sendPaymentCancellationNotification($invoiceData, $amount, $cancellationReason) {
    try {
        require_once __DIR__ . '/../services/EmailService.php';
        
        $emailService = new EmailService();
        
        // Notify admin of canceled payment
        $subject = "Payment Canceled - " . $invoiceData['username'];
        $message = "Payment was canceled for " . $invoiceData['username'] . " (" . $invoiceData['user_email'] . ")\n\n";
        $message .= "Invoice: " . $invoiceData['invoice_number'] . "\n";
        $message .= "Amount: $" . number_format($amount, 2) . "\n";
        $message .= "Billing Period: " . $invoiceData['billing_period'] . "\n";
        $message .= "Cancellation Reason: " . $cancellationReason . "\n\n";
        $message .= "The client may need to retry the payment or use an alternative payment method.\n\n";
        $message .= "View invoice: " . $invoiceData['hosted_invoice_url'];
        
        $emailService->sendEmail('admin@aetia.com', $subject, $message);
        
    } catch (Exception $e) {
        logWebhook("Failed to send payment cancellation notification: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Send partial payment notification
 */
function sendPartialPaymentNotification($invoiceData, $amountReceived, $totalAmount, $amountRemaining) {
    try {
        require_once __DIR__ . '/../services/EmailService.php';
        
        $emailService = new EmailService();
        
        // Notify admin of partial payment
        $subject = "Partial Payment Received - " . $invoiceData['username'];
        $message = "Partial payment received from " . $invoiceData['username'] . " (" . $invoiceData['user_email'] . ")\n\n";
        $message .= "Invoice: " . $invoiceData['invoice_number'] . "\n";
        $message .= "Amount Received: $" . number_format($amountReceived, 2) . "\n";
        $message .= "Total Amount Due: $" . number_format($totalAmount, 2) . "\n";
        $message .= "Remaining Balance: $" . number_format($amountRemaining, 2) . "\n";
        $message .= "Billing Period: " . $invoiceData['billing_period'] . "\n\n";
        $message .= "The remaining balance is still outstanding and may require follow-up.\n\n";
        $message .= "View invoice: " . $invoiceData['hosted_invoice_url'];
        
        $emailService->sendEmail('admin@aetia.com', $subject, $message);
        
    } catch (Exception $e) {
        logWebhook("Failed to send partial payment notification: " . $e->getMessage(), 'ERROR');
    }
}

// Clean up old webhook events periodically
if (rand(1, 100) === 1) { // 1% chance per webhook
    try {
        $deletedCount = $stripeDataService->cleanupOldWebhookEvents(30, 1000);
        if ($deletedCount > 0) {
            logWebhook("Cleaned up $deletedCount old webhook events");
        }
    } catch (Exception $e) {
        logWebhook("Failed to clean up old webhook events: " . $e->getMessage(), 'ERROR');
    }
}
?>
