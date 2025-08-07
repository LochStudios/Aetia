<?php
// admin/archived-messages.php - Admin interface for viewing archived messages
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';
require_once __DIR__ . '/../includes/FileUploader.php';

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
$messageDiscussion = [];
$messageAttachments = [];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($messageId) {
    $currentMessage = $messageModel->getMessage($messageId);
    if ($currentMessage && $currentMessage['status'] === 'closed') {
        $messageComments = $messageModel->getMessageComments($messageId);
        $messageDiscussion = $messageModel->getMessageDiscussion($messageId);
        $messageAttachments = $messageModel->getMessageAttachments($messageId);
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
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li><a href="users.php"><span class="icon is-small"><i class="fas fa-users-cog"></i></span><span>Users</span></a></li>
            <li><a href="messages.php"><span class="icon is-small"><i class="fas fa-envelope-open-text"></i></span><span>Messages</span></a></li>
            <li><a href="create-message.php"><span class="icon is-small"><i class="fas fa-plus"></i></span><span>New Message</span></a></li>
            <li><a href="send-emails.php"><span class="icon is-small"><i class="fas fa-paper-plane"></i></span><span>Send Emails</span></a></li>
            <li><a href="email-logs.php"><span class="icon is-small"><i class="fas fa-chart-line"></i></span><span>Email Logs</span></a></li>
            <li><a href="contact-form.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Contact Forms</span></a></li>
            <li><a href="contracts.php"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Contracts</span></a></li>
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-archive"></i></span><span>Archived Messages</span></a></li>
        </ul>
    </nav>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="notification is-success is-light">
            <button class="delete"></button>
            <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
        <?php unset($_SESSION['message']); endif; ?>    <?php if (isset($_SESSION['error'])): ?>
    <div class="notification is-danger is-light">
        <button class="delete"></button>
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <div class="level mb-4">
        <div class="level-left">
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fas fa-archive"></i></span>
                Archived Messages
            </h2>
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
                
                <!-- Attachments -->
                <?php if (!empty($messageAttachments)): ?>
                <div class="content">
                    <h5 class="title is-6">
                        <span class="icon"><i class="fas fa-paperclip"></i></span>
                        Attachments
                    </h5>
                    
                    <div class="columns is-multiline">
                        <?php foreach ($messageAttachments as $attachment): ?>
                        <div class="column is-half">
                            <div class="box attachment-card has-background-grey-dark has-text-white p-3">
                                <div class="media">
                                    <div class="media-left">
                                        <figure class="image is-48x48">
                                            <?php
                                            $iconClass = 'fas fa-file fa-2x has-text-grey-dark';
                                            $isImage = isset($attachment['is_image']) ? $attachment['is_image'] : (strpos($attachment['mime_type'] ?? '', 'image/') === 0);
                                            
                                            if ($isImage) {
                                                $iconClass = 'fas fa-image fa-2x has-text-info';
                                            } elseif (strpos($attachment['mime_type'] ?? '', 'pdf') !== false) {
                                                $iconClass = 'fas fa-file-pdf fa-2x has-text-danger';
                                            } elseif (strpos($attachment['mime_type'] ?? '', 'video') !== false) {
                                                $iconClass = 'fas fa-video fa-2x has-text-primary';
                                            } elseif (strpos($attachment['mime_type'] ?? '', 'audio') !== false) {
                                                $iconClass = 'fas fa-volume-up fa-2x has-text-warning';
                                            } elseif (strpos($attachment['mime_type'] ?? '', 'zip') !== false || strpos($attachment['mime_type'] ?? '', 'archive') !== false) {
                                                $iconClass = 'fas fa-file-archive fa-2x has-text-warning';
                                            }
                                            ?>
                                            <span class="icon is-large">
                                                <i class="<?= $iconClass ?>"></i>
                                            </span>
                                        </figure>
                                    </div>
                                    <div class="media-content">
                                        <p class="title is-6 attachment-filename"><?= htmlspecialchars($attachment['original_filename']) ?></p>
                                        <p class="subtitle is-7 has-text-grey"><?= FileUploader::formatFileSize($attachment['file_size']) ?></p>
                                        <div class="buttons are-small">
                                            <a href="../download-attachment.php?id=<?= $attachment['id'] ?>" class="button is-small is-info">
                                                <span class="icon"><i class="fas fa-download"></i></span>
                                                <span>Download</span>
                                            </a>
                                            <?php if ($isImage): ?>
                                            <button class="button is-small is-primary" 
                                                    onclick="showImageModal('<?= htmlspecialchars($attachment['original_filename']) ?>', '../view-image.php?id=<?= $attachment['id'] ?>')">
                                                <span class="icon"><i class="fas fa-eye"></i></span>
                                                <span>View</span>
                                            </button>
                                            <?php endif; ?>
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
                                            <?= nl2br(htmlspecialchars($item['comment'])) ?>
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
                                            <?= nl2br(htmlspecialchars($item['comment'])) ?>
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
            image: 'swal-image-responsive'
        }
    });
}
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
