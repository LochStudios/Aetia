<?php
// admin/send-emails.php - Admin interface for sending emails to clients
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/EmailService.php';

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

// Get all users for the dropdown
$allUsers = $userModel->getAllUsers();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $emailService = new EmailService();
        
        if (isset($_POST['send_custom_email'])) {
            // Send custom email to selected users
            $selectedUsers = $_POST['selected_users'] ?? [];
            $emailSubject = trim($_POST['email_subject'] ?? '');
            $emailBody = trim($_POST['email_body'] ?? '');
            
            if (empty($selectedUsers)) {
                $error = 'Please select at least one recipient.';
            } elseif (empty($emailSubject)) {
                $error = 'Please enter an email subject.';
            } elseif (empty($emailBody)) {
                $error = 'Please enter an email body.';
            } else {
                $successCount = 0;
                $failCount = 0;
                
                foreach ($selectedUsers as $userId) {
                    $user = $userModel->getUserById($userId);
                    if ($user && !empty($user['email'])) {
                        $result = $emailService->sendEmail(
                            $user['email'],
                            $emailSubject,
                            $emailBody,
                            strip_tags($emailBody),
                            [],
                            null,
                            'admin_custom',
                            $_SESSION['user_id']
                        );
                        
                        if ($result) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                        
                        // Small delay to prevent overwhelming the SMTP server
                        usleep(500000); // 0.5 second delay
                    }
                }
                
                if ($successCount > 0) {
                    $message = "Successfully sent {$successCount} email(s).";
                    if ($failCount > 0) {
                        $message .= " {$failCount} email(s) failed to send.";
                    }
                } else {
                    $error = "Failed to send any emails. Please check your email configuration.";
                }
            }
        } elseif (isset($_POST['send_newsletter'])) {
            // Send newsletter to all active users
            $newsletterSubject = trim($_POST['newsletter_subject'] ?? '');
            $newsletterBody = trim($_POST['newsletter_body'] ?? '');
            
            if (empty($newsletterSubject) || empty($newsletterBody)) {
                $error = 'Please enter both subject and body for the newsletter.';
            } else {
                $activeUsers = $userModel->getAllActiveUsers();
                $successCount = 0;
                $failCount = 0;
                
                foreach ($activeUsers as $user) {
                    if (!empty($user['email'])) {
                        $result = $emailService->sendEmail(
                            $user['email'],
                            $newsletterSubject,
                            $newsletterBody,
                            strip_tags($newsletterBody),
                            [],
                            null,
                            'newsletter',
                            $_SESSION['user_id']
                        );
                            $newsletterSubject,
                            $newsletterBody,
                            strip_tags($newsletterBody)
                        );
                        
                        if ($result) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                        
                        // Small delay to prevent overwhelming the SMTP server
                        usleep(500000); // 0.5 second delay
                    }
                }
                
                if ($successCount > 0) {
                    $message = "Newsletter sent to {$successCount} user(s).";
                    if ($failCount > 0) {
                        $message .= " {$failCount} email(s) failed to send.";
                    }
                } else {
                    $error = "Failed to send newsletter. Please check your email configuration.";
                }
            }
        } elseif (isset($_POST['test_connection'])) {
            // Test SMTP connection
            $connectionTest = $emailService->testConfiguration();
            if ($connectionTest) {
                $message = 'SMTP connection test successful!';
            } else {
                $error = 'SMTP connection test failed. Please check your email configuration.';
            }
        }
    } catch (Exception $e) {
        $error = 'Email service error: ' . $e->getMessage();
    }
}

$pageTitle = 'Send Emails | Aetia Admin';
ob_start();
?>

