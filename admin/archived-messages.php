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

$pageTitle = $currentMessage ? 'Archived: ' . htmlspecialchars($currentMessage['subject']) . ' - Admin' : 'Archived Messages - Admin';
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
    color: #444;
    margin-top: 0.5rem;
    font-size: 0.9rem;
    font-weight: 500;
}

.archived-message-header {
    background: linear-gradient(45deg, #ff8c00, #ffa500);
    color: white;
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
}

.archive-info {
    background-color: #fff8e1;
    border: 1px solid #ffcc02;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
    color: #333;
}

.archive-info strong {
    color: #1a1a1a;
    font-weight: 600;
}

.archive-info .column {
    color: #2c2c2c;
}

.admin-badge {
    background-color: #3273dc;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: bold;
}

.message-item {
    cursor: pointer;
    border-radius: 0.375rem;
    transition: all 0.2s ease;
}

.message-item:hover {
    background-color: #f5f5f5;
}

.message-item.is-active {
    background-color: #fff3cd;
    border-left: 4px solid #ff8c00;
}
</style>

<div class="content">
    <!-- Header -->
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
                <h3 class="title is-5 mb-4">Archived Messages</h3>
                
                <!-- Tag Filter -->
                <div class="field mb-4">
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
                        <a href="javascript:updateFilters('tag', '')" class="has-text-link">Clear filter</a>
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- Messages List -->
                <?php if (!empty($archivedMessages)): ?>
                <div class="message-list" style="max-height: 60vh; overflow-y: auto;">
                    <?php foreach ($archivedMessages as $msg): ?>
                    <div class="message-item p-3 mb-2 <?= $messageId === $msg['id'] ? 'is-active' : '' ?>" 
                         onclick="window.location.href='?id=<?= $msg['id'] ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?>'">
                        <div class="level is-mobile">
                            <div class="level-left" style="min-width: 0; flex: 1;">
                                <div style="min-width: 0; width: 100%;">
                                    <p class="title is-6 mb-1" style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($msg['subject']) ?>
                                    </p>
                                    <p class="subtitle is-7 has-text-grey-dark mb-1">
                                        <?php if ($msg['owner_first_name']): ?>
                                        <?= htmlspecialchars($msg['owner_first_name'] . ' ' . $msg['owner_last_name']) ?>
                                        <?php else: ?>
                                        Unknown User
                                        <?php endif; ?>
                                    </p>
                                    <p class="is-size-7 has-text-grey-dark">
                                        Archived: <?= formatDateForUser($msg['archived_at'], 'M j, g:i A') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="tags">
                                    <span class="tag is-<?= match($msg['priority']) {
                                        'urgent' => 'danger',
                                        'high' => 'warning', 
                                        'normal' => 'info',
                                        'low' => 'light'
                                    } ?> is-small">
                                        <?= ucfirst($msg['priority']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($msg['tags'])): ?>
                        <div class="tags mt-2">
                            <?php foreach (array_map('trim', explode(',', $msg['tags'])) as $tag): ?>
                                <?php if (!empty($tag)): ?>
                                <span class="tag is-small is-<?= $tag === 'Internal' ? 'primary' : 'dark' ?>">
                                    <?= htmlspecialchars($tag) ?>
                                </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($msg['archive_reason'])): ?>
                        <div class="archive-reason mt-2">
                            Reason: <?= htmlspecialchars(substr($msg['archive_reason'], 0, 100)) ?><?= strlen($msg['archive_reason']) > 100 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                    </div>
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
                <div class="has-text-centered has-text-grey">
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
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Message Detail -->
        <div class="column is-8">
            <?php if ($currentMessage): ?>
            <!-- Archived Message Header -->
            <div class="archived-message-header">
                <h1 class="title is-4 has-text-white mb-2">
                    <span class="icon"><i class="fas fa-archive"></i></span>
                    Archived Message - Admin View
                </h1>
                <p class="subtitle is-6 has-text-white">
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
                            <p class="subtitle is-6 has-text-grey-dark">
                                From: <?= htmlspecialchars($currentMessage['created_by_username'] ?? 'Unknown') ?>
                                <span class="ml-2 has-text-dark"><?= formatDateForUser($currentMessage['created_at']) ?></span>
                                <span class="ml-2 has-text-dark">
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
                                    <small class="has-text-grey-dark">
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
            <div class="box has-text-centered">
                <span class="icon is-large has-text-grey">
                    <i class="fas fa-archive fa-3x"></i>
                </span>
                <h3 class="title is-4 has-text-grey">Select an Archived Message</h3>
                <?php if (!empty($archivedMessages)): ?>
                <p class="has-text-grey">Click on a message from the sidebar to view its details and discussion history.</p>
                <?php else: ?>
                <p class="has-text-grey">No archived messages found.</p>
                <?php if ($tagFilter): ?>
                <p class="has-text-grey">Try adjusting your filters or check different criteria.</p>
                <?php else: ?>
                <p class="has-text-grey">When messages are archived, they will appear in the sidebar for viewing.</p>
                <?php endif; ?>
                <br>
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
