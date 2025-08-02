<?php
// admin/messages.php - Admin interface for managing user messages
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

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_comment':
                $messageId = intval($_POST['message_id']);
                $comment = trim($_POST['comment']);
                
                if (!empty($comment)) {
                    $result = $messageModel->addComment($messageId, $_SESSION['user_id'], $comment, true);
                    if ($result['success']) {
                        $message = 'Admin response added successfully!';
                    } else {
                        $error = $result['message'] ?? 'Failed to add response';
                    }
                } else {
                    $error = 'Response cannot be empty';
                }
                break;
                
            case 'update_status':
                $messageId = intval($_POST['message_id']);
                $status = $_POST['status'];
                
                if ($messageModel->updateMessageStatus($messageId, $status)) {
                    $message = 'Message status updated successfully!';
                } else {
                    $error = 'Failed to update message status';
                }
                break;
                
            case 'archive_message':
                $messageId = intval($_POST['message_id']);
                $archiveReason = isset($_POST['archive_reason']) ? trim($_POST['archive_reason']) : null;
                
                $result = $messageModel->archiveMessage($messageId, $_SESSION['user_id'], $archiveReason);
                if ($result['success']) {
                    $message = 'Message archived successfully!';
                    // Redirect to messages list after successful archive
                    header('Location: messages.php');
                    exit;
                } else {
                    $error = $result['message'] ?? 'Failed to archive message';
                }
                break;
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? null;
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
    if ($currentMessage) {
        $messageComments = $messageModel->getMessageComments($messageId);
    }
}

// Get messages for admin view
$messages = $messageModel->getAllMessages($limit, $offset, $statusFilter, $tagFilter);
$availableTags = $messageModel->getAvailableTags();

