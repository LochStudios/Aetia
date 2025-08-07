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
                        // Use the dark mode template wrapper for consistency
                        $formattedBody = $emailService->wrapInDarkTemplate($emailSubject, $emailBody);
                        
                        $result = $emailService->sendEmail(
                            $user['email'],
                            $emailSubject,
                            $formattedBody,
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
                        // Use the dark mode template wrapper for consistency
                        $formattedBody = $emailService->wrapInDarkTemplate($newsletterSubject, $newsletterBody);
                        
                        $result = $emailService->sendEmail(
                            $user['email'],
                            $newsletterSubject,
                            $formattedBody,
                            strip_tags($newsletterBody),
                            [],
                            null,
                            'newsletter',
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
    
    <!-- Navigation -->
    <div class="field is-grouped" style="margin-bottom: 30px;">
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
    </div>
    
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
<p>Dear Valued Talent,</p>
<p>We're thrilled to have you join our community at Aetia Talent Agency. Our platform is designed to connect talented individuals with exciting opportunities in the entertainment and creative industries.</p>

<div class='highlight-box'>
    <h3 style='color: #209cee; margin-top: 0;'>What You Can Expect:</h3>
    <ul>
        <li>Access to exclusive talent opportunities and casting calls</li>
        <li>Professional networking with industry professionals</li>
        <li>Direct communication with talent agents and casting directors</li>
        <li>Portfolio and profile management tools</li>
        <li>Regular updates on new opportunities that match your skills</li>
        <li>Dedicated support from our experienced team</li>
    </ul>
</div>

<p>To get started, please ensure your profile is complete and up-to-date. Upload your best photos, videos, and resume to showcase your talents.</p>

<p>If you have any questions about our platform or need assistance setting up your profile, please don't hesitate to contact us at <a href='mailto:talent@aetia.com.au'>talent@aetia.com.au</a></p>

<p>Best regards,<br>The Aetia Talent Agency Team</p>`
        },
        update: {
            subject: 'Platform Updates - New Features Available',
            body: `<h2>Exciting Platform Updates Available Now!</h2>
<p>Dear Aetia Community,</p>
<p>We're excited to announce some significant improvements to the Aetia Talent Agency platform based on your feedback:</p>

<div class='highlight-box'>
    <h3 style='color: #209cee; margin-top: 0;'>New Features & Improvements:</h3>
    <ul>
        <li>Enhanced messaging system for better communication</li>
        <li>Improved profile management with new media upload options</li>
        <li>Advanced search and filtering for opportunities</li>
        <li>Mobile-optimized interface for better mobile experience</li>
        <li>New notification system to keep you updated</li>
        <li>Enhanced security and privacy features</li>
    </ul>
</div>

<p style='text-align: center; margin: 30px 0;'>
    <a href='https://aetia.com/login.php' class='button-primary'>Log In to Explore New Features</a>
</p>

<p>These updates are now live and ready for you to explore. We encourage you to log in and familiarize yourself with the new features.</p>

<p>Thank you for your continued trust in Aetia Talent Agency!</p>

<p>Best regards,<br>The Aetia Development Team</p>`
        },
        promotion: {
            subject: 'Exclusive Opportunity - Premium Talent Showcase',
            body: `<h2>Exclusive Talent Showcase Opportunity!</h2>
<p>Dear Valued Talent,</p>
<p>We're excited to announce an exclusive opportunity for selected talent to participate in our Premium Talent Showcase event.</p>

<div class='highlight-box'>
    <h3 style='color: #209cee; margin-top: 0;'>Showcase Benefits:</h3>
    <ul>
        <li>Direct access to leading casting directors and agents</li>
        <li>Professional networking opportunities</li>
        <li>Priority consideration for upcoming projects</li>
        <li>Professional portfolio review and feedback</li>
        <li>Industry insights and career guidance</li>
        <li>Potential for immediate casting opportunities</li>
    </ul>
</div>

<div class='highlight-box' style='border-left-color: #ffdd57;'>
    <p><strong>Event Details:</strong></p>
    <ul>
        <li><strong>Date:</strong> [To be confirmed based on applications]</li>
        <li><strong>Location:</strong> Sydney/Melbourne (Multiple venues)</li>
        <li><strong>Application Deadline:</strong> [Insert Date]</li>
        <li><strong>Participation:</strong> By invitation and application only</li>
    </ul>
</div>

<p>This is a unique opportunity to advance your career and connect with industry professionals. Spaces are limited and selection is competitive.</p>

<p>To apply or learn more about this exclusive showcase, please contact us at <a href='mailto:showcase@aetia.com.au'>showcase@aetia.com.au</a></p>

<p>Best regards,<br>The Aetia Talent Agency Team</p>`
        },
        maintenance: {
            subject: 'Scheduled Platform Maintenance - Brief Service Interruption',
            body: `<h2>Scheduled Platform Maintenance Notice</h2>
<p>Dear Aetia Community,</p>
<p>We will be performing scheduled maintenance on the Aetia Talent Agency platform to improve performance, security, and add new features.</p>

<div class='highlight-box'>
    <h3 style='color: #209cee; margin-top: 0;'>Maintenance Schedule:</h3>
    <ul>
        <li><strong>Date:</strong> [Insert Specific Date]</li>
        <li><strong>Start Time:</strong> [Insert Start Time] AEST</li>
        <li><strong>Expected Duration:</strong> Approximately 2-3 hours</li>
        <li><strong>End Time:</strong> [Insert End Time] AEST (estimated)</li>
    </ul>
</div>

<div class='highlight-box' style='border-left-color: #ffdd57;'>
    <h3 style='color: #ffdd57; margin-top: 0;'>During Maintenance:</h3>
    <ul>
        <li>The platform will be temporarily unavailable</li>
        <li>You will not be able to log in or access your account</li>
        <li>Messaging and notification services will be paused</li>
        <li>Mobile app functionality will be limited</li>
    </ul>
</div>

<p><strong>What's Being Improved:</strong></p>
<ul>
    <li>Enhanced platform security</li>
    <li>Improved server performance</li>
    <li>Database optimizations</li>
    <li>New feature implementations</li>
</ul>

<p>We apologize for any inconvenience this may cause and appreciate your patience as we work to improve your experience on our platform.</p>

<p>For urgent matters during the maintenance window, please email us at <a href='mailto:support@aetia.com.au'>support@aetia.com.au</a></p>

<p>Thank you for your understanding and continued support.</p>

<p>Best regards,<br>The Aetia Technical Team</p>`
        }
    };
    
    if (templates[type]) {
        subjectField.value = templates[type].subject;
        bodyField.value = templates[type].body;
    }
}

// Check for SMTP debug output and log to console
<?php if (isset($_SESSION['smtp_debug_output']) && !empty($_SESSION['smtp_debug_output'])): ?>
console.log('SMTP Debug Output:');
console.log(<?php echo json_encode($_SESSION['smtp_debug_output']); ?>);
<?php 
    // Clear the debug output from session after displaying
    unset($_SESSION['smtp_debug_output']); 
endif; 
?>
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
