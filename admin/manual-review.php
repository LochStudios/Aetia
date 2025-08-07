<?php
// admin/manual-review.php - Admin interface for viewing messages marked for manual review
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

// Get pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get manual review messages
$manualReviewMessages = $messageModel->getManualReviewMessages($limit, $offset);

$pageTitle = 'Manual Review Messages | Admin | Aetia';
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
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-dollar-sign"></i></span><span>Manual Review</span></a></li>
        </ul>
    </nav>

    <!-- Header -->
    <div class="level">
        <div class="level-left">
            <h2 class="title is-2 has-text-warning">
                <span class="icon"><i class="fas fa-dollar-sign"></i></span>
                Manual Review Messages
            </h2>
        </div>
        <div class="level-right">
            <a href="messages.php" class="button is-info">
                <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                <span>All Messages</span>
            </a>
        </div>
    </div>

    <p class="subtitle has-text-light">
        Messages marked for manual review incur an additional $1.00 fee as per contract terms (Section 5.6).
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

    <!-- Manual Review Messages List -->
    <?php if (!empty($manualReviewMessages)): ?>
        <div class="box">
            <h3 class="title is-4">
                <span class="icon"><i class="fas fa-list"></i></span>
                Manual Review Messages (<?= count($manualReviewMessages) ?>)
            </h3>
            
            <div class="table-container">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Client</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Marked By</th>
                            <th>Review Date</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manualReviewMessages as $msg): ?>
                            <tr>
                                <td>
                                    <a href="messages.php?id=<?= $msg['id'] ?>" class="has-text-info">
                                        #<?= $msg['id'] ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="messages.php?id=<?= $msg['id'] ?>" class="has-text-dark">
                                        <?= htmlspecialchars($msg['subject']) ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($msg['target_display_name']) ?></strong>
                                    <br>
                                    <small class="has-text-grey">@<?= htmlspecialchars($msg['target_username']) ?></small>
                                </td>
                                <td>
                                    <span class="tag is-<?= match($msg['priority']) {
                                        'urgent' => 'danger',
                                        'high' => 'warning', 
                                        'normal' => 'info',
                                        'low' => 'light'
                                    } ?>">
                                        <?= ucfirst($msg['priority']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="tag is-<?= match($msg['status']) {
                                        'unread' => 'danger',
                                        'read' => 'info',
                                        'responded' => 'success',
                                        'closed' => 'dark'
                                    } ?>">
                                        <?= ucfirst($msg['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($msg['manual_review_by_display_name']) ?>
                                </td>
                                <td>
                                    <span class="is-size-7">
                                        <?= formatDateForUser($msg['manual_review_at']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($msg['manual_review_reason'])): ?>
                                        <span title="<?= htmlspecialchars($msg['manual_review_reason']) ?>">
                                            <?= htmlspecialchars(strlen($msg['manual_review_reason']) > 30 ? substr($msg['manual_review_reason'], 0, 30) . '...' : $msg['manual_review_reason']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="has-text-grey-light">No reason provided</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="buttons are-small">
                                        <a href="messages.php?id=<?= $msg['id'] ?>" class="button is-info is-small">
                                            <span class="icon"><i class="fas fa-eye"></i></span>
                                            <span>View</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php else: ?>
        <div class="notification is-info is-light">
            <span class="icon"><i class="fas fa-info-circle"></i></span>
            <span>No messages are currently marked for manual review.</span>
        </div>
    <?php endif; ?>

    <!-- Information Box -->
    <div class="box">
        <h3 class="title is-5">
            <span class="icon"><i class="fas fa-info-circle"></i></span>
            Manual Review Information
        </h3>
        <div class="content">
            <h6 class="title is-6">Contract Terms - Section 5.6: Manual Review Fee</h6>
            <blockquote>
                "Manual review services requested outside standard processing hours incur an additional fee of One United States Dollar (US$1.00) per email processed. These fees will be included in the monthly invoice along with standard service fees."
            </blockquote>
            
            <h6 class="title is-6 mt-4">How Manual Review Works</h6>
            <ul>
                <li><strong>Additional Fee:</strong> Each message marked for manual review incurs a $1.00 fee</li>
                <li><strong>Billing:</strong> Manual review fees are added to the monthly invoice alongside standard service fees</li>
                <li><strong>Processing:</strong> These messages require additional handling outside normal processing hours</li>
                <li><strong>Tracking:</strong> All manual review requests are logged with timestamp, reason, and reviewer</li>
                <li><strong>Reporting:</strong> Manual review fees appear separately in billing reports for transparency</li>
            </ul>
            
            <div class="notification is-warning is-light">
                <strong>Note:</strong> Only administrators can mark messages for manual review. This ensures proper authorization and fee tracking.
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Start output buffering for scripts
ob_start();
?>
<script>
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
$scripts = ob_get_clean();
include '../layout.php';
?>
