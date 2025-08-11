<?php
// admin/email-logs.php - Admin interface for viewing email logs
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
$emailType = $_GET['email_type'] ?? '';
$status = $_GET['status'] ?? '';
$recipient = $_GET['recipient'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build WHERE clause
$whereConditions = [];
$params = [];
$types = "";

if (!empty($emailType)) {
    $whereConditions[] = "el.email_type = ?";
    $params[] = $emailType;
    $types .= "s";
}

if (!empty($status)) {
    $whereConditions[] = "el.status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($recipient)) {
    $whereConditions[] = "(el.recipient_email LIKE ? OR el.recipient_name LIKE ?)";
    $searchTerm = "%{$recipient}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(el.sent_at) >= ?";
    $params[] = $dateFrom;
    $types .= "s";
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(el.sent_at) <= ?";
    $params[] = $dateTo;
    $types .= "s";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM email_logs el 
    LEFT JOIN users u ON el.recipient_user_id = u.id 
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
        if ($countResult) {
            $totalRecords = $countResult->fetch_assoc()['total'];
        } else {
            throw new Exception("Failed to execute count query: " . $mysqli->error);
        }
    }
} catch (Exception $e) {
    error_log("Email logs count query error: " . $e->getMessage());
    $totalRecords = 0;
}

$totalPages = ceil($totalRecords / $perPage);

// Get email logs
$query = "
    SELECT 
        el.id,
        el.recipient_email,
        el.recipient_name,
        el.email_type,
        el.subject,
        el.body_content,
        el.html_content,
        el.status,
        el.error_message,
        el.sent_at,
        el.delivery_attempts,
        u.username as recipient_username,
        u.first_name,
        u.last_name,
        sender.username as sender_username
    FROM email_logs el 
    LEFT JOIN users u ON el.recipient_user_id = u.id 
    LEFT JOIN users sender ON el.sender_user_id = sender.id
    {$whereClause}
    ORDER BY el.sent_at DESC 
    LIMIT ? OFFSET ?
";

// Add pagination parameters
$params[] = $perPage;
$params[] = $offset;
$types .= "ii";

try {
    $stmt = $mysqli->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $emailLogs = [];
        while ($row = $result->fetch_assoc()) {
            $emailLogs[] = $row;
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare email logs query: " . $mysqli->error);
    }
} catch (Exception $e) {
    error_log("Email logs query error: " . $e->getMessage());
    $emailLogs = [];
}

// Get email type statistics
$statsQuery = "
    SELECT 
        email_type,
        COUNT(*) as count,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count
    FROM email_logs 
    GROUP BY email_type 
    ORDER BY count DESC
";

try {
    $statsResult = $mysqli->query($statsQuery);
    $emailStats = [];
    if ($statsResult) {
        while ($row = $statsResult->fetch_assoc()) {
            $emailStats[] = $row;
        }
    } else {
        throw new Exception("Failed to execute stats query: " . $mysqli->error);
    }
} catch (Exception $e) {
    error_log("Email stats query error: " . $e->getMessage());
    $emailStats = [];
}

