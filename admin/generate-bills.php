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

$userModel = new User();
$messageModel = new Message();

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
    }
}

// Default to previous month for the form
$defaultMonth = date('n', strtotime('last month'));
$defaultYear = date('Y', strtotime('last month'));

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
            
            <div class="columns">
                <div class="column is-6">
                    <div class="field">
                        <label class="label">Month</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="month" required>
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
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
                                <select name="year" required>
                                    <?php for ($year = date('Y') - 2; $year <= date('Y'); $year++): ?>
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
                            <th><abbr title="User ID">ID</abbr></th>
                            <th>Client Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Account Type</th>
                            <th><abbr title="Number of Messages">Messages</abbr></th>
                            <th><abbr title="Manual Reviews">Manual Reviews</abbr></th>
                            <th><abbr title="Service Fee">Service Fee</abbr></th>
                            <th><abbr title="Manual Review Fee">Review Fee</abbr></th>
                            <th><abbr title="Total Fee">Total Fee</abbr></th>
                            <th>First Message</th>
                            <th>Last Message</th>
                            <th>Message Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billData as $client): ?>
                            <tr>
                                <td><?= htmlspecialchars($client['user_id']) ?></td>
                                <td>
                                    <strong>
                                        <?php if (!empty($client['first_name']) || !empty($client['last_name'])): ?>
                                            <?= htmlspecialchars(trim($client['first_name'] . ' ' . $client['last_name'])) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($client['username']) ?>
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <td>
                                    <a href="users.php?user_id=<?= $client['user_id'] ?>" target="_blank">
                                        <?= htmlspecialchars($client['username']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($client['email']) ?>">
                                        <?= htmlspecialchars($client['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="tag is-<?= $client['account_type'] === 'manual' ? 'primary' : 'info' ?>">
                                        <?= ucfirst(htmlspecialchars($client['account_type'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="tag is-success is-large">
                                        <?= $client['total_message_count'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($client['manual_review_count'] > 0): ?>
                                        <span class="tag is-warning is-medium" title="Manual Review Messages">
                                            <span class="icon"><i class="fas fa-dollar-sign"></i></span>
                                            <span><?= $client['manual_review_count'] ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="tag is-light">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tag is-info is-medium">
                                        $<?= number_format($client['standard_fee'], 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($client['manual_review_fee'] > 0): ?>
                                        <span class="tag is-warning is-medium">
                                            $<?= number_format($client['manual_review_fee'], 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="tag is-light">$0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="tag is-success is-large">
                                        <strong>$<?= number_format($client['total_fee'], 2) ?></strong>
                                    </span>
                                </td>
                                <td>
                                    <span class="is-size-7">
                                        <?= isset($client['first_message_date']) ? date('M j, Y', strtotime($client['first_message_date'])) : 'N/A' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="is-size-7">
                                        <?= isset($client['last_message_date']) ? date('M j, Y', strtotime($client['last_message_date'])) : 'N/A' ?>
                                    </span>
                                </td>
                                <td class="message-details">
                                    <details>
                                        <summary class="button is-small is-light">
                                            View Details (<?= $client['total_message_count'] ?> messages<?= $client['manual_review_count'] > 0 ? ', ' . $client['manual_review_count'] . ' manual review' : '' ?>)
                                        </summary>
                                        <div class="content mt-2 p-2" style="background: #f5f5f5; border-radius: 4px; max-height: 200px; overflow-y: auto;">
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
                                            
                                            <h6 class="title is-6 mb-2">All Messages:</h6>
                                            <?php 
                                            // For now, show summary since we changed the data structure
                                            ?>
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

    <!-- Information Box -->
    <div class="box">
        <h3 class="title is-5">
            <span class="icon"><i class="fas fa-info-circle"></i></span>
            How Bill Generation Works
        </h3>
        <div class="content">
            <ul>
                <li><strong>Period Selection:</strong> Choose any month and year to analyze client activity</li>
                <li><strong>Message Analysis:</strong> The system looks at all messages where clients are the recipients (user_id) during the selected period</li>
                <li><strong>Service Fee:</strong> $1.00 per qualifying communication (email conversation thread)</li>
                <li><strong>Manual Review Fee:</strong> Additional $1.00 per email marked for manual review outside standard processing hours</li>
                <li><strong>Client Identification:</strong> Clients are identified using their user_id and matched to the users table for complete information</li>
                <li><strong>Fee Calculation:</strong> Shows service fees, manual review fees, and total amount due for each client</li>
                <li><strong>Export Options:</strong> Generate CSV reports or print the summary for billing purposes</li>
            </ul>
            <div class="notification is-warning is-light">
                <strong>Manual Review Fees:</strong> Messages marked with the manual review flag incur an additional $1.00 fee as per Section 5.6 of the service contract. These are processed outside standard hours and require additional handling.
            </div>
            <div class="notification is-info is-light">
                <strong>Note:</strong> Only active clients (is_active = 1) with message activity during the selected period are included in the billing report. All fees are in USD as per contract terms.
            </div>
        </div>
    </div>
</div>

<script>
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
    window.print();
}

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

<style>
@media print {
    .button, .breadcrumb, .level .level-right {
        display: none !important;
    }
    
    .box {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    
    .message-details details {
        display: block !important;
    }
    
    .message-details summary {
        display: none !important;
    }
    
    .message-details .content {
        display: block !important;
        max-height: none !important;
        overflow: visible !important;
    }
}

.message-details details summary {
    cursor: pointer;
}

.message-details details[open] summary {
    margin-bottom: 0.5rem;
}
</style>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
