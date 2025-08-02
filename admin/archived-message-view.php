<?php
// admin/archived-message-view.php - Admin view for individual archived messages
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/User.php';

$messageModel = new Message();
$userModel = new User();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

$messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$messageId) {
    header('Location: archived-messages.php');
    exit;
}

// Get the archived message
$currentMessage = $messageModel->getMessage($messageId);

if (!$currentMessage || $currentMessage['status'] !== 'archived') {
    header('Location: archived-messages.php');
    exit;
}

// Get comments for this message
$messageComments = $messageModel->getMessageComments($messageId);

$pageTitle = 'Archived Message: ' . htmlspecialchars($currentMessage['subject']) . ' - Admin';
ob_start();
?>

<style>
.archived-message-header {
    background: linear-gradient(45deg, #ff8c00, #ffa500);
    color: white;
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
}

.archive-info {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.admin-badge {
    background-color: #3273dc;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: bold;
}
</style>

<div class="container">
    <!-- Back Navigation -->
    <nav class="breadcrumb mb-4" aria-label="breadcrumbs">
        <ul>
            <li><a href="messages.php">Admin Messages</a></li>
            <li><a href="archived-messages.php">Archived Messages</a></li>
            <li class="is-active"><a href="#" aria-current="page"><?= htmlspecialchars($currentMessage['subject']) ?></a></li>
        </ul>
    </nav>
    
    <!-- Archived Message Header -->
    <div class="archived-message-header">
        <h1 class="title is-3 has-text-white mb-2">
            <span class="icon"><i class="fas fa-archive"></i></span>
            Archived Message - Admin View
        </h1>
        <p class="subtitle is-5 has-text-white">
            This message has been archived and is read-only
        </p>
    </div>
    
    <!-- Archive Information -->
    <div class="archive-info">
        <h4 class="title is-6 mb-2">
            <span class="icon"><i class="fas fa-info-circle"></i></span>
            Archive Information
        </h4>
        <div class="columns">
            <div class="column">
                <strong>Archived:</strong> <?= formatDateForUser($currentMessage['archived_at'] ?? $currentMessage['updated_at']) ?>
            </div>
            <div class="column">
                <strong>Archived By:</strong> 
                <?php
                // Need to get archiver info - for now show generic message
                echo "System";
                ?>
            </div>
            <div class="column">
                <strong>Message Owner:</strong> <?= htmlspecialchars($currentMessage['target_username'] ?? 'Unknown') ?>
            </div>
            <?php if (!empty($currentMessage['archive_reason'])): ?>
            <div class="column is-full">
                <strong>Archive Reason:</strong> <?= nl2br(htmlspecialchars($currentMessage['archive_reason'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Message Content -->
    <div class="box">
        <!-- Message Header -->
        <div class="level">
            <div class="level-left">
                <div>
                    <h2 class="title is-4 mb-1"><?= htmlspecialchars($currentMessage['subject']) ?></h2>
                    <p class="subtitle is-6 has-text-grey">
                        From: <?= htmlspecialchars($currentMessage['created_by_username'] ?? 'Unknown') ?>
                        <span class="ml-2"><?= formatDateForUser($currentMessage['created_at']) ?></span>
                        <span class="ml-2">
                            To: <?= ($currentMessage['target_username'] ?? 'Unknown') === 'admin' ? 'Aetia Talant Agency' : htmlspecialchars($currentMessage['target_username'] ?? 'Unknown') ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="level-right">
                <div class="tags">
                    <span class="tag is-<?= match($currentMessage['priority']) {
                        'urgent' => 'danger',
                        'high' => 'warning', 
                        'normal' => 'info',
                        'low' => 'light'
                    } ?>">
                        <?= ucfirst($currentMessage['priority']) ?> Priority
                    </span>
                    <span class="tag is-warning">
                        Archived
                    </span>
                    <?php if (!empty($currentMessage['tags'])): ?>
                        <?php foreach (array_map('trim', explode(',', $currentMessage['tags'])) as $tag): ?>
                            <?php if (!empty($tag)): ?>
                            <span class="tag is-<?= $tag === 'Internal' ? 'primary' : 'dark' ?>">
                                <?= htmlspecialchars($tag) ?>
                            </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Original Message -->
        <div class="content">
            <div class="box has-background-light has-text-dark">
                <?= nl2br(htmlspecialchars($currentMessage['message'])) ?>
            </div>
        </div>
        
        <!-- Comments -->
        <?php if (!empty($messageComments)): ?>
        <div class="content">
            <h4 class="title is-5">
                <span class="icon"><i class="fas fa-comments"></i></span>
                Discussion History
            </h4>
            
            <?php foreach ($messageComments as $comment): ?>
            <article class="media">
                <figure class="media-left">
                    <p class="image is-48x48">
                        <?php if ($comment['profile_image']): ?>
                            <img src="<?= htmlspecialchars($comment['profile_image']) ?>" 
                                 alt="Profile Picture" 
                                 style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <span class="icon is-large has-text-grey">
                                <i class="fas fa-user-circle fa-2x"></i>
                            </span>
                        <?php endif; ?>
                    </p>
                </figure>
                <div class="media-content">
                    <div class="content">
                        <p>
                            <strong><?= htmlspecialchars($comment['display_name']) ?></strong>
                            <?php if ($comment['is_admin_comment']): ?>
                                <span class="admin-badge">Talant Team</span>
                            <?php endif; ?>
                            <br>
                            <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                            <br>
                            <small class="has-text-grey">
                                <?= formatDateForUser($comment['created_at']) ?>
                            </small>
                        </p>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Archived Message Notice -->
        <div class="notification is-warning is-light">
            <span class="icon"><i class="fas fa-archive"></i></span>
            <strong>This message is archived.</strong> 
            No further responses can be added to this conversation. This is a read-only view for administrative reference.
        </div>
        
        <!-- Admin Actions -->
        <div class="field is-grouped">
            <div class="control">
                <a href="archived-messages.php" class="button is-light">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    <span>Back to Archived Messages</span>
                </a>
            </div>
            <div class="control">
                <a href="messages.php" class="button is-primary">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>View Active Messages</span>
                </a>
            </div>
            <div class="control">
                <a href="create-message.php" class="button is-info">
                    <span class="icon"><i class="fas fa-plus"></i></span>
                    <span>Create New Message</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
