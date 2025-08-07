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
                
                // Query to get all messages from the selected month with user details
                $stmt = $mysqli->prepare("
                    SELECT 
                        u.id as user_id,
                        u.username,
                        u.email,
                        u.first_name,
                        u.last_name,
                        u.account_type,
                        COUNT(m.id) as message_count,
                        MIN(m.created_at) as first_message_date,
                        MAX(m.created_at) as last_message_date,
                        GROUP_CONCAT(
                            CONCAT(
                                DATE_FORMAT(m.created_at, '%Y-%m-%d %H:%i'), 
                                ' - ', 
                                SUBSTRING(m.subject, 1, 50),
                                CASE WHEN LENGTH(m.subject) > 50 THEN '...' ELSE '' END
                            ) 
                            ORDER BY m.created_at 
                            SEPARATOR '; '
                        ) as message_details
                    FROM messages m
                    INNER JOIN users u ON m.user_id = u.id
                    WHERE m.created_at >= ? AND m.created_at <= ?
                    AND u.is_active = 1
                    GROUP BY u.id, u.username, u.email, u.first_name, u.last_name, u.account_type
                    HAVING message_count > 0
                    ORDER BY message_count DESC, u.username ASC
                ");
                
                $stmt->bind_param("ss", $firstDay, $lastDay . ' 23:59:59');
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $billData[] = $row;
                }
                
                $stmt->close();
                
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
                <a href="index.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    <span>Back to Dashboard</span>
                </a>
                <a href="messages.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                    <span>View Messages</span>
                </a>
                <a href="users.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-users-cog"></i></span>
                    <span>User Management</span>
                </a>
            </div>
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
                            <p class="title"><?= array_sum(array_column($billData, 'message_count')) ?></p>
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
                                        <?= $client['message_count'] ?>
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
                                            View Details (<?= $client['message_count'] ?> messages)
                                        </summary>
                                        <div class="content mt-2 p-2" style="background: #f5f5f5; border-radius: 4px; max-height: 200px; overflow-y: auto;">
                                            <?php 
                                            if (!empty($client['message_details'])) {
                                                $details = explode('; ', $client['message_details']);
                                                foreach ($details as $detail): 
                                            ?>
                                                <p class="is-size-7 mb-1">• <?= htmlspecialchars($detail) ?></p>
                                            <?php 
                                                endforeach;
                                            } else {
                                                echo '<p class="is-size-7 has-text-grey">No message details available</p>';
                                            }
                                            ?>
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
                <li><strong>Client Identification:</strong> Clients are identified using their user_id and matched to the users table for complete information</li>
                <li><strong>Activity Summary:</strong> Shows message count and detailed breakdown for each client</li>
                <li><strong>Export Options:</strong> Generate CSV reports or print the summary for billing purposes</li>
            </ul>
            <div class="notification is-info is-light">
                <strong>Note:</strong> Only active clients (is_active = 1) with message activity during the selected period are included in the billing report.
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
