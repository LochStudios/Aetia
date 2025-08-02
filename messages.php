<?php
// messages.php - User interface for viewing and responding to messages
session_start();

// Include timezone utilities
require_once __DIR__ . '/includes/timezone.php';
require_once __DIR__ . '/includes/FileUploader.php';

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
                        // Handle file uploads if any
                        if (!empty($_FILES['attachments']['name'][0])) {
                            $fileUploader = new FileUploader();
                            $uploadErrors = [];
                            
                            // Process multiple files
                            $fileCount = count($_FILES['attachments']['name']);
                            for ($i = 0; $i < $fileCount; $i++) {
                                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                                    $file = [
                                        'name' => $_FILES['attachments']['name'][$i],
                                        'type' => $_FILES['attachments']['type'][$i],
                                        'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                                        'error' => $_FILES['attachments']['error'][$i],
                                        'size' => $_FILES['attachments']['size'][$i]
                                    ];
                                    
                                    $uploadResult = $fileUploader->uploadMessageAttachment($file, $userId, $messageId);
                                    if ($uploadResult['success']) {
                                        // Save attachment record to database
                                        $attachResult = $messageModel->addAttachment(
                                            $messageId,
                                            $userId,
                                            $uploadResult['filename'],
                                            $uploadResult['original_filename'],
                                            $uploadResult['file_size'],
                                            $uploadResult['mime_type'],
                                            $uploadResult['file_path']
                                        );
                                        
                                        if (!$attachResult['success']) {
                                            $uploadErrors[] = "Failed to save attachment: " . $uploadResult['original_filename'];
                                        }
                                    } else {
                                        $uploadErrors[] = $uploadResult['original_filename'] . ": " . $uploadResult['message'];
                                    }
                                }
                            }
                            
                            if (!empty($uploadErrors)) {
                                $error = "Comment added but some attachments failed: " . implode(', ', $uploadErrors);
                            } else {
                                $message = 'Comment added successfully!';
                            }
                        } else {
                            $message = 'Comment added successfully!';
                        }
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
$messageDiscussion = [];
$messageAttachments = [];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($messageId) {
    $currentMessage = $messageModel->getMessage($messageId, $userId);
    if ($currentMessage) {
        $messageComments = $messageModel->getMessageComments($messageId);
        $messageDiscussion = $messageModel->getMessageDiscussion($messageId);
        $messageAttachments = $messageModel->getMessageAttachments($messageId);
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
                                From: <strong><?= htmlspecialchars($currentMessage['created_by_display_name']) ?></strong>
                                <span class="ml-2">•</span>
                                <span class="ml-2"><?= formatDateForUser($currentMessage['created_at']) ?></span>
                                <br>
                                To: <strong><?= $currentMessage['target_display_name'] === 'admin' ? 'Aetia Talant Agency' : htmlspecialchars($currentMessage['target_display_name']) ?></strong>
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
                
                <!-- Message Attachments -->
                <?php if (!empty($messageAttachments)): ?>
                <div class="content">
                    <h5 class="title is-6">
                        <span class="icon"><i class="fas fa-paperclip"></i></span>
                        Attachments (<?= count($messageAttachments) ?>)
                    </h5>
                    <div class="field is-grouped is-grouped-multiline">
                        <?php foreach ($messageAttachments as $attachment): ?>
                        <div class="control">
                            <div class="card" style="width: 300px;">
                                <div class="card-content">
                                    <div class="media">
                                        <div class="media-left">
                                            <span class="icon is-large">
                                                <?php if (FileUploader::isImage($attachment['mime_type'])): ?>
                                                    <i class="fas fa-image fa-2x has-text-info"></i>
                                                <?php elseif (strpos($attachment['mime_type'], 'pdf') !== false): ?>
                                                    <i class="fas fa-file-pdf fa-2x has-text-danger"></i>
                                                <?php elseif (strpos($attachment['mime_type'], 'video') !== false): ?>
                                                    <i class="fas fa-video fa-2x has-text-primary"></i>
                                                <?php elseif (strpos($attachment['mime_type'], 'audio') !== false): ?>
                                                    <i class="fas fa-volume-up fa-2x has-text-warning"></i>
                                                <?php elseif (strpos($attachment['mime_type'], 'zip') !== false || strpos($attachment['mime_type'], 'archive') !== false): ?>
                                                    <i class="fas fa-file-archive fa-2x has-text-grey"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file fa-2x has-text-grey-dark"></i>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="media-content">
                                            <p class="title is-6" style="word-break: break-word;"><?= htmlspecialchars($attachment['original_filename']) ?></p>
                                            <p class="subtitle is-7">
                                                <?= FileUploader::formatFileSize($attachment['file_size']) ?><br>
                                                <small>Uploaded by <?= htmlspecialchars($attachment['uploaded_by_display_name']) ?></small><br>
                                                <small><?= formatDateForUser($attachment['uploaded_at']) ?></small>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="field is-grouped">
                                        <div class="control">
                                            <a href="download-attachment.php?id=<?= $attachment['id'] ?>" 
                                               class="button is-small is-primary">
                                                <span class="icon"><i class="fas fa-download"></i></span>
                                                <span>Download</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Discussion (Comments & Images) -->
                <?php if (!empty($messageDiscussion)): ?>
                <div class="content">
                    <h4 class="title is-5">
                        <span class="icon"><i class="fas fa-comments"></i></span>
                        Discussion
                    </h4>
                    
                    <?php foreach ($messageDiscussion as $item): ?>
                    <article class="media">
                        <?php if ($item['is_admin_comment']): ?>
                        <!-- Admin item - icon on left -->
                        <figure class="media-left">
                            <p class="image is-48x48">
                                <?php if ($item['profile_image']): ?>
                                    <img src="<?= htmlspecialchars($item['profile_image']) ?>" 
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
                                                <?= $item['display_name'] === 'admin' ? 'System Administrator' : htmlspecialchars($item['display_name']) ?>
                                            </strong>
                                            <span class="tag is-info is-small ml-1">Admin</span>
                                        </div>
                                        <small class="has-text-dark">
                                            <?= formatDateForUser($item['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="has-text-dark">
                                        <?php if ($item['type'] === 'comment'): ?>
                                            <?= nl2br(htmlspecialchars($item['comment'])) ?>
                                        <?php elseif ($item['type'] === 'image'): ?>
                                            <div class="has-text-centered">
                                                <p class="mb-2"><strong>Shared an image:</strong></p>
                                                <figure class="image" style="max-width: 400px; margin: 0 auto;">
                                                    <img src="view-image.php?id=<?= $item['attachment_id'] ?>" 
                                                         alt="<?= htmlspecialchars($item['original_filename']) ?>"
                                                         style="border-radius: 8px; cursor: pointer;"
                                                         onclick="showImageModal('<?= htmlspecialchars($item['original_filename']) ?>', 'view-image.php?id=<?= $item['attachment_id'] ?>')">
                                                </figure>
                                                <p class="is-size-7 has-text-grey mt-2">
                                                    <?= htmlspecialchars($item['original_filename']) ?> 
                                                    (<?= FileUploader::formatFileSize($item['file_size']) ?>)
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <!-- User item - icon on right -->
                        <div class="media-content">
                            <div class="content">
                                <div class="box has-background-light has-text-dark">
                                    <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                        <div>
                                            <strong class="has-text-dark">
                                                <?= htmlspecialchars($item['display_name']) ?>
                                            </strong>
                                        </div>
                                        <small class="has-text-dark">
                                            <?= formatDateForUser($item['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="has-text-dark">
                                        <?php if ($item['type'] === 'comment'): ?>
                                            <?= nl2br(htmlspecialchars($item['comment'])) ?>
                                        <?php elseif ($item['type'] === 'image'): ?>
                                            <div class="has-text-centered">
                                                <p class="mb-2"><strong>Shared an image:</strong></p>
                                                <figure class="image" style="max-width: 400px; margin: 0 auto;">
                                                    <img src="view-image.php?id=<?= $item['attachment_id'] ?>" 
                                                         alt="<?= htmlspecialchars($item['original_filename']) ?>"
                                                         style="border-radius: 8px; cursor: pointer;"
                                                         onclick="showImageModal('<?= htmlspecialchars($item['original_filename']) ?>', 'view-image.php?id=<?= $item['attachment_id'] ?>')">
                                                </figure>
                                                <p class="is-size-7 has-text-grey mt-2">
                                                    <?= htmlspecialchars($item['original_filename']) ?> 
                                                    (<?= FileUploader::formatFileSize($item['file_size']) ?>)
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <figure class="media-right">
                            <p class="image is-48x48">
                                <?php if ($item['profile_image']): ?>
                                    <img src="<?= htmlspecialchars($item['profile_image']) ?>" 
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
                    <form method="POST" enctype="multipart/form-data">
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
                            <label class="label">Attachments</label>
                            <div class="control">
                                <div class="file is-boxed">
                                    <label class="file-label">
                                        <input class="file-input" type="file" name="attachments[]" multiple 
                                               accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.mp4,.avi,.mov,.wmv,.mp3,.wav,.ogg"
                                               onchange="updateFileList(this)">
                                        <span class="file-cta">
                                            <span class="file-icon">
                                                <i class="fas fa-upload"></i>
                                            </span>
                                            <span class="file-label">
                                                Choose files…
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <div id="file-list" class="mt-2"></div>
                                <p class="help">Maximum file size: 1GB per file. Supported formats: Images, PDFs, Documents, Videos, Audio files, and Archives.</p>
                            </div>
                        </div>
                        
                        <div class="field">
                            <div class="control is-flex is-justify-content-space-between">
                                <!-- Archive Message Button -->
                                <button class="button is-warning" type="button" onclick="archiveMessage(<?= $currentMessage['id'] ?>)">
                                    <span class="icon"><i class="fas fa-archive"></i></span>
                                    <span>Archive Message</span>
                                </button>
                                
                                <!-- Send Response Button -->
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
    
    // Real-time message updates
    <?php if ($messageId): ?>
    let lastCheckTime = new Date().toISOString();
    let isCheckingMessages = false;
    
    function checkForNewMessages() {
        if (isCheckingMessages) return;
        
        isCheckingMessages = true;
        
        fetch(`api/check-new-messages.php?message_id=<?= $messageId ?>&last_check=${encodeURIComponent(lastCheckTime)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.has_new_items) {
                        appendNewMessages(data.new_items);
                    }
                    
                    if (data.has_new_attachments) {
                        appendNewAttachments(data.new_attachments);
                    }
                    
                    if (data.has_new_items || data.has_new_attachments) {
                        lastCheckTime = data.last_check;
                    }
                    
                    // Update message status if changed
                    updateMessageStatus(data.message_status);
                }
            })
            .catch(error => {
                console.error('Error checking for new messages:', error);
            })
            .finally(() => {
                isCheckingMessages = false;
            });
    }
    
    function appendNewMessages(newItems) {
        const discussionContainer = document.querySelector('.content h4.title').parentElement;
        
        newItems.forEach(item => {
            const messageHtml = createMessageHTML(item);
            discussionContainer.insertAdjacentHTML('beforeend', messageHtml);
        });
        
        // Scroll to bottom to show new messages
        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }
    
    function createMessageHTML(item) {
        const isAdmin = item.is_admin_comment;
        const displayName = item.display_name === 'admin' ? 'System Administrator' : item.display_name;
        
        let contentHTML = '';
        if (item.type === 'comment') {
            contentHTML = item.comment.replace(/\n/g, '<br>');
        } else if (item.type === 'image') {
            contentHTML = `
                <div class="has-text-centered">
                    <p class="mb-2"><strong>Shared an image:</strong></p>
                    <figure class="image" style="max-width: 400px; margin: 0 auto;">
                        <img src="view-image.php?id=${item.attachment_id}" 
                             alt="${item.original_filename}"
                             style="border-radius: 8px; cursor: pointer;"
                             onclick="showImageModal('${item.original_filename}', 'view-image.php?id=${item.attachment_id}')">
                    </figure>
                    <p class="is-size-7 has-text-grey mt-2">
                        ${item.original_filename} (${item.formatted_file_size})
                    </p>
                </div>
            `;
        }
        
        const profileImage = item.profile_image ? 
            `<img src="${item.profile_image}" alt="Profile Picture" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">` :
            `<span class="icon is-large has-text-grey"><i class="fas fa-user-circle fa-2x"></i></span>`;
        
        if (isAdmin) {
            return `
                <article class="media" style="opacity: 0; animation: fadeIn 0.5s ease-in forwards;">
                    <figure class="media-left">
                        <p class="image is-48x48">${profileImage}</p>
                    </figure>
                    <div class="media-content">
                        <div class="content">
                            <div class="box has-background-info-light has-text-dark">
                                <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                    <div>
                                        <strong class="has-text-dark">${displayName}</strong>
                                        <span class="tag is-info is-small ml-1">Admin</span>
                                    </div>
                                    <small class="has-text-dark">${item.formatted_date}</small>
                                </div>
                                <div class="has-text-dark">${contentHTML}</div>
                            </div>
                        </div>
                    </div>
                </article>
            `;
        } else {
            return `
                <article class="media" style="opacity: 0; animation: fadeIn 0.5s ease-in forwards;">
                    <div class="media-content">
                        <div class="content">
                            <div class="box has-background-light has-text-dark">
                                <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                    <div>
                                        <strong class="has-text-dark">${displayName}</strong>
                                    </div>
                                    <small class="has-text-dark">${item.formatted_date}</small>
                                </div>
                                <div class="has-text-dark">${contentHTML}</div>
                            </div>
                        </div>
                    </div>
                    <figure class="media-right">
                        <p class="image is-48x48">${profileImage}</p>
                    </figure>
                </article>
            `;
        }
    }
    
    function updateMessageStatus(newStatus) {
        const statusElement = document.querySelector('.tag[class*="is-"]');
        if (statusElement && statusElement.textContent.toLowerCase() !== newStatus.toLowerCase()) {
            statusElement.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
            statusElement.className = statusElement.className.replace(/is-(danger|info|success|dark|warning)/g, 
                newStatus === 'unread' ? 'is-danger' :
                newStatus === 'read' ? 'is-info' :
                newStatus === 'responded' ? 'is-success' :
                newStatus === 'closed' ? 'is-dark' :
                newStatus === 'archived' ? 'is-warning' : 'is-info'
            );
        }
    }
    
    function appendNewAttachments(newAttachments) {
        // Find or create the attachments section
        let attachmentsSection = document.querySelector('.content h5.title.is-6');
        let attachmentsContainer;
        
        if (!attachmentsSection || !attachmentsSection.textContent.includes('Attachments')) {
            // Create new attachments section if it doesn't exist
            const messageContent = document.querySelector('.content');
            if (messageContent) {
                const attachmentsSectionHTML = `
                    <div class="content">
                        <h5 class="title is-6">
                            <span class="icon"><i class="fas fa-paperclip"></i></span>
                            Attachments (${newAttachments.length})
                        </h5>
                        <div class="field is-grouped is-grouped-multiline">
                        </div>
                    </div>
                `;
                
                // Insert before the discussion section
                const discussionSection = document.querySelector('.content h4.title.is-5');
                if (discussionSection) {
                    discussionSection.parentElement.insertAdjacentHTML('beforebegin', attachmentsSectionHTML);
                } else {
                    messageContent.insertAdjacentHTML('beforeend', attachmentsSectionHTML);
                }
                
                attachmentsSection = document.querySelector('.content h5.title.is-6');
                attachmentsContainer = attachmentsSection.parentElement.querySelector('.field.is-grouped.is-grouped-multiline');
            }
        } else {
            // Update existing attachments section
            attachmentsContainer = attachmentsSection.parentElement.querySelector('.field.is-grouped.is-grouped-multiline');
            
            // Update the count in the title
            const currentCount = attachmentsContainer.children.length;
            const newCount = currentCount + newAttachments.length;
            attachmentsSection.innerHTML = `
                <span class="icon"><i class="fas fa-paperclip"></i></span>
                Attachments (${newCount})
            `;
        }
        
        // Add each new attachment
        newAttachments.forEach(attachment => {
            const attachmentHtml = createAttachmentHTML(attachment);
            attachmentsContainer.insertAdjacentHTML('beforeend', attachmentHtml);
        });
    }
    
    function createAttachmentHTML(attachment) {
        let iconClass = 'fas fa-file fa-2x has-text-grey-dark';
        
        if (attachment.is_image) {
            iconClass = 'fas fa-image fa-2x has-text-info';
        } else if (attachment.mime_type.includes('pdf')) {
            iconClass = 'fas fa-file-pdf fa-2x has-text-danger';
        } else if (attachment.mime_type.includes('video')) {
            iconClass = 'fas fa-video fa-2x has-text-primary';
        } else if (attachment.mime_type.includes('audio')) {
            iconClass = 'fas fa-volume-up fa-2x has-text-warning';
        } else if (attachment.mime_type.includes('zip') || attachment.mime_type.includes('archive')) {
            iconClass = 'fas fa-file-archive fa-2x has-text-grey';
        }
        
        return `
            <div class="control" style="opacity: 0; animation: fadeIn 0.5s ease-in forwards;">
                <div class="card" style="width: 300px;">
                    <div class="card-content">
                        <div class="media">
                            <div class="media-left">
                                <span class="icon is-large">
                                    <i class="${iconClass}"></i>
                                </span>
                            </div>
                            <div class="media-content">
                                <p class="title is-6" style="word-break: break-word;">${attachment.original_filename}</p>
                                <p class="subtitle is-7">
                                    ${attachment.formatted_file_size}<br>
                                    <small>Uploaded by ${attachment.display_name}</small><br>
                                    <small>${attachment.formatted_date}</small>
                                </p>
                            </div>
                        </div>
                        <div class="field is-grouped">
                            <div class="control">
                                <a href="download-attachment.php?id=${attachment.id}" 
                                   class="button is-small is-primary">
                                    <span class="icon"><i class="fas fa-download"></i></span>
                                    <span>Download</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Start checking for new messages every 5 seconds
    const messageCheckInterval = setInterval(checkForNewMessages, 5000);
    
    // Clean up interval when page is hidden/unloaded
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(messageCheckInterval);
        } else {
            // Restart interval when page becomes visible
            setInterval(checkForNewMessages, 5000);
        }
    });
    <?php endif; ?>
    
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

// Image modal function
function showImageModal(filename, imageUrl) {
    Swal.fire({
        title: filename,
        imageUrl: imageUrl,
        imageAlt: filename,
        showConfirmButton: false,
        showCloseButton: true,
        width: '90%',
        padding: '1rem',
        background: '#fff',
        customClass: {
            popup: 'has-text-dark',
            title: 'has-text-dark',
            image: 'swal-image-responsive'
        }
    });
}

// File upload functions
function updateFileList(input) {
    const fileList = document.getElementById('file-list');
    fileList.innerHTML = '';
    
    if (input.files.length > 0) {
        const container = document.createElement('div');
        container.className = 'field is-grouped is-grouped-multiline';
        
        Array.from(input.files).forEach((file, index) => {
            const control = document.createElement('div');
            control.className = 'control';
            
            const tag = document.createElement('span');
            tag.className = 'tag is-info is-medium';
            tag.innerHTML = `
                <span class="icon">
                    <i class="fas fa-file"></i>
                </span>
                <span>${file.name} (${formatFileSize(file.size)})</span>
                <button class="delete is-small" type="button" onclick="removeFile(${index})"></button>
            `;
            
            control.appendChild(tag);
            container.appendChild(control);
        });
        
        fileList.appendChild(container);
    }
}

function removeFile(index) {
    const input = document.querySelector('input[name="attachments[]"]');
    const dt = new DataTransfer();
    
    Array.from(input.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    input.files = dt.files;
    updateFileList(input);
}

function formatFileSize(bytes) {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return Math.round(size * 100) / 100 + ' ' + units[unitIndex];
}
</script>

<style>
.swal-image-responsive {
    max-width: 100% !important;
    max-height: 80vh !important;
    object-fit: contain !important;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php
$scripts = ob_get_clean();
include 'layout.php';
?>