$pageTitle = 'Email Logs | Aetia Admin';
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
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-chart-line"></i></span><span>Email Logs</span></a></li>
            <li><a href="sms-logs.php"><span class="icon is-small"><i class="fas fa-sms"></i></span><span>SMS Logs</span></a></li>
            <li><a href="contact-form.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Contact Forms</span></a></li>
            <li><a href="contracts.php"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Contracts</span></a></li>
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
        </ul>
    </nav>
    <h1 class="title has-text-light">Email Logs</h1>
    <p class="subtitle has-text-light">View and filter all sent emails</p>
    <!-- Statistics Section -->
    <div class="stats-section">
        <h2 class="subtitle has-text-light">Email Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($totalRecords) ?></div>
                <div class="stat-label">Total Emails</div>
            </div>
            <?php foreach ($emailStats as $stat): ?>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stat['count']) ?></div>
                <div class="stat-label"><?= ucfirst(str_replace('_', ' ', $stat['email_type'])) ?></div>
                <div style="font-size: 12px; margin-top: 5px;">
                    <span style="color: #48c78e;"><?= $stat['sent_count'] ?> sent</span> | 
                    <span style="color: #ff3b30;"><?= $stat['failed_count'] ?> failed</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Filters Section -->
    <div class="filters-section">
        <h3 class="subtitle has-text-light">Filter Emails</h3>
        <form method="GET" class="filter-form">
            <div class="field">
                <label class="label has-text-light">Email Type</label>
                <div class="control">
                    <select class="input" name="email_type">
                        <option value="">All Types</option>
                        <option value="welcome" <?= $emailType === 'welcome' ? 'selected' : '' ?>>Welcome</option>
                        <option value="signup_notification" <?= $emailType === 'signup_notification' ? 'selected' : '' ?>>Signup Notification</option>
                        <option value="password_reset" <?= $emailType === 'password_reset' ? 'selected' : '' ?>>Password Reset</option>
                        <option value="admin_notification" <?= $emailType === 'admin_notification' ? 'selected' : '' ?>>Admin Notification</option>
                        <option value="contact_form" <?= $emailType === 'contact_form' ? 'selected' : '' ?>>Contact Form</option>
                        <option value="general" <?= $emailType === 'general' ? 'selected' : '' ?>>General</option>
                    </select>
                </div>
            </div>
            
            <div class="field">
                <label class="label has-text-light">Status</label>
                <div class="control">
                    <select class="input" name="status">
                        <option value="">All Status</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="queued" <?= $status === 'queued' ? 'selected' : '' ?>>Queued</option>
                    </select>
                </div>
            </div>
            
            <div class="field">
                <label class="label has-text-light">Recipient</label>
                <div class="control">
                    <input class="input" type="text" name="recipient" value="<?= htmlspecialchars($recipient) ?>" placeholder="Email or name">
                </div>
            </div>
            
            <div class="field">
                <label class="label has-text-light">Date From</label>
                <div class="control">
                    <input class="input" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
            </div>
            
            <div class="field">
                <label class="label has-text-light">Date To</label>
                <div class="control">
                    <input class="input" type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
            </div>
            
            <div class="field">
                <div class="control">
                    <button type="submit" class="button is-primary">Filter</button>
                    <a href="email-logs.php" class="button is-light">Clear</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Email Logs Table -->
    <div class="email-log-table">
        <table>
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Recipient</th>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($emailLogs)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <em>No email logs found matching your criteria.</em>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($emailLogs as $log): ?>
                <tr>
                    <td><?= convertToUserTimezone($log['sent_at']) ?></td>
                    <td>
                        <div>
                            <strong><?= htmlspecialchars($log['recipient_email']) ?></strong>
                            <?php if ($log['recipient_name']): ?>
                            <br><small><?= htmlspecialchars($log['recipient_name']) ?></small>
                            <?php endif; ?>
                            <?php if ($log['recipient_username']): ?>
                            <br><small>@<?= htmlspecialchars($log['recipient_username']) ?></small>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="email-type-badge"><?= ucfirst(str_replace('_', ' ', $log['email_type'])) ?></span>
                    </td>
                    <td>
                        <div class="email-content-preview"><?= htmlspecialchars($log['subject']) ?></div>
                    </td>
                    <td>
                        <span class="status-<?= $log['status'] ?>">
                            <?= ucfirst($log['status']) ?>
                        </span>
                        <?php if ($log['delivery_attempts'] > 1): ?>
                        <br><small><?= $log['delivery_attempts'] ?> attempts</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="button is-small is-info" onclick="viewEmail(<?= $log['id'] ?>)">View</button>
                        <?php if ($log['status'] === 'failed'): ?>
                        <button class="button is-small is-warning" onclick="viewError(<?= $log['id'] ?>)">Error</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php 
        // Create base query without page parameter
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseQuery = http_build_query($queryParams);
        $baseQuery = $baseQuery ? '&' . $baseQuery : '';
        ?>
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $baseQuery ?>">&laquo; Previous</a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
        <?php if ($i == $page): ?>
        <span class="current"><?= $i ?></span>
        <?php else: ?>
        <a href="?page=<?= $i ?><?= $baseQuery ?>"><?= $i ?></a>
        <?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?><?= $baseQuery ?>">Next &raquo;</a>
        <?php endif; ?>
    </div>
    <div style="text-align: center; margin-top: 10px; color: #ddd;">
        Showing <?= count($emailLogs) ?> of <?= number_format($totalRecords) ?> emails
        (Page <?= $page ?> of <?= $totalPages ?>)
    </div>
    <?php endif; ?>
