<?php
// admin/generate-bills.php - Admin interface for generating client bills based on messages
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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SecurityManager.php';
require_once __DIR__ . '/../includes/SecurityException.php';

$userModel = new User();
$messageModel = new Message();
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
$billData = [];
$invoiceResults = [
    'total_created' => 0,
    'success' => [],
    'errors' => [],
    'total_amount' => 0
];

// Handle bill generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate_bills') {
        // Get the selected month and year, default to previous month
        $selectedMonth = isset($_POST['month']) ? intval($_POST['month']) : date('n', strtotime('last month'));
        $selectedYear = isset($_POST['year']) ? intval($_POST['year']) : date('Y', strtotime('last month'));
        
        // Validate month and year inputs
        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $error = 'Invalid month selected.';
        } elseif ($selectedYear < 2000 || $selectedYear > date('Y')) {
            $error = 'Invalid year selected.';
        } else {
            // Calculate the first and last day of the selected month
            $firstDay = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
            $lastDay = date('Y-m-t', strtotime($firstDay)); // Last day of the month
            
            try {
                $database = new Database();
                $mysqli = $database->getConnection();
                
                if (!$mysqli) {
                    throw new Exception('Database connection failed');
                }
                
                // Use the new billing method that includes manual review fees
                $billData = $messageModel->getBillingDataWithManualReview($firstDay, $lastDay);
                
                if (empty($billData)) {
                    $error = 'No client activity found for ' . date('F Y', strtotime($firstDay));
                } else {
                    $message = 'Bill data generated successfully for ' . date('F Y', strtotime($firstDay)) . '. Found ' . count($billData) . ' clients with activity.';
                    // Store the period for display
                    $_SESSION['bill_period_start'] = $firstDay;
                    $_SESSION['bill_period_end'] = $lastDay;
                }
                
            } catch (Exception $e) {
                error_log("Generate bills error: " . $e->getMessage());
                $error = 'Database error occurred while generating bills.';
            }
        }
    } elseif ($_POST['action'] === 'export_for_wise') {
        // Export billing data for manual Wise.com invoice creation
        try {
            // Verify admin access and security
            if (!$securityManager->verifyAdminAccess($_SESSION['user_id'], 'export_billing_data')) {
                throw new SecurityException('Access denied for billing data export');
            }
            
            // Get bill data from session or regenerate
            $firstDay = $_SESSION['bill_period_start'] ?? '';
            $lastDay = $_SESSION['bill_period_end'] ?? '';
            if (empty($firstDay) || empty($lastDay)) {
                $error = 'No billing period found. Please generate bills first.';
            } else {
                $billData = $messageModel->getBillingDataWithManualReview($firstDay, $lastDay);
                
                if (empty($billData)) {
                    $error = 'No billing data found for the current period.';
                } else {
                    // Generate CSV export for Wise.com invoice creation
                    $billingPeriod = date('F Y', strtotime($firstDay));
                    $filename = 'billing_export_' . date('Y_m', strtotime($firstDay)) . '.csv';
                    
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    header('Cache-Control: no-cache, must-revalidate');
                    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
                    
                    $output = fopen('php://output', 'w');
                    
                    // CSV headers
                    fputcsv($output, [
                        'Client Name',
                        'Email',
                        'Username', 
                        'User ID',
                        'Standard Fee',
                        'Manual Review Fee',
                        'Total Amount',
                        'Billing Period',
                        'Message Count',
                        'Manual Review Count'
                    ]);
                    
                    // Export data
                    foreach ($billData as $client) {
                        fputcsv($output, [
                            $client['name'] ?? $client['client_name'],
                            $client['email'],
                            $client['username'],
                            $client['user_id'],
                            '$' . number_format($client['standard_fee'], 2),
                            '$' . number_format($client['manual_review_fee'], 2),
                            '$' . number_format($client['total_fee'], 2),
                            $billingPeriod,
                            $client['message_count'],
                            $client['manual_review_count']
                        ]);
                    }
                    
                    fclose($output);
                    exit;
                }
            }
        } catch (SecurityException $e) {
            error_log("Security violation in billing export: " . $e->getMessage());
            $error = 'Security violation detected. Access denied.';
        } catch (Exception $e) {
            error_log("Billing export error: " . $e->getMessage());
            $error = 'Error exporting billing data. Please try again.';
        }
    }
}

