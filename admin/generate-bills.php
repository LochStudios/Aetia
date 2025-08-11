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
$savedReport = null;
$invoiceResults = [
    'total_created' => 0,
    'success' => [],
    'errors' => [],
    'total_amount' => 0
];

// Get the selected month and year, default to current month
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : (isset($_POST['month']) ? intval($_POST['month']) : date('n'));
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : (isset($_POST['year']) ? intval($_POST['year']) : date('Y'));

// Calculate the first and last day of the selected month
if ($selectedMonth >= 1 && $selectedMonth <= 12 && $selectedYear >= 2000 && $selectedYear <= date('Y')) {
    $firstDay = sprintf('%04d-%02d-01', $selectedYear, $selectedMonth);
    $lastDay = date('Y-m-t', strtotime($firstDay)); // Last day of the month
    
    // Check if there's a saved report for this period
    $savedReport = $messageModel->getSavedBillingReport($firstDay, $lastDay);
    if ($savedReport) {
        $billData = $savedReport['report_data'];
    } else {
        // No saved report exists, generate billing data automatically
        try {
            $database = new Database();
            $mysqli = $database->getConnection();
            
            if ($mysqli) {
                $billData = $messageModel->getBillingDataWithManualReview($firstDay, $lastDay);
                
                // If we have data, save it automatically
                if (!empty($billData)) {
                    $saveResult = $messageModel->saveBillingReport($firstDay, $lastDay, $billData, $_SESSION['user_id']);
                    if ($saveResult['success']) {
                        $savedReport = $messageModel->getSavedBillingReport($firstDay, $lastDay);
                    }
                }
                
                // Store the period for display regardless of whether we have data
                $_SESSION['bill_period_start'] = $firstDay;
                $_SESSION['bill_period_end'] = $lastDay;
            } else {
                error_log("Database connection failed during auto-load");
            }
        } catch (Exception $e) {
            error_log("Auto-generate billing data error: " . $e->getMessage());
            // Set a user-friendly message if this is a database connectivity issue
            if (strpos($e->getMessage(), 'Database connection failed') !== false) {
                $error = 'Database connection unavailable. Please check your configuration.';
            }
        }
    }
}

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
                    // Save the billing report to database
                    $saveResult = $messageModel->saveBillingReport($firstDay, $lastDay, $billData, $_SESSION['user_id']);
                    
                    if ($saveResult['success']) {
                        $action = $saveResult['action'];
                        $message = 'Bill data ' . ($action === 'updated' ? 'updated' : 'generated') . ' successfully for ' . date('F Y', strtotime($firstDay)) . '. Found ' . count($billData) . ' clients with activity.';
                        
                        // Update saved report for display
                        $savedReport = $messageModel->getSavedBillingReport($firstDay, $lastDay);
                    } else {
                        $message = 'Bill data generated successfully for ' . date('F Y', strtotime($firstDay)) . '. Found ' . count($billData) . ' clients with activity. Warning: Could not save to database.';
                        error_log("Failed to save billing report: " . $saveResult['message']);
                    }
                    
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
                // Try to get saved report first, then fall back to regenerating
                $savedReport = $messageModel->getSavedBillingReport($firstDay, $lastDay);
                if ($savedReport) {
                    $billData = $savedReport['report_data'];
                } else {
                    $billData = $messageModel->getBillingDataWithManualReview($firstDay, $lastDay);
                }
                
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

