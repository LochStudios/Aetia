<?php
// messages.php - User interface for viewing and responding to messages
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/models/Message.php';
require_once __DIR__ . '/models/User.php';

$messageModel = new Message();
$userModel = new User();
$userId = $_SESSION['user_id'];

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
                    $result = $messageModel->addComment($messageId, $userId, $comment, false);
                    if ($result['success']) {
                        $message = 'Comment added successfully!';
                    } else {
                        $error = $result['message'] ?? 'Failed to add comment';
                    }
                } else {
                    $error = 'Comment cannot be empty';
                }
                break;
                
            case 'mark_read':
                $messageId = intval($_POST['message_id']);
                if ($messageModel->updateMessageStatus($messageId, 'read', $userId)) {
                    $message = 'Message marked as read';
                }
                break;
        }
    }
}

// Get current message if viewing one
$currentMessage = null;
$messageComments = [];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($messageId) {
    $currentMessage = $messageModel->getMessage($messageId, $userId);
    if ($currentMessage) {
        $messageComments = $messageModel->getMessageComments($messageId);
        // Mark as read if it's unread
        if ($currentMessage['status'] === 'unread') {
            $messageModel->updateMessageStatus($messageId, 'read', $userId);
            $currentMessage['status'] = 'read';
        }
    }
}

// Get user's messages
$messages = $messageModel->getUserMessages($userId);
$messageCounts = $messageModel->getUserMessageCounts($userId);

$pageTitle = $currentMessage ? htmlspecialchars($currentMessage['subject']) . ' | Messages' : 'Messages | Aetia';
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

    <div class="columns">
        <!-- Messages List Sidebar -->
        <div class="column is-4">
            <div class="box">
                <h3 class="title is-4 has-text-info">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    Your Messages
                </h3>
                
                <!-- Message Counts -->
                <div class="field is-grouped is-grouped-multiline mb-4">
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag is-dark">Unread</span>
                            <span class="tag is-danger"><?= $messageCounts['unread'] ?></span>
                        </div>
                    </div>
                    <div class="control">
                        <div class="tags has-addons">
                            <span class="tag is-dark">Total</span>
                            <span class="tag is-info"><?= array_sum($messageCounts) ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($messages)): ?>
                <div class="has-text-centered has-text-grey">
                    <span class="icon is-large">
                        <i class="fas fa-inbox fa-3x"></i>
                    </span>
                    <p class="mt-2">No messages yet</p>
                </div>
                <?php else: ?>
                
                <!-- Messages List -->
                <div class="panel">
                    <?php foreach ($messages as $msg): ?>
                    <a class="panel-block <?= $msg['id'] == $messageId ? 'is-active' : '' ?>" 
                       href="?id=<?= $msg['id'] ?>">
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
                                <strong class="<?= $msg['status'] === 'unread' ? 'has-text-weight-bold' : '' ?>">
                                    <?= htmlspecialchars($msg['subject']) ?>
                                </strong>
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
                                <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?>
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
                                From: <strong><?= htmlspecialchars($currentMessage['created_by_username']) ?></strong>
                                <span class="ml-2">•</span>
                                <span class="ml-2"><?= date('M j, Y g:i A', strtotime($currentMessage['created_at'])) ?></span>
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
                            <span class="tag is-<?= match($currentMessage['status']) {
                                'unread' => 'danger',
                                'read' => 'info',
                                'responded' => 'success',
                                'closed' => 'dark'
                            } ?>">
                                <?= ucfirst($currentMessage['status']) ?>
                            </span>
                        </div>
                    </div>
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
                    </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Add Comment Form -->
                <?php if ($currentMessage['status'] !== 'closed'): ?>
                <div class="content">
                    <h5 class="title is-6">Add Your Response</h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="message_id" value="<?= $currentMessage['id'] ?>">
                        
                        <div class="field">
                            <div class="control">
                                <textarea class="textarea" 
                                          name="comment" 
                                          placeholder="Type your response here..." 
                                          rows="4" 
                                          required></textarea>
                            </div>
                        </div>
                        
                        <div class="field">
                            <div class="control">
                                <button class="button is-primary" type="submit">
                                    <span class="icon"><i class="fas fa-reply"></i></span>
                                    <span>Send Response</span>
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
    // Auto-refresh message counts every 30 seconds
    setInterval(function() {
        // You could implement AJAX refresh here if needed
    }, 30000);
    
    // Smooth scrolling for comment submission
    const commentForm = document.querySelector('form[action*="add_comment"]');
    if (commentForm) {
        commentForm.addEventListener('submit', function() {
            setTimeout(function() {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            }, 100);
        });
    }
});
</script>
<?php
$scripts = ob_get_clean();
include 'layout.php';
?>