// Project started in August 2025 - default to current month/year or August 2025 if before
$projectStartMonth = 8; // August
$projectStartYear = 2025;
$currentMonth = date('n');
$currentYear = date('Y');

// Default to current month if we're past project start, otherwise August 2025
if ($currentYear > $projectStartYear || ($currentYear == $projectStartYear && $currentMonth >= $projectStartMonth)) {
    $defaultMonth = $currentMonth;
    $defaultYear = $currentYear;
} else {
    $defaultMonth = $projectStartMonth;
    $defaultYear = $projectStartYear;
}

// Initialize variables for display
$firstDay = '';
$lastDay = '';

$pageTitle = 'Generate Bills | Admin | Aetia';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li><a href="users.php"><span class="icon is-small"><i class="fas fa-users-cog"></i></span><span>Users</span></a></li>
            <li><a href="messages.php"><span class="icon is-small"><i class="fas fa-envelope-open-text"></i></span><span>Messages</span></a></li>
            <li><a href="archived-messages.php"><span class="icon is-small"><i class="fas fa-archive"></i></span><span>Archived Messages</span></a></li>
            <li><a href="create-message.php"><span class="icon is-small"><i class="fas fa-plus"></i></span><span>New Message</span></a></li>
            <li><a href="send-emails.php"><span class="icon is-small"><i class="fas fa-paper-plane"></i></span><span>Send Emails</span></a></li>
            <li><a href="email-logs.php"><span class="icon is-small"><i class="fas fa-chart-line"></i></span><span>Email Logs</span></a></li>
            <li><a href="contact-form.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Contact Forms</span></a></li>
            <li><a href="contracts.php"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Contracts</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="level">
        <div class="level-left">
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fas fa-receipt"></i></span>
                Generate Client Bills
            </h2>
        </div>
    </div>

    <p class="subtitle has-text-light">
        Generate billing information based on client message activity for a specific month.
    </p>

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

    <!-- Bill Generation Form -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-calendar-alt"></i></span>
            Select Billing Period
        </h3>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_bills">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($securityManager->getCsrfToken()) ?>">
            
            <div class="columns">
                <div class="column is-6">
                    <div class="field">
                        <label class="label">Month</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="month" id="monthSelect" required>
                                    <?php 
                                    // Show months based on project start (August 2025)
                                    $startMonth = ($defaultYear == $projectStartYear) ? $projectStartMonth : 1;
                                    $endMonth = ($defaultYear == $currentYear) ? $currentMonth : 12;
                                    
                                    for ($i = $startMonth; $i <= $endMonth; $i++): 
                                    ?>
                                        <option value="<?= $i ?>" <?= $i == $defaultMonth ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="column is-6">
                    <div class="field">
                        <label class="label">Year</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="year" id="yearSelect" required onchange="updateMonthOptions()">
                                    <?php 
                                    // Show years from project start (2025) to current year + 1 (for future planning)
                                    for ($year = $projectStartYear; $year <= $currentYear + 1; $year++): 
                                    ?>
                                        <option value="<?= $year ?>" <?= $year == $defaultYear ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="field">
                <div class="control">
                    <button type="submit" class="button is-primary is-medium">
                        <span class="icon"><i class="fas fa-calculator"></i></span>
                        <span>Generate Bills</span>
                    </button>
                </div>
                <p class="help">
                    This will analyze all messages created during the selected month and generate billing information for each client.
                </p>
            </div>
        </form>
    </div>

    <!-- Export for Wise.com Invoice Creation -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-file-export"></i></span>
            Export for Wise.com Invoice Creation
        </h3>
        
        <div class="columns">
            <div class="column is-8">
                <div class="content">
                    <p>Export billing data to create invoices manually on Wise.com. This will:</p>
                    <ul>
                        <li>Download a CSV file with all client billing information</li>
                        <li>Include user details, email addresses, and billing amounts</li>
                        <li>Provide all data needed to create invoices on Wise.com</li>
                        <li>After creating invoices, upload them to the client's Documents section</li>
                    </ul>
                </div>
            </div>
            <div class="column is-4">
                <div class="field">
                    <div class="control">
                        <?php if (!empty($billData)): ?>
                        <form method="POST" action="" style="display: inline-block;" 
                              onsubmit="return confirm('Export billing data for <?= count($billData) ?> clients for manual Wise.com invoice creation?');">
                            <input type="hidden" name="action" value="export_for_wise">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($securityManager->getCsrfToken()) ?>">
                            <button type="submit" class="button is-success is-medium">
                                <span class="icon"><i class="fas fa-download"></i></span>
                                <span>Export Billing Data (CSV)</span>
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="button is-success is-medium" disabled title="Generate bills first">
                            <span class="icon"><i class="fas fa-download"></i></span>
                            <span>Export Billing Data (CSV)</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bill Results -->
    <div class="box">
        <h3 class="title is-4">
            <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
            Manual Invoice Results
        </h3>
        
        <div class="level mb-4">
            <div class="level-left">
                <div class="level-item">
                    <div>
                        <p class="heading">Total Processed</p>
                        <p class="title"><?= $invoiceResults['total_created'] ?></p>
                    </div>
                </div>
                <div class="level-item">
                    <div>
                        <p class="heading">Successful</p>
                        <p class="title has-text-success"><?= count($invoiceResults['success']) ?></p>
                    </div>
                </div>
                <div class="level-item">
                    <div>
                        <p class="heading">Errors</p>
                        <p class="title has-text-danger"><?= count($invoiceResults['errors']) ?></p>
                    </div>
                </div>
                <div class="level-item">
                    <div>
                        <p class="heading">Total Amount</p>
                        <p class="title has-text-success">$<?= number_format($invoiceResults['total_amount'], 2) ?></p>
                    </div>
                </div>
                <div class="level-item">
                    <div>
                        <p class="heading">Emails Sent</p>
                        <p class="title has-text-info"><?= count($invoiceResults['success']) ?></p>
                    </div>
                </div>
            </div>
        </div>

    <!-- Bill Results -->
        <h4 class="title is-5 has-text-success">
            <span class="icon"><i class="fas fa-check-circle"></i></span>
            Successfully Created Invoices
        </h4>
        <div class="table-container mb-5">
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Invoice ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoiceResults['success'] as $success): ?>
                    <tr>
                        <td><?= htmlspecialchars($success['user_id']) ?></td>
                        <td><?= htmlspecialchars($success['email']) ?></td>
                        <td>
                            <code><?= htmlspecialchars($success['invoice_id']) ?></code>
                        </td>
                        <td>
                            <span class="tag is-success">$<?= number_format($success['amount'], 2) ?></span>
                        </td>
                        <td>
                            <span class="tag is-success is-small">
                                <span class="icon"><i class="fas fa-check"></i></span>
                                <span>Sent via Stripe</span>
                            </span>
                        </td>
                        <td>
                            <a href="<?= htmlspecialchars($success['invoice_url']) ?>" target="_blank" class="button is-small is-info">
                                <span class="icon"><i class="fas fa-external-link-alt"></i></span>
                                <span>View Invoice</span>
    <!-- Bill Results -->
    <?php if (!empty($billData)): ?>
        <?php 
        // Get period from session if available
        $displayFirstDay = $_SESSION['bill_period_start'] ?? '';
        $displayLastDay = $_SESSION['bill_period_end'] ?? '';
        ?>
        <div class="box">
            <h3 class="title is-4">
                <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                Billing Summary - <?= $displayFirstDay ? date('F Y', strtotime($displayFirstDay)) : 'Selected Period' ?>
            </h3>
            
            <div class="level mb-4">
                <div class="level-left">
                    <div class="level-item">
                        <div>
                            <p class="heading">Total Clients</p>
                            <p class="title"><?= count($billData) ?></p>
                        </div>
                    </div>
                    <div class="level-item">
                        <div>
                            <p class="heading">Total Messages</p>
                            <p class="title"><?= array_sum(array_column($billData, 'total_message_count')) ?></p>
                        </div>
                    </div>
                    <div class="level-item">
                        <div>
                            <p class="heading">Manual Reviews</p>
                            <p class="title has-text-warning"><?= array_sum(array_column($billData, 'manual_review_count')) ?></p>
                        </div>
                    </div>
                    <div class="level-item">
                        <div>
                            <p class="heading">Service Fees</p>
                            <p class="title has-text-info">$<?= number_format(array_sum(array_column($billData, 'standard_fee')), 2) ?></p>
                        </div>
                    </div>
                    <div class="level-item">
                        <div>
                            <p class="heading">Review Fees</p>
                            <p class="title has-text-warning">$<?= number_format(array_sum(array_column($billData, 'manual_review_fee')), 2) ?></p>
                        </div>
                    </div>
                    <div class="level-item">
                        <div>
                            <p class="heading">Total Revenue</p>
                            <p class="title has-text-success">$<?= number_format(array_sum(array_column($billData, 'total_fee')), 2) ?></p>
                        </div>
                    </div>
                    <div class="level-item">
                        <div>
                            <p class="heading">Period</p>
                            <p class="title"><?= $displayFirstDay && $displayLastDay ? date('M j', strtotime($displayFirstDay)) . ' - ' . date('M j, Y', strtotime($displayLastDay)) : 'N/A' ?></p>
                        </div>
                    </div>
                </div>
                <div class="level-right">
                    <div class="buttons">
                        <button class="button is-success" onclick="exportToCSV()">
                            <span class="icon"><i class="fas fa-download"></i></span>
                            <span>Export CSV</span>
                        </button>
                        <button class="button is-info" onclick="printBills()">
                            <span class="icon"><i class="fas fa-print"></i></span>
                            <span>Print</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable" id="billTable">
                    <thead>
                        <tr>
                            <th class="col-id"><abbr title="User ID">ID</abbr></th>
                            <th class="col-client">Client</th>
                            <th class="col-contact">Contact</th>
                            <th class="col-type has-text-centered"><abbr title="Account Type">Type</abbr></th>
                            <th class="col-messages has-text-centered"><abbr title="Messages">Msgs</abbr></th>
                            <th class="col-review has-text-centered"><abbr title="Manual Reviews">MR</abbr></th>
                            <th class="col-service has-text-right"><abbr title="Service Fee">Service</abbr></th>
                            <th class="col-review-fee has-text-right"><abbr title="Review Fee">Review</abbr></th>
                            <th class="col-total has-text-right"><abbr title="Total Fee">Total</abbr></th>
                            <th class="col-period has-text-centered">Period</th>
                            <th class="col-details has-text-centered">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billData as $client): ?>
                            <tr>
                                <td class="has-text-weight-bold"><?= htmlspecialchars($client['user_id']) ?></td>
                                <td>
                                    <div class="is-size-7">
                                        <div class="has-text-weight-bold">
                                            <?php if (!empty($client['first_name']) || !empty($client['last_name'])): ?>
                                                <?= htmlspecialchars(trim($client['first_name'] . ' ' . $client['last_name'])) ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars($client['username']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="has-text-grey is-size-7">
                                            <a href="users.php?user_id=<?= $client['user_id'] ?>" target="_blank" class="has-text-grey">
                                                @<?= htmlspecialchars($client['username']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="is-size-7">
                                        <a href="mailto:<?= htmlspecialchars($client['email']) ?>" class="has-text-link">
                                            <?= htmlspecialchars($client['email']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="has-text-centered">
                                    <span class="tag is-small is-<?= $client['account_type'] === 'manual' ? 'primary' : 'info' ?>">
                                        <?= ucfirst(htmlspecialchars($client['account_type'])) ?>
                                    </span>
                                </td>
                                <td class="has-text-centered">
                                    <span class="tag is-success is-small-compact">
                                        <?= $client['total_message_count'] ?>
                                    </span>
                                </td>
                                <td class="has-text-centered">
                                    <?php if ($client['manual_review_count'] > 0): ?>
                                        <span class="tag is-warning is-small-compact" title="Manual Review Messages">
                                            <?= $client['manual_review_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="tag is-dark is-small-compact">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="has-text-right">
                                    <span class="has-text-weight-semibold has-text-info is-size-7">
                                        $<?= number_format($client['standard_fee'], 2) ?>
                                    </span>
                                </td>
                                <td class="has-text-right">
                                    <?php if ($client['manual_review_fee'] > 0): ?>
                                        <span class="has-text-weight-semibold has-text-warning is-size-7">
                                            $<?= number_format($client['manual_review_fee'], 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="has-text-grey is-size-7">$0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td class="has-text-right">
                                    <span class="has-text-weight-bold has-text-success is-size-6">
                                        $<?= number_format($client['total_fee'], 2) ?>
                                    </span>
                                </td>
                                <td class="has-text-centered">
                                    <div class="is-size-7">
                                        <div><?= isset($client['first_message_date']) ? date('M j', strtotime($client['first_message_date'])) : 'N/A' ?></div>
                                        <div class="has-text-grey">to</div>
                                        <div><?= isset($client['last_message_date']) ? date('M j', strtotime($client['last_message_date'])) : 'N/A' ?></div>
                                    </div>
                                </td>
                                <td class="has-text-centered" style="position: relative;">
                                    <details>
                                        <summary class="button is-small is-outlined">
                                            <span class="icon is-small"><i class="fas fa-eye"></i></span>
                                        </summary>
                                        <div class="details-popup p-3">
                                            <?php if ($client['manual_review_count'] > 0 && !empty($client['manual_review_details'])): ?>
                                                <h6 class="title is-6 has-text-warning mb-2">
                                                    <span class="icon"><i class="fas fa-dollar-sign"></i></span>
                                                    Manual Review Messages (<?= $client['manual_review_count'] ?>):
                                                </h6>
                                                <?php 
                                                $manualReviewDetails = explode('; ', $client['manual_review_details']);
                                                foreach ($manualReviewDetails as $detail): 
                                                    if (!empty(trim($detail))):
                                                ?>
                                                    <p class="is-size-7 mb-1 has-text-warning">• <?= htmlspecialchars($detail) ?> <strong>[+$1.00]</strong></p>
                                                <?php 
                                                    endif;
                                                endforeach;
                                                ?>
                                                <hr class="my-2">
                                            <?php endif; ?>
                                            
                                            <h6 class="title is-6 mb-2">Billing Summary:</h6>
                                            <p class="is-size-7 mb-1">
                                                <strong>Period:</strong> <?= date('M j, Y', strtotime($client['first_message_date'])) ?> - <?= date('M j, Y', strtotime($client['last_message_date'])) ?>
                                            </p>
                                            <p class="is-size-7 mb-1">
                                                <strong>Total Messages:</strong> <?= $client['total_message_count'] ?>
                                            </p>
                                            <p class="is-size-7 mb-1">
                                                <strong>Service Fee:</strong> $<?= number_format($client['standard_fee'], 2) ?> (<?= $client['total_message_count'] ?> × $1.00)
                                            </p>
                                            <?php if ($client['manual_review_count'] > 0): ?>
                                            <p class="is-size-7 mb-1">
                                                <strong>Manual Review Fee:</strong> $<?= number_format($client['manual_review_fee'], 2) ?> (<?= $client['manual_review_count'] ?> × $1.00)
                                            </p>
                                            <?php endif; ?>
                                            <p class="is-size-7 mb-1">
                                                <strong>Total Amount Due:</strong> <strong>$<?= number_format($client['total_fee'], 2) ?></strong>
                                            </p>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Project configuration
const PROJECT_START_YEAR = <?= $projectStartYear ?>;
const PROJECT_START_MONTH = <?= $projectStartMonth ?>;
const CURRENT_YEAR = <?= $currentYear ?>;
const CURRENT_MONTH = <?= $currentMonth ?>;

// Month names
const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

// Update month options based on selected year
function updateMonthOptions() {
    const yearSelect = document.getElementById('yearSelect');
    const monthSelect = document.getElementById('monthSelect');
    const selectedYear = parseInt(yearSelect.value);
    
    // Clear existing options
    monthSelect.innerHTML = '';
    
    // Determine valid month range
    let startMonth = 1;
    let endMonth = 12;
    
    if (selectedYear === PROJECT_START_YEAR) {
        startMonth = PROJECT_START_MONTH; // August 2025
    }
    
    if (selectedYear === CURRENT_YEAR) {
        endMonth = CURRENT_MONTH; // Don't show future months in current year
    }
    
    // If selected year is in the future, limit to all months
    if (selectedYear > CURRENT_YEAR) {
        startMonth = 1;
        endMonth = 12;
    }
    
    // Add valid month options
    for (let month = startMonth; month <= endMonth; month++) {
        const option = document.createElement('option');
        option.value = month;
        option.textContent = MONTH_NAMES[month - 1];
        
        // Select current month if it's available, otherwise select the last available month
        if ((selectedYear === CURRENT_YEAR && month === CURRENT_MONTH) || 
            (selectedYear === PROJECT_START_YEAR && month === PROJECT_START_MONTH && CURRENT_YEAR > PROJECT_START_YEAR) ||
            (month === endMonth && selectedYear < CURRENT_YEAR)) {
            option.selected = true;
        }
        
        monthSelect.appendChild(option);
    }
    
    // If no months are available (shouldn't happen with our logic), add a placeholder
    if (monthSelect.children.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No valid months available';
        option.disabled = true;
        monthSelect.appendChild(option);
    }
}

// Show notification helper
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification is-${type}`;
    notification.innerHTML = `
        <button class="delete"></button>
        ${message}
    `;
    
    // Insert at the top of the content area
    const content = document.querySelector('.content');
    content.insertBefore(notification, content.firstChild);
    
    // Add delete functionality
    notification.querySelector('.delete').addEventListener('click', () => {
        notification.remove();
    });
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Export to CSV functionality
function exportToCSV() {
    const table = document.getElementById('billTable');
    const rows = table.querySelectorAll('tr');
    const csvContent = [];
    
    // Process each row
    rows.forEach((row, index) => {
        const cols = row.querySelectorAll(index === 0 ? 'th' : 'td');
        const rowData = [];
        
        cols.forEach((col, colIndex) => {
            let cellData = '';
            if (colIndex === 6 && index > 0) { // Message details column
                // Extract just the count for CSV
                const tag = col.querySelector('.tag');
                cellData = tag ? tag.textContent.trim() : col.textContent.trim();
            } else {
                cellData = col.textContent.trim();
            }
            // Escape quotes and wrap in quotes if contains comma
            cellData = cellData.replace(/"/g, '""');
            if (cellData.includes(',') || cellData.includes('"') || cellData.includes('\n')) {
                cellData = `"${cellData}"`;
            }
            rowData.push(cellData);
        });
        
        csvContent.push(rowData.join(','));
    });
    
    // Create and download CSV
    const csvString = csvContent.join('\n');
    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    
    const date = new Date();
    const filename = `aetia_bills_${date.getFullYear()}_${String(date.getMonth() + 1).padStart(2, '0')}.csv`;
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Print functionality
function printBills() {
    // Get the billing data from the table
    const table = document.getElementById('billTable');
    const rows = table.querySelectorAll('tr');
    // Create a new window for printing
    const printWindow = window.open('', '_blank');
    // Start building the print document
    let printContent = `
    <!DOCTYPE html>
    <html>
    <head>
        <title>Aetia Billing Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #209cee;
                padding-bottom: 15px;
            }
            .header h1 {
                color: #209cee;
                margin: 0;
                font-size: 24px;
            }
            .header p {
                margin: 5px 0;
                color: #666;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-size: 12px;
            }
            th {
                background-color: #f8f9fa;
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
                font-weight: bold;
                color: #333;
            }
            td {
                border: 1px solid #ddd;
                padding: 6px 8px;
                text-align: left;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .number {
                text-align: right;
            }
            .center {
                text-align: center;
            }
            .total-row {
                font-weight: bold;
                background-color: #e3f2fd !important;
            }
            .footer {
                margin-top: 30px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
                font-size: 10px;
                color: #666;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Aetia Talent Agency - Billing Report</h1>
            <p>Generated on: ${new Date().toLocaleDateString()}</p>
            <p>Period: ${document.querySelector('select[name="month"]').selectedOptions[0].text} ${document.querySelector('select[name="year"]').value}</p>
        </div>
        <table>
    `;
    
    // Process table headers (first row)
    const headerRow = rows[0];
    const headerCols = headerRow.querySelectorAll('th');
    printContent += '<thead><tr>';
    
    headerCols.forEach((col, index) => {
        if (index < headerCols.length - 1) { // Skip the last column (Actions)
            let headerText = col.textContent.trim();
            printContent += `<th>${headerText}</th>`;
        }
    });
    
    printContent += '</tr></thead><tbody>';
    
    // Process data rows
    let totalAmount = 0;
    let totalMessages = 0;
    let totalClients = 0;
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const cols = row.querySelectorAll('td');
        
        if (cols.length === 0) continue;
        
        printContent += '<tr>';
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip the last column (Actions)
            const col = cols[j];
            let cellData = '';
            
            // Extract clean text data
            if (j === 4 || j === 5) { // Message count columns
                const tag = col.querySelector('.tag');
                cellData = tag ? tag.textContent.trim() : col.textContent.trim();
                if (j === 4 && !isNaN(cellData)) {
                    totalMessages += parseInt(cellData) || 0;
                }
            } else if (j === 6 || j === 7 || j === 8) { // Fee columns
                const cleanText = col.textContent.trim().replace(/[^0-9.]/g, '');
                cellData = cleanText ? '$' + parseFloat(cleanText).toFixed(2) : '$0.00';
                if (j === 8 && !isNaN(parseFloat(cleanText))) { // Total column
                    totalAmount += parseFloat(cleanText) || 0;
                }
            } else {
                // For other columns, extract plain text
                cellData = col.textContent.trim().replace(/\\s+/g, ' ');
            }
            
            const className = (j === 6 || j === 7 || j === 8) ? 'number' : (j === 3 || j === 4 || j === 5) ? 'center' : '';
            printContent += `<td class="${className}">${cellData}</td>`;
        }
        
        printContent += '</tr>';
        totalClients++;
    }
    
    // Add totals row
    printContent += `
        <tr class="total-row">
            <td colspan="4"><strong>TOTALS:</strong></td>
            <td class="center"><strong>${totalMessages}</strong></td>
            <td class="center">-</td>
            <td class="number">-</td>
            <td class="number">-</td>
            <td class="number"><strong>$${totalAmount.toFixed(2)}</strong></td>
            <td class="center">-</td>
        </tr>
    `;
    
    printContent += `
        </tbody>
        </table>
        <div class="footer">
            <p><strong>Summary:</strong> ${totalClients} clients, ${totalMessages} total messages, $${totalAmount.toFixed(2)} total billing</p>
            <p>Aetia Talent Agency | Generated via Admin Dashboard</p>
        </div>
    </body>
    </html>
    `;
    
    // Write content to print window and print
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Wait for content to load then print
    printWindow.onload = function() {
        printWindow.print();
        printWindow.close();
    };
}

// Delete notification functionality
document.addEventListener('DOMContentLoaded', (event) => {
    const deleteButtons = document.querySelectorAll('.notification .delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', () => {
            button.parentElement.remove();
        });
    });
    
    // Initialize month options on page load
    updateMonthOptions();
});
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
