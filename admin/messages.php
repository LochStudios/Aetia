<?php
// admin/messages.php - Admin interface for managing user messages
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';
require_once __DIR__ . '/../includes/FileUploader.php';
require_once __DIR__ . '/../includes/FormTokenManager.php';
require_once __DIR__ . '/../includes/LinkConverter.php';

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
        // Validate form token to prevent duplicate submissions
        $formName = $_POST['form_name'] ?? '';
        $formToken = $_POST['form_token'] ?? '';
        
        // Debug information
        if (empty($formName)) {
            $error = 'Invalid form submission: Missing form name. Please refresh the page and try again.';
        } elseif (empty($formToken)) {
            $error = 'Invalid form submission: Missing form token. Please refresh the page and try again.';
        } elseif (!FormTokenManager::validateToken($formName, $formToken)) {
            $error = 'This form has already been submitted or has expired. Please refresh the page and try again.';
        } elseif (FormTokenManager::isRecentSubmission($formName)) {
            $error = 'Please wait a moment before submitting again.';
        } else {
            // Process the form action
            switch ($_POST['action']) {
            case 'add_comment':
                $messageId = intval($_POST['message_id']);
                $comment = trim($_POST['comment']);
                
                if (!empty($comment)) {
                    $result = $messageModel->addComment($messageId, $_SESSION['user_id'], $comment, true);
                    if ($result['success']) {
                        // Handle file uploads if any
                        if (!empty($_FILES['attachments']['name'][0])) {
                            $fileUploader = new FileUploader();
                            $uploadErrors = [];
                            
                            // Get the message details to use the message owner's ID for file organization
                            $messageDetails = $messageModel->getMessage($messageId);
                            $messageOwnerId = $messageDetails ? $messageDetails['from_user_id'] : $_SESSION['user_id'];
                            
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
                                    
                                    // Use message owner's ID for file organization consistency
                                    $uploadResult = $fileUploader->uploadMessageAttachment($file, $messageOwnerId, $messageId);
                                    if ($uploadResult['success']) {
                                        // Save attachment record to database
                                        $attachResult = $messageModel->addAttachment(
                                            $messageId,
                                            $_SESSION['user_id'],
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
                                $error = "Admin response added but some attachments failed: " . implode(', ', $uploadErrors);
                            } else {
                                $message = 'Admin response added successfully!';
                            }
                        } else {
                            $message = 'Admin response added successfully!';
                        }
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
                    // Check if this is an AJAX request
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                        // For AJAX requests, return JSON response
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Message archived successfully!']);
                        exit;
                    } else {
                        // For regular form submissions, redirect
                        $message = 'Message archived successfully!';
                        header('Location: messages.php');
                        exit;
                    }
                } else {
                    $error = $result['message'] ?? 'Failed to archive message';
                }
                break;
            }
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
$messageDiscussion = [];
$messageAttachments = [];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($messageId) {
    $currentMessage = $messageModel->getMessage($messageId);
    if ($currentMessage) {
        $messageComments = $messageModel->getMessageComments($messageId);
        $messageDiscussion = $messageModel->getMessageDiscussion($messageId);
        $messageAttachments = $messageModel->getMessageAttachments($messageId);
    }
}

// Get messages for admin view
$messages = $messageModel->getAllMessages($limit, $offset, $statusFilter, $tagFilter);
$availableTags = $messageModel->getAvailableTags();

$pageTitle = $currentMessage ? htmlspecialchars($currentMessage['subject']) . ' | Admin Messages' : 'Admin Messages | Aetia';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li><a href="users.php"><span class="icon is-small"><i class="fas fa-users-cog"></i></span><span>Users</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-envelope-open-text"></i></span><span>Messages</span></a></li>
            <li><a href="create-message.php"><span class="icon is-small"><i class="fas fa-plus"></i></span><span>New Message</span></a></li>
            <li><a href="send-emails.php"><span class="icon is-small"><i class="fas fa-paper-plane"></i></span><span>Send Emails</span></a></li>
            <li><a href="email-logs.php"><span class="icon is-small"><i class="fas fa-chart-line"></i></span><span>Email Logs</span></a></li>
            <li><a href="contact-form.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Contact Forms</span></a></li>
            <li><a href="contracts.php"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Contracts</span></a></li>
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
            <li><a href="archived-messages.php"><span class="icon is-small"><i class="fas fa-archive"></i></span><span>Archived Messages</span></a></li>
        </ul>
    </nav>
    
    <?php if ($message): ?>
        <div class="notification is-success is-light">
            <button class="delete"></button>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>    <?php if ($error): ?>
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
                                'closed' => 'fas fa-archive text-orange',
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
                                To: <?= $msg['target_display_name'] === 'admin' ? 'Aetia Talent Agency' : htmlspecialchars($msg['target_display_name']) ?>
                                <span class="ml-2">•</span>
                                <span class="ml-1"><?= formatDateForUser($msg['created_at'], 'M j, Y') ?></span>
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
                            <h2 class="title is-3 message-title"><?= htmlspecialchars($currentMessage['subject']) ?></h2>
                            <p class="subtitle is-6">
                                To: <strong><?= $currentMessage['target_display_name'] === 'admin' ? 'Aetia Talent Agency' : htmlspecialchars($currentMessage['target_display_name']) ?></strong>
                                <span class="ml-2">•</span>
                                <span class="ml-2"><?= formatDateForUser($currentMessage['created_at']) ?></span>
                                <span class="ml-2">•</span>
                                <span class="ml-2">By: <?= $currentMessage['created_by_display_name'] === 'admin' ? 'Aetia Talent Agency' : htmlspecialchars($currentMessage['created_by_display_name']) ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="level-right">
                        <div class="field has-addons">
                            <div class="control">
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="message_id" value="<?= $currentMessage['id'] ?>">
                                    <?= FormTokenManager::getTokenField('update_status_' . $currentMessage['id']) ?>
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
                    <div class="box has-background-grey-dark has-text-light">
                        <?= LinkConverter::processMessageText($currentMessage['message']) ?>
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
                            <div class="card attachment-card">
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
                                            <p class="title is-6 attachment-filename"><?= htmlspecialchars($attachment['original_filename']) ?></p>
                                            <p class="subtitle is-7">
                                                <?= FileUploader::formatFileSize($attachment['file_size']) ?><br>
                                                <small>Uploaded by <?= htmlspecialchars($attachment['uploaded_by_display_name']) ?></small><br>
                                                <small><?= formatDateForUser($attachment['uploaded_at']) ?></small>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="field is-grouped">
                                        <div class="control">
                                            <a href="/download-attachment.php?id=<?= $attachment['id'] ?>" 
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
                                                <?= $item['display_name'] === 'admin' ? 'System Administrator' : htmlspecialchars($item['display_name']) ?>
                                            </strong>
                                            <span class="tag is-info is-small ml-1">Admin</span>
                                        </div>
                                        <small class="has-text-light">
                                            <?= formatDateForUser($item['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="has-text-light">
                                        <?php if ($item['type'] === 'comment'): ?>
                                            <?= LinkConverter::processMessageText($item['comment']) ?>
                                        <?php elseif ($item['type'] === 'image'): ?>
                                            <div class="has-text-centered">
                                                <p class="mb-2"><strong>Shared an image:</strong></p>
                                                <figure class="image discussion-image">
                                                    <img src="../view-image.php?id=<?= $item['attachment_id'] ?>" 
                                                         alt="<?= htmlspecialchars($item['original_filename']) ?>"
                                                         class="discussion-image img"
                                                         onclick="showImageModal('<?= htmlspecialchars($item['original_filename']) ?>', '../view-image.php?id=<?= $item['attachment_id'] ?>')">
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
                                <div class="box has-background-grey-dark has-text-light">
                                    <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                        <div>
                                            <strong class="has-text-light">
                                                <?= htmlspecialchars($item['display_name']) ?>
                                            </strong>
                                        </div>
                                        <small class="has-text-light">
                                            <?= formatDateForUser($item['created_at']) ?>
                                        </small>
                                    </div>
                                    <div class="has-text-light">
                                        <?php if ($item['type'] === 'comment'): ?>
                                            <?= LinkConverter::processMessageText($item['comment']) ?>
                                        <?php elseif ($item['type'] === 'image'): ?>
                                            <div class="has-text-centered">
                                                <p class="mb-2"><strong>Shared an image:</strong></p>
                                                <figure class="image discussion-image">
                                                    <img src="../view-image.php?id=<?= $item['attachment_id'] ?>" 
                                                         alt="<?= htmlspecialchars($item['original_filename']) ?>"
                                                         class="discussion-image img"
                                                         onclick="showImageModal('<?= htmlspecialchars($item['original_filename']) ?>', '../view-image.php?id=<?= $item['attachment_id'] ?>')">
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
                
                <!-- Add Admin Response Form -->
                <?php if ($currentMessage['status'] !== 'closed'): ?>
                <div class="content">
                    <h5 class="title is-6">Add Admin Response</h5>
                    <form id="comment-form" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_comment">
                        <input type="hidden" name="message_id" value="<?= $currentMessage['id'] ?>">
                        <input type="hidden" name="UPLOAD_IDENTIFIER" id="upload-id" value="">
                        <?= FormTokenManager::getTokenField('add_comment_' . $currentMessage['id']) ?>
                        
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
                                <div id="upload-progress" class="mt-3 upload-progress-hidden">
                                    <div class="notification is-info">
                                        <div class="is-flex is-align-items-center">
                                            <span class="icon mr-3">
                                                <i class="fas fa-spinner fa-spin"></i>
                                            </span>
                                            <div class="is-flex-grow-1">
                                                <div class="is-flex is-justify-content-space-between mb-2">
                                                    <span id="upload-status">Uploading files...</span>
                                                    <span id="upload-percentage">0%</span>
                                                </div>
                                                <progress id="upload-progress-bar" class="progress is-primary" value="0" max="100">0%</progress>
                                                <div class="is-size-7 has-text-grey-dark mt-1">
                                                    <span id="upload-speed"></span> • 
                                                    <span id="upload-eta"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="help">Maximum file size: 1GB per file. Supported formats: Images, PDFs, Documents, Videos, Audio files, and Archives.</p>
                            </div>
                        </div>
                        
                        <div class="field">
                            <div class="control is-flex is-justify-content-space-between">
                                <!-- Archive Message Button - only show if message is not closed -->
                                <?php if ($currentMessage['status'] !== 'closed'): ?>
                                <button class="button is-warning" type="button" onclick="archiveMessage(<?= $currentMessage['id'] ?>)">
                                    <span class="icon"><i class="fas fa-archive"></i></span>
                                    <span>Archive Message</span>
                                </button>
                                <?php else: ?>
                                <div></div> <!-- Empty div to maintain layout -->
                                <?php endif; ?>
                                
                                <!-- Send Admin Response Button -->
                                <button id="submit-button" class="button is-info" type="submit">
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
// Form tokens for JavaScript submissions
const formTokens = {
    archiveMessage: {
        token: '<?= $messageId ? FormTokenManager::generateToken('archive_message_' . $messageId) : '' ?>',
        name: '<?= $messageId ? 'archive_message_' . $messageId : '' ?>'
    }
};
document.addEventListener('DOMContentLoaded', function() {
    // Real-time message updates
    <?php if ($messageId): ?>
    let lastCheckTime = new Date().toISOString();
    let isCheckingMessages = false;
    
    function checkForNewMessages() {
        if (isCheckingMessages) return;
        
        isCheckingMessages = true;
        
        fetch(`../api/check-new-messages.php?message_id=<?= $messageId ?>&last_check=${encodeURIComponent(lastCheckTime)}`)
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
                    <figure class="image discussion-image">
                        <img src="../view-image.php?id=${item.attachment_id}" 
                             alt="${item.original_filename}"
                             class="discussion-image img"
                             onclick="showImageModal('${item.original_filename}', '../view-image.php?id=${item.attachment_id}')">
                    </figure>
                    <p class="is-size-7 has-text-grey mt-2">
                        ${item.original_filename} (${item.formatted_file_size})
                    </p>
                </div>
            `;
        }
        
        const profileImage = item.profile_image ? 
            `<img src="${item.profile_image.startsWith('http') ? item.profile_image : '../' + item.profile_image}" alt="Profile Picture" class="profile-image">` :
            `<span class="icon is-large has-text-grey"><i class="fas fa-user-circle fa-2x"></i></span>`;
        
        if (isAdmin) {
            return `
                <article class="media fade-in-new">
                    <figure class="media-left">
                        <p class="image is-48x48">${profileImage}</p>
                    </figure>
                    <div class="media-content">
                        <div class="content">
                            <div class="box has-background-info-dark has-text-light">
                                <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                    <div>
                                        <strong class="has-text-light">${displayName}</strong>
                                        <span class="tag is-info is-small ml-1">Admin</span>
                                    </div>
                                    <small class="has-text-light">${item.formatted_date}</small>
                                </div>
                                <div class="has-text-light">${contentHTML}</div>
                            </div>
                        </div>
                    </div>
                </article>
            `;
        } else {
            return `
                <article class="media fade-in-new">
                    <div class="media-content">
                        <div class="content">
                            <div class="box has-background-grey-dark has-text-light">
                                <div class="is-flex is-justify-content-space-between is-align-items-start mb-2">
                                    <div>
                                        <strong class="has-text-light">${displayName}</strong>
                                    </div>
                                    <small class="has-text-light">${item.formatted_date}</small>
                                </div>
                                <div class="has-text-light">${contentHTML}</div>
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
                newStatus === 'closed' ? 'is-warning' : 'is-info'
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
            <div class="control fade-in-new">
                <div class="card attachment-card">
                    <div class="card-content">
                        <div class="media">
                            <div class="media-left">
                                <span class="icon is-large">
                                    <i class="${iconClass}"></i>
                                </span>
                            </div>
                            <div class="media-content">
                                <p class="title is-6 attachment-filename">${attachment.original_filename}</p>
                                <p class="subtitle is-7">
                                    ${attachment.formatted_file_size}<br>
                                    <small>Uploaded by ${attachment.display_name}</small><br>
                                    <small>${attachment.formatted_date}</small>
                                </p>
                            </div>
                        </div>
                        <div class="field is-grouped">
                            <div class="control">
                                <a href="/download-attachment.php?id=${attachment.id}" 
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
    
    // Smooth scrolling for comment submission
    const commentForm = document.querySelector('form[action*="add_comment"]');
    if (commentForm) {
        commentForm.addEventListener('submit', function() {
            setTimeout(function() {
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
            }, 100);
        });
    }
    <?php else: ?>
    // Auto-refresh message list every 60 seconds when viewing message list
    setInterval(function() {
        // You could implement AJAX refresh here if needed
    }, 60000);
    <?php endif; ?>
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
            
            // Add form token
            formData.append('form_token', formTokens.archiveMessage.token);
            formData.append('form_name', formTokens.archiveMessage.name);
            
            // Submit form
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(response => {
                if (response.ok) {
                    // Check if response is JSON
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        // Handle redirect or HTML response
                        return { success: true };
                    }
                } else {
                    throw new Error('Archive failed');
                }
            }).then(data => {
                Swal.fire({
                    title: 'Message Closed!',
                    text: 'The message has been closed and archived successfully.',
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

// Upload progress functionality
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('comment-form');
    if (!form) return; // Exit if form doesn't exist
    
    const uploadIdField = document.getElementById('upload-id');
    const uploadProgress = document.getElementById('upload-progress');
    const uploadProgressBar = document.getElementById('upload-progress-bar');
    const uploadPercentage = document.getElementById('upload-percentage');
    const uploadStatus = document.getElementById('upload-status');
    const uploadSpeed = document.getElementById('upload-speed');
    const uploadEta = document.getElementById('upload-eta');
    const submitButton = document.getElementById('submit-button');
    
    if (form) {
        form.addEventListener('submit', function(e) {
            const fileInput = form.querySelector('input[type="file"]');
            const files = fileInput.files;
            // Only use AJAX upload if files are selected
            if (files.length > 0) {
                e.preventDefault();
                submitWithProgress();
            }
            // Otherwise let the form submit normally
        });
    }
    
    function submitWithProgress() {
        // Generate unique upload ID
        const uploadId = 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        uploadIdField.value = uploadId;
        
        // Show progress bar
        uploadProgress.classList.remove('upload-progress-hidden');
        submitButton.disabled = true;
        
        // Create FormData
        const formData = new FormData(form);
        
        // Start progress tracking
        const progressInterval = setInterval(() => {
            checkUploadProgress(uploadId, progressInterval);
        }, 500);
        
        // Submit form
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            clearInterval(progressInterval);
            if (response.ok) {
                return response.text();
            }
            throw new Error('Upload failed');
        })
        .then(data => {
            // Check if upload was successful
            if (data.includes('success') || data.includes('comment added') || !data.includes('error')) {
                uploadStatus.textContent = 'Upload completed successfully!';
                uploadProgressBar.value = 100;
                uploadPercentage.textContent = '100%';
                
                setTimeout(() => {
                    // Hide progress bar before reload
                    uploadProgress.classList.add('upload-progress-hidden');
                    // Reload page to show new content
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error('Server error occurred');
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            uploadStatus.textContent = 'Upload failed: ' + error.message;
            uploadProgress.querySelector('.notification').classList.remove('is-info');
            uploadProgress.querySelector('.notification').classList.add('is-danger');
            submitButton.disabled = false;
            
            // Hide progress bar after a delay to show error message
            setTimeout(() => {
                uploadProgress.classList.add('upload-progress-hidden');
            }, 3000);
        });
    }
    
    function checkUploadProgress(uploadId, progressInterval) {
        fetch(`../api/upload-progress.php?upload_id=${uploadId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    return;
                }
                
                const percentage = data.percentage || 0;
                uploadProgressBar.value = percentage;
                uploadPercentage.textContent = percentage + '%';
                
                // Format upload speed
                if (data.speed_last > 0) {
                    uploadSpeed.textContent = formatFileSize(data.speed_last) + '/s';
                } else {
                    uploadSpeed.textContent = '';
                }
                
                // Format ETA
                if (data.est_sec > 0) {
                    const minutes = Math.floor(data.est_sec / 60);
                    const seconds = data.est_sec % 60;
                    if (minutes > 0) {
                        uploadEta.textContent = `${minutes}m ${seconds}s remaining`;
                    } else {
                        uploadEta.textContent = `${seconds}s remaining`;
                    }
                } else {
                    uploadEta.textContent = '';
                }
                
                // Update status based on progress
                if (percentage > 0 && percentage < 100) {
                    uploadStatus.textContent = `Uploading ${formatFileSize(data.bytes_uploaded)} of ${formatFileSize(data.bytes_total)}`;
                } else if (percentage >= 100) {
                    uploadStatus.textContent = 'Processing upload...';
                    clearInterval(progressInterval);
                }
            })
            .catch(error => {
                console.log('Progress check failed:', error);
            });
    }
    
    // Prevent double submissions by disabling submit buttons after click
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(button => {
                if (!button.disabled) {
                    button.disabled = true;
                    button.textContent = button.textContent.includes('...') ? button.textContent : button.textContent + '...';
                    // Re-enable after 3 seconds as a safety measure
                    setTimeout(() => {
                        button.disabled = false;
                        button.textContent = button.textContent.replace('...', '');
                    }, 3000);
                }
            });
        });
    });
});
</script>

<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