// Initialize variables for display - already set above based on selected month/year

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
        <div class="level-right">
            <div class="buttons">
                <a href="?month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="button is-info is-outlined">
                    <span class="icon"><i class="fas fa-sync"></i></span>
                    <span>Refresh Data</span>
                </a>
            </div>
        </div>
    </div>

    <p class="subtitle has-text-light">
        Generate billing information based on client message activity for a specific month.
    </p>

    <!-- Quick Month Navigation -->
    <div class="box">
        <h4 class="title is-6">Quick Month Navigation</h4>
        <div class="buttons are-small">
            <?php
            // Show navigation for nearby months
            for ($nav_month = 1; $nav_month <= 12; $nav_month++) {
                $isCurrentMonth = ($nav_month == $selectedMonth && $selectedYear == $selectedYear);
                $buttonClass = $isCurrentMonth ? 'is-primary' : 'is-outlined';
                ?>
                <a href="?month=<?= $nav_month ?>&year=<?= $selectedYear ?>" 
                   class="button <?= $buttonClass ?> is-small">
                    <?= date('M', mktime(0, 0, 0, $nav_month, 1)) ?>
                </a>
            <?php } ?>
        </div>
        
        <?php if (empty($billData) && $firstDay && $lastDay): ?>
            <div class="notification is-warning is-light mt-3">
                <p><strong>No billing data found for <?= date('F Y', strtotime($firstDay)) ?></strong></p>
                <p>Click "Generate Bills" below to create billing data for this period, or select a different month above.</p>
            </div>
        <?php endif; ?>
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
                                    <?php for ($month = 1; $month <= 12; $month++): ?>
                                        <option value="<?= $month ?>" <?= ($month == $selectedMonth) ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $month, 1)) ?>
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
                                    <?php for ($year = $projectStartYear; $year <= $currentYear; $year++): ?>
                                        <option value="<?= $year ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>>
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
                        <span><?= !empty($billData) ? 'Update Bills' : 'Generate Bills' ?></span>
                    </button>
                </div>
                <p class="help">
                    This will analyze all messages created during the selected month and <?= !empty($billData) ? 'update' : 'generate' ?> billing information for each client.
                </p>
                
                <?php if ($savedReport): ?>
                    <div class="notification is-info is-light mt-3">
                        <p class="has-text-weight-medium">Saved Report Information:</p>
                        <p><strong>Last Generated:</strong> <?= date('F j, Y \a\t g:i A', strtotime($savedReport['generated_at'])) ?></p>
                        <?php if ($savedReport['updated_at'] !== $savedReport['generated_at']): ?>
                            <p><strong>Last Updated:</strong> <?= date('F j, Y \a\t g:i A', strtotime($savedReport['updated_at'])) ?></p>
                        <?php endif; ?>
                        <p><strong>Generated By:</strong> <?= htmlspecialchars($savedReport['generated_by_display_name']) ?></p>
                        <p class="mb-0"><strong>Summary:</strong> <?= $savedReport['total_clients'] ?> clients, <?= $savedReport['total_messages'] ?> messages, $<?= number_format($savedReport['total_amount'], 2) ?> total</p>
                    </div>
                <?php endif; ?>
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
    <?php if (!empty($billData)): ?>
        <div class="box">
            <h3 class="title is-4">
                <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                Billing Summary - <?= date('F Y', strtotime($firstDay)) ?>
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
                            <p class="heading">SMS Sent</p>
                            <p class="title has-text-link"><?= array_sum(array_column($billData, 'sms_count')) ?></p>
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
                            <p class="heading">SMS Fees</p>
                            <p class="title has-text-link">$<?= number_format(array_sum(array_column($billData, 'sms_fee')), 2) ?></p>
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
                            <p class="title"><?= $firstDay && $lastDay ? date('M j', strtotime($firstDay)) . ' - ' . date('M j, Y', strtotime($lastDay)) : 'N/A' ?></p>
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
                            <th class="col-sms has-text-centered"><abbr title="SMS Sent">SMS</abbr></th>
                            <th class="col-service has-text-right"><abbr title="Service Fee">Service</abbr></th>
                            <th class="col-review-fee has-text-right"><abbr title="Review Fee">Review</abbr></th>
                            <th class="col-sms-fee has-text-right"><abbr title="SMS Fee">SMS Fee</abbr></th>
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
                                <td class="has-text-centered">
                                    <?php if ($client['sms_count'] > 0): ?>
                                        <span class="tag is-link is-small-compact" title="SMS Messages Sent">
                                            <?= $client['sms_count'] ?>
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
                                    <?php if ($client['sms_fee'] > 0): ?>
                                        <span class="has-text-weight-semibold has-text-link is-size-7">
                                            $<?= number_format($client['sms_fee'], 2) ?>
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
                                <td class="has-text-centered">
                                    <button class="button is-small is-outlined has-text-white" onclick="showBillingDetails(<?= $client['user_id'] ?>)">
                                        <span class="icon is-small has-text-white"><i class="fas fa-eye"></i></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Billing Details Modal -->
    <div class="modal" id="billingDetailsModal">
        <div class="modal-background" onclick="closeBillingModal()"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">
                    <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    Billing Details
                </p>
                <button class="delete" aria-label="close" onclick="closeBillingModal()"></button>
            </header>
            <section class="modal-card-body">
                <div id="modalClientInfo" class="mb-4">
                    <!-- Client info will be populated here -->
                </div>
                
                <div id="modalManualReviews" class="mb-4" style="display: none;">
                    <h5 class="title is-5 has-text-warning">
                        <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                        Manual Review Messages
                    </h5>
                    <div class="box has-background-warning-light">
                        <div id="modalManualReviewList">
                            <!-- Manual review details will be populated here -->
                        </div>
                    </div>
                </div>
                
                <div class="columns">
                    <div class="column is-6">
                        <div class="box">
                            <h6 class="title is-6 has-text-info">
                                <span class="icon"><i class="fas fa-calendar-alt"></i></span>
                                Activity Period
                            </h6>
                            <div id="modalPeriodInfo">
                                <!-- Period info will be populated here -->
                            </div>
                        </div>
                    </div>
                    <div class="column is-6">
                        <div class="box">
                            <h6 class="title is-6 has-text-success">
                                <span class="icon"><i class="fas fa-calculator"></i></span>
                                Billing Breakdown
                            </h6>
                            <div id="modalBillingBreakdown">
                                <!-- Billing breakdown will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary" onclick="closeBillingModal()">
                    <span class="icon"><i class="fas fa-check"></i></span>
                    <span>Close</span>
                </button>
            </footer>
        </div>
    </div>
