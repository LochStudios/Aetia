<?php
// admin/archived-messages.php - Admin interface for viewing archived messages
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

// Get filter parameters
$tagFilter = $_GET['tag'] ?? null;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get current message if viewing one
$currentMessage = null;
$messageComments = [];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($messageId) {
    $currentMessage = $messageModel->getMessage($messageId);
    if ($currentMessage && $currentMessage['status'] === 'closed') {
        $messageComments = $messageModel->getMessageComments($messageId);
    } else {
        // Message not found or not archived, redirect to list
        header('Location: archived-messages.php');
        exit;
    }
}

// Get archived messages
$archivedMessages = $messageModel->getAllArchivedMessages($limit, $offset, $tagFilter);

// Get available tags for filter
$availableTags = $messageModel->getAvailableTags();

$pageTitle = $currentMessage ? 'Archived: ' . htmlspecialchars($currentMessage['subject']) . ' | Admin Messages' : 'Archived Messages | Admin';
ob_start();
?>

<div class="content">
    <?php if ($message): ?>
    <div class="notification is-success is-light">
        <button class="delete"></button>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="notification is-danger is-light">
        <button class="delete"></button>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="level mb-4">
        <div class="level-left">
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fas fa-archive"></i></span>
                Archived Messages
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="messages.php" class="button is-primary">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>Active Messages</span>
                </a>
                <a href="create-message.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-plus"></i></span>
                    <span>New Message</span>
                </a>
                <a href="pending-users.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-users-cog"></i></span>
                    <span>Pending Users</span>
                </a>
                <a href="unverified-users.php" class="button is-warning is-small">
                    <span class="icon"><i class="fas fa-user-check"></i></span>
                    <span>Unverified Users</span>
                </a>
                <a href="../index.php" class="button is-light is-small">
                    <span class="icon"><i class="fas fa-home"></i></span>
                    <span>Back to Website</span>
                </a>
            </div>
        </div>
    </div>

    <div class="columns">
        <!-- Archived Messages List Sidebar -->
        <div class="column is-4">
            <div class="box">
                <!-- Tag Filter -->
                <?php if (!empty($availableTags)): ?>
                <div class="field">
                    <label class="label">Filter by Tag</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select onchange="updateFilters('tag', this.value)">
                                <option value="">All Tags</option>
                                <?php foreach ($availableTags as $tag): ?>
                                <option value="<?= htmlspecialchars($tag) ?>" <?= $tagFilter === $tag ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tag) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($tagFilter): ?>
                    <p class="help">
                        Showing messages tagged with "<?= htmlspecialchars($tagFilter) ?>"
                        <a href="javascript:updateFilters('tag', '')" class="has-text-link">Clear</a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (empty($archivedMessages)): ?>
                <div class="has-text-centered has-text-grey">
                    <span class="icon is-large">
                        <i class="fas fa-archive fa-3x"></i>
                    </span>
                    <p class="mt-2">No archived messages found</p>
                </div>
                <?php else: ?>
                
                <!-- Messages List -->
                <div class="panel">
                    <?php foreach ($archivedMessages as $msg): ?>
                    <a class="panel-block <?= $msg['id'] == $messageId ? 'is-active' : '' ?>" 
                       href="?id=<?= $msg['id'] ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?>">
                        <span class="panel-icon">
                            <i class="fas fa-archive has-text-warning"></i>
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
                                From: <?= $msg['owner_first_name'] ? htmlspecialchars($msg['owner_first_name'] . ' ' . $msg['owner_last_name']) : 'Unknown User' ?>
                                <span class="ml-2">•</span>
                                <span class="ml-1">Archived: <?= formatDateForUser($msg['archived_at'] ?? $msg['updated_at'], 'M j, Y') ?></span>
                                <?php if ($msg['comment_count'] > 0): ?>
                                <span class="ml-2">
                                    <i class="fas fa-comments"></i> <?= $msg['comment_count'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($msg['attachment_count'] > 0): ?>
                                <span class="ml-2">
                                    <i class="fas fa-paperclip"></i> <?= $msg['attachment_count'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if (count($archivedMessages) === $limit || $page > 1): ?>
                <nav class="pagination is-small mt-4" role="navigation" aria-label="pagination">
                    <?php if ($page > 1): ?>
                    <a class="pagination-previous" href="?page=<?= $page - 1 ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?><?= $messageId ? '&id=' . $messageId : '' ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php if (count($archivedMessages) === $limit): ?>
                    <a class="pagination-next" href="?page=<?= $page + 1 ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?><?= $messageId ? '&id=' . $messageId : '' ?>">Next</a>
                    <?php endif; ?>
                    
                    <ul class="pagination-list">
                        <li><span class="pagination-link is-current is-small">Page <?= $page ?></span></li>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="has-text-centered has-text-white">
                    <span class="icon is-large">
                        <i class="fas fa-archive fa-3x"></i>
                    </span>
                    <p class="mt-2">No archived messages</p>
                    <?php if ($tagFilter): ?>
                    <p class="is-size-7">No archived messages found with the selected filter.</p>
                    <?php else: ?>
                    <p class="is-size-7">Archived messages will appear here.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if (count($archivedMessages) === $limit || $page > 1): ?>
                <nav class="pagination is-small mt-4" role="navigation" aria-label="pagination">
                    <?php if ($page > 1): ?>
                    <a class="pagination-previous" href="?page=<?= $page - 1 ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?><?= $messageId ? '&id=' . $messageId : '' ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php if (count($archivedMessages) === $limit): ?>
                    <a class="pagination-next" href="?page=<?= $page + 1 ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?><?= $messageId ? '&id=' . $messageId : '' ?>">Next</a>
                    <?php endif; ?>
                    
                    <ul class="pagination-list">
                        <li><span class="pagination-link is-current is-small">Page <?= $page ?></span></li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Message Detail -->
        <div class="column is-8">
            <?php if ($currentMessage): ?>
            <div class="box">
                <!-- Archive Notice -->
                <div class="notification is-warning is-light mb-4">
                    <span class="icon"><i class="fas fa-archive"></i></span>
                    <strong>Archived Message</strong> - This message has been archived and is read-only
                </div>
                
                <!-- Message Header -->
                <div class="level mb-4">
                    <div class="level-left">
                        <div>
                            <h2 class="title is-3"><?= htmlspecialchars($currentMessage['subject']) ?></h2>
                            <p class="subtitle is-6">
                                From: <strong><?= htmlspecialchars($currentMessage['created_by_display_name'] ?? 'Unknown User') ?></strong>
                                <span class="ml-2">•</span>
                                <span class="ml-2"><?= formatDateForUser($currentMessage['created_at']) ?></span>
                                <span class="ml-2">•</span>
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
                        <h6 class="title is-6 has-text-dark mb-2">Original Message:</h6>
                        <?= nl2br(htmlspecialchars($currentMessage['message'])) ?>
                    </div>
                </div>
                
                <!-- Comments -->
                <?php if (!empty($messageComments)): ?>
                <div class="content">
                    <h4 class="title is-5">
                        <span class="icon"><i class="fas fa-comments"></i></span>
                        Discussion
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
                                     class="profile-image">
                                <?php else: ?>
                                <span class="icon is-large has-text-grey">
                                    <i class="fas fa-user-circle fa-2x"></i>
                                </span>
                                <?php endif; ?>
                            </p>
                        </figure>
                        <div class="media-content">
                            <div class="content">
                                <div class="box has-background-info-dark has-text-light">
                                    <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                        <div>
                                            <strong class="has-text-light">
                                                <?= $comment['display_name'] === 'admin' ? 'System Administrator' : htmlspecialchars($comment['display_name']) ?>
                                            </strong>
                                            <span class="tag is-info is-small ml-1">Admin</span>
                                        </div>
                                        <small class="has-text-light">
                                            <?= formatDateForUser($comment['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="has-text-light">
                                        <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- User comment - icon on right -->
                        <div class="media-content">
                            <div class="content">
                                <div class="box has-background-grey-dark has-text-light">
                                    <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                        <div>
                                            <strong class="has-text-light">
                                                <?= htmlspecialchars($comment['display_name']) ?>
                                            </strong>
                                        </div>
                                        <small class="has-text-light">
                                            <?= formatDateForUser($comment['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="has-text-light">
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
                                     class="profile-image">
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
                            <span class="icon"><i class="fas fa-list"></i></span>
                            <span>Back to List</span>
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
            
            <?php else: ?>
            <!-- No message selected -->
            <div class="panel-block is-flex-direction-column has-text-centered p-5">
                <span class="icon is-large has-text-grey mb-4">
                    <i class="fas fa-archive fa-4x"></i>
                </span>
                <h3 class="title is-4 mb-4">Select an Archived Message</h3>
                <?php if (!empty($archivedMessages)): ?>
                <p class="mb-4">Click on a message from the sidebar to view its archived content and discussion history.</p>
                <?php else: ?>
                <p class="mb-2">No archived messages found.</p>
                <?php if ($tagFilter): ?>
                <p class="mb-4">Try adjusting your filters or check different criteria.</p>
                <?php else: ?>
                <p class="mb-4">When messages are archived, they will appear in the sidebar for viewing.</p>
                <?php endif; ?>
                <a href="messages.php" class="button is-primary">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>View Active Messages</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateFilters(filterType, value) {
    const url = new URL(window.location);
    
    // Update the specified filter
    if (value) {
        url.searchParams.set(filterType, value);
    } else {
        url.searchParams.delete(filterType);
    }
    
    // Preserve the current message ID if viewing one
    <?php if ($messageId): ?>
    url.searchParams.set('id', '<?= $messageId ?>');
    <?php endif; ?>
    
    // Reset page when changing filters
    url.searchParams.delete('page');
    
    // Navigate to the updated URL
    window.location.href = url.toString();
}
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