</div>

<!-- Email Content Modal -->
<div id="emailModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <div id="emailContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<script>
// Email logs stored in JavaScript for modal viewing
const emailLogs = <?= json_encode(array_map(function($log) {
    $log['sent_at_formatted'] = convertToUserTimezone($log['sent_at']);
    return $log;
}, $emailLogs)) ?>;

function viewEmail(logId) {
    const log = emailLogs.find(l => l.id == logId);
    if (!log) return;
    const modal = document.getElementById('emailModal');
    const content = document.getElementById('emailContent');
    content.innerHTML = `
        <h2 style="color: #48c78e; margin-bottom: 20px;">Email Details</h2>
        <div class="email-meta">
            <p><strong>Date:</strong> ${log.sent_at_formatted}</p>
            <p><strong>To:</strong> ${log.recipient_email.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
            <p><strong>Type:</strong> ${log.email_type.replace(/_/g, ' ').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
            <p><strong>Subject:</strong> ${log.subject.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
            <p><strong>Status:</strong> <span class="status-${log.status}">${log.status}</span></p>
            ${log.delivery_attempts > 1 ? `<p><strong>Delivery Attempts:</strong> ${log.delivery_attempts}</p>` : ''}
        </div>
        <h3 style="color: #48c78e; margin-bottom: 15px;">Email Content</h3>
        <div class="email-content-display">
            ${log.html_content ? log.html_content : log.body_content.replace(/\n/g, '<br>').replace(/</g, '&lt;').replace(/>/g, '&gt;')}
        </div>
    `;
    modal.style.display = 'block';
}

function viewError(logId) {
    const log = emailLogs.find(l => l.id == logId);
    if (!log) return;
    const modal = document.getElementById('emailModal');
    const content = document.getElementById('emailContent');
    content.innerHTML = `
        <h2 style="color: #ff3b30; margin-bottom: 20px;">Email Error Details</h2>
        <div class="email-meta">
            <p><strong>Date:</strong> ${log.sent_at_formatted}</p>
            <p><strong>To:</strong> ${log.recipient_email.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
            <p><strong>Subject:</strong> ${log.subject.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</p>
            <p><strong>Status:</strong> <span class="status-failed">Failed</span></p>
            <p><strong>Delivery Attempts:</strong> ${log.delivery_attempts}</p>
        </div>
        <h3 style="color: #ff3b30; margin-bottom: 15px;">Error Message</h3>
        <div style="background: rgba(255, 59, 48, 0.15); color: #fff; padding: 20px; border-radius: 8px; border-left: 4px solid #ff3b30; font-family: 'Courier New', monospace;">
            <code style="color: #fff; font-size: 14px; line-height: 1.4;">${(log.error_message || 'No error message available').replace(/</g, '&lt;').replace(/>/g, '&gt;')}</code>
        </div>
    `;
    modal.style.display = 'block';
}

// Modal close functionality
document.querySelector('.close').onclick = function() {
    document.getElementById('emailModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('emailModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
