<?php
// admin/create-message.php - Admin interface for creating new user messages
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/FileUploader.php';
require_once __DIR__ . '/../includes/FormTokenManager.php';

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form token
    $formName = $_POST['form_name'] ?? '';
    $formToken = $_POST['form_token'] ?? '';
    
    if (empty($formName) || empty($formToken)) {
        $error = 'Invalid form submission. Please refresh the page and try again.';
    } elseif (!FormTokenManager::validateToken($formName, $formToken)) {
        $error = 'This form has already been submitted or has expired. Please refresh the page and try again.';
    } elseif (FormTokenManager::isRecentSubmission($formName)) {
        $error = 'Please wait a moment before submitting again.';
    } else {
        $userId = intval($_POST['user_id']);
        $subject = trim($_POST['subject']);
        $messageText = trim($_POST['message']);
        $priority = $_POST['priority'];
        $tags = trim($_POST['tags'] ?? '');
        $manualReview = isset($_POST['manual_review']) ? true : false;
        $manualReviewReason = $manualReview ? trim($_POST['manual_review_reason'] ?? '') : null;
        
        if (empty($subject) || empty($messageText) || empty($userId)) {
            $error = 'All fields are required';
        } else {
            $result = $messageModel->createMessage($userId, $subject, $messageText, $_SESSION['user_id'], $priority, $tags);
            if ($result['success']) {
                $messageId = $result['message_id'];
                
                // Mark for manual review if requested
                if ($manualReview) {
                    $reviewResult = $messageModel->toggleManualReview($messageId, $_SESSION['user_id'], $manualReviewReason);
                    if (!$reviewResult['success']) {
                        error_log("Failed to mark message for manual review: " . $reviewResult['message']);
                    }
                }
                
                // Handle file uploads if any
                $uploadErrors = [];
                if (!empty($_FILES['attachments']['name'][0])) {
                    $fileUploader = new FileUploader();
                    
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
                            
                            $uploadResult = $fileUploader->uploadMessageAttachment($file, $_SESSION['user_id'], $messageId);
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
                }
                
                if (!empty($uploadErrors)) {
                    $_SESSION['success_message'] = 'Message sent successfully! Some attachments failed: ' . implode(', ', $uploadErrors);
                } else {
                    $_SESSION['success_message'] = 'Message sent successfully! The user has been notified by email.';
                }
                
                header('Location: messages.php?id=' . $messageId);
                exit;
            } else {
                $error = $result['message'] ?? 'Failed to send message';
            }
        }
    }
}

// Handle AJAX user search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    header('Content-Type: application/json');
    $users = $messageModel->searchUsers($_GET['search']);
    echo json_encode($users);
    exit;
}