$pageTitle = $currentMessage ? htmlspecialchars($currentMessage['subject']) . ' | Admin Messages' : 'Admin Messages | Aetia';
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
                <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                Message Management
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="create-message.php" class="button is-primary">
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
                <a href="archived-messages.php" class="button is-dark is-small">
                    <span class="icon"><i class="fas fa-archive"></i></span>
                    <span>Archived Messages</span>
                </a>
                <a href="../index.php" class="button is-light is-small">
                    <span class="icon"><i class="fas fa-home"></i></span>
                    <span>Back to Website</span>
                </a>
            </div>
        </div>
    </div>

    <div class="columns">
        <!-- Messages List Sidebar -->
        <div class="column is-4">
            <div class="box">
                <!-- Status Filter -->
                <div class="field">
                    <label class="label">Filter by Status</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select onchange="updateFilters('status', this.value)">
                                <option value="">All Messages</option>
                                <option value="unread" <?= $statusFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
                                <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read</option>
                                <option value="responded" <?= $statusFilter === 'responded' ? 'selected' : '' ?>>Responded</option>
                                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                                <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>
                    </div>
                </div>
                
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
                
                <?php if (empty($messages)): ?>
                <div class="has-text-centered has-text-grey">
                    <span class="icon is-large">
                        <i class="fas fa-inbox fa-3x"></i>
                    </span>
                    <p class="mt-2">No messages found</p>
                </div>
                <?php else: ?>
                
                <!-- Messages List -->
                <div class="panel">
                    <?php foreach ($messages as $msg): ?>
                    <a class="panel-block <?= $msg['id'] == $messageId ? 'is-active' : '' ?>" 
                       href="?id=<?= $msg['id'] ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?>">
                        <span class="panel-icon">
                            <?php 
                            $iconClass = match($msg['status']) {
                                'unread' => 'fas fa-envelope text-danger',
                                'read' => 'fas fa-envelope-open text-info',
                                'responded' => 'fas fa-reply text-success',
                                'closed' => 'fas fa-archive text-grey',
                                'archived' => 'fas fa-box-archive text-orange',
                                default => 'fas fa-envelope'
                            };
                            ?>
                            <i class="<?= $iconClass ?>"></i>
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
                                To: <?= htmlspecialchars($msg['target_display_name']) ?>
                                <span class="ml-2">•</span>
                                <span class="ml-1"><?= formatDateForUser($msg['created_at'], 'M j, Y') ?></span>
                                <?php if ($msg['comment_count'] > 0): ?>
                                <span class="ml-2">
                                    <i class="fas fa-comments"></i> <?= $msg['comment_count'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Message Detail -->
        <div class="column is-8">
            <?php if ($currentMessage): ?>
            <div class="box">
                <!-- Message Header -->
                <div class="level mb-4">
                    <div class="level-left">
                        <div>
                            <h2 class="title is-3"><?= htmlspecialchars($currentMessage['subject']) ?></h2>
                            <p class="subtitle is-6">
                                To: <strong><?= htmlspecialchars($currentMessage['target_display_name']) ?></strong>
                                <span class="ml-2">•</span>
                                <span class="ml-2"><?= formatDateForUser($currentMessage['created_at']) ?></span>
                                <span class="ml-2">•</span>
                                <span class="ml-2">By: <?= htmlspecialchars($currentMessage['created_by_display_name']) ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="level-right">
                        <div class="field has-addons">
                            <div class="control">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="message_id" value="<?= $currentMessage['id'] ?>">
                                    <div class="select">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="unread" <?= $currentMessage['status'] === 'unread' ? 'selected' : '' ?>>Unread</option>
                                            <option value="read" <?= $currentMessage['status'] === 'read' ? 'selected' : '' ?>>Read</option>
                                            <option value="responded" <?= $currentMessage['status'] === 'responded' ? 'selected' : '' ?>>Responded</option>
                                            <option value="closed" <?= $currentMessage['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Priority and Status Tags -->
                <div class="tags mb-4">
                    <span class="tag is-<?= match($currentMessage['priority']) {
                        'urgent' => 'danger',
                        'high' => 'warning', 
                        'normal' => 'info',
                        'low' => 'light'
                    } ?>">
                        <?= ucfirst($currentMessage['priority']) ?> Priority
                    </span>
                    <span class="tag is-<?= match($currentMessage['status']) {
                        'unread' => 'danger',
                        'read' => 'info',
                        'responded' => 'success',
                        'closed' => 'dark'
                    } ?>">
                        <?= ucfirst($currentMessage['status']) ?>
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
                                            <span class="tag is-info is-small ml-1">Admin</span>
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
                
                <!-- Add Admin Response Form -->
                <?php if ($currentMessage['status'] !== 'closed'): ?>
                <div class="content">
                    <h5 class="title is-6">Add Admin Response</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="message_id" value="<?= $currentMessage['id'] ?>">
                        
                        <div class="field">
                            <div class="control">
                                <textarea class="textarea" 
                                          name="comment" 
                                          placeholder="Type your admin response here..." 
                                          rows="4" 
                                          required></textarea>
                            </div>
                        </div>
                        
                        <div class="field">
                            <div class="control is-flex is-justify-content-space-between">
                                <button class="button is-info" type="submit">
                                    <span class="icon"><i class="fas fa-reply"></i></span>
                                    <span>Send Admin Response</span>
                                </button>
                                
                                <!-- Archive Message Button -->
                                <button class="button is-warning" type="button" onclick="archiveMessage(<?= $currentMessage['id'] ?>)">
                                    <span class="icon"><i class="fas fa-archive"></i></span>
                                    <span>Archive Message</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="notification is-warning is-light">
                    <span class="icon"><i class="fas fa-lock"></i></span>
                    This message has been closed and no longer accepts responses.
                </div>
                <!-- Archive button for closed messages -->
                <div class="content">
                    <div class="field">
                        <div class="control">
                            <button class="button is-warning" type="button" onclick="archiveMessage(<?= $currentMessage['id'] ?>)">
                                <span class="icon"><i class="fas fa-archive"></i></span>
                                <span>Archive Message</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- No message selected -->
            <div class="box has-text-centered">
                <span class="icon is-large has-text-grey">
                    <i class="fas fa-envelope-open fa-4x"></i>
                </span>
                <h3 class="title is-4 has-text-grey">Select a Message</h3>
                <p class="has-text-grey">Choose a message from the sidebar to view its details and respond.</p>
                <a href="create-message.php" class="button is-primary mt-4">
                    <span class="icon"><i class="fas fa-plus"></i></span>
                    <span>Create New Message</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Start output buffering for scripts
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh message list every 60 seconds
    setInterval(function() {
        // You could implement AJAX refresh here if needed
    }, 60000);
});

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
    
    // Navigate to the updated URL
    window.location.href = url.toString();
}

// Archive message function
function archiveMessage(messageId) {
    Swal.fire({
        title: 'Archive Message',
        text: 'Are you sure you want to archive this message? This will close the conversation.',
        input: 'textarea',
        inputPlaceholder: 'Optional: Reason for archiving...',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive Message',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ff8c00',
        cancelButtonColor: '#d33',
        customClass: {
            popup: 'has-text-dark',
            title: 'has-text-dark',
            htmlContainer: 'has-text-dark',
            input: 'has-text-dark'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Create form data
            const formData = new FormData();
            formData.append('action', 'archive_message');
            formData.append('message_id', messageId);
            if (result.value) {
                formData.append('archive_reason', result.value);
            }
            
            // Submit form
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    Swal.fire({
                        title: 'Archived!',
                        text: 'The message has been archived successfully.',
                        icon: 'success',
                        customClass: {
                            popup: 'has-text-dark',
                            title: 'has-text-dark',
                            htmlContainer: 'has-text-dark'
                        }
                    }).then(() => {
                        // Redirect to messages list
                        window.location.href = 'messages.php';
                    });
                } else {
                    throw new Error('Archive failed');
                }
            }).catch(error => {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to archive the message. Please try again.',
                    icon: 'error',
                    customClass: {
                        popup: 'has-text-dark',
                        title: 'has-text-dark',
                        htmlContainer: 'has-text-dark'
                    }
                });
            });
        }
    });
}
</script>
<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
