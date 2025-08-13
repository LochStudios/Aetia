<?php
// admin/billing-history.php - Admin interface for managing user billing history
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';

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
                    $result = $billingService->createUserBill(
                        $userId, 
                        $billingPeriodStart, 
                        $billingPeriodEnd, 
                        $userBillingData, 
                        $_SESSION['user_id']
                    );
                    
                    if ($result['success']) {
                        $message = 'Bill created successfully.';
                    } else {
                        $error = $result['message'];
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
                
                $result = $billingService->updateBillStatus(
                    $billId, 
                    $status, 
                    $paymentDate, 
                    $paymentMethod, 
                    $paymentReference, 
                    $notes
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
        }
    } catch (Exception $e) {
        error_log("Billing history action error: " . $e->getMessage());
        $error = 'An error occurred while processing the request.';
    }
}

// Get user bills
$userBills = $billingService->getUserBills($userId);
$billingStats = $billingService->getUserBillingStats($userId);

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
                    Billing History
                </h2>
                <p class="subtitle">
                    Managing billing for <strong><?= htmlspecialchars($userDisplayName) ?></strong> (@<?= htmlspecialchars($user['username']) ?>)
                </p>
            </div>
        </div>
        <div class="level-right">
            <div class="buttons">
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
                <p class="title is-4 has-text-success">$<?= number_format($billingStats['total_billed'], 2) ?></p>
                <p class="subtitle is-6">Total Billed</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-success">$<?= number_format($billingStats['total_paid'], 2) ?></p>
                <p class="subtitle is-6">Total Paid</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-warning">$<?= number_format($billingStats['total_pending'], 2) ?></p>
                <p class="subtitle is-6">Pending</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-danger">$<?= number_format($billingStats['total_overdue'], 2) ?></p>
                <p class="subtitle is-6">Overdue</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-link">$<?= number_format($billingStats['total_credits'], 2) ?></p>
                <p class="subtitle is-6">Credits</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bills List -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-file-invoice"></i></span>
            Bills History
        </h3>

        <?php if (empty($userBills)): ?>
            <div class="notification is-info is-light">
                <p><strong>No bills found for this user.</strong></p>
                <p>Click "Create Bill" to generate a bill from existing billing data.</p>
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
                            <tr>
                                <td>
                                    <strong><?= date('M Y', strtotime($bill['billing_period_start'])) ?></strong>
                                    <br>
                                    <small class="has-text-grey">
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
                                        <span class="has-text-grey">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($bill['invoices'])): ?>
                                        <div class="tags">
                                            <?php foreach ($bill['invoices'] as $invoice): ?>
                                                <span class="tag is-small is-<?= $invoice['invoice_type'] === 'generated' ? 'primary' : 'info' ?>">
                                                    <?= ucfirst($invoice['invoice_type']) ?>
                                                    <?php if ($invoice['is_primary_invoice']): ?>
                                                        <span class="icon is-small"><i class="fas fa-star"></i></span>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="has-text-grey">No invoices</span>
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
            <section class="modal-card-body">
                <div class="field">
                    <label class="label">Billing Period Start</label>
                    <div class="control">
                        <input class="input" type="date" name="billing_period_start" required>
                    </div>
                </div>
                <div class="field">
                    <label class="label">Billing Period End</label>
                    <div class="control">
                        <input class="input" type="date" name="billing_period_end" required>
                    </div>
                </div>
                <div class="notification is-info is-light">
                    <p><strong>Note:</strong> This will create a bill based on the user's activity during the specified period. The system will automatically calculate fees based on messages, manual reviews, and SMS usage.</p>
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
                        <input class="input" type="file" name="invoice_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
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
                                <input class="input" type="text" name="invoice_number" placeholder="INV-2024-001">
                            </div>
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
    
    alert('Bill Details:\n\n' +
          'Period: ' + new Date(bill.billing_period_start).toLocaleDateString() + ' - ' + new Date(bill.billing_period_end).toLocaleDateString() + '\n' +
          'Amount: $' + parseFloat(bill.total_amount).toFixed(2) + '\n' +
          'Status: ' + bill.bill_status.charAt(0).toUpperCase() + bill.bill_status.slice(1) + '\n' +
          'Messages: ' + bill.message_count + '\n' +
          'Manual Reviews: ' + bill.manual_review_count + '\n' +
          'SMS Count: ' + bill.sms_count + '\n' +
          (bill.account_credit > 0 ? 'Account Credit: $' + parseFloat(bill.account_credit).toFixed(2) + '\n' : '') +
          (bill.notes ? 'Notes: ' + bill.notes : ''));
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
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