</div>

<script>
// Store billing data for modal display
const billingData = <?= json_encode($billData) ?>;

// Show billing details modal
function showBillingDetails(userId) {
    const client = billingData.find(c => c.user_id == userId);
    if (!client) return;
    
    // Populate client info
    const clientName = (client.first_name || client.last_name) 
        ? `${client.first_name || ''} ${client.last_name || ''}`.trim()
        : client.username;
    
    // Determine profile image HTML based on account type and profile image
    let profileImageHtml = '';
    if (client.profile_image) {
        if (client.account_type === 'manual') {
            // Manual account - use secure admin endpoint
            profileImageHtml = `
                <img src="admin/view-user-profile-image.php?user_id=${client.user_id}" 
                     alt="Profile Picture" 
                     style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;"
                     onerror="handleImageError(this);">`;
        } else {
            // Social account - use direct URL
            profileImageHtml = `
                <img src="${client.profile_image}" 
                     alt="Profile Picture" 
                     style="width: 64px; height: 64px; border-radius: 50%; object-fit: cover;"
                     onerror="handleImageError(this);">`;
        }
    } else {
        // No profile image - show placeholder
        profileImageHtml = `
            <div class="has-background-info-light is-flex is-align-items-center is-justify-content-center" 
                 style="width: 64px; height: 64px; border-radius: 50%;">
                <span class="icon is-large has-text-info">
                    <i class="fas fa-user fa-2x"></i>
                </span>
            </div>`;
    }
    
    document.getElementById('modalClientInfo').innerHTML = `
        <div class="media">
            <div class="media-left">
                <figure class="image is-64x64">
                    ${profileImageHtml}
                </figure>
            </div>
            <div class="media-content">
                <h4 class="title is-4">${clientName}</h4>
                <p class="subtitle is-6">
                    <span class="icon"><i class="fas fa-at"></i></span>
                    ${client.username}
                </p>
                <p class="is-size-6">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <a href="mailto:${client.email}" class="has-text-link">${client.email}</a>
                </p>
                <p class="is-size-6">
                    <span class="icon"><i class="fas fa-id-badge"></i></span>
                    User ID: <strong>${client.user_id}</strong>
                </p>
                <div class="tags mt-2">
                    <span class="tag is-${client.account_type === 'manual' ? 'primary' : 'info'}">
                        ${client.account_type.charAt(0).toUpperCase() + client.account_type.slice(1)} Account
                    </span>
                </div>
            </div>
        </div>
    `;
    
    // Populate manual reviews if any
    if (client.manual_review_count > 0 && client.manual_review_details) {
        document.getElementById('modalManualReviews').style.display = 'block';
        const reviews = client.manual_review_details.split('; ').filter(r => r.trim());
        document.getElementById('modalManualReviewList').innerHTML = reviews.map(review => 
            `<p class="mb-2">
                <span class="icon has-text-warning"><i class="fas fa-exclamation-circle"></i></span>
                ${review} <strong class="has-text-warning">[+$1.00]</strong>
            </p>`
        ).join('');
    } else {
        document.getElementById('modalManualReviews').style.display = 'none';
    }
    
    // Populate period info
    document.getElementById('modalPeriodInfo').innerHTML = `
        <div class="content">
            <p><strong>Start Date:</strong> ${new Date(client.first_message_date).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            })}</p>
            <p><strong>End Date:</strong> ${new Date(client.last_message_date).toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            })}</p>
            <p><strong>Duration:</strong> ${Math.ceil((new Date(client.last_message_date) - new Date(client.first_message_date)) / (1000 * 60 * 60 * 24))} days</p>
        </div>
    `;
    
    // Populate billing breakdown
    document.getElementById('modalBillingBreakdown').innerHTML = `
        <div class="content">
            <table class="table is-fullwidth">
                <tbody>
                    <tr>
                        <td><strong>Total Messages:</strong></td>
                        <td class="has-text-right">${client.total_message_count}</td>
                    </tr>
                    <tr>
                        <td><strong>Service Fee:</strong></td>
                        <td class="has-text-right has-text-info">$${parseFloat(client.standard_fee).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td><strong>Manual Reviews:</strong></td>
                        <td class="has-text-right has-text-warning">$${parseFloat(client.manual_review_fee || 0).toFixed(2)}</td>
                    </tr>
                    <tr>
                        <td><strong>SMS Messages:</strong></td>
                        <td class="has-text-right">${client.sms_count || 0}</td>
                    </tr>
                    <tr>
                        <td><strong>SMS Fee:</strong></td>
                        <td class="has-text-right has-text-link">$${parseFloat(client.sms_fee || 0).toFixed(2)}</td>
                    </tr>
                    <tr class="has-background-success-light">
                        <td><strong>Total Amount Due:</strong></td>
                        <td class="has-text-right has-text-weight-bold has-text-success">$${parseFloat(client.total_fee).toFixed(2)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;
    
    // Show modal
    document.getElementById('billingDetailsModal').classList.add('is-active');
}

// Close billing modal
function closeBillingModal() {
    document.getElementById('billingDetailsModal').classList.remove('is-active');
}

// Handle image loading errors
function handleImageError(img) {
    // Replace the failed image with a placeholder
    const placeholder = document.createElement('div');
    placeholder.className = 'has-background-info-light is-flex is-align-items-center is-justify-content-center';
    placeholder.style.cssText = 'width: 64px; height: 64px; border-radius: 50%;';
    placeholder.innerHTML = `
        <span class="icon is-large has-text-info">
            <i class="fas fa-user fa-2x"></i>
        </span>
    `;
    img.parentNode.replaceChild(placeholder, img);
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBillingModal();
    }
});

// Project configuration
const PROJECT_START_YEAR = <?= $projectStartYear ?>;
const PROJECT_START_MONTH = <?= $projectStartMonth ?>;
const CURRENT_YEAR = <?= $currentYear ?>;
const CURRENT_MONTH = <?= $currentMonth ?>;
const SELECTED_YEAR = <?= $selectedYear ?>;
const SELECTED_MONTH = <?= $selectedMonth ?>;

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
        if ((selectedYear === CURRENT_YEAR && month === SELECTED_MONTH) || 
            (selectedYear === PROJECT_START_YEAR && month === PROJECT_START_MONTH && CURRENT_YEAR > PROJECT_START_YEAR) ||
            (month === endMonth && selectedYear < CURRENT_YEAR) ||
            (selectedYear === SELECTED_YEAR && month === SELECTED_MONTH)) {
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
            <td class="center">-</td>
            <td class="number">-</td>
            <td class="number">-</td>
            <td class="number">-</td>
            <td class="number"><strong>$${totalAmount.toFixed(2)}</strong></td>
            <td class="center">-</td>
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
