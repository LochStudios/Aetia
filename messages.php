<?php
// messages.php - User interface for viewing and responding to messages
session_start();

// Include timezone utilities
require_once __DIR__ . '/includes/timezone.php';

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
                
            case 'create_team_message':
                $subject = trim($_POST['subject']);
                $messageText = trim($_POST['message']);
                // Internal messages to Talant Team are always urgent priority
                
                if (!empty($subject) && !empty($messageText)) {
                    // Find the first admin user to send the message to
                    $adminUser = $userModel->getFirstAdmin();
                    if ($adminUser) {
                        // Internal messages to Talant Team are always urgent priority
                        $result = $messageModel->createMessage($adminUser['id'], $subject, $messageText, $userId, 'urgent', 'Internal');
                        if ($result['success']) {
                            // Mark the new message as read by the sender (user who created it)
                            $messageModel->updateMessageStatus($result['message_id'], 'read');
                            
                            $message = 'Message sent to Talant Team successfully!';
                            // Redirect to view the new message
                            header('Location: messages.php?id=' . $result['message_id']);
                            exit;
                        } else {
                            $error = $result['message'] ?? 'Failed to send message';
                        }
                    } else {
                        $error = 'No admin users available to receive messages';
                    }
                } else {
                    $error = 'Subject and message are required';
                }
                break;
                
            case 'archive_message':
                $messageId = intval($_POST['message_id']);
                $archiveReason = isset($_POST['archive_reason']) ? trim($_POST['archive_reason']) : null;
                
                // Verify user has access to this message
                $messageDetails = $messageModel->getMessage($messageId, $userId);
                if ($messageDetails) {
                    $result = $messageModel->archiveMessage($messageId, $userId, $archiveReason);
                    if ($result['success']) {
                        $message = 'Message archived successfully!';
                        // Redirect to messages list after successful archive
                        header('Location: messages.php');
                        exit;
                    } else {
                        $error = $result['message'] ?? 'Failed to archive message';
                    }
                } else {
                    $error = 'Message not found or access denied';
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

// Sidebar pagination settings (5 messages per page)
$sidebarLimit = 5;
$sidebarPage = isset($_GET['sidebar_page']) ? max(1, intval($_GET['sidebar_page'])) : 1;
$sidebarOffset = ($sidebarPage - 1) * $sidebarLimit;

// Get user's messages for sidebar
$tagFilter = $_GET['tag'] ?? null;
$priorityFilter = $_GET['priority'] ?? null;
$messages = $messageModel->getUserMessages($userId, $sidebarLimit, $sidebarOffset, $tagFilter, $priorityFilter);

// Check if there are more messages for pagination
$hasNextPage = count($messages) === $sidebarLimit;
$hasPrevPage = $sidebarPage > 1;

$messageCounts = $messageModel->getUserMessageCounts($userId);
$availableTags = $messageModel->getAvailableTags($userId);

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
                <div class="level mb-4">
                    <div class="level-left">
                        <h3 class="title is-4 has-text-info mb-0">
                            <span class="icon"><i class="fas fa-envelope"></i></span>
                            Your Messages
                        </h3>
                    </div>
                    <div class="level-right">
                        <div class="field is-grouped">
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
                    </div>
                </div>
                
                <!-- Tag Filter -->
                <?php if (!empty($availableTags)): ?>
                <div class="field mb-4">
                    <label class="label">Filter by Tag</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select onchange="updateUserFilters('tag', this.value)">
                                <option value="">All Messages</option>
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
                        <a href="javascript:updateUserFilters('tag', '')" class="has-text-link">Clear filter</a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Priority Filter -->
                <div class="field mb-4">
                    <label class="label">Filter by Priority</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select onchange="updateUserFilters('priority', this.value)">
                                <option value="">All Priorities</option>
                                <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                                <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                                <option value="normal" <?= $priorityFilter === 'normal' ? 'selected' : '' ?>>Normal</option>
                                <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                            </select>
                        </div>
                    </div>
                    <?php if ($priorityFilter): ?>
                    <p class="help">
                        Showing <?= ucfirst($priorityFilter) ?> priority messages
                        <a href="javascript:updateUserFilters('priority', '')" class="has-text-link">Clear filter</a>
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- New Talant Team Message Button -->
                <div class="field mb-4">
                    <button class="button is-primary is-fullwidth" onclick="showNewMessageModal()">
                        <span class="icon"><i class="fas fa-plus"></i></span>
                        <span>New Talant Team Message</span>
                    </button>
                </div>
                
                <!-- View Archived Messages Link -->
                <div class="field mb-4">
                    <a href="archived-messages.php" class="button is-light is-fullwidth">
                        <span class="icon"><i class="fas fa-archive"></i></span>
                        <span>View Archived Messages</span>
                    </a>
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
                       href="?id=<?= $msg['id'] ?><?= $sidebarPage > 1 ? '&sidebar_page=' . $sidebarPage : '' ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?><?= $priorityFilter ? '&priority=' . urlencode($priorityFilter) : '' ?>">
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
                                <strong class="<?= $msg['status'] === 'unread' ? 'has-text-weight-bold' : '' ?>">
                                    <?= htmlspecialchars($msg['subject']) ?>
                                </strong>
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
                                <?= formatDateForUser($msg['created_at']) ?>
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
                
                <!-- Sidebar Pagination -->
                <?php if ($hasPrevPage || $hasNextPage): ?>
                <div class="field is-grouped is-grouped-centered mt-3">
                    <?php if ($hasPrevPage): ?>
                    <div class="control">
                        <a href="?sidebar_page=<?= $sidebarPage - 1 ?><?= $messageId ? '&id=' . $messageId : '' ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?><?= $priorityFilter ? '&priority=' . urlencode($priorityFilter) : '' ?>" 
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
                        <a href="?sidebar_page=<?= $sidebarPage + 1 ?><?= $messageId ? '&id=' . $messageId : '' ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?><?= $priorityFilter ? '&priority=' . urlencode($priorityFilter) : '' ?>" 
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
                                <span class="ml-2"><?= formatDateForUser($currentMessage['created_at']) ?></span>
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
                                'closed' => 'dark',
                                'archived' => 'warning'
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
                                                <?= $comment['username'] === 'admin' ? 'System Administrator' : htmlspecialchars($comment['username']) ?>
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
                                <div class="box has-background-light">
                                    <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                        <div>
                                            <strong>
                                                <?= htmlspecialchars($comment['username']) ?>
                                            </strong>
                                        </div>
                                        <small class="has-text-dark">
                                            <?= formatDateForUser($comment['created_at']) ?>
                                        </small>
                                    </div>
                                    <div>
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
                            <div class="control is-flex is-justify-content-space-between">
                                <button class="button is-primary" type="submit">
                                    <span class="icon"><i class="fas fa-reply"></i></span>
                                    <span>Send Response</span>
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

async function showNewMessageModal() {
    const { value: formValues } = await Swal.fire({
        title: 'Message Our Talant Team Directly',
        html: `
            <div class="field">
                <label class="label has-text-left has-text-dark">Subject</label>
                <div class="control">
                    <input id="swal-subject" class="input" type="text" placeholder="Enter message subject" style="color: #363636; background-color: #ffffff;">
                </div>
            </div>
            <div class="field">
                <label class="label has-text-left has-text-dark">Message</label>
                <div class="control">
                    <textarea id="swal-message" class="textarea" rows="5" placeholder="Enter your message to the Talant Team" style="color: #363636; background-color: #ffffff;"></textarea>
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Send Message',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3273dc',
        preConfirm: () => {
            const subject = document.getElementById('swal-subject').value;
            const message = document.getElementById('swal-message').value;
            
            if (!subject || !message) {
                Swal.showValidationMessage('Please fill in both subject and message');
                return false;
            }
            
            if (subject.trim().length < 3) {
                Swal.showValidationMessage('Subject must be at least 3 characters long');
                return false;
            }
            
            if (message.trim().length < 10) {
                Swal.showValidationMessage('Message must be at least 10 characters long');
                return false;
            }
            
            return {
                subject: subject.trim(),
                message: message.trim()
            };
        }
    });

    if (formValues) {
        // Show loading
        Swal.fire({
            title: 'Sending...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Submit the form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'create_team_message';
        form.appendChild(actionInput);
        
        const subjectInput = document.createElement('input');
        subjectInput.type = 'hidden';
        subjectInput.name = 'subject';
        subjectInput.value = formValues.subject;
        form.appendChild(subjectInput);
        
        const messageInput = document.createElement('input');
        messageInput.type = 'hidden';
        messageInput.name = 'message';
        messageInput.value = formValues.message;
        form.appendChild(messageInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function updateUserFilters(filterType, value) {
    const url = new URL(window.location);
    
    // Update the specified filter
    if (value) {
        url.searchParams.set(filterType, value);
    } else {
        url.searchParams.delete(filterType);
    }
    
    // Reset to page 1 when filters change
    url.searchParams.delete('sidebar_page');
    
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
include 'layout.php';
?>