<style>
    .email-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .email-section {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .email-form {
        margin-top: 15px;
    }
    
    .email-form .field {
        margin-bottom: 15px;
    }
    
    .status-message {
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .status-success {
        background-color: rgba(72, 199, 142, 0.2);
        border: 1px solid #48c78e;
        color: #48c78e;
    }
    
    .status-error {
        background-color: rgba(255, 59, 48, 0.2);
        border: 1px solid #ff3b30;
        color: #ff3b30;
    }
    
    .user-list {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #444;
        padding: 10px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 5px;
    }
    
    .user-checkbox {
        margin-bottom: 8px;
    }
    
    .user-checkbox label {
        color: #ddd;
        font-size: 14px;
    }
    
    textarea {
        min-height: 150px;
    }
    
    .quick-actions {
        margin-bottom: 10px;
    }
</style>

<div class="email-container">
    <h1 class="title has-text-light">Send Emails to Clients</h1>
    <p class="subtitle has-text-light">Send custom emails and newsletters to your users</p>
    
    <?php if ($message): ?>
        <div class="status-message status-success">
            <strong>Success:</strong> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="status-message status-error">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <!-- SMTP Connection Test -->
    <div class="email-section">
        <h2 class="subtitle has-text-light">Test Email Configuration</h2>
        <p class="has-text-light">Test your SMTP connection before sending emails.</p>
        
        <form method="POST" class="email-form">
            <button type="submit" name="test_connection" class="button is-info">
                <span class="icon">
                    <i class="fas fa-plug"></i>
                </span>
                <span>Test SMTP Connection</span>
            </button>
        </form>
    </div>
    
    <!-- Send Custom Email -->
    <div class="email-section">
        <h2 class="subtitle has-text-light">Send Custom Email</h2>
        <p class="has-text-light">Send a custom email to selected users.</p>
        
        <form method="POST" class="email-form">
            <div class="field">
                <label class="label has-text-light">Select Recipients</label>
                <div class="quick-actions">
                    <button type="button" class="button is-small is-light" onclick="selectAllUsers()">Select All</button>
                    <button type="button" class="button is-small is-light" onclick="selectActiveUsers()">Select Active Only</button>
                    <button type="button" class="button is-small is-light" onclick="clearAllUsers()">Clear All</button>
                </div>
                <div class="user-list">
                    <?php foreach ($allUsers as $user): ?>
                        <div class="user-checkbox">
                            <label class="checkbox">
                                <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>" 
                                       data-active="<?= $user['is_active'] ? '1' : '0' ?>">
                                <?= htmlspecialchars($user['username']) ?> 
                                (<?= htmlspecialchars($user['email']) ?>) 
                                <?php if (!$user['is_active']): ?>
                                    <span class="tag is-small is-warning">Inactive</span>
                                <?php endif; ?>
                                <?php if ($user['is_admin']): ?>
                                    <span class="tag is-small is-primary">Admin</span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="help has-text-light">Select users to send the email to. Inactive users are shown but should be selected carefully.</p>
            </div>
            
            <div class="field">
                <label class="label has-text-light">Email Subject</label>
                <div class="control">
                    <input class="input has-background-grey-darker has-text-light" 
                           type="text" 
                           name="email_subject" 
                           placeholder="Enter email subject"
                           required>
                </div>
            </div>
            
            <div class="field">
                <label class="label has-text-light">Email Body (HTML)</label>
                <div class="control">
                    <textarea class="textarea has-background-grey-darker has-text-light" 
                              name="email_body" 
                              placeholder="Enter your email message (HTML supported)"
                              required></textarea>
                </div>
                <p class="help has-text-light">You can use HTML formatting. Line breaks will be preserved.</p>
            </div>
            
            <button type="submit" name="send_custom_email" class="button is-success">
                <span class="icon">
                    <i class="fas fa-paper-plane"></i>
                </span>
                <span>Send Custom Email</span>
            </button>
        </form>
    </div>
    
    <!-- Send Newsletter -->
    <div class="email-section">
        <h2 class="subtitle has-text-light">Send Newsletter</h2>
        <p class="has-text-light">Send a newsletter to all active users automatically.</p>
        
        <form method="POST" class="email-form">
            <div class="field">
                <label class="label has-text-light">Newsletter Subject</label>
                <div class="control">
                    <input class="input has-background-grey-darker has-text-light" 
                           type="text" 
                           name="newsletter_subject" 
                           placeholder="Enter newsletter subject"
                           required>
                </div>
            </div>
            
            <div class="field">
                <label class="label has-text-light">Newsletter Body (HTML)</label>
                <div class="control">
                    <textarea class="textarea has-background-grey-darker has-text-light" 
                              name="newsletter_body" 
                              placeholder="Enter your newsletter content (HTML supported)"
                              required></textarea>
                </div>
                <p class="help has-text-light">This will be sent to all active users. Professional platform communications only.</p>
            </div>
            
            <button type="submit" name="send_newsletter" class="button is-warning" 
                    onclick="return confirm('This will send an email to ALL active users. Are you sure?')">
                <span class="icon">
                    <i class="fas fa-newspaper"></i>
                </span>
                <span>Send Newsletter to All Active Users</span>
            </button>
        </form>
    </div>
    
    <!-- Email Templates -->
    <div class="email-section">
        <h2 class="subtitle has-text-light">Quick Email Templates</h2>
        <div class="content has-text-light">
            <div class="buttons">
                <button class="button is-small is-light" onclick="loadTemplate('welcome')">Welcome Message</button>
                <button class="button is-small is-light" onclick="loadTemplate('update')">System Update</button>
                <button class="button is-small is-light" onclick="loadTemplate('promotion')">Promotion</button>
                <button class="button is-small is-light" onclick="loadTemplate('maintenance')">Maintenance Notice</button>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="email-section">
        <h2 class="subtitle has-text-light">User Statistics</h2>
        <div class="content has-text-light">
            <div class="columns">
                <div class="column">
                    <div class="box has-background-grey-darker">
                        <p class="title has-text-light"><?= count($allUsers) ?></p>
                        <p class="subtitle has-text-light">Total Users</p>
                    </div>
                </div>
                <div class="column">
                    <div class="box has-background-grey-darker">
                        <p class="title has-text-light"><?= count(array_filter($allUsers, function($u) { return $u['is_active']; })) ?></p>
                        <p class="subtitle has-text-light">Active Users</p>
                    </div>
                </div>
                <div class="column">
                    <div class="box has-background-grey-darker">
                        <p class="title has-text-light"><?= count(array_filter($allUsers, function($u) { return $u['is_admin']; })) ?></p>
                        <p class="subtitle has-text-light">Admin Users</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Navigation -->
    <div class="field is-grouped">
        <div class="control">
            <a href="email-logs.php" class="button is-info">
                <span class="icon"><i class="fas fa-list"></i></span>
                <span>View Email Logs</span>
            </a>
        </div>
        <div class="control">
            <a href="messages.php" class="button is-light">
                <span class="icon"><i class="fas fa-comments"></i></span>
                <span>Back to Messages</span>
            </a>
        </div>
        <div class="control">
            <a href="../index.php" class="button is-light">
                <span class="icon"><i class="fas fa-home"></i></span>
                <span>Back to Dashboard</span>
            </a>
        </div>
    </div>
</div>

<script>
function selectAllUsers() {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
    checkboxes.forEach(cb => cb.checked = true);
}

function selectActiveUsers() {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
    checkboxes.forEach(cb => {
        cb.checked = cb.dataset.active === '1';
    });
}

function clearAllUsers() {
    const checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
    checkboxes.forEach(cb => cb.checked = false);
}

function loadTemplate(type) {
    const subjectField = document.querySelector('input[name="email_subject"]');
    const bodyField = document.querySelector('textarea[name="email_body"]');
    
    const templates = {
        welcome: {
            subject: 'Welcome to Aetia Talent Agency!',
            body: `<h2>Welcome to Aetia Talent Agency!</h2>
<p>Dear Valued Client,</p>
<p>We're thrilled to have you join our community at Aetia Talent Agency. Our platform is designed to connect talented individuals with exciting opportunities.</p>
<p>Here's what you can expect:</p>
<ul>
<li>Access to exclusive talent opportunities</li>
<li>Professional networking with industry professionals</li>
<li>Regular updates on new opportunities</li>
<li>Dedicated support from our team</li>
</ul>
<p>If you have any questions, please don't hesitate to reach out to our support team.</p>
<p>Best regards,<br>The Aetia Team</p>`
        },
        update: {
            subject: 'System Update - Aetia Platform',
            body: `<h2>System Update Notification</h2>
<p>Dear Users,</p>
<p>We're excited to announce some new updates to the Aetia platform:</p>
<ul>
<li>Improved user interface</li>
<li>Enhanced security features</li>
<li>Better mobile experience</li>
<li>New messaging capabilities</li>
</ul>
<p>These updates are now live and ready for you to explore. Please log in to your account to see the improvements.</p>
<p>Thank you for your continued support!</p>
<p>Best regards,<br>The Aetia Team</p>`
        },
        promotion: {
            subject: 'Special Promotion - Limited Time Offer',
            body: `<h2>Special Promotion Just for You!</h2>
<p>Dear Valued Client,</p>
<p>We're excited to offer you an exclusive promotion on our premium services.</p>
<p><strong>Limited Time Offer:</strong></p>
<ul>
<li>Enhanced profile visibility</li>
<li>Priority application processing</li>
<li>Dedicated talent consultant</li>
<li>Exclusive event invitations</li>
</ul>
<p>This offer is valid for a limited time only. Don't miss out on this opportunity to advance your career!</p>
<p>Contact us today to learn more about this exclusive offer.</p>
<p>Best regards,<br>The Aetia Team</p>`
        },
        maintenance: {
            subject: 'Scheduled Maintenance - Aetia Platform',
            body: `<h2>Scheduled Maintenance Notice</h2>
<p>Dear Users,</p>
<p>We will be performing scheduled maintenance on the Aetia platform to improve our services.</p>
<p><strong>Maintenance Details:</strong></p>
<ul>
<li><strong>Date:</strong> [Insert Date]</li>
<li><strong>Time:</strong> [Insert Time]</li>
<li><strong>Duration:</strong> Approximately 2 hours</li>
<li><strong>Impact:</strong> Platform will be temporarily unavailable</li>
</ul>
<p>During this time, you may experience limited access to certain features. We apologize for any inconvenience this may cause.</p>
<p>Thank you for your patience and understanding.</p>
<p>Best regards,<br>The Aetia Team</p>`
        }
    };
    
    if (templates[type]) {
        subjectField.value = templates[type].subject;
        bodyField.value = templates[type].body;
    }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
