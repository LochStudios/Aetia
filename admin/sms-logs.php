<?php
// admin/sms-logs.php - Admin interface for viewing SMS logs
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

// Include user utilities
require_once __DIR__ . '/../models/User.php';
$userModel = new User();
// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

// Include database connection
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$mysqli = $database->getConnection();

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filter settings
$purpose = $_GET['purpose'] ?? '';
$status = $_GET['status'] ?? '';
$recipient = $_GET['recipient'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$userId = $_GET['user_id'] ?? '';

// Build WHERE clause
$whereConditions = [];
$params = [];
$types = "";

if (!empty($purpose)) {
    $whereConditions[] = "sl.purpose = ?";
    $params[] = $purpose;
    $types .= "s";
}

if (!empty($status)) {
    if ($status === 'success') {
        $whereConditions[] = "sl.success = 1";
    } elseif ($status === 'failed') {
        $whereConditions[] = "sl.success = 0";
    }
}

if (!empty($recipient)) {
    $whereConditions[] = "sl.to_number LIKE ?";
    $searchTerm = "%{$recipient}%";
    $params[] = $searchTerm;
    $types .= "s";
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(sl.sent_at) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(sl.sent_at) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

if (!empty($userId)) {
    $whereConditions[] = "sl.user_id = ?";
    $params[] = (int)$userId;
    $types .= "i";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM sms_logs sl 
    LEFT JOIN users u ON sl.user_id = u.id 
    {$whereClause}
";

try {
    if (!empty($params)) {
        $countStmt = $mysqli->prepare($countQuery);
        if ($countStmt) {
            $countStmt->bind_param($types, ...$params);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $totalRecords = $countResult->fetch_assoc()['total'];
            $countStmt->close();
        } else {
            throw new Exception("Failed to prepare count query: " . $mysqli->error);
        }
    } else {
        $countResult = $mysqli->query($countQuery);
        $totalRecords = $countResult->fetch_assoc()['total'];
    }
} catch (Exception $e) {
    error_log("SMS logs count query error: " . $e->getMessage());
    $totalRecords = 0;
}

$totalPages = ceil($totalRecords / $perPage);

// Get SMS logs
$query = "
    SELECT 
        sl.id,
        sl.user_id,
        sl.to_number,
        sl.message_content,
        sl.provider,
        sl.success,
        sl.response_message,
        sl.provider_message_id,
        sl.purpose,
        sl.client_ip,
        sl.sent_at,
        u.username,
        u.email as user_email
    FROM sms_logs sl 
    LEFT JOIN users u ON sl.user_id = u.id 
    {$whereClause}
    ORDER BY sl.sent_at DESC 
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

try {
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $smsLogs = [];
        while ($row = $result->fetch_assoc()) {
            $smsLogs[] = $row;
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare SMS logs query: " . $mysqli->error);
    }
} catch (Exception $e) {
    error_log("SMS logs query error: " . $e->getMessage());
    $smsLogs = [];
}

// Get SMS purpose statistics
$statsQuery = "
    SELECT 
        purpose,
        COUNT(*) as count,
        SUM(success) as success_count,
        COUNT(*) - SUM(success) as failed_count
    FROM sms_logs 
    GROUP BY purpose 
    ORDER BY count DESC
";

try {
    $statsResult = $mysqli->query($statsQuery);
    $smsStats = [];
    if ($statsResult) {
        while ($row = $statsResult->fetch_assoc()) {
            $smsStats[] = $row;
        }
    } else {
        throw new Exception("Failed to get SMS stats: " . $mysqli->error);
    }
} catch (Exception $e) {
    error_log("SMS stats query error: " . $e->getMessage());
    $smsStats = [];
}

// Get overall statistics
$overallStats = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
    'today' => 0,
    'week' => 0
];

try {
    $result = $mysqli->query("SELECT COUNT(*) as total FROM sms_logs");
    $overallStats['total'] = $result->fetch_assoc()['total'];
    
    $result = $mysqli->query("SELECT COUNT(*) as success FROM sms_logs WHERE success = 1");
    $overallStats['success'] = $result->fetch_assoc()['success'];
    
    $result = $mysqli->query("SELECT COUNT(*) as failed FROM sms_logs WHERE success = 0");
    $overallStats['failed'] = $result->fetch_assoc()['failed'];
    
    $result = $mysqli->query("SELECT COUNT(*) as today FROM sms_logs WHERE DATE(sent_at) = CURDATE()");
    $overallStats['today'] = $result->fetch_assoc()['today'];
    
    $result = $mysqli->query("SELECT COUNT(*) as week FROM sms_logs WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $overallStats['week'] = $result->fetch_assoc()['week'];
} catch (Exception $e) {
    error_log("SMS overall stats error: " . $e->getMessage());
}

$pageTitle = 'SMS Logs | Aetia Admin';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php">
                <span class="icon is-small"><i class="fas fa-home"></i></span>
                <span>Home</span>
            </a></li>
            <li><a href="index.php">
                <span class="icon is-small"><i class="fas fa-shield-alt"></i></span>
                <span>Admin Dashboard</span>
            </a></li>
            <li><a href="email-logs.php">
                <span class="icon is-small"><i class="fas fa-chart-line"></i></span>
                <span>Email Logs</span>
            </a></li>
            <li class="is-active"><a href="#" aria-current="page">
                <span class="icon is-small"><i class="fas fa-sms"></i></span>
                <span>SMS Logs</span>
            </a></li>
        </ul>
    </nav>
    
    <h1 class="title has-text-light">SMS Logs</h1>
    <p class="subtitle has-text-light">View and filter all sent SMS messages</p>

    <!-- Statistics Cards -->
    <div class="columns is-multiline mb-5">
        <div class="column is-3">
            <div class="box has-background-info-dark">
                <div class="level">
                    <div class="level-left">
                        <div>
                            <p class="heading has-text-info-light">Total SMS</p>
                            <p class="title is-4 has-text-white"><?= number_format($overallStats['total']) ?></p>
                        </div>
                    </div>
                    <div class="level-right">
                        <span class="icon is-large has-text-info-light">
                            <i class="fas fa-sms fa-2x"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="column is-3">
            <div class="box has-background-success-dark">
                <div class="level">
                    <div class="level-left">
                        <div>
                            <p class="heading has-text-success-light">Successful</p>
                            <p class="title is-4 has-text-white"><?= number_format($overallStats['success']) ?></p>
                        </div>
                    </div>
                    <div class="level-right">
                        <span class="icon is-large has-text-success-light">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="column is-3">
            <div class="box has-background-danger-dark">
                <div class="level">
                    <div class="level-left">
                        <div>
                            <p class="heading has-text-danger-light">Failed</p>
                            <p class="title is-4 has-text-white"><?= number_format($overallStats['failed']) ?></p>
                        </div>
                    </div>
                    <div class="level-right">
                        <span class="icon is-large has-text-danger-light">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="column is-3">
            <div class="box has-background-warning-dark">
                <div class="level">
                    <div class="level-left">
                        <div>
                            <p class="heading has-text-warning-light">Today</p>
                            <p class="title is-4 has-text-white"><?= number_format($overallStats['today']) ?></p>
                        </div>
                    </div>
                    <div class="level-right">
                        <span class="icon is-large has-text-warning-light">
                            <i class="fas fa-calendar-day fa-2x"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="box">
        <h3 class="title is-5">Filters</h3>
        <form method="GET" class="columns is-multiline">
            <div class="column is-2">
                <div class="field">
                    <label class="label">Purpose</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="purpose">
                                <option value="">All Purposes</option>
                                <option value="verification" <?= $purpose === 'verification' ? 'selected' : '' ?>>Verification</option>
                                <option value="notification" <?= $purpose === 'notification' ? 'selected' : '' ?>>Notification</option>
                                <option value="test" <?= $purpose === 'test' ? 'selected' : '' ?>>Test</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="column is-2">
                <div class="field">
                    <label class="label">Status</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="status">
                                <option value="">All Status</option>
                                <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                                <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="column is-2">
                <div class="field">
                    <label class="label">Phone Number</label>
                    <div class="control">
                        <input class="input" type="text" name="recipient" value="<?= htmlspecialchars($recipient) ?>" placeholder="Search phone...">
                    </div>
                </div>
            </div>
            
            <div class="column is-2">
                <div class="field">
                    <label class="label">User ID</label>
                    <div class="control">
                        <input class="input" type="number" name="user_id" value="<?= htmlspecialchars($userId) ?>" placeholder="User ID">
                    </div>
                </div>
            </div>
            
            <div class="column is-2">
                <div class="field">
                    <label class="label">Date From</label>
                    <div class="control">
                        <input class="input" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                </div>
            </div>
            
            <div class="column is-2">
                <div class="field">
                    <label class="label">Date To</label>
                    <div class="control">
                        <input class="input" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                </div>
            </div>
            
            <div class="column is-12">
                <div class="field is-grouped">
                    <div class="control">
                        <button type="submit" class="button is-primary">
                            <span class="icon"><i class="fas fa-search"></i></span>
                            <span>Filter</span>
                        </button>
                    </div>
                    <div class="control">
                        <a href="sms-logs.php" class="button is-light">
                            <span class="icon"><i class="fas fa-times"></i></span>
                            <span>Clear</span>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- SMS Stats by Purpose -->
    <?php if (!empty($smsStats)): ?>
    <div class="box">
        <h3 class="title is-5">Statistics by Purpose</h3>
        <div class="table-container">
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th>Total</th>
                        <th>Success</th>
                        <th>Failed</th>
                        <th>Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($smsStats as $stat): ?>
                    <tr>
                        <td>
                            <span class="tag is-info">
                                <?= htmlspecialchars(ucfirst($stat['purpose'])) ?>
                            </span>
                        </td>
                        <td><?= number_format($stat['count']) ?></td>
                        <td>
                            <span class="has-text-success">
                                <?= number_format($stat['success_count']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="has-text-danger">
                                <?= number_format($stat['failed_count']) ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            $successRate = $stat['count'] > 0 ? ($stat['success_count'] / $stat['count']) * 100 : 0;
                            $rateClass = $successRate >= 90 ? 'has-text-success' : ($successRate >= 70 ? 'has-text-warning' : 'has-text-danger');
                            ?>
                            <span class="<?= $rateClass ?>">
                                <?= number_format($successRate, 1) ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- SMS Logs Table -->
    <div class="box">
        <div class="level">
            <div class="level-left">
                <h3 class="title is-5">SMS Logs (<?= number_format($totalRecords) ?> total)</h3>
            </div>
            <div class="level-right">
                <?php if ($totalPages > 1): ?>
                <nav class="pagination is-small" role="navigation" aria-label="pagination">
                    <?php if ($page > 1): ?>
                    <a class="pagination-previous" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a class="pagination-next" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">Next</a>
                    <?php endif; ?>
                    
                    <ul class="pagination-list">
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li>
                            <a class="pagination-link <?= $i === $page ? 'is-current' : '' ?>" 
                               href="?page=<?= $i ?>&<?= http_build_query(array_filter($_GET, function($k) { return $k !== 'page'; }, ARRAY_FILTER_USE_KEY)) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($smsLogs)): ?>
        <div class="notification is-info is-light">
            <span class="icon-text">
                <span class="icon"><i class="fas fa-info-circle"></i></span>
                <span>No SMS logs found matching your criteria.</span>
            </span>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>User</th>
                        <th>To Number</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Message</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($smsLogs as $log): ?>
                    <tr>
                        <td>
                            <span class="has-text-weight-semibold">
                                <?= formatDateForUser($log['sent_at'], 'M j, Y') ?>
                            </span><br>
                            <small class="has-text-white">
                                <?= formatDateForUser($log['sent_at'], 'g:i A') ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($log['username']): ?>
                            <span class="has-text-weight-semibold"><?= htmlspecialchars($log['username']) ?></span><br>
                            <small class="has-text-white">ID: <?= $log['user_id'] ?></small>
                            <?php else: ?>
                            <span class="has-text-white">No user</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="has-text-weight-semibold">
                                <?= htmlspecialchars($log['to_number']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="tag is-info">
                                <?= htmlspecialchars(ucfirst($log['purpose'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['success']): ?>
                            <span class="tag is-success">
                                <span class="icon"><i class="fas fa-check"></i></span>
                                <span>Success</span>
                            </span>
                            <?php else: ?>
                            <span class="tag is-danger">
                                <span class="icon"><i class="fas fa-times"></i></span>
                                <span>Failed</span>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="tag is-light">
                                <?= htmlspecialchars(ucfirst($log['provider'])) ?>
                            </span>
                        </td>
                        <td>
                            <div class="content">
                                <?php 
                                $messagePreview = strlen($log['message_content']) > 50 ? 
                                    substr($log['message_content'], 0, 50) . '...' : 
                                    $log['message_content']; 
                                ?>
                                <span title="<?= htmlspecialchars($log['message_content']) ?>">
                                    <?= htmlspecialchars($messagePreview) ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="buttons are-small">
                                <button class="button is-info is-outlined" onclick="viewSmsDetails(<?= $log['id'] ?>)">
                                    <span class="icon"><i class="fas fa-eye"></i></span>
                                    <span>Details</span>
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

<!-- SMS Details Modal -->
<div id="smsModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width: 90%; max-width: 800px;">
        <header class="modal-card-head">
            <p class="modal-card-title">SMS Details</p>
            <button class="delete" aria-label="close" onclick="closeSmsModal()"></button>
        </header>
        <section class="modal-card-body" id="smsModalContent">
            <!-- Content will be loaded here -->
        </section>
        <footer class="modal-card-foot">
            <button class="button" onclick="closeSmsModal()">Close</button>
        </footer>
    </div>
</div>

<script>
// SMS logs stored in JavaScript for modal viewing
const smsLogs = <?= json_encode($smsLogs) ?>;

function viewSmsDetails(logId) {
    const log = smsLogs.find(l => l.id == logId);
    if (!log) return;
    
    const modalContent = document.getElementById('smsModalContent');
    const statusClass = log.success == 1 ? 'success' : 'danger';
    const statusIcon = log.success == 1 ? 'check' : 'times';
    const statusText = log.success == 1 ? 'Success' : 'Failed';
    
    modalContent.innerHTML = `
        <div class="content">
            <div class="columns">
                <div class="column is-6">
                    <div class="field">
                        <label class="label">Date/Time</label>
                        <div class="control">
                            <input class="input" type="text" value="${log.sent_at}" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">User</label>
                        <div class="control">
                            <input class="input" type="text" value="${log.username || 'No user'} (ID: ${log.user_id || 'N/A'})" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">To Number</label>
                        <div class="control">
                            <input class="input" type="text" value="${log.to_number}" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Purpose</label>
                        <div class="control">
                            <span class="tag is-info">${log.purpose.charAt(0).toUpperCase() + log.purpose.slice(1)}</span>
                        </div>
                    </div>
                </div>
                
                <div class="column is-6">
                    <div class="field">
                        <label class="label">Status</label>
                        <div class="control">
                            <span class="tag is-${statusClass}">
                                <span class="icon"><i class="fas fa-${statusIcon}"></i></span>
                                <span>${statusText}</span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Provider</label>
                        <div class="control">
                            <input class="input" type="text" value="${log.provider.charAt(0).toUpperCase() + log.provider.slice(1)}" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Provider Message ID</label>
                        <div class="control">
                            <input class="input" type="text" value="${log.provider_message_id || 'N/A'}" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Client IP</label>
                        <div class="control">
                            <input class="input" type="text" value="${log.client_ip || 'N/A'}" readonly>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="field">
                <label class="label">Message Content</label>
                <div class="control">
                    <textarea class="textarea" rows="3" readonly>${log.message_content}</textarea>
                </div>
            </div>
            
            <div class="field">
                <label class="label">Response Message</label>
                <div class="control">
                    <textarea class="textarea" rows="2" readonly>${log.response_message || 'N/A'}</textarea>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('smsModal').classList.add('is-active');
}

function closeSmsModal() {
    document.getElementById('smsModal').classList.remove('is-active');
}

// Modal close functionality
document.querySelector('.modal-background').onclick = function() {
    closeSmsModal();
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-background')) {
        closeSmsModal();
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSmsModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