$pageTitle = 'Create Message | Admin | Aetia';
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
            <li><a href="archived-messages.php"><span class="icon is-small"><i class="fas fa-archive"></i></span><span>Archived Messages</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-plus"></i></span><span>Create Message</span></a></li>
            <li><a href="send-emails.php"><span class="icon is-small"><i class="fas fa-paper-plane"></i></span><span>Send Emails</span></a></li>
            <li><a href="email-logs.php"><span class="icon is-small"><i class="fas fa-chart-line"></i></span><span>Email Logs</span></a></li>
            <li><a href="contact-form.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Contact Forms</span></a></li>
            <li><a href="contracts.php"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Contracts</span></a></li>
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
            <li><a href="fix-primary-connections.php"><span class="icon is-small"><i class="fas fa-tools"></i></span><span>Fix Social Connections</span></a></li>
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
            <h2 class="title is-2 has-text-primary">
                <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                Create New Message
            </h2>
        </div>
    </div>

    <div class="columns">
        <div class="column">
            <div class="box">
                <form method="POST" enctype="multipart/form-data" id="message-form">
                    <?= FormTokenManager::getTokenField('create_message') ?>
                    <!-- User Selection -->
                    <div class="field">
                        <label class="label">Send To</label>
                        <div class="control">
                            <div class="dropdown" id="user-dropdown">
                                <div class="dropdown-trigger">
                                    <input class="input" 
                                           type="text" 
                                           id="user-search" 
                                           placeholder="Search for a user by name, username, or email..."
                                           autocomplete="off">
                                </div>
                                <div class="dropdown-menu" id="dropdown-menu" role="menu">
                                    <div class="dropdown-content" id="user-results">
                                        <!-- User search results will appear here -->
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="user_id" id="selected-user-id" required>
                            <div id="selected-user" class="mt-2" style="display: none;">
                                <div class="tags">
                                    <span class="tag is-info is-medium" id="selected-user-tag">
                                        <span id="selected-user-name"></span>
                                        <button class="delete is-small" type="button" onclick="clearSelectedUser()"></button>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <p class="help">Start typing to search for users</p>
                    </div>

                    <!-- Subject -->
                    <div class="field">
                        <label class="label">Subject</label>
                        <div class="control">
                            <input class="input" 
                                   type="text" 
                                   name="subject" 
                                   placeholder="Message subject"
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                   required>
                        </div>
                    </div>

                    <!-- Priority -->
                    <div class="field">
                        <label class="label">Priority</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="priority" required>
                                    <option value="low" <?= ($_POST['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low Priority</option>
                                    <option value="normal" <?= ($_POST['priority'] ?? 'normal') === 'normal' ? 'selected' : '' ?>>Normal Priority</option>
                                    <option value="high" <?= ($_POST['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High Priority</option>
                                    <option value="urgent" <?= ($_POST['priority'] ?? '') === 'urgent' ? 'selected' : '' ?>>Urgent Priority</option>
                                </select>
                            </div>
                        </div>
                        <p class="help">
                            <span class="has-text-grey">Low:</span> General information •
                            <span class="has-text-info">Normal:</span> Standard messages •
                            <span class="has-text-warning">High:</span> Important updates •
                            <span class="has-text-danger">Urgent:</span> Requires immediate attention
                        </p>
                    </div>

                    <!-- Tags -->
                    <div class="field">
                        <label class="label">Tags</label>
                        <div class="control">
                            <input class="input" type="text" name="tags" 
                                   value="<?= htmlspecialchars($_POST['tags'] ?? '') ?>"
                                   placeholder="e.g., Internal, External Message, Support, Update">
                        </div>
                        <p class="help">
                            <span class="has-text-grey">Optional:</span> Comma-separated tags for categorizing messages (e.g., "Internal, Support")
                        </p>
                    </div>

                    <!-- Manual Review -->
                    <div class="field">
                        <div class="control">
                            <label class="checkbox">
                                <input type="checkbox" name="manual_review" value="1" <?= isset($_POST['manual_review']) ? 'checked' : '' ?>>
                                <span class="icon has-text-warning"><i class="fas fa-dollar-sign"></i></span>
                                <strong>Mark for Manual Review</strong> <span class="tag is-warning is-small">+$1.00</span>
                            </label>
                        </div>
                        <p class="help">
                            <span class="has-text-warning">Optional:</span> Mark this message for manual review outside standard processing hours. 
                            This will incur an additional $1.00 fee as per contract terms (Section 5.6).
                        </p>
                    </div>

                    <!-- Manual Review Reason -->
                    <div class="field" id="manual-review-reason-field" style="display: none;">
                        <label class="label">Manual Review Reason</label>
                        <div class="control">
                            <input class="input" type="text" name="manual_review_reason" 
                                   value="<?= htmlspecialchars($_POST['manual_review_reason'] ?? '') ?>"
                                   placeholder="e.g., Complex case requiring special attention, After-hours processing">
                        </div>
                        <p class="help">
                            <span class="has-text-grey">Optional:</span> Provide a reason for marking this message for manual review.
                        </p>
                    </div>

                    <!-- Message Content -->
                    <div class="field">
                        <label class="label">Message</label>
                        <div class="control">
                            <textarea class="textarea" 
                                      name="message" 
                                      placeholder="Type your message here..."
                                      rows="8"
                                      required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <p class="help">You can use line breaks to format your message. The user will be able to respond to this message.</p>
                    </div>

                    <!-- Attachments -->
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
                        </div>
                        <p class="help">
                            <strong>Optional:</strong> Attach images, documents, or other files to this message.<br>
                            <span class="has-text-info">Images</span> will display inline in the conversation.<br>
                            <span class="has-text-warning">Documents</span> will be available for download and stored as email documents.<br>
                            Maximum file size: 1GB per file. Supported formats: Images, PDFs, Documents, Videos, Audio files, and Archives.
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary is-medium" type="submit">
                                <span class="icon"><i class="fas fa-paper-plane"></i></span>
                                <span>Send Message</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Start output buffering for scripts
ob_start();
?>
<style>
.upload-progress-hidden {
    display: none !important;
}

.attachment-card {
    margin-bottom: 0.5rem;
}

.attachment-filename {
    word-break: break-word;
}

.file-list .tag {
    margin-bottom: 0.25rem;
}

.file-list .field.is-grouped.is-grouped-multiline .control {
    margin-bottom: 0.5rem;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userSearch = document.getElementById('user-search');
    const userDropdown = document.getElementById('user-dropdown');
    const userResults = document.getElementById('user-results');
    const selectedUserId = document.getElementById('selected-user-id');
    const selectedUserDiv = document.getElementById('selected-user');
    const selectedUserName = document.getElementById('selected-user-name');
    const manualReviewCheckbox = document.querySelector('input[name="manual_review"]');
    const manualReviewReasonField = document.getElementById('manual-review-reason-field');
    let searchTimeout;

    // Manual review checkbox toggle
    if (manualReviewCheckbox && manualReviewReasonField) {
        function toggleManualReviewReason() {
            if (manualReviewCheckbox.checked) {
                manualReviewReasonField.style.display = 'block';
            } else {
                manualReviewReasonField.style.display = 'none';
                // Clear the reason field when unchecked
                const reasonInput = manualReviewReasonField.querySelector('input[name="manual_review_reason"]');
                if (reasonInput) reasonInput.value = '';
            }
        }
        
        // Set initial state
        toggleManualReviewReason();
        
        // Add event listener
        manualReviewCheckbox.addEventListener('change', toggleManualReviewReason);
    }

    // User search functionality
    userSearch.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (query.length < 2) {
            userDropdown.classList.remove('is-active');
            return;
        }

        // Debounce search
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            fetch(`?search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(users => {
                    userResults.innerHTML = '';
                    
                    if (users.length === 0) {
                        userResults.innerHTML = '<div class="dropdown-item">No users found</div>';
                    } else {
                        users.forEach(user => {
                            const userItem = document.createElement('a');
                            userItem.className = 'dropdown-item';
                            userItem.href = '#';
                            userItem.innerHTML = `
                                <div>
                                    <strong>${escapeHtml(user.username)}</strong>
                                    <span class="tag is-small is-${user.account_type === 'manual' ? 'info' : 'link'} ml-2">
                                        ${user.account_type}
                                    </span>
                                    <br>
                                    <small class="has-text-grey">${escapeHtml(user.email)}</small>
                                    ${user.first_name || user.last_name ? 
                                        `<br><small class="has-text-grey">${escapeHtml((user.first_name + ' ' + user.last_name).trim())}</small>` : 
                                        ''}
                                </div>
                            `;
                            
                            userItem.addEventListener('click', function(e) {
                                e.preventDefault();
                                selectUser(user);
                            });
                            
                            userResults.appendChild(userItem);
                        });
                    }
                    
                    userDropdown.classList.add('is-active');
                })
                .catch(error => {
                    console.error('Search error:', error);
                    userResults.innerHTML = '<div class="dropdown-item has-text-danger">Search error occurred</div>';
                });
        }, 300);
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!userDropdown.contains(e.target)) {
            userDropdown.classList.remove('is-active');
        }
    });

    function selectUser(user) {
        selectedUserId.value = user.id;
        selectedUserName.textContent = `${user.username} (${user.email})`;
        selectedUserDiv.style.display = 'block';
        userSearch.style.display = 'none';
        userDropdown.classList.remove('is-active');
    }

    window.clearSelectedUser = function() {
        selectedUserId.value = '';
        selectedUserDiv.style.display = 'none';
        userSearch.style.display = 'block';
        userSearch.value = '';
        userSearch.focus();
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Form validation
    document.getElementById('message-form').addEventListener('submit', function(e) {
        if (!selectedUserId.value) {
            e.preventDefault();
            Swal.fire({
                title: 'User Required',
                text: 'Please select a user to send the message to.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            userSearch.focus();
            return false;
        }
        
        // Show upload progress if files are selected
        const fileInput = this.querySelector('input[type="file"]');
        const files = fileInput ? fileInput.files : [];
        
        if (files.length > 0) {
            const uploadProgress = document.getElementById('upload-progress');
            const submitButton = this.querySelector('button[type="submit"]');
            
            if (uploadProgress) uploadProgress.classList.remove('upload-progress-hidden');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="icon"><i class="fas fa-spinner fa-spin"></i></span><span>Uploading & Sending...</span>';
            }
        }
    });

    // File upload functions
    window.updateFileList = function(input) {
        const fileList = document.getElementById('file-list');
        fileList.innerHTML = '';
        
        if (input.files.length > 0) {
            const container = document.createElement('div');
            container.className = 'field is-grouped is-grouped-multiline';
            
            Array.from(input.files).forEach((file, index) => {
                const control = document.createElement('div');
                control.className = 'control';
                
                // Determine file type icon
                let iconClass = 'fas fa-file';
                let iconColor = 'has-text-grey';
                const extension = file.name.split('.').pop().toLowerCase();
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
                
                if (isImage) {
                    iconClass = 'fas fa-image';
                    iconColor = 'has-text-info';
                } else if (extension === 'pdf') {
                    iconClass = 'fas fa-file-pdf';
                    iconColor = 'has-text-danger';
                } else if (['doc', 'docx'].includes(extension)) {
                    iconClass = 'fas fa-file-word';
                    iconColor = 'has-text-info';
                } else if (['xls', 'xlsx'].includes(extension)) {
                    iconClass = 'fas fa-file-excel';
                    iconColor = 'has-text-success';
                } else if (['mp4', 'avi', 'mov', 'wmv'].includes(extension)) {
                    iconClass = 'fas fa-video';
                    iconColor = 'has-text-primary';
                } else if (['mp3', 'wav', 'ogg'].includes(extension)) {
                    iconClass = 'fas fa-music';
                    iconColor = 'has-text-warning';
                } else if (extension === 'zip') {
                    iconClass = 'fas fa-file-archive';
                    iconColor = 'has-text-grey';
                }
                
                const tag = document.createElement('span');
                tag.className = `tag ${isImage ? 'is-info' : 'is-light'} is-medium`;
                tag.innerHTML = `
                    <span class="icon">
                        <i class="${iconClass} ${iconColor}"></i>
                    </span>
                    <span>${escapeHtml(file.name)} (${formatFileSize(file.size)})</span>
                    <button class="delete is-small" type="button" onclick="removeFile(${index})"></button>
                `;
                
                control.appendChild(tag);
                container.appendChild(control);
            });
            
            fileList.appendChild(container);
        }
    };

    window.removeFile = function(index) {
        const input = document.querySelector('input[name="attachments[]"]');
        if (!input) return;
        
        const dt = new DataTransfer();
        
        Array.from(input.files).forEach((file, i) => {
            if (i !== index) {
                dt.items.add(file);
            }
        });
        
        input.files = dt.files;
        updateFileList(input);
    };

    window.formatFileSize = function(bytes) {
        const units = ['B', 'KB', 'MB', 'GB'];
        let size = bytes;
        let unitIndex = 0;
        
        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex++;
        }
        
        return Math.round(size * 100) / 100 + ' ' + units[unitIndex];
    };
});
</script>
<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
