<?php
// archived-messages.php - User interface for viewing archived messages
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
require_once __DIR__ . '/services/ImageUploadService.php';

// Helper function to process profile image URLs
function processProfileImageUrl($profileImage, $userId = null) {
    if (empty($profileImage)) {
        return null;
    }
    // If it's already a proper URL (starts with http), return as-is
    if (strpos($profileImage, 'http') === 0) {
        return $profileImage;
    }
    // If it's a flag pattern like "user-X-has-image", get S3 presigned URL directly
    if (preg_match('/^user-(\d+)-has-image/', $profileImage, $matches)) {
        $imageUserId = $matches[1];
        try {
            $imageUploadService = new ImageUploadService();
            $presignedUrl = $imageUploadService->getPresignedProfileImageUrl($imageUserId, 'jpeg', 30);
            return $presignedUrl ?: null;
        } catch (Exception $e) {
            error_log("Failed to get profile image URL for user {$imageUserId}: " . $e->getMessage());
            return null;
        }
    }
    // If it looks like a file path and we have a user ID, try S3
    if ($userId && !strpos($profileImage, '/')) {
        try {
            $imageUploadService = new ImageUploadService();
            $presignedUrl = $imageUploadService->getPresignedProfileImageUrl($userId, 'jpeg', 30);
            return $presignedUrl ?: null;
        } catch (Exception $e) {
            error_log("Failed to get profile image URL for user {$userId}: " . $e->getMessage());
            return null;
        }
    }
    // Default case - treat as relative path
    return $profileImage;
}

$messageModel = new Message();
$userId = $_SESSION['user_id'];

// Get current message ID if viewing one
$currentMessage = null;
$messageComments = [];
$messageDiscussion = [];
$messageAttachments = [];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($messageId) {
    $currentMessage = $messageModel->getMessage($messageId, $userId);
    if ($currentMessage && $currentMessage['status'] === 'closed') {
        $messageComments = $messageModel->getMessageComments($messageId);
        $messageDiscussion = $messageModel->getMessageDiscussion($messageId);
        $messageAttachments = $messageModel->getMessageAttachments($messageId);
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

$pageTitle = $currentMessage ? 'Archived: ' . htmlspecialchars($currentMessage['subject']) . ' | Messages' : 'Archived Messages | Aetia';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="messages.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Messages</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-archive"></i></span><span>Archived</span></a></li>
        </ul>
    </nav>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="notification is-success is-light">
        <button class="delete"></button>
        <?= htmlspecialchars($_SESSION['message']) ?>
    </div>
    <?php unset($_SESSION['message']); endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <div class="notification is-danger is-light">
        <button class="delete"></button>
        <?= htmlspecialchars($_SESSION['error']) ?>
    </div>
    <?php unset($_SESSION['error']); endif; ?>

    <div class="columns">
        <!-- Archived Messages List Sidebar -->
        <div class="column is-4">
            <div class="box">
                <div class="level mb-4">
                    <div class="level-left">
                        <h3 class="title is-4 has-text-warning mb-0">
                            <span class="icon"><i class="fas fa-archive"></i></span>
                            Archived Messages
                        </h3>
                    </div>
                    <div class="level-right">
                        <div class="field is-grouped">
                            <div class="control">
                                <div class="tags has-addons">
                                    <span class="tag is-dark">Total</span>
                                    <span class="tag is-warning"><?= count($archivedMessages) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Back to Messages -->
                <div class="field mb-4">
                    <a href="messages.php" class="button is-primary is-fullwidth">
                        <span class="icon"><i class="fas fa-arrow-left"></i></span>
                        <span>Back to Messages</span>
                    </a>
                </div>
                
                <?php if (empty($archivedMessages)): ?>
                <div class="has-text-centered has-text-white">
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
                       href="?id=<?= $msg['id'] ?><?= $sidebarPage > 1 ? '&sidebar_page=' . $sidebarPage : '' ?>"
                       style="text-decoration: none;">
                        <span class="panel-icon">
                            <i class="fas fa-box-archive has-text-warning"></i>
                        </span>
                        <div class="is-flex-grow-1">
                            <div class="is-flex is-justify-content-space-between is-align-items-center">
                                <strong class="has-text-overflow-ellipsis" style="flex: 1; margin-right: 0.5rem;">
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
                            <div class="is-size-7 has-text-white">
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
        
        <!-- Message Detail -->
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
                            <p class="subtitle is-6 has-text-white">
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
                                            } elseif (strpos($attachment['mime_type'], 'pdf') !== false) {
                                                $iconClass = 'fas fa-file-pdf fa-2x has-text-danger';
                                            } elseif (strpos($attachment['mime_type'], 'video') !== false) {
                                                $iconClass = 'fas fa-video fa-2x has-text-primary';
                                            } elseif (strpos($attachment['mime_type'], 'audio') !== false) {
                                                $iconClass = 'fas fa-volume-up fa-2x has-text-warning';
                                            } elseif (strpos($attachment['mime_type'], 'zip') !== false || strpos($attachment['mime_type'], 'archive') !== false) {
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
                                            <a href="download-attachment.php?id=<?= $attachment['id'] ?>" class="button is-small is-info">
                                                <span class="icon"><i class="fas fa-download"></i></span>
                                                <span>Download</span>
                                            </a>
                                            <?php if ($isImage): ?>
                                            <button class="button is-small is-primary" 
                                                    onclick="showImageModal('<?= htmlspecialchars($attachment['original_filename']) ?>', 'view-image.php?id=<?= $attachment['id'] ?>')">
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
                                                    <img src="view-image.php?id=<?= $item['attachment_id'] ?>" 
                                                         alt="<?= htmlspecialchars($item['original_filename']) ?>"
                                                         class="discussion-image img"
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
                                                    <img src="view-image.php?id=<?= $item['attachment_id'] ?>" 
                                                         alt="<?= htmlspecialchars($item['original_filename']) ?>"
                                                         class="discussion-image img"
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
            </div>
            
            <?php else: ?>
            <!-- No Message Selected -->
            <div class="panel-block is-flex-direction-column has-text-centered p-5">
                <span class="icon is-large has-text-white mb-4">
                    <i class="fas fa-archive fa-4x"></i>
                </span>
                <h3 class="title is-4 has-text-white mb-4">Select an Archived Message</h3>
                <p class="has-text-white mb-4">Choose a message from the sidebar to view its archived content.</p>
                <?php if (empty($archivedMessages)): ?>
                <p class="has-text-white mb-4">You don't have any archived messages yet.</p>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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
include 'layout.php';
?>
