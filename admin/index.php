<?php
// admin/index.php - Main admin dashboard for Aetia Talent Agency
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
require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../config/database.php';

$userModel = new User();
$messageModel = new Message();
$contactModel = new Contact();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

// Get dashboard statistics
$database = new Database();
$mysqli = $database->getConnection();

// User statistics
$userStats = [
    'total' => 0,
    'active' => 0,
    'pending' => 0,
    'unverified' => 0,
    'recent_signups' => 0
];

$result = $mysqli->query("SELECT COUNT(*) as total FROM users");
$userStats['total'] = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
$userStats['active'] = $result->fetch_assoc()['active'];

$result = $mysqli->query("SELECT COUNT(*) as pending FROM users WHERE approval_status = 'pending'");
$userStats['pending'] = $result->fetch_assoc()['pending'];

$result = $mysqli->query("SELECT COUNT(*) as unverified FROM users WHERE is_verified = 0");
$userStats['unverified'] = $result->fetch_assoc()['unverified'];

$result = $mysqli->query("SELECT COUNT(*) as recent FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$userStats['recent_signups'] = $result->fetch_assoc()['recent'];

// Message statistics
$messageStats = [
    'total' => 0,
    'unread' => 0,
    'archived' => 0,
    'recent' => 0
];

$result = $mysqli->query("SELECT COUNT(*) as total FROM messages");
$messageStats['total'] = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as unread FROM messages WHERE status = 'open'");
$messageStats['unread'] = $result->fetch_assoc()['unread'];

$result = $mysqli->query("SELECT COUNT(*) as archived FROM messages WHERE status = 'closed'");
$messageStats['archived'] = $result->fetch_assoc()['archived'];

$result = $mysqli->query("SELECT COUNT(*) as recent FROM messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$messageStats['recent'] = $result->fetch_assoc()['recent'];

// Contact form statistics
$contactStats = [
    'total' => 0,
    'new' => 0,
    'responded' => 0,
    'recent' => 0
];

$result = $mysqli->query("SELECT COUNT(*) as total FROM contact_submissions");
$contactStats['total'] = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as new FROM contact_submissions WHERE status = 'new'");
$contactStats['new'] = $result->fetch_assoc()['new'];

$result = $mysqli->query("SELECT COUNT(*) as responded FROM contact_submissions WHERE status = 'responded'");
$contactStats['responded'] = $result->fetch_assoc()['responded'];

$result = $mysqli->query("SELECT COUNT(*) as recent FROM contact_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$contactStats['recent'] = $result->fetch_assoc()['recent'];

// Email statistics
$emailStats = [
    'total' => 0,
    'sent_today' => 0,
    'failed' => 0,
    'sent_week' => 0
];

$result = $mysqli->query("SELECT COUNT(*) as total FROM email_logs");
$emailStats['total'] = $result->fetch_assoc()['total'];

$result = $mysqli->query("SELECT COUNT(*) as sent_today FROM email_logs WHERE DATE(sent_at) = CURDATE() AND status = 'sent'");
$emailStats['sent_today'] = $result->fetch_assoc()['sent_today'];

$result = $mysqli->query("SELECT COUNT(*) as failed FROM email_logs WHERE status = 'failed'");
$emailStats['failed'] = $result->fetch_assoc()['failed'];

$result = $mysqli->query("SELECT COUNT(*) as sent_week FROM email_logs WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'sent'");
$emailStats['sent_week'] = $result->fetch_assoc()['sent_week'];

// Recent activity
$recentUsers = [];
$result = $mysqli->query("SELECT id, username, email, created_at, approval_status FROM users ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recentUsers[] = $row;
}

$recentMessages = [];
$result = $mysqli->query("
    SELECT m.id, m.subject, m.created_at, m.status, u.username 
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
while ($row = $result->fetch_assoc()) {
    $recentMessages[] = $row;
}

$recentContacts = [];
$result = $mysqli->query("SELECT id, name, subject, created_at, status FROM contact_submissions ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recentContacts[] = $row;
}

$pageTitle = 'Admin Dashboard | Aetia Talent Agency';
ob_start();
?>

<div class="dashboard-container">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin Dashboard</span></a></li>
        </ul>
    </nav>
    
    <h1 class="title has-text-light">Admin Dashboard</h1>
    <p class="subtitle has-text-light">Welcome to the Aetia Talent Agency administrative control panel</p>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="users.php" class="action-card">
            <div class="action-icon"><i class="fas fa-users-cog"></i></div>
            <div class="action-title">User Management</div>
            <div class="action-desc">Manage user accounts and approvals</div>
        </a>
        <a href="messages.php" class="action-card">
            <div class="action-icon"><i class="fas fa-envelope-open-text"></i></div>
            <div class="action-title">Messages</div>
            <div class="action-desc">View and respond to user messages</div>
        </a>
        <a href="send-emails.php" class="action-card">
            <div class="action-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="action-title">Send Emails</div>
            <div class="action-desc">Send custom emails and newsletters</div>
        </a>
        <a href="contact-form.php" class="action-card">
            <div class="action-icon"><i class="fas fa-envelope"></i></div>
            <div class="action-title">Contact Forms</div>
            <div class="action-desc">Review contact form submissions</div>
        </a>
        <a href="email-logs.php" class="action-card">
            <div class="action-icon"><i class="fas fa-chart-line"></i></div>
            <div class="action-title">Email Logs</div>
            <div class="action-desc">Monitor email delivery and statistics</div>
        </a>
        <a href="archived-messages.php" class="action-card">
            <div class="action-icon"><i class="fas fa-archive"></i></div>
            <div class="action-title">Archive</div>
            <div class="action-desc">View archived messages and data</div>
        </a>
    </div>
    
    <!-- Statistics Overview -->
    <h2 class="subtitle has-text-light">Platform Statistics</h2>
    <div class="stats-grid">
        <!-- User Statistics -->
        <div class="stat-card">
            <div class="stat-number"><?= number_format($userStats['total']) ?></div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card success">
            <div class="stat-number"><?= number_format($userStats['active']) ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card <?= $userStats['pending'] > 0 ? 'warning' : '' ?>">
            <div class="stat-number"><?= number_format($userStats['pending']) ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card <?= $userStats['unverified'] > 5 ? 'warning' : '' ?>">
            <div class="stat-number"><?= number_format($userStats['unverified']) ?></div>
            <div class="stat-label">Unverified Users</div>
        </div>
        
        <!-- Message Statistics -->
        <div class="stat-card">
            <div class="stat-number"><?= number_format($messageStats['total']) ?></div>
            <div class="stat-label">Total Messages</div>
        </div>
        <div class="stat-card <?= $messageStats['unread'] > 0 ? 'urgent' : '' ?>">
            <div class="stat-number"><?= number_format($messageStats['unread']) ?></div>
            <div class="stat-label">Open Messages</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($messageStats['recent']) ?></div>
            <div class="stat-label">Messages Today</div>
        </div>
        
        <!-- Contact Statistics -->
        <div class="stat-card <?= $contactStats['new'] > 0 ? 'urgent' : '' ?>">
            <div class="stat-number"><?= number_format($contactStats['new']) ?></div>
            <div class="stat-label">New Contact Forms</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($contactStats['recent']) ?></div>
            <div class="stat-label">Contacts Today</div>
        </div>
        
        <!-- Email Statistics -->
        <div class="stat-card">
            <div class="stat-number"><?= number_format($emailStats['sent_today']) ?></div>
            <div class="stat-label">Emails Sent Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= number_format($emailStats['sent_week']) ?></div>
            <div class="stat-label">Emails This Week</div>
        </div>
        <div class="stat-card <?= $emailStats['failed'] > 10 ? 'warning' : '' ?>">
            <div class="stat-number"><?= number_format($emailStats['failed']) ?></div>
            <div class="stat-label">Failed Emails</div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="recent-activity">
        <!-- Recent Users -->
        <div class="activity-card">
            <div class="activity-header">
                <i class="fas fa-user-plus"></i>
                Recent User Registrations
            </div>
            <?php if (empty($recentUsers)): ?>
                <div class="activity-item">No recent user registrations</div>
            <?php else: ?>
                <?php foreach ($recentUsers as $user): ?>
                    <div class="activity-item">
                        <strong><?= htmlspecialchars($user['username']) ?></strong>
                        <span class="status-badge status-<?= $user['approval_status'] ?>"><?= ucfirst($user['approval_status']) ?></span>
                        <div class="activity-meta">
                            <?= htmlspecialchars($user['email']) ?> • <?= date('M j, Y g:i A', strtotime($user['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="users.php" class="button is-small is-info">View All Users</a>
            </div>
        </div>
        
        <!-- Recent Messages -->
        <div class="activity-card">
            <div class="activity-header">
                <i class="fas fa-envelope"></i>
                Recent Messages
            </div>
            <?php if (empty($recentMessages)): ?>
                <div class="activity-item">No recent messages</div>
            <?php else: ?>
                <?php foreach ($recentMessages as $message): ?>
                    <div class="activity-item">
                        <strong><?= htmlspecialchars($message['subject']) ?></strong>
                        <span class="status-badge status-<?= $message['status'] ?>"><?= ucfirst($message['status']) ?></span>
                        <div class="activity-meta">
                            From: <?= htmlspecialchars($message['username']) ?> • <?= date('M j, Y g:i A', strtotime($message['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="messages.php" class="button is-small is-info">View All Messages</a>
            </div>
        </div>
        
        <!-- Recent Contacts -->
        <div class="activity-card">
            <div class="activity-header">
                <i class="fas fa-address-book"></i>
                Recent Contact Forms
            </div>
            <?php if (empty($recentContacts)): ?>
                <div class="activity-item">No recent contact submissions</div>
            <?php else: ?>
                <?php foreach ($recentContacts as $contact): ?>
                    <div class="activity-item">
                        <strong><?= htmlspecialchars($contact['subject'] ?: 'No Subject') ?></strong>
                        <span class="status-badge status-<?= $contact['status'] ?>"><?= ucfirst($contact['status']) ?></span>
                        <div class="activity-meta">
                            From: <?= htmlspecialchars($contact['name']) ?> • <?= date('M j, Y g:i A', strtotime($contact['created_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <div style="text-align: center; margin-top: 15px;">
                <a href="contact-form.php" class="button is-small is-info">View All Contacts</a>
            </div>
        </div>
    </div>
    
    <!-- System Information -->
    <div class="activity-card" style="margin-top: 20px;">
        <div class="activity-header">
            <i class="fas fa-info-circle"></i>
            System Information
        </div>
        <div class="activity-item">
            <strong>Last Updated:</strong> <?= date('F j, Y g:i A') ?>
        </div>
        <div class="activity-item">
            <strong>Platform:</strong> Aetia Talent Agency Management System
        </div>
        <div class="activity-item">
            <strong>Admin User:</strong> <?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?>
        </div>
    </div>
</div>

<script>
// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    window.location.reload();
}, 300000);

// Add click handlers for action cards
document.addEventListener('DOMContentLoaded', function() {
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.tagName !== 'A') {
                window.location.href = this.href;
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
