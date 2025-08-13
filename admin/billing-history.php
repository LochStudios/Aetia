<?php
// admin/billing-history.php - Admin interface for managing user billing history
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../services/BillingService.php';
require_once __DIR__ . '/../services/DocumentService.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../includes/FormTokenManager.php';

$userModel = new User();
$messageModel = new Message();
$billingService = new BillingService();
$documentService = new DocumentService();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../services/BillingService.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../includes/SecurityException.php';

$userModel = new User();
$messageModel = new Message();
$billingService = new BillingService();
$securityManager = new SecurityManager();

// Initialize secure session
$securityManager->initializeSecureSession();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

// Handle repair action via URL parameter
if (isset($_GET['repair_invoices']) && $_GET['repair_invoices'] === '1') {
    $result = $billingService->repairInvalidInvoiceTypes();
    if ($result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
}

// Get user ID from query parameter
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if (!$userId) {
    $_SESSION['error_message'] = 'User ID is required.';
    header('Location: users.php');
    exit;
}

// Get user details
$user = $userModel->getUserById($userId);
if (!$user) {
    $_SESSION['error_message'] = 'User not found.';
    header('Location: users.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_bill_from_period':
                $billingPeriodStart = $_POST['billing_period_start'];
                $billingPeriodEnd = $_POST['billing_period_end'];
                $customBillAmount = floatval($_POST['custom_bill_amount'] ?? 0);
                $calculatedAmount = floatval($_POST['calculated_amount'] ?? 0);
                
                // Validate custom amount
                if ($customBillAmount < 1.00) {
                    $error = 'Bill amount must be at least $1.00.';
                } elseif ($customBillAmount > $calculatedAmount) {
                    $error = 'Bill amount cannot exceed the calculated amount of $' . number_format($calculatedAmount, 2) . '.';
                } else {
                    // Get billing data for the period
                    $billingData = $messageModel->getBillingDataWithManualReview($billingPeriodStart, $billingPeriodEnd);
                    $userBillingData = null;
                    
                    foreach ($billingData as $client) {
                        if ($client['user_id'] == $userId) {
                            $userBillingData = $client;
                            break;
                        }
                    }
                    
                    if (!$userBillingData) {
                        $error = 'No billing data found for this user in the selected period.';
                    } else {
                        // Override the total fee with the custom amount
                        $userBillingData['total_fee'] = $customBillAmount;
                        $userBillingData['custom_amount'] = true;
                        $userBillingData['calculated_amount'] = $calculatedAmount;
                        
                        $result = $billingService->createUserBill(
                            $userId, 
                            $billingPeriodStart, 
                            $billingPeriodEnd, 
                            $userBillingData, 
                            $_SESSION['user_id']
                        );
                        
                        if ($result['success']) {
                            $message = 'Bill created successfully for $' . number_format($customBillAmount, 2) . 
                                      ($customBillAmount != $calculatedAmount ? 
                                       ' (Custom amount - calculated was $' . number_format($calculatedAmount, 2) . ')' : '');
                        } else {
                            $error = $result['message'];
                        }
                    }
                }
                break;
                
            case 'upload_invoice':
                $billId = intval($_POST['bill_id']);
                $invoiceType = $_POST['invoice_type'];
                $invoiceNumber = $_POST['invoice_number'] ?? '';
                $invoiceAmount = floatval($_POST['invoice_amount'] ?? 0);
                $isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] == '1';
                
                if (isset($_FILES['invoice_file']) && $_FILES['invoice_file']['error'] === UPLOAD_ERR_OK) {
                    $result = $billingService->uploadInvoiceForBill(
                        $billId,
                        $_FILES['invoice_file'],
                        $invoiceType,
                        $invoiceNumber,
                        $invoiceAmount,
                        $_SESSION['user_id'],
                        $isPrimary
                    );
                    
                    if ($result['success']) {
                        $message = 'Invoice uploaded successfully.';
                    } else {
                        $error = $result['message'];
                    }
                } else {
                    $error = 'No file uploaded or upload error occurred.';
                }
                break;
                
            case 'update_bill_status':
                $billId = intval($_POST['bill_id']);
                $status = $_POST['bill_status'];
                $paymentDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
                $paymentMethod = !empty($_POST['payment_method']) ? $_POST['payment_method'] : null;
                $paymentReference = !empty($_POST['payment_reference']) ? $_POST['payment_reference'] : null;
                $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;
                $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
                
                $result = $billingService->updateBillStatus(
                    $billId, 
                    $status, 
                    $paymentDate, 
                    $paymentMethod, 
                    $paymentReference, 
                    $notes,
                    $dueDate
                );
                
                if ($result['success']) {
                    $message = 'Bill status updated successfully.';
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'apply_credit':
                $billId = intval($_POST['bill_id']);
                $creditAmount = floatval($_POST['credit_amount']);
                $reason = $_POST['credit_reason'];
                
                $result = $billingService->applyAccountCredit($billId, $creditAmount, $reason);
                
                if ($result['success']) {
                    $message = 'Account credit applied successfully.';
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'send_invoice_email':
                $billId = intval($_POST['bill_id']);
                
                $result = $billingService->sendInvoiceEmail($billId);
                
                if ($result['success']) {
                    $message = 'Invoice email sent successfully.';
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'delete_bill':
                $billId = intval($_POST['bill_id']);
                
                $result = $billingService->deleteBill($billId);
                
                if ($result['success']) {
                    $message = 'Bill deleted successfully.';
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'link_existing_invoice':
                $billId = intval($_POST['bill_id']);
                $documentId = intval($_POST['document_id']);
                $invoiceType = $_POST['invoice_type'] ?? 'generated';
                $invoiceNumber = $_POST['invoice_number'] ?? '';
                $invoiceAmount = floatval($_POST['invoice_amount'] ?? 0);
                $isPrimary = isset($_POST['is_primary']) && $_POST['is_primary'] == '1';
                
                $result = $billingService->linkExistingDocumentToBill($billId, $documentId, $invoiceType, $invoiceNumber, $invoiceAmount, $isPrimary);
                
                if ($result['success']) {
                    $message = 'Existing invoice linked to bill successfully.';
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'repair_invoice_types':
                $result = $billingService->repairInvalidInvoiceTypes();
                
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Billing history action error: " . $e->getMessage());
        $error = 'An error occurred while processing the request.';
    }
}

// Get user bills
$userBills = $billingService->getUserBills($userId);
$billingStats = $billingService->getUserBillingStats($userId);

// Get existing invoice documents
$allUserDocuments = $documentService->getUserDocuments($userId);
$linkedDocumentIds = $billingService->getLinkedDocumentIds($userId);
$existingInvoices = array_filter($allUserDocuments, function($doc) use ($linkedDocumentIds) {
    return strtolower($doc['document_type']) === 'invoice' 
           && !$doc['archived'] 
           && !in_array($doc['id'], $linkedDocumentIds);
});

// Get user display name
$userDisplayName = !empty($user['first_name']) ? 
    trim($user['first_name'] . ' ' . ($user['last_name'] ?? '')) : 
    $user['username'];

$pageTitle = 'Billing History - ' . $userDisplayName . ' | Admin | Aetia';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li><a href="users.php"><span class="icon is-small"><i class="fas fa-users-cog"></i></span><span>Users</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-file-invoice-dollar"></i></span><span>Billing History</span></a></li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="level">
        <div class="level-left">
            <div>
                <h2 class="title is-2 has-text-info">
                    <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    Billing Management
                </h2>
                <p class="subtitle">
                    Managing invoices and payments for <strong><?= htmlspecialchars($userDisplayName) ?></strong> (@<?= htmlspecialchars($user['username']) ?>)
                </p>
                <p class="has-text-white">
                    <span class="icon"><i class="fas fa-info-circle"></i></span>
                    Create bills from <a href="generate-bills.php" class="has-text-link">Generate Bills</a> data, upload invoices, and track payments
                </p>
            </div>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="generate-bills.php" class="button is-info is-outlined" target="_blank">
                    <span class="icon"><i class="fas fa-external-link-alt"></i></span>
                    <span>Generate Bills</span>
                </a>
                <a href="users.php" class="button is-outlined">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    <span>Back to Users</span>
                </a>
                <button class="button is-primary" onclick="showCreateBillModal()">
                    <span class="icon"><i class="fas fa-plus"></i></span>
                    <span>Create Bill</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="notification is-success">
            <button class="delete"></button>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notification is-danger">
            <button class="delete"></button>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- User Info Card -->
    <div class="box">
        <div class="media">
            <div class="media-left">
                <figure class="image is-64x64">
                    <?php if ($user['profile_image']): ?>
                        <?php if ($user['account_type'] === 'manual'): ?>
                            <img src="view-user-profile-image.php?user_id=<?= $user['id'] ?>" 
                                 alt="Profile Picture" 
                                 style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;"
                                 onerror="this.parentNode.innerHTML='<div class=\'has-background-info-light is-flex is-align-items-center is-justify-content-center\' style=\'width: 64px; height: 64px; border-radius: 50%;\'><span class=\'icon is-large has-text-info\'><i class=\'fas fa-user fa-2x\'></i></span></div>';">
                        <?php else: ?>
                            <img src="<?= htmlspecialchars($user['profile_image']) ?>" 
                                 alt="Profile Picture" 
                                 style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;"
                                 onerror="this.parentNode.innerHTML='<div class=\'has-background-info-light is-flex is-align-items-center is-justify-content-center\' style=\'width: 64px; height: 64px; border-radius: 50%;\'><span class=\'icon is-large has-text-info\'><i class=\'fas fa-user fa-2x\'></i></span></div>';">
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="has-background-info-light is-flex is-align-items-center is-justify-content-center" 
                             style="width: 64px; height: 64px; border-radius: 50%;">
                            <span class="icon is-large has-text-info">
                                <i class="fas fa-user fa-2x"></i>
                            </span>
                        </div>
                    <?php endif; ?>
                </figure>
            </div>
            <div class="media-content">
                <div class="content">
                    <p>
                        <strong><?= htmlspecialchars($userDisplayName) ?></strong> 
                        <small>@<?= htmlspecialchars($user['username']) ?></small>
                        <br>
                        <a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a>
                        <br>
                        <span class="tag is-<?= $user['account_type'] === 'manual' ? 'primary' : 'info' ?>">
                            <?= ucfirst($user['account_type']) ?> Account
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Billing Statistics -->
    <?php if ($billingStats): ?>
    <div class="columns">
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-info"><?= $billingStats['total_bills'] ?></p>
                <p class="subtitle is-6">Total Bills</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-success">$<?= number_format($billingStats['total_billed'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Total Billed</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-success">$<?= number_format($billingStats['total_paid'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Total Paid</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-warning">$<?= number_format($billingStats['total_pending'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Pending</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-danger">$<?= number_format($billingStats['total_overdue'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Overdue</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-link">$<?= number_format($billingStats['total_credits'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Credits</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Available Billing Periods from Generate Bills -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-calendar-check"></i></span>
            Available Billing Periods
        </h3>
        <p class="subtitle is-6 has-text-white mb-4">
            These are billing periods that have been generated in the <a href="generate-bills.php" class="has-text-link">Generate Bills</a> system. 
            Create individual bills from these periods to track invoices and payments.
        </p>

        <?php
        // Get available billing periods for this user from the generate-bills system
        $availablePeriods = [];
        try {
            // Check for recent billing periods (last 12 months)
            for ($i = 0; $i < 12; $i++) {
                $periodStart = date('Y-m-01', strtotime("-$i months"));
                $periodEnd = date('Y-m-t', strtotime("-$i months"));
                
                $billingData = $messageModel->getBillingDataWithManualReview($periodStart, $periodEnd);
                $userFound = false;
                $userBillingData = null;
                
                foreach ($billingData as $client) {
                    if ($client['user_id'] == $userId) {
                        $userFound = true;
                        $userBillingData = $client;
                        break;
                    }
                }
                
                if ($userFound) {
                    // Check if bill already exists for this period
                    $existingBill = null;
                    foreach ($userBills as $bill) {
                        $billStart = date('Y-m-01', strtotime($bill['billing_period_start']));
                        $billEnd = date('Y-m-t', strtotime($bill['billing_period_start']));
                        
                        // Check if the bill falls within this period (same month/year)
                        if ($billStart === $periodStart && $billEnd === $periodEnd) {
                            $existingBill = $bill;
                            break;
                        }
                    }
                    
                    $availablePeriods[] = [
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'billing_data' => $userBillingData,
                        'existing_bill' => $existingBill,
                        'period_name' => date('F Y', strtotime($periodStart))
                    ];
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching available periods: " . $e->getMessage());
        }
        ?>

        <?php if (!empty($availablePeriods)): ?>
            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Messages</th>
                            <th>Manual Reviews</th>
                            <th>SMS</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availablePeriods as $period): ?>
                            <tr>
                                <td>
                                    <strong><?= $period['period_name'] ?></strong>
                                    <br>
                                    <small class="has-text-white">
                                        <?= date('M j', strtotime($period['period_start'])) ?> - 
                                        <?= date('M j, Y', strtotime($period['period_end'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="tag is-success">
                                        <?= $period['billing_data']['total_message_count'] ?? 0 ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($period['billing_data']['manual_review_count'] ?? 0) > 0): ?>
                                        <span class="tag is-warning">
                                            <?= $period['billing_data']['manual_review_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="tag is-dark">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($period['billing_data']['sms_count'] ?? 0) > 0): ?>
                                        <span class="tag is-link">
                                            <?= $period['billing_data']['sms_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="tag is-dark">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="has-text-success">
                                        $<?= number_format($period['billing_data']['total_fee'] ?? 0, 2) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($period['existing_bill']): ?>
                                        <span class="tag is-primary">
                                            Bill Created
                                        </span>
                                        <br>
                                        <small class="has-text-grey">
                                            <?= ucfirst($period['existing_bill']['bill_status']) ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="tag is-warning">
                                            Not Created
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$period['existing_bill']): ?>
                                        <button class="button is-primary is-small" 
                                                onclick="createBillFromPeriod('<?= $period['period_start'] ?>', '<?= $period['period_end'] ?>', '<?= $period['period_name'] ?>', '<?= $period['billing_data']['total_fee'] ?? 0 ?>')">>
                                            <span class="icon is-small">
                                                <i class="fas fa-plus"></i>
                                            </span>
                                            <span>Create Bill</span>
                                        </button>
                                    <?php else: ?>
                                        <div class="buttons is-grouped">
                                            <a href="#bill-<?= $period['existing_bill']['id'] ?>" class="button is-info is-small">
                                                <span class="icon is-small">
                                                    <i class="fas fa-eye"></i>
                                                </span>
                                                <span>View Bill</span>
                                            </a>
                                            <a href="generate-bills.php?month=<?= date('n', strtotime($period['period_start'])) ?>&year=<?= date('Y', strtotime($period['period_start'])) ?>" 
                                               class="button is-light is-small" target="_blank">
                                                <span class="icon is-small">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </span>
                                                <span>View in Generate Bills</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="notification is-info is-light">
                <p><strong>No billing periods found</strong></p>
                <p>This user doesn't have any activity in recent billing periods generated in the 
                <a href="generate-bills.php" class="has-text-link">Generate Bills</a> system.</p>
            </div>
        <?php endif; ?>

        <div class="notification is-info is-light">
            <div class="content">
                <p><strong>How this works:</strong></p>
                <ol>
                    <li>Use <a href="generate-bills.php" class="has-text-link"><strong>Generate Bills</strong></a> to analyze message activity and create billing data</li>
                    <li>Come here to <strong>create individual bills</strong> from that generated data</li>
                    <li><strong>Upload invoices</strong> and link them to the bills</li>
                    <li><strong>Track payment status</strong> and apply account credits</li>
                    <li><strong>Send invoice emails</strong> to users</li>
                </ol>
            </div>
        </div>
    </div>

    <!-- Bills History -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
            Individual Bills & Invoice Management
        </h3>
        <p class="subtitle is-6 has-text-white mb-4">
            Manage individual bills created from billing periods. Upload invoices, track payments, and apply credits.
        </p>

        <?php if (empty($userBills)): ?>
            <div class="notification is-info is-light">
                <p><strong>No individual bills found for this user.</strong></p>
                <p>Create bills from the billing periods above to start tracking invoices and payments for this user.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Invoices</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userBills as $bill): ?>
                            <tr id="bill-<?= $bill['id'] ?>">
                                <td>
                                    <strong><?= date('M Y', strtotime($bill['billing_period_start'])) ?></strong>
                                    <br>
                                    <small class="has-text-white">
                                        <?= date('M j', strtotime($bill['billing_period_start'])) ?> - 
                                        <?= date('M j, Y', strtotime($bill['billing_period_end'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <strong class="has-text-success">$<?= number_format($bill['total_amount'], 2) ?></strong>
                                    <?php if ($bill['account_credit'] > 0): ?>
                                        <br><small class="has-text-link">Credit: $<?= number_format($bill['account_credit'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tag is-<?= 
                                        $bill['bill_status'] === 'paid' ? 'success' : 
                                        ($bill['bill_status'] === 'overdue' ? 'danger' : 
                                        ($bill['bill_status'] === 'sent' ? 'warning' : 'info')) 
                                    ?>">
                                        <?= ucfirst($bill['bill_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($bill['due_date']): ?>
                                        <?= date('M j, Y', strtotime($bill['due_date'])) ?>
                                        <?php if (strtotime($bill['due_date']) < time() && $bill['bill_status'] !== 'paid'): ?>
                                            <br><small class="has-text-danger">Overdue</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="has-text-white">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($bill['invoices'])): ?>
                                        <div class="content">
                                            <?php foreach ($bill['invoices'] as $invoice): ?>
                                                <div class="is-flex is-align-items-center mb-1">
                                                    <span class="tag is-small is-<?= $invoice['invoice_type'] === 'generated' ? 'primary' : 'info' ?> mr-2">
                                                        <?= ucfirst($invoice['invoice_type'] ?: 'Generated') ?>
                                                        <?php if ($invoice['is_primary_invoice']): ?>
                                                            <span class="icon is-small"><i class="fas fa-star"></i></span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="has-text-white is-size-7">
                                                        <?= htmlspecialchars($invoice['original_filename'] ?? 'No filename') ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="has-text-white">No invoices</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="buttons are-small">
                                        <button class="button is-info is-small" 
                                                onclick="showBillDetails(<?= $bill['id'] ?>)">
                                            <span class="icon"><i class="fas fa-eye"></i></span>
                                        </button>
                                        <button class="button is-primary is-small" 
                                                onclick="showUploadInvoiceModal(<?= $bill['id'] ?>)">
                                            <span class="icon"><i class="fas fa-upload"></i></span>
                                        </button>
                                        <button class="button is-success is-small" 
                                                onclick="showUpdateStatusModal(<?= $bill['id'] ?>)">
                                            <span class="icon"><i class="fas fa-edit"></i></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Existing Invoice Documents -->
    <?php if (!empty($existingInvoices)): ?>
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-file-pdf"></i></span>
            Existing Invoice Documents
        </h3>
        <p class="subtitle is-6 has-text-white mb-4">
            These are invoice documents already uploaded that aren't linked to specific bills. You can link them to bills above.
        </p>

        <?php if (empty($userBills)): ?>
            <div class="notification is-warning">
                <p><strong>No bills available for linking.</strong></p>
                <p>Create bills from the billing periods above first, then you can link these invoice documents to specific bills.</p>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Upload Date</th>
                        <th>Size</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingInvoices as $invoice): ?>
                        <tr>
                            <td>
                                <div class="media">
                                    <div class="media-left">
                                        <span class="icon is-large has-text-danger">
                                            <?php 
                                            $ext = strtolower(pathinfo($invoice['original_filename'], PATHINFO_EXTENSION));
                                            if ($ext === 'pdf'): ?>
                                                <i class="fas fa-file-pdf fa-2x"></i>
                                            <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                <i class="fas fa-file-image fa-2x"></i>
                                            <?php else: ?>
                                                <i class="fas fa-file fa-2x"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="media-content">
                                        <p class="is-size-6 has-text-weight-bold">
                                            <?= htmlspecialchars($invoice['original_filename']) ?>
                                        </p>
                                        <p class="is-size-7 has-text-white">
                                            ID: <?= $invoice['id'] ?> | <?= strtoupper($invoice['mime_type']) ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <time datetime="<?= $invoice['uploaded_at'] ?>">
                                    <?= date('M j, Y', strtotime($invoice['uploaded_at'])) ?>
                                </time>
                                <br>
                                <small class="has-text-white">
                                    <?= date('g:i A', strtotime($invoice['uploaded_at'])) ?>
                                </small>
                            </td>
                            <td>
                                <?php 
                                $fileSize = $invoice['file_size'];
                                if ($fileSize > 1024 * 1024) {
                                    echo number_format($fileSize / (1024 * 1024), 1) . ' MB';
                                } elseif ($fileSize > 1024) {
                                    echo number_format($fileSize / 1024, 1) . ' KB';
                                } else {
                                    echo $fileSize . ' bytes';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($invoice['description'])): ?>
                                    <span class="has-text-white">
                                        <?= htmlspecialchars($invoice['description']) ?>
                                    </span>
                                <?php else: ?>
                                    <em class="has-text-white">No description</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="field is-grouped">
                                    <?php if (!empty($userBills)): ?>
                                        <div class="control">
                                            <button class="button is-info is-small" 
                                                    onclick="showLinkDocumentModal(<?= $invoice['id'] ?>, '<?= htmlspecialchars($invoice['original_filename']) ?>')">
                                                <span class="icon is-small">
                                                    <i class="fas fa-link"></i>
                                                </span>
                                                <span>Link to Bill</span>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="control">
                                            <button class="button is-light is-small" disabled title="Create bills first from the billing periods above">
                                                <span class="icon is-small">
                                                    <i class="fas fa-link"></i>
                                                </span>
                                                <span>Link to Bill</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    <div class="control">
                                        <a href="../api/download-document.php?document_id=<?= $invoice['id'] ?>" 
                                           class="button is-light is-small">
                                            <span class="icon is-small">
                                                <i class="fas fa-download"></i>
                                            </span>
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="notification is-info is-light">
            <div class="content">
                <p><strong>Note:</strong> Use the "Link to Bill" button to associate these documents with specific bills. 
                Once linked, they'll appear in the bill's invoice list above.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Link Document Modal -->
<div class="modal" id="linkDocumentModal">
    <div class="modal-background" onclick="closeModal('linkDocumentModal')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon"><i class="fas fa-link"></i></span>
                Link Document to Bill
            </p>
            <button class="delete" aria-label="close" onclick="closeModal('linkDocumentModal')"></button>
        </header>
        <form method="POST">
            <input type="hidden" name="action" value="link_existing_invoice">
            <input type="hidden" name="document_id" id="linkDocumentId">
            <section class="modal-card-body">
                <div class="content">
                    <p>You are about to link the document <strong id="linkDocumentName"></strong> to a bill.</p>
                </div>
                
                <div class="field">
                    <label class="label">Select Bill</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="bill_id" required>
                                <option value="">Choose a bill...</option>
                                <?php foreach ($userBills as $bill): ?>
                                    <option value="<?= $bill['id'] ?>">
                                        <?= date('F Y', strtotime($bill['billing_period_start'])) ?> - 
                                        $<?= number_format($bill['total_amount'] ?? 0, 2) ?> 
                                        (<?= ucfirst($bill['bill_status']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="field">
                    <label class="label">Invoice Type</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="invoice_type">
                                <option value="generated">Generated Invoice</option>
                                <option value="payment_receipt">Payment Receipt</option>
                                <option value="credit_note">Credit Note</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label">Invoice Number</label>
                            <div class="control">
                                <input class="input" type="text" name="invoice_number" id="linkInvoiceNumber" placeholder="INV-2024-001" readonly>
                            </div>
                            <p class="help">Auto-populated from document filename</p>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label">Invoice Amount</label>
                            <div class="control">
                                <input class="input" type="number" name="invoice_amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                            <p class="help">Amount invoiced to track against future billing calculations</p>
                        </div>
                    </div>
                </div>
                
                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="is_primary" value="1">
                            Set as primary invoice for this bill
                        </label>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-info" type="submit">
                    <span class="icon"><i class="fas fa-link"></i></span>
                    <span>Link Document</span>
                </button>
                <button class="button" type="button" onclick="closeModal('linkDocumentModal')">Cancel</button>
            </footer>
        </form>
    </div>
</div>

<!-- Create Bill Modal -->
<div class="modal" id="createBillModal">
    <div class="modal-background" onclick="closeModal('createBillModal')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon"><i class="fas fa-plus"></i></span>
                Create Bill from Billing Period
            </p>
            <button class="delete" aria-label="close" onclick="closeModal('createBillModal')"></button>
        </header>
        <form method="POST">
            <input type="hidden" name="action" value="create_bill_from_period">
            <input type="hidden" name="calculated_amount" id="calculatedAmount">
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Billing Period Start</label>
                    <div class="control">
                        <input class="input" type="date" name="billing_period_start" id="billPeriodStart" required>
                    </div>
                    <p class="help">Start date for this billing period</p>
                </div>
                <div class="field">
                    <label class="label">Billing Period End</label>
                    <div class="control">
                        <input class="input" type="date" name="billing_period_end" id="billPeriodEnd" required>
                    </div>
                    <p class="help">End date for this billing period</p>
                </div>
                <div class="field">
                    <label class="label">Bill Amount</label>
                    <div class="control">
                        <input class="input" type="number" name="custom_bill_amount" id="customBillAmount" step="0.01" min="1.00" required>
                    </div>
                    <p class="help">
                        <span>Minimum: $1.00 | Maximum: $<span id="maxAmount">0.00</span> (calculated amount)</span>
                    </p>
                </div>
                <div class="notification is-info">
                    <p><strong>Custom Bill Amount:</strong> Enter the amount you want to bill for this period. This is useful when your invoice amount differs from the calculated amount.</p>
                    <p><strong>Billing Period:</strong> You can adjust the start and end dates if needed to match your billing requirements.</p>
                    <p><strong>Calculated Amount:</strong> $<span id="displayCalculatedAmount">0.00</span> based on user activity for the suggested period</p>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary" type="submit">
                    <span class="icon"><i class="fas fa-plus"></i></span>
                    <span>Create Bill</span>
                </button>
                <button class="button" type="button" onclick="closeModal('createBillModal')">Cancel</button>
            </footer>
        </form>
    </div>
</div>

<!-- Upload Invoice Modal -->
<div class="modal" id="uploadInvoiceModal">
    <div class="modal-background" onclick="closeModal('uploadInvoiceModal')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon"><i class="fas fa-upload"></i></span>
                Upload Invoice
            </p>
            <button class="delete" aria-label="close" onclick="closeModal('uploadInvoiceModal')"></button>
        </header>
        <form method="POST" enctype="multipart/form-data" id="uploadInvoiceForm">
            <input type="hidden" name="action" value="upload_invoice">
            <input type="hidden" name="bill_id" id="upload_bill_id">
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Invoice File</label>
                    <div class="control">
                        <input class="input" type="file" name="invoice_file" id="invoiceFileInput" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required onchange="populateInvoiceNumber()">
                    </div>
                    <p class="help">Supported formats: PDF, DOC, DOCX, JPG, PNG (max 10MB)</p>
                </div>
                <div class="field">
                    <label class="label">Invoice Type</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="invoice_type" required>
                                <option value="generated">Generated Invoice</option>
                                <option value="payment_receipt">Payment Receipt</option>
                                <option value="credit_note">Credit Note</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label">Invoice Number</label>
                            <div class="control">
                                <input class="input" type="text" name="invoice_number" id="uploadInvoiceNumber" placeholder="INV-2024-001" readonly>
                            </div>
                            <p class="help">Auto-populated from selected filename</p>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label">Invoice Amount</label>
                            <div class="control">
                                <input class="input" type="number" name="invoice_amount" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <div class="control">
                        <label class="checkbox">
                            <input type="checkbox" name="is_primary" value="1">
                            Set as primary invoice for this bill
                        </label>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary" type="submit">
                    <span class="icon"><i class="fas fa-upload"></i></span>
                    <span>Upload Invoice</span>
                </button>
                <button class="button" type="button" onclick="closeModal('uploadInvoiceModal')">Cancel</button>
            </footer>
        </form>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="updateStatusModal">
    <div class="modal-background" onclick="closeModal('updateStatusModal')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon"><i class="fas fa-edit"></i></span>
                Update Bill Status
            </p>
            <button class="delete" aria-label="close" onclick="closeModal('updateStatusModal')"></button>
        </header>
        <form method="POST" id="updateStatusForm">
            <input type="hidden" name="action" value="update_bill_status">
            <input type="hidden" name="bill_id" id="status_bill_id">
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Bill Status</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="bill_status" id="bill_status_select" onchange="togglePaymentFields()" required>
                                <option value="draft">Draft</option>
                                <option value="sent">Sent</option>
                                <option value="overdue">Overdue</option>
                                <option value="paid">Paid</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div id="paymentFields" style="display: none;">
                    <div class="field">
                        <label class="label">Payment Date</label>
                        <div class="control">
                            <input class="input" type="date" name="payment_date">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Payment Method</label>
                        <div class="control">
                            <input class="input" type="text" name="payment_method" placeholder="e.g., Bank Transfer, PayPal, Check">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">Payment Reference</label>
                        <div class="control">
                            <input class="input" type="text" name="payment_reference" placeholder="Transaction ID or reference number">
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Due Date</label>
                    <div class="control">
                        <input class="input" type="date" name="due_date" placeholder="Update due date">
                    </div>
                </div>
                <div class="field">
                    <label class="label">Notes</label>
                    <div class="control">
                        <textarea class="textarea" name="notes" placeholder="Additional notes about this bill..."></textarea>
                    </div>
                </div>
                <div class="buttons">
                    <button class="button is-warning" type="button" onclick="showApplyCreditModal()">
                        <span class="icon"><i class="fas fa-dollar-sign"></i></span>
                        <span>Apply Credit</span>
                    </button>
                    <button class="button is-info" type="button" onclick="sendInvoiceEmail()">
                        <span class="icon"><i class="fas fa-envelope"></i></span>
                        <span>Send Invoice Email</span>
                    </button>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary" type="submit">
                    <span class="icon"><i class="fas fa-save"></i></span>
                    <span>Update Status</span>
                </button>
                <button class="button" type="button" onclick="closeModal('updateStatusModal')">Cancel</button>
            </footer>
        </form>
    </div>
</div>

<!-- Apply Credit Modal -->
<div class="modal" id="applyCreditModal">
    <div class="modal-background" onclick="closeModal('applyCreditModal')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon"><i class="fas fa-dollar-sign"></i></span>
                Apply Account Credit
            </p>
            <button class="delete" aria-label="close" onclick="closeModal('applyCreditModal')"></button>
        </header>
        <form method="POST" id="applyCreditForm">
            <input type="hidden" name="action" value="apply_credit">
            <input type="hidden" name="bill_id" id="credit_bill_id">
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Credit Amount</label>
                    <div class="control">
                        <input class="input" type="number" name="credit_amount" step="0.01" min="0" required placeholder="0.00">
                    </div>
                </div>
                <div class="field">
                    <label class="label">Reason</label>
                    <div class="control">
                        <input class="input" type="text" name="credit_reason" required placeholder="e.g., Overpayment, Service issue compensation">
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-warning" type="submit">
                    <span class="icon"><i class="fas fa-dollar-sign"></i></span>
                    <span>Apply Credit</span>
                </button>
                <button class="button" type="button" onclick="closeModal('applyCreditModal')">Cancel</button>
            </footer>
        </form>
    </div>
</div>

<!-- Bill Details Modal -->
<div class="modal" id="billDetailsModal">
    <div class="modal-background" onclick="closeModal('billDetailsModal')"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                Bill Details
            </p>
            <button class="delete" aria-label="close" onclick="closeModal('billDetailsModal')"></button>
        </header>
        <section class="modal-card-body">
            <div class="content">
                <div class="field is-horizontal">
                    <div class="field-label is-normal">
                        <label class="label">Billing Period:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <span id="billPeriod" class="has-text-weight-semibold"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-horizontal">
                    <div class="field-label is-normal">
                        <label class="label">Total Amount:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <span id="billAmount" class="has-text-weight-semibold has-text-success"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-horizontal">
                    <div class="field-label is-normal">
                        <label class="label">Status:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <span id="billStatus" class="tag"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-horizontal">
                    <div class="field-label is-normal">
                        <label class="label">Messages:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <span id="billMessages"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-horizontal">
                    <div class="field-label is-normal">
                        <label class="label">Manual Reviews:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <span id="billReviews"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-horizontal">
                    <div class="field-label is-normal">
                        <label class="label">SMS Count:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <span id="billSms"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-horizontal" id="creditField" style="display: none;">
                    <div class="field-label is-normal">
                        <label class="label">Account Credit:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <span id="billCredit" class="has-text-warning has-text-weight-semibold"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="field is-horizontal" id="notesField" style="display: none;">
                    <div class="field-label is-normal">
                        <label class="label">Notes:</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <div class="content">
                                    <p id="billNotes"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <button class="button" type="button" onclick="closeModal('billDetailsModal')">Close</button>
        </footer>
    </div>
</div>

<script>
// Bill data for JavaScript
const billsData = <?= json_encode($userBills) ?>;

function showCreateBillModal() {
    // Set default dates to last month
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
    
    document.querySelector('input[name="billing_period_start"]').value = firstDay.toISOString().split('T')[0];
    document.querySelector('input[name="billing_period_end"]').value = lastDay.toISOString().split('T')[0];
    
    document.getElementById('createBillModal').classList.add('is-active');
}

function showUploadInvoiceModal(billId) {
    document.getElementById('upload_bill_id').value = billId;
    document.getElementById('uploadInvoiceModal').classList.add('is-active');
}

function showUpdateStatusModal(billId) {
    const bill = billsData.find(b => b.id == billId);
    if (!bill) return;
    
    document.getElementById('status_bill_id').value = billId;
    document.getElementById('credit_bill_id').value = billId;
    document.getElementById('bill_status_select').value = bill.bill_status;
    
    // Populate existing data
    if (bill.payment_date) {
        document.querySelector('input[name="payment_date"]').value = bill.payment_date.split(' ')[0];
    }
    if (bill.payment_method) {
        document.querySelector('input[name="payment_method"]').value = bill.payment_method;
    }
    if (bill.payment_reference) {
        document.querySelector('input[name="payment_reference"]').value = bill.payment_reference;
    }
    if (bill.due_date) {
        document.querySelector('input[name="due_date"]').value = bill.due_date.split(' ')[0];
    }
    if (bill.notes) {
        document.querySelector('textarea[name="notes"]').value = bill.notes;
    }
    
    togglePaymentFields();
    document.getElementById('updateStatusModal').classList.add('is-active');
}

function showApplyCreditModal() {
    closeModal('updateStatusModal');
    document.getElementById('applyCreditModal').classList.add('is-active');
}

function togglePaymentFields() {
    const status = document.getElementById('bill_status_select').value;
    const paymentFields = document.getElementById('paymentFields');
    
    if (status === 'paid') {
        paymentFields.style.display = 'block';
    } else {
        paymentFields.style.display = 'none';
    }
}

function sendInvoiceEmail() {
    const billId = document.getElementById('status_bill_id').value;
    
    if (confirm('Send invoice email to the user?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="send_invoice_email">
            <input type="hidden" name="bill_id" value="${billId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showBillDetails(billId) {
    const bill = billsData.find(b => b.id == billId);
    if (!bill) return;
    
    // Populate modal fields
    document.getElementById('billPeriod').textContent = 
        new Date(bill.billing_period_start).toLocaleDateString() + ' - ' + 
        new Date(bill.billing_period_end).toLocaleDateString();
    
    document.getElementById('billAmount').textContent = '$' + parseFloat(bill.total_amount).toFixed(2);
    
    // Set status with appropriate styling
    const statusElement = document.getElementById('billStatus');
    const status = bill.bill_status.charAt(0).toUpperCase() + bill.bill_status.slice(1);
    statusElement.textContent = status;
    statusElement.className = 'tag';
    
    // Add appropriate color based on status
    switch(bill.bill_status) {
        case 'pending':
            statusElement.classList.add('is-warning');
            break;
        case 'paid':
            statusElement.classList.add('is-success');
            break;
        case 'overdue':
            statusElement.classList.add('is-danger');
            break;
        default:
            statusElement.classList.add('is-light');
    }
    
    document.getElementById('billMessages').textContent = bill.message_count || '0';
    document.getElementById('billReviews').textContent = bill.manual_review_count || '0';
    document.getElementById('billSms').textContent = bill.sms_count || '0';
    
    // Show/hide credit field
    const creditField = document.getElementById('creditField');
    const creditElement = document.getElementById('billCredit');
    if (bill.account_credit && parseFloat(bill.account_credit) > 0) {
        creditElement.textContent = '$' + parseFloat(bill.account_credit).toFixed(2);
        creditField.style.display = 'block';
    } else {
        creditField.style.display = 'none';
    }
    
    // Show/hide notes field
    const notesField = document.getElementById('notesField');
    const notesElement = document.getElementById('billNotes');
    if (bill.notes && bill.notes.trim()) {
        notesElement.textContent = bill.notes;
        notesField.style.display = 'block';
    } else {
        notesField.style.display = 'none';
    }
    
    // Show the modal
    document.getElementById('billDetailsModal').classList.add('is-active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('is-active');
}

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.is-active').forEach(modal => {
            modal.classList.remove('is-active');
        });
    }
});

// Delete notification functionality
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('.notification .delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.remove();
        });
    });
});

// Show link document modal
function showLinkDocumentModal(documentId, documentName) {
    document.getElementById('linkDocumentId').value = documentId;
    document.getElementById('linkDocumentName').textContent = documentName;
    
    // Auto-populate invoice number with document name (remove .pdf extension if present)
    const invoiceNumber = documentName.replace(/\.pdf$/i, '');
    document.getElementById('linkInvoiceNumber').value = invoiceNumber;
    
    document.getElementById('linkDocumentModal').classList.add('is-active');
}

// Auto-populate invoice number from selected file
function populateInvoiceNumber() {
    const fileInput = document.getElementById('invoiceFileInput');
    const invoiceNumberInput = document.getElementById('uploadInvoiceNumber');
    
    if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        // Remove file extension and use as invoice number
        const invoiceNumber = fileName.replace(/\.[^/.]+$/, '');
        invoiceNumberInput.value = invoiceNumber;
    }
}

// Create bill from period
function createBillFromPeriod(periodStart, periodEnd, periodName, calculatedAmount) {
    // Populate the modal with the period information
    document.getElementById('billPeriodStart').value = periodStart;
    document.getElementById('billPeriodEnd').value = periodEnd;
    document.getElementById('calculatedAmount').value = calculatedAmount;
    document.getElementById('maxAmount').textContent = parseFloat(calculatedAmount).toFixed(2);
    document.getElementById('displayCalculatedAmount').textContent = parseFloat(calculatedAmount).toFixed(2);
    
    // Set the custom amount field to the calculated amount by default
    const customAmountField = document.getElementById('customBillAmount');
    customAmountField.value = parseFloat(calculatedAmount).toFixed(2);
    customAmountField.max = calculatedAmount;
    
    // Show the modal
    document.getElementById('createBillModal').classList.add('is-active');
}

// Validate custom bill amount
document.addEventListener('DOMContentLoaded', function() {
    const customAmountField = document.getElementById('customBillAmount');
    const startDateField = document.getElementById('billPeriodStart');
    const endDateField = document.getElementById('billPeriodEnd');
    
    if (customAmountField) {
        customAmountField.addEventListener('input', function() {
            const value = parseFloat(this.value);
            const max = parseFloat(this.max);
            const min = parseFloat(this.min);
            
            if (value > max) {
                this.setCustomValidity(`Amount cannot exceed $${max.toFixed(2)} (calculated amount)`);
            } else if (value < min) {
                this.setCustomValidity(`Amount must be at least $${min.toFixed(2)}`);
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    // Date validation
    if (startDateField && endDateField) {
        function validateDates() {
            const startDate = new Date(startDateField.value);
            const endDate = new Date(endDateField.value);
            
            if (startDate && endDate && startDate >= endDate) {
                endDateField.setCustomValidity('End date must be after start date');
            } else {
                endDateField.setCustomValidity('');
            }
        }
        
        startDateField.addEventListener('change', validateDates);
        endDateField.addEventListener('change', validateDates);
    }
});
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
