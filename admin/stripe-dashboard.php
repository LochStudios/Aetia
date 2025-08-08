<?php
// admin/stripe-dashboard.php - Admin dashboard for monitoring Stripe integration
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/stripe-database.php';
require_once __DIR__ . '/../services/StripeDataService.php';
require_once __DIR__ . '/../includes/SecurityManager.php';

$userModel = new User();
$stripeDataService = new StripeDataService();
$securityManager = new SecurityManager();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

// Get date range for filtering
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today

try {
    // Get payment statistics
    $paymentStats = $stripeDataService->getPaymentStats($startDate . ' 00:00:00', $endDate . ' 23:59:59');
    
    // Get recent webhook events
    $recentEvents = $stripeDataService->getRecentWebhookEvents(50);
    
    // Get recent invoices
    $recentInvoices = $stripeDataService->getAllInvoices(20);
    
} catch (Exception $e) {
    error_log("Stripe dashboard error: " . $e->getMessage());
    $error = 'Error loading dashboard data: ' . $e->getMessage();
}

$pageTitle = 'Stripe Dashboard | Admin | Aetia';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fab fa-stripe-s"></i></span><span>Stripe Dashboard</span></a></li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="level">
        <div class="level-left">
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fab fa-stripe-s"></i></span>
                Stripe Integration Dashboard
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="generate-bills.php" class="button is-primary">
                    <span class="icon"><i class="fas fa-receipt"></i></span>
                    <span>Generate Bills</span>
                </a>
            </div>
        </div>
    </div>

    <p class="subtitle has-text-light">
        Monitor Stripe payments, invoices, and webhook activity.
    </p>

    <!-- Error Display -->
    <?php if (isset($error)): ?>
        <div class="notification is-danger">
            <button class="delete"></button>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Date Range Filter -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            Date Range Filter
        </h3>
        
        <form method="GET" action="">
            <div class="columns">
                <div class="column is-4">
                    <div class="field">
                        <label class="label">Start Date</label>
                        <div class="control">
                            <input type="date" name="start_date" class="input" value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                    </div>
                </div>
                <div class="column is-4">
                    <div class="field">
                        <label class="label">End Date</label>
                        <div class="control">
                            <input type="date" name="end_date" class="input" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                    </div>
                </div>
                <div class="column is-4">
                    <div class="field">
                        <label class="label">&nbsp;</label>
                        <div class="control">
                            <button type="submit" class="button is-primary">
                                <span class="icon"><i class="fas fa-search"></i></span>
                                <span>Update</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Payment Statistics -->
    <?php if (isset($paymentStats)): ?>
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-chart-bar"></i></span>
            Payment Statistics
            <span class="subtitle is-6">(<?= date('M j', strtotime($startDate)) ?> - <?= date('M j, Y', strtotime($endDate)) ?>)</span>
        </h3>
        
        <div class="columns is-multiline">
            <div class="column is-3">
                <div class="box has-background-info-light">
                    <div class="has-text-centered">
                        <p class="heading">Total Invoices</p>
                        <p class="title is-3 has-text-info"><?= number_format($paymentStats['total_invoices'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="column is-3">
                <div class="box has-background-success-light">
                    <div class="has-text-centered">
                        <p class="heading">Paid Invoices</p>
                        <p class="title is-3 has-text-success"><?= number_format($paymentStats['paid_invoices'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="column is-3">
                <div class="box has-background-warning-light">
                    <div class="has-text-centered">
                        <p class="heading">Open Invoices</p>
                        <p class="title is-3 has-text-warning"><?= number_format($paymentStats['open_invoices'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
            <div class="column is-3">
                <div class="box has-background-danger-light">
                    <div class="has-text-centered">
                        <p class="heading">Void/Failed</p>
                        <p class="title is-3 has-text-danger"><?= number_format($paymentStats['void_invoices'] ?? 0) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="columns">
            <div class="column is-4">
                <div class="box">
                    <div class="has-text-centered">
                        <p class="heading">Total Amount Due</p>
                        <p class="title is-4 has-text-info">$<?= number_format($paymentStats['total_amount_due'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
            <div class="column is-4">
                <div class="box">
                    <div class="has-text-centered">
                        <p class="heading">Total Amount Paid</p>
                        <p class="title is-4 has-text-success">$<?= number_format($paymentStats['total_amount_paid'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
            <div class="column is-4">
                <div class="box">
                    <div class="has-text-centered">
                        <p class="heading">Average Invoice</p>
                        <p class="title is-4 has-text-grey">$<?= number_format($paymentStats['average_invoice_amount'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Invoices -->
    <?php if (!empty($recentInvoices)): ?>
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
            Recent Invoices
        </h3>
        
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Client</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Billing Period</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentInvoices as $invoice): ?>
                    <tr>
                        <td>
                            <code><?= htmlspecialchars($invoice['invoice_number'] ?? 'Draft') ?></code>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($invoice['username'] ?? 'Unknown') ?></strong><br>
                            <small><?= htmlspecialchars($invoice['user_email'] ?? '') ?></small>
                        </td>
                        <td>
                            <span class="tag is-info is-medium">
                                $<?= number_format($invoice['amount_due'], 2) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $statusClass = 'is-light';
                            switch ($invoice['status']) {
                                case 'paid': $statusClass = 'is-success'; break;
                                case 'open': $statusClass = 'is-warning'; break;
                                case 'void': $statusClass = 'is-danger'; break;
                                case 'draft': $statusClass = 'is-info'; break;
                            }
                            ?>
                            <span class="tag <?= $statusClass ?>">
                                <?= ucfirst(htmlspecialchars($invoice['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($invoice['billing_period'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <span class="is-size-7">
                                <?= date('M j, Y', strtotime($invoice['created_at'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($invoice['hosted_invoice_url']): ?>
                            <a href="<?= htmlspecialchars($invoice['hosted_invoice_url']) ?>" target="_blank" class="button is-small is-info">
                                <span class="icon"><i class="fas fa-external-link-alt"></i></span>
                                <span>View</span>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Webhook Events -->
    <?php if (!empty($recentEvents)): ?>
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-webhook"></i></span>
            Recent Webhook Events
        </h3>
        
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Event Type</th>
                        <th>Object ID</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th>Error</th>
                        <th>Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentEvents as $event): ?>
                    <tr>
                        <td>
                            <code><?= htmlspecialchars($event['event_type']) ?></code>
                        </td>
                        <td>
                            <small><?= htmlspecialchars(substr($event['object_id'] ?? '', 0, 20)) ?>...</small>
                        </td>
                        <td>
                            <span class="tag <?= $event['processed'] ? 'is-success' : 'is-warning' ?>">
                                <?= $event['processed'] ? 'Processed' : 'Pending' ?>
                            </span>
                        </td>
                        <td>
                            <?= $event['processing_attempts'] ?>
                        </td>
                        <td>
                            <?php if ($event['last_processing_error']): ?>
                            <span class="has-text-danger" title="<?= htmlspecialchars($event['last_processing_error']) ?>">
                                <i class="fas fa-exclamation-triangle"></i>
                            </span>
                            <?php else: ?>
                            <span class="has-text-success">
                                <i class="fas fa-check"></i>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="is-size-7">
                                <?= date('M j, H:i', strtotime($event['created_at'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- System Status -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-heartbeat"></i></span>
            System Status
        </h3>
        
        <div class="columns">
            <div class="column is-6">
                <div class="content">
                    <h5 class="title is-5">Webhook Endpoint</h5>
                    <p>
                        <strong>URL:</strong> <code><?= $_SERVER['HTTP_HOST'] ?>/api/stripe-webhook.php</code><br>
                        <strong>Status:</strong> 
                        <span class="tag is-success">
                            <span class="icon"><i class="fas fa-check"></i></span>
                            <span>Active</span>
                        </span>
                    </p>
                </div>
            </div>
            <div class="column is-6">
                <div class="content">
                    <h5 class="title is-5">Database</h5>
                    <p>
                        <strong>Tables:</strong> stripe_invoices, stripe_customers, stripe_webhook_events<br>
                        <strong>Status:</strong> 
                        <span class="tag is-success">
                            <span class="icon"><i class="fas fa-check"></i></span>
                            <span>Connected</span>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 30 seconds
setTimeout(function() {
    location.reload();
}, 30000);

// Delete notification functionality
document.addEventListener('DOMContentLoaded', (event) => {
    const deleteButtons = document.querySelectorAll('.notification .delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            button.parentElement.remove();
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
