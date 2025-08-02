<?php
// archived-messages.php - User interface for viewing archived messages
session_start();

// Include timezone utilities
require_once __DIR__ . '/includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/models/Message.php';

$messageModel = new Message();
$userId = $_SESSION['user_id'];

// Get current message ID if viewing one
$currentMessage = null;
$messageComments = [];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($messageId) {
    $currentMessage = $messageModel->getMessage($messageId, $userId);
    if ($currentMessage && $currentMessage['status'] === 'archived') {
        $messageComments = $messageModel->getMessageComments($messageId);
    } else {
        $currentMessage = null; // Reset if not archived or not accessible
    }
}

// Sidebar pagination settings (5 messages per page)
$sidebarLimit = 5;
$sidebarPage = isset($_GET['sidebar_page']) ? max(1, intval($_GET['sidebar_page'])) : 1;
$sidebarOffset = ($sidebarPage - 1) * $sidebarLimit;

// Get archived messages for sidebar
$archivedMessages = $messageModel->getArchivedMessages($userId, $sidebarLimit, $sidebarOffset);

// Check if there are more messages for pagination
$hasNextPage = count($archivedMessages) === $sidebarLimit;
$hasPrevPage = $sidebarPage > 1;

$pageTitle = $currentMessage ? 'Archived: ' . htmlspecialchars($currentMessage['subject']) . ' | Messages' : 'Archived Messages';
ob_start();
?>

<style>
.archived-badge {
    background-color: #ff8c00;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: bold;
}

.archive-reason {
    font-style: italic;
    color: #666;
    margin-top: 0.5rem;
}
</style>

<div class="columns">
    <!-- Sidebar -->
    <div class="column is-4">
        <div class="box">
            <h3 class="title is-4 mb-4">
                <span class="icon"><i class="fas fa-archive"></i></span>
                Archived Messages
            </h3>
            
            <!-- Back to Messages -->
            <div class="field mb-4">
                <a href="messages.php" class="button is-primary is-fullwidth">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    <span>Back to Messages</span>
                </a>
            </div>
            
            <?php if (empty($archivedMessages)): ?>
            <div class="has-text-centered has-text-grey">
                <span class="icon is-large">
                    <i class="fas fa-archive fa-3x"></i>
                </span>
                <p class="mt-2">No archived messages</p>
                <p class="is-size-7">Messages you archive will appear here.</p>
            </div>
            <?php else: ?>
            
            <!-- Archived Messages List -->
            <div class="panel">
                <?php foreach ($archivedMessages as $msg): ?>
                <a class="panel-block <?= $msg['id'] == $messageId ? 'is-active' : '' ?>" 
                   href="?id=<?= $msg['id'] ?><?= $sidebarPage > 1 ? '&sidebar_page=' . $sidebarPage : '' ?>">
                    <span class="panel-icon">
                        <i class="fas fa-box-archive has-text-warning"></i>
                    </span>
                    <div class="is-flex-grow-1">
                        <div class="is-flex is-justify-content-space-between is-align-items-center">
                            <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                            <div class="tags">
                                <span class="tag is-small is-<?= match($msg['priority']) {
                                    'urgent' => 'danger',
                                    'high' => 'warning',
                                    'normal' => 'info',
                                    'low' => 'light'
                                } ?>">
                                    <?= ucfirst($msg['priority']) ?>
                                </span>
                                <?php if (!empty($msg['tags'])): ?>
                                    <?php foreach (array_map('trim', explode(',', $msg['tags'])) as $tag): ?>
                                        <?php if (!empty($tag)): ?>
                                        <span class="tag is-small is-<?= $tag === 'Internal' ? 'primary' : 'dark' ?>">
                                            <?= htmlspecialchars($tag) ?>
                                        </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="is-size-7 has-text-grey">
                            Archived: <?= formatDateForUser($msg['archived_at']) ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Sidebar Pagination -->
            <?php if ($hasPrevPage || $hasNextPage): ?>
            <div class="field is-grouped is-grouped-centered mt-3">
                <?php if ($hasPrevPage): ?>
                <div class="control">
                    <a href="?sidebar_page=<?= $sidebarPage - 1 ?><?= $messageId ? '&id=' . $messageId : '' ?>" 
                       class="button is-small">
                        <span class="icon"><i class="fas fa-chevron-left"></i></span>
                        <span>Prev</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="control">
                    <span class="button is-small is-static">Page <?= $sidebarPage ?></span>
                </div>
                
                <?php if ($hasNextPage): ?>
                <div class="control">
                    <a href="?sidebar_page=<?= $sidebarPage + 1 ?><?= $messageId ? '&id=' . $messageId : '' ?>" 
                       class="button is-small">
                        <span>Next</span>
                        <span class="icon"><i class="fas fa-chevron-right"></i></span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="column is-8">
        <?php if ($currentMessage): ?>
        <!-- Individual Archived Message View -->
        <div class="box">
            <!-- Archive Notice -->
            <div class="notification is-warning is-light mb-4">
                <span class="icon"><i class="fas fa-archive"></i></span>
                <strong>Archived Message</strong> - This conversation is read-only
            </div>
            
            <!-- Message Header -->
            <div class="level">
                <div class="level-left">
                    <div>
                        <h2 class="title is-4 mb-1"><?= htmlspecialchars($currentMessage['subject']) ?></h2>
                        <p class="subtitle is-6 has-text-grey">
                            Created: <?= formatDateForUser($currentMessage['created_at']) ?>
                            <span class="ml-2">Archived: <?= formatDateForUser($currentMessage['archived_at'] ?? $currentMessage['updated_at']) ?></span>
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
                    <?php if ($comment['is_admin_comment']): ?>
                    <!-- Admin comment - icon on left -->
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
                            <div class="box has-background-info-light has-text-dark">
                                <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                    <div>
                                        <strong class="has-text-dark">
                                            <?= $comment['display_name'] === 'admin' ? 'System Administrator' : htmlspecialchars($comment['display_name']) ?>
                                        </strong>
                                        <span class="tag is-small is-primary">Talant Team</span>
                                    </div>
                                    <small class="has-text-dark">
                                        <?= formatDateForUser($comment['created_at']) ?>
                                    </small>
                                </div>
                                <div class="has-text-dark">
                                    <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <!-- User comment - icon on right -->
                    <div class="media-content">
                        <div class="content">
                            <div class="box has-background-light has-text-dark">
                                <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                    <div>
                                        <strong class="has-text-dark">
                                            <?= htmlspecialchars($comment['display_name']) ?>
                                        </strong>
                                    </div>
                                    <small class="has-text-dark">
                                        <?= formatDateForUser($comment['created_at']) ?>
                                    </small>
                                </div>
                                <div class="has-text-dark">
                                    <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <figure class="media-right">
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
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- No Message Selected -->
        <div class="box has-text-centered">
            <span class="icon is-large has-text-grey">
                <i class="fas fa-archive fa-4x"></i>
            </span>
            <h3 class="title is-4 has-text-grey">Select an Archived Message</h3>
            <p class="has-text-grey">Choose a message from the sidebar to view its archived content.</p>
            <?php if (empty($archivedMessages)): ?>
            <p class="has-text-grey mt-4">You don't have any archived messages yet.</p>
            <a href="messages.php" class="button is-primary mt-4">
                <span class="icon"><i class="fas fa-envelope"></i></span>
                <span>View Active Messages</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
