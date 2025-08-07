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
    $userId = intval($_POST['user_id']);
    $subject = trim($_POST['subject']);
    $messageText = trim($_POST['message']);
    $priority = $_POST['priority'];
    $tags = trim($_POST['tags'] ?? '');
    
    if (empty($subject) || empty($messageText) || empty($userId)) {
        $error = 'All fields are required';
    } else {
        $result = $messageModel->createMessage($userId, $subject, $messageText, $_SESSION['user_id'], $priority, $tags);
        if ($result['success']) {
            $_SESSION['success_message'] = 'Message sent successfully! The user has been notified by email.';
            header('Location: messages.php?id=' . $result['message_id']);
            exit;
        } else {
            $error = $result['message'] ?? 'Failed to send message';
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
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-plus"></i></span><span>Create Message</span></a></li>
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
            <h2 class="title is-2 has-text-primary">
                <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                Create New Message
            </h2>
        </div>
    </div>

    <div class="columns">
        <div class="column is-8 is-offset-2">
            <div class="box">
                <form method="POST" id="message-form">
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

                    <!-- Submit Button -->
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary is-large" type="submit">
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userSearch = document.getElementById('user-search');
    const userDropdown = document.getElementById('user-dropdown');
    const userResults = document.getElementById('user-results');
    const selectedUserId = document.getElementById('selected-user-id');
    const selectedUserDiv = document.getElementById('selected-user');
    const selectedUserName = document.getElementById('selected-user-name');
    let searchTimeout;

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
        }
    });
});
</script>
<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
