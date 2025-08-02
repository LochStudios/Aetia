<?php
// admin/messages.php - Admin interface for managing user messages
session_start();

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
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? null;
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
$messages = $messageModel->getAllMessages($limit, $offset, $statusFilter);

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
                            <select onchange="location.href='?status=' + this.value + (<?= $messageId ? "'&id=$messageId'" : "''" ?>)">
                                <option value="">All Messages</option>
                                <option value="unread" <?= $statusFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
                                <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read</option>
                                <option value="responded" <?= $statusFilter === 'responded' ? 'selected' : '' ?>>Responded</option>
                                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                    </div>
                </div>
                
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
                       href="?id=<?= $msg['id'] ?><?= $statusFilter ? '&status=' . $statusFilter : '' ?>">
                        <span class="panel-icon">
                            <?php 
                            $iconClass = match($msg['status']) {
                                'unread' => 'fas fa-envelope text-danger',
                                'read' => 'fas fa-envelope-open text-info',
                                'responded' => 'fas fa-reply text-success',
                                'closed' => 'fas fa-archive text-grey',
                                default => 'fas fa-envelope'
                            };
                            ?>
                            <i class="<?= $iconClass ?>"></i>
                        </span>
                        <div class="is-flex-grow-1">
                            <div class="is-flex is-justify-content-space-between is-align-items-center">
                                <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                                <span class="tag is-small is-<?= match($msg['priority']) {
                                    'urgent' => 'danger',
                                    'high' => 'warning',
                                    'normal' => 'info',
                                    'low' => 'light'
                                } ?>">
                                    <?= ucfirst($msg['priority']) ?>
                                </span>
                            </div>
                            <div class="is-size-7 has-text-grey">
                                To: <?= htmlspecialchars($msg['target_username']) ?>
                                <span class="ml-2">•</span>
                                <span class="ml-1"><?= date('M j, Y', strtotime($msg['created_at'])) ?></span>
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
                                To: <strong><?= htmlspecialchars($currentMessage['target_username']) ?></strong>
                                <span class="ml-2">•</span>
                                <span class="ml-2"><?= date('M j, Y g:i A', strtotime($currentMessage['created_at'])) ?></span>
                                <span class="ml-2">•</span>
                                <span class="ml-2">By: <?= htmlspecialchars($currentMessage['created_by_username']) ?></span>
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
                </div>
                
                <!-- Original Message -->
                <div class="content">
                    <div class="box has-background-light">
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
                                <div class="box has-background-<?= $comment['is_admin_comment'] ? 'info-light' : 'light' ?>">
                                    <p>
                                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                                        <?php if ($comment['is_admin_comment']): ?>
                                            <span class="tag is-info is-small ml-1">Admin</span>
                                        <?php endif; ?>
                                        <br>
                                        <?= nl2br(htmlspecialchars($comment['comment'])) ?>
                                        <br>
                                        <small class="has-text-grey">
                                            <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                                        </small>
                                    </p>
                                </div>
                            </div>
                        </div>
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
                            <div class="control">
                                <button class="button is-info" type="submit">
                                    <span class="icon"><i class="fas fa-reply"></i></span>
                                    <span>Send Admin Response</span>
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
</script>
<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
