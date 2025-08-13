<?php
// billing.php - User billing history and invoice management
session_start();

// Include timezone utilities
require_once __DIR__ . '/includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/services/BillingService.php';
require_once __DIR__ . '/services/DocumentService.php';

$userModel = new User();
$billingService = new BillingService();
$documentService = new DocumentService();

// Get current user details
$currentUser = $userModel->getUserById($_SESSION['user_id']);
if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Get user display name
$userDisplayName = !empty($currentUser['first_name']) ? 
    trim($currentUser['first_name'] . ' ' . ($currentUser['last_name'] ?? '')) : 
    $currentUser['username'];

// Get user bills and billing statistics
$userBills = $billingService->getUserBills($_SESSION['user_id']);
$billingStats = $billingService->getUserBillingStats($_SESSION['user_id']);

$pageTitle = 'Billing History | Aetia';
ob_start();
?>

<div class="content">
    <!-- Header -->
    <div class="level">
        <div class="level-left">
            <div>
                <h2 class="title is-2 has-text-info">
                    <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    Billing History
                </h2>
                <p class="subtitle">
                    View your invoices, payment history, and account statements
                </p>
            </div>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="index.php" class="button is-outlined">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Billing Overview -->
    <?php if ($billingStats): ?>
    <div class="columns">
        <div class="column is-3">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-info"><?= $billingStats['total_bills'] ?></p>
                <p class="subtitle is-6">Total Bills</p>
            </div>
        </div>
        <div class="column is-3">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-success">$<?= number_format($billingStats['total_billed'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Total Billed</p>
            </div>
        </div>
        <div class="column is-3">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-warning">$<?= number_format($billingStats['total_pending'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Outstanding Balance</p>
            </div>
        </div>
        <div class="column is-3">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-link">$<?= number_format($billingStats['total_credits'] ?? 0, 2) ?></p>
                <p class="subtitle is-6">Account Credits</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bills List -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-file-invoice"></i></span>
            Your Bills & Invoices
        </h3>

        <?php if (empty($userBills)): ?>
            <div class="notification is-info is-light">
                <p><strong>No billing history found.</strong></p>
                <p>Your billing history will appear here once invoices are generated. This typically happens monthly after your service usage is calculated.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Billing Period</th>
                            <th>Service Details</th>
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
                                    <strong><?= date('F Y', strtotime($bill['billing_period_start'])) ?></strong>
                                    <br>
                                    <small class="has-text-grey">
                                        <?= date('M j', strtotime($bill['billing_period_start'])) ?> - 
                                        <?= date('M j, Y', strtotime($bill['billing_period_end'])) ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="content">
                                        <p class="mb-1">
                                            <span class="icon is-small"><i class="fas fa-envelope"></i></span>
                                            <strong><?= $bill['message_count'] ?></strong> messages processed
                                        </p>
                                        <?php if ($bill['manual_review_count'] > 0): ?>
                                            <p class="mb-1 has-text-warning">
                                                <span class="icon is-small"><i class="fas fa-eye"></i></span>
                                                <strong><?= $bill['manual_review_count'] ?></strong> manual reviews
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($bill['sms_count'] > 0): ?>
                                            <p class="mb-0 has-text-link">
                                                <span class="icon is-small"><i class="fas fa-sms"></i></span>
                                                <strong><?= $bill['sms_count'] ?></strong> SMS messages
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="content">
                                        <?php if (($bill['standard_fee'] ?? 0) > 0): ?>
                                            <p class="mb-1">Service: <strong>$<?= number_format($bill['standard_fee'] ?? 0, 2) ?></strong></p>
                                        <?php endif; ?>
                                        <?php if (($bill['manual_review_fee'] ?? 0) > 0): ?>
                                            <p class="mb-1 has-text-warning">Reviews: <strong>$<?= number_format($bill['manual_review_fee'] ?? 0, 2) ?></strong></p>
                                        <?php endif; ?>
                                        <?php if (($bill['sms_fee'] ?? 0) > 0): ?>
                                            <p class="mb-1 has-text-link">SMS: <strong>$<?= number_format($bill['sms_fee'] ?? 0, 2) ?></strong></p>
                                        <?php endif; ?>
                                        <hr class="my-2">
                                        <p class="mb-0">
                                            <strong class="has-text-success">Total: $<?= number_format($bill['total_amount'] ?? 0, 2) ?></strong>
                                        </p>
                                        <?php if (($bill['account_credit'] ?? 0) > 0): ?>
                                            <p class="mb-0 has-text-link">
                                                <small>Credit: $<?= number_format($bill['account_credit'] ?? 0, 2) ?></small>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag is-<?= 
                                        $bill['bill_status'] === 'paid' ? 'success' : 
                                        ($bill['bill_status'] === 'overdue' ? 'danger' : 
                                        ($bill['bill_status'] === 'sent' ? 'warning' : 'info')) 
                                    ?>">
                                        <?= ucfirst($bill['bill_status']) ?>
                                    </span>
                                    <?php if ($bill['payment_date']): ?>
                                        <br><small class="has-text-grey">
                                            Paid: <?= date('M j, Y', strtotime($bill['payment_date'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($bill['due_date']): ?>
                                        <?= date('M j, Y', strtotime($bill['due_date'])) ?>
                                        <?php if (strtotime($bill['due_date']) < time() && $bill['bill_status'] !== 'paid'): ?>
                                            <br><span class="tag is-danger is-small">Overdue</span>
                                        <?php elseif (strtotime($bill['due_date']) - time() < 3 * 24 * 3600 && $bill['bill_status'] !== 'paid'): ?>
                                            <br><span class="tag is-warning is-small">Due Soon</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="has-text-grey">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($bill['invoices'])): ?>
                                        <div class="buttons are-small">
                                            <?php foreach ($bill['invoices'] as $invoice): ?>
                                                <a href="api/download-document.php?id=<?= $invoice['document_id'] ?>" 
                                                   class="button is-small is-<?= $invoice['invoice_type'] === 'generated' ? 'primary' : 'info' ?>" 
                                                   target="_blank"
                                                   title="Download <?= ucfirst($invoice['invoice_type']) ?>">
                                                    <span class="icon">
                                                        <i class="fas fa-<?= $invoice['invoice_type'] === 'generated' ? 'file-invoice' : 
                                                            ($invoice['invoice_type'] === 'payment_receipt' ? 'receipt' : 'file-alt') ?>"></i>
                                                    </span>
                                                    <?php if ($invoice['is_primary_invoice']): ?>
                                                        <span class="icon is-small"><i class="fas fa-star"></i></span>
                                                    <?php endif; ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="has-text-grey">No invoices</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="buttons are-small">
                                        <button class="button is-info is-small" 
                                                onclick="showBillDetails(<?= $bill['id'] ?>)"
                                                title="View Details">
                                            <span class="icon"><i class="fas fa-eye"></i></span>
                                        </button>
                                        <?php if ($bill['bill_status'] === 'sent' || $bill['bill_status'] === 'overdue'): ?>
                                            <a href="mailto:billing@aetia.com.au?subject=Payment Inquiry - Invoice <?= urlencode(date('F Y', strtotime($bill['billing_period_start']))) ?>&body=Hi,%0D%0A%0D%0AI have a question about my invoice for <?= urlencode(date('F Y', strtotime($bill['billing_period_start']))) ?>." 
                                               class="button is-warning is-small"
                                               title="Contact Billing">
                                                <span class="icon"><i class="fas fa-envelope"></i></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Billing Information -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-info-circle"></i></span>
            Billing Information
        </h3>
        
        <div class="content">
            <div class="columns">
                <div class="column is-6">
                    <h4 class="title is-6">Payment Terms</h4>
                    <ul>
                        <li>Invoices are generated monthly based on your service usage</li>
                        <li>Payment is due within 14 days of the invoice date</li>
                        <li>Standard service fees are based on messages processed</li>
                        <li>Manual review services incur additional fees of $1.00 per email</li>
                        <li>SMS notifications are charged per message sent</li>
                    </ul>
                </div>
                <div class="column is-6">
                    <h4 class="title is-6">Need Help?</h4>
                    <p>If you have questions about your billing or need assistance with payments:</p>
                    <div class="buttons">
                        <a href="mailto:billing@aetia.com.au" class="button is-info">
                            <span class="icon"><i class="fas fa-envelope"></i></span>
                            <span>Contact Billing</span>
                        </a>
                        <a href="documents.php" class="button is-outlined">
                            <span class="icon"><i class="fas fa-folder"></i></span>
                            <span>View All Documents</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bill Details Modal -->
<div class="modal" id="billDetailsModal">
    <div class="modal-background" onclick="closeBillModal()"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">
                <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                Bill Details
            </p>
            <button class="delete" aria-label="close" onclick="closeBillModal()"></button>
        </header>
        <section class="modal-card-body">
            <div id="modalBillContent">
                <!-- Bill details will be populated here -->
            </div>
        </section>
        <footer class="modal-card-foot">
            <button class="button is-primary" onclick="closeBillModal()">
                <span class="icon"><i class="fas fa-check"></i></span>
                <span>Close</span>
            </button>
        </footer>
    </div>
</div>

<script>
// Store bills data for modal display
const billsData = <?= json_encode($userBills) ?>;

// Show bill details modal
function showBillDetails(billId) {
    const bill = billsData.find(b => b.id == billId);
    if (!bill) return;
    
    const billingPeriod = new Date(bill.billing_period_start).toLocaleDateString('en-US', {
        year: 'numeric', month: 'long'
    });
    
    let invoicesHtml = '';
    if (bill.invoices && bill.invoices.length > 0) {
        invoicesHtml = `
            <h5 class="title is-6 mt-4">Available Documents:</h5>
            <div class="buttons">
                ${bill.invoices.map(invoice => `
                    <a href="api/download-document.php?id=${invoice.document_id}" 
                       class="button is-small is-${invoice.invoice_type === 'generated' ? 'primary' : 'info'}" 
                       target="_blank">
                        <span class="icon">
                            <i class="fas fa-${invoice.invoice_type === 'generated' ? 'file-invoice' : 
                                (invoice.invoice_type === 'payment_receipt' ? 'receipt' : 'file-alt')}"></i>
                        </span>
                        <span>${invoice.original_filename}</span>
                        ${invoice.is_primary_invoice ? '<span class="icon is-small"><i class="fas fa-star"></i></span>' : ''}
                    </a>
                `).join('')}
            </div>
        `;
    }
    
    document.getElementById('modalBillContent').innerHTML = `
        <div class="columns">
            <div class="column is-6">
                <h4 class="title is-5">${billingPeriod} Statement</h4>
                <div class="content">
                    <p><strong>Billing Period:</strong><br>
                       ${new Date(bill.billing_period_start).toLocaleDateString()} - 
                       ${new Date(bill.billing_period_end).toLocaleDateString()}</p>
                    
                    <p><strong>Service Summary:</strong></p>
                    <ul>
                        <li>${bill.message_count} messages processed</li>
                        ${bill.manual_review_count > 0 ? `<li>${bill.manual_review_count} manual reviews</li>` : ''}
                        ${bill.sms_count > 0 ? `<li>${bill.sms_count} SMS messages sent</li>` : ''}
                    </ul>
                    
                    ${bill.notes ? `<p><strong>Notes:</strong><br>${bill.notes}</p>` : ''}
                </div>
            </div>
            <div class="column is-6">
                <h4 class="title is-5">Billing Breakdown</h4>
                <table class="table is-fullwidth">
                    <tbody>
                        ${bill.standard_fee > 0 ? `
                        <tr>
                            <td>Standard Service Fee:</td>
                            <td class="has-text-right">$${parseFloat(bill.standard_fee).toFixed(2)}</td>
                        </tr>` : ''}
                        ${bill.manual_review_fee > 0 ? `
                        <tr>
                            <td>Manual Review Fee:</td>
                            <td class="has-text-right has-text-warning">$${parseFloat(bill.manual_review_fee).toFixed(2)}</td>
                        </tr>` : ''}
                        ${bill.sms_fee > 0 ? `
                        <tr>
                            <td>SMS Fee:</td>
                            <td class="has-text-right has-text-link">$${parseFloat(bill.sms_fee).toFixed(2)}</td>
                        </tr>` : ''}
                        ${bill.account_credit > 0 ? `
                        <tr>
                            <td>Account Credit:</td>
                            <td class="has-text-right has-text-link">-$${parseFloat(bill.account_credit).toFixed(2)}</td>
                        </tr>` : ''}
                        <tr class="has-background-success-light">
                            <td><strong>Total Amount:</strong></td>
                            <td class="has-text-right has-text-weight-bold">$${parseFloat(bill.total_amount).toFixed(2)}</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="notification is-${bill.bill_status === 'paid' ? 'success' : 
                    (bill.bill_status === 'overdue' ? 'danger' : 'info')} is-light">
                    <p><strong>Status:</strong> ${bill.bill_status.charAt(0).toUpperCase() + bill.bill_status.slice(1)}</p>
                    ${bill.due_date ? `<p><strong>Due Date:</strong> ${new Date(bill.due_date).toLocaleDateString()}</p>` : ''}
                    ${bill.payment_date ? `<p><strong>Payment Date:</strong> ${new Date(bill.payment_date).toLocaleDateString()}</p>` : ''}
                    ${bill.payment_method ? `<p><strong>Payment Method:</strong> ${bill.payment_method}</p>` : ''}
                </div>
            </div>
        </div>
        ${invoicesHtml}
    `;
    
    // Show modal
    document.getElementById('billDetailsModal').classList.add('is-active');
}

// Close bill modal
function closeBillModal() {
    document.getElementById('billDetailsModal').classList.remove('is-active');
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBillModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>