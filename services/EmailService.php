<?php
// services/EmailService.php - Email service using PHPMailer for Aetia

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer files
require_once '/home/aetiacom/vendors/PHPMailer/src/Exception.php';
require_once '/home/aetiacom/vendors/PHPMailer/src/PHPMailer.php';
require_once '/home/aetiacom/vendors/PHPMailer/src/SMTP.php';

// Include database connection
require_once __DIR__ . '/../config/database.php';

class EmailService {
    private $mail;
    private $config;
    private $db;
    private $mysqli;
    
    public function __construct() {
        // Load email configuration
        $this->loadConfig();
        
        // Initialize database connection
        $this->db = new Database();
        $this->mysqli = $this->db->getConnection();
        
        // Create a new PHPMailer instance
        $this->mail = new PHPMailer(true);
        
        // Configure SMTP settings
        $this->configureSMTP();
    }
    
    private function loadConfig() {
        try {
            $configFile = '/home/aetiacom/web-config/mail.php';
            
            if (!file_exists($configFile)) {
                throw new Exception("Email configuration file not found at: {$configFile}. Please ensure the mail.php configuration file exists.");
            }
            
            // Load the configuration array
            $config = include $configFile;
            
            // Validate that we have a proper array structure
            if (!is_array($config)) {
                throw new Exception("Email configuration file must return an array structure");
            }
            
            // Check that required sections exist
            if (!isset($config['smtp']) || !isset($config['from'])) {
                throw new Exception("Email configuration file must contain 'smtp' and 'from' sections");
            }
            
            // Check required SMTP settings
            $requiredSmtp = ['host', 'username', 'password'];
            foreach ($requiredSmtp as $field) {
                if (!isset($config['smtp'][$field]) || empty($config['smtp'][$field])) {
                    throw new Exception("SMTP configuration must include: {$field}");
                }
            }
            
            $this->config = [
                'host' => $config['smtp']['host'],
                'username' => $config['smtp']['username'],
                'password' => $config['smtp']['password'],
                'port' => isset($config['smtp']['port']) ? intval($config['smtp']['port']) : 587,
                'encryption' => $this->parseEncryption($config['smtp']['encryption'] ?? 'starttls'),
                'timeout' => isset($config['smtp']['timeout']) ? intval($config['smtp']['timeout']) : 30,
                'auth' => isset($config['smtp']['auth']) ? $config['smtp']['auth'] : true,
                'from_email' => $config['from']['email'] ?? $config['smtp']['username'],
                'from_name' => $config['from']['name'] ?? 'Aetia Talent Agency',
                'debug' => isset($config['settings']['debug']) ? $config['settings']['debug'] : false,
                'charset' => isset($config['settings']['charset']) ? $config['settings']['charset'] : 'UTF-8',
                'html' => isset($config['settings']['html']) ? $config['settings']['html'] : true,
                'footer' => $config['templates']['footer'] ?? 'Aetia Talent Agency - Professional Talent Solutions'
            ];
            
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            throw new Exception("Failed to load email configuration: " . $e->getMessage());
        }
    }
    
    /**
     * Parse encryption setting from config file
     */
    private function parseEncryption($encryption) {
        if (empty($encryption)) {
            return '';
        }
        
        // Handle string values from config file
        $encryption = strtolower(trim($encryption));
        
        switch ($encryption) {
            case 'ssl':
            case 'smtps':
                return PHPMailer::ENCRYPTION_SMTPS;
            case 'tls':
            case 'starttls':
                return PHPMailer::ENCRYPTION_STARTTLS;
            case 'none':
            case '':
                return '';
            default:
                // If it's already a PHPMailer constant, return as is
                if ($encryption === PHPMailer::ENCRYPTION_STARTTLS || $encryption === PHPMailer::ENCRYPTION_SMTPS) {
                    return $encryption;
                }
                // Default to STARTTLS for unknown values
                return PHPMailer::ENCRYPTION_STARTTLS;
        }
    }
    
    private function configureSMTP() {
        try {
            // Server settings
            if ($this->config['debug']) {
                $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }
            
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['host'];
            $this->mail->SMTPAuth = $this->config['auth'];
            $this->mail->Username = $this->config['username'];
            $this->mail->Password = $this->config['password'];
            $this->mail->SMTPSecure = $this->config['encryption'];
            $this->mail->Port = $this->config['port'];
            $this->mail->Timeout = $this->config['timeout'];
            
            // Default sender
            $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
            
            // Set charset
            $this->mail->CharSet = $this->config['charset'];
            
        } catch (Exception $e) {
            error_log("SMTP configuration error: " . $e->getMessage());
            throw new Exception("Failed to configure SMTP: " . $e->getMessage());
        }
    }
    
    /** Get dark mode email styling */
    private function getDarkModeStyles() {
        return "
        <style>
            body, table, td, p, h1, h2, h3, h4, h5, h6 {
                color: #ffffff !important;
                font-family: Arial, sans-serif !important;
            }
            body {
                background-color: #181a1b !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .email-container {
                max-width: 600px !important;
                margin: 0 auto !important;
                background-color: #181a1b !important;
                padding: 20px !important;
            }
            .email-header {
                text-align: center !important;
                margin-bottom: 30px !important;
            }
            .email-content {
                background-color: #1f2122 !important;
                border-radius: 1rem !important;
                padding: 30px !important;
                border: 2px solid #209cee !important;
                box-shadow: 0 4px 32px 0 rgba(0,0,0,0.18) !important;
            }
            .highlight-box {
                background-color: #2a2d2e !important;
                padding: 20px !important;
                border-radius: 0.5rem !important;
                margin: 20px 0 !important;
                border-left: 4px solid #209cee !important;
            }
            .button-primary {
                background-color: #209cee !important;
                color: #ffffff !important;
                padding: 12px 24px !important;
                text-decoration: none !important;
                border-radius: 0.5rem !important;
                font-weight: bold !important;
                display: inline-block !important;
                margin: 10px 0 !important;
                border: none !important;
            }
            .button-primary:hover {
                background-color: #1a7bc4 !important;
            }
            .footer {
                margin-top: 30px !important;
                padding-top: 20px !important;
                border-top: 1px solid #3a3d3e !important;
                text-align: center !important;
                color: #b0b3b5 !important;
                font-size: 14px !important;
            }
            a {
                color: #209cee !important;
                text-decoration: none !important;
            }
            a:hover {
                color: #1a7bc4 !important;
                text-decoration: underline !important;
            }
            ul, ol {
                color: #ffffff !important;
            }
            li {
                margin-bottom: 8px !important;
                color: #ffffff !important;
            }
            .priority-urgent {
                color: #ff3860 !important;
                font-weight: bold !important;
            }
            .priority-high {
                color: #ffdd57 !important;
                font-weight: bold !important;
            }
            .priority-normal {
                color: #209cee !important;
            }
            .priority-low {
                color: #b0b3b5 !important;
            }
        </style>";
    }
    
    /** Wrap email content in dark mode template */
    public function wrapInDarkTemplate($title, $content) {
        $styles = $this->getDarkModeStyles();
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
            {$styles}
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h1 style='color: #209cee; margin: 0;'>Aetia Talent Agency</h1>
                </div>
                <div class='email-content'>
                    {$content}
                </div>
                <div class='footer'>
                    <p>{$this->config['footer']}</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /** Add footer to email body */
    private function addEmailFooter($body) {
        // If the body is already wrapped in our dark template, return as is
        if (strpos($body, 'email-container') !== false) {
            return $body;
        }
        
        // Legacy footer for non-templated emails
        $footer = "<br><br><hr>";
        $footer .= "<p><small>{$this->config['footer']}</small></p>";
        
        return $body . $footer;
    }
    
    /** Log email to database */
    private function logEmail($recipientEmail, $recipientName, $recipientUserId, $senderUserId, $emailType, $subject, $body, $status, $errorMessage = null) {
        try {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $stmt = $this->mysqli->prepare("
                INSERT INTO email_logs (
                    recipient_user_id, recipient_email, recipient_name, sender_user_id, 
                    email_type, subject, body_content, html_content, status, error_message, 
                    ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $htmlContent = $this->addEmailFooter($body);
            $plainContent = strip_tags(html_entity_decode($body));
            
            $stmt->bind_param(
                "ississssssss", 
                $recipientUserId, $recipientEmail, $recipientName, $senderUserId,
                $emailType, $subject, $plainContent, $htmlContent, $status, $errorMessage,
                $ipAddress, $userAgent
            );
            
            $stmt->execute();
            $logId = $this->mysqli->insert_id;
            $stmt->close();
            
            return $logId;
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
            return false;
        }
    }
    
    /** Get recipient user ID from email address */
    private function getRecipientUserId($email) {
        try {
            $stmt = $this->mysqli->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            return $user ? $user['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /** Send an email
        * @param string|array $to Recipient email address(es)
        * @param string $subject Email subject
        * @param string $body Email body (HTML)
        * @param string $altBody Plain text alternative body
        * @param array $attachments Array of file paths to attach
        * @param string $replyTo Reply-to email address
        * @param string $emailType Type of email for logging (default: 'general')
        * @param int $senderUserId User ID of sender (for logging)
        * @return bool Success status
    */
    public function sendEmail($to, $subject, $body, $altBody = '', $attachments = [], $replyTo = null, $emailType = 'general', $senderUserId = null) {
        $recipients = [];
        $logIds = [];
        
        try {
            // Clear any previous recipients
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->clearReplyTos();
            
            // Process recipients and prepare for logging
            if (is_array($to)) {
                foreach ($to as $email => $name) {
                    if (is_numeric($email)) {
                        $this->mail->addAddress($name);
                        $recipients[] = ['email' => $name, 'name' => ''];
                    } else {
                        $this->mail->addAddress($email, $name);
                        $recipients[] = ['email' => $email, 'name' => $name];
                    }
                }
            } else {
                $this->mail->addAddress($to);
                $recipients[] = ['email' => $to, 'name' => ''];
            }
            
            // Set reply-to if provided
            if ($replyTo) {
                $this->mail->addReplyTo($replyTo);
            }
            
            // Add attachments
            foreach ($attachments as $attachment) {
                if (is_array($attachment)) {
                    $this->mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } else {
                    $this->mail->addAttachment($attachment);
                }
            }
            
            // Content
            $this->mail->isHTML($this->config['html']);
            $this->mail->Subject = $subject;
            $this->mail->Body = $this->addEmailFooter($body);
            
            if ($altBody) {
                $this->mail->AltBody = $altBody;
            } else {
                // Generate a basic plain text version from HTML
                $this->mail->AltBody = strip_tags(html_entity_decode($body));
            }
            
            // Send the email
            $result = $this->mail->send();
            
            // Log email for each recipient
            foreach ($recipients as $recipient) {
                $recipientUserId = $this->getRecipientUserId($recipient['email']);
                $status = $result ? 'sent' : 'failed';
                $errorMessage = $result ? null : $this->mail->ErrorInfo;
                
                $logId = $this->logEmail(
                    $recipient['email'], 
                    $recipient['name'], 
                    $recipientUserId, 
                    $senderUserId, 
                    $emailType, 
                    $subject, 
                    $body, 
                    $status, 
                    $errorMessage
                );
                
                if ($logId) {
                    $logIds[] = $logId;
                }
            }
            
            if ($result) {
                error_log("Email sent successfully to: " . (is_array($to) ? implode(', ', array_keys($to)) : $to));
            }
            
            return $result;
            
        } catch (Exception $e) {
            // Log failed email attempts
            foreach ($recipients as $recipient) {
                $recipientUserId = $this->getRecipientUserId($recipient['email']);
                $this->logEmail(
                    $recipient['email'], 
                    $recipient['name'], 
                    $recipientUserId, 
                    $senderUserId, 
                    $emailType, 
                    $subject, 
                    $body, 
                    'failed', 
                    $e->getMessage()
                );
            }
            
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /** Send a welcome email to new users */
    public function sendWelcomeEmail($userEmail, $userName) {
        $subject = "Welcome to Aetia Talent Agency";
        
        $content = "
        <h2>Welcome to Aetia Talent Agency, {$userName}!</h2>
        <p>Thank you for joining our platform. We're excited to have you as part of our community.</p>
        <p>You can now access all features of our platform by logging in to your account.</p>
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        <br>
        <p>Best regards,<br>The Aetia Team</p>
        ";
        
        $body = $this->wrapInDarkTemplate($subject, $content);
        
        return $this->sendEmail($userEmail, $subject, $body, '', [], null, 'welcome');
    }
    
    /** Send signup notification email to new users */
    public function sendSignupNotificationEmail($userEmail, $userName) {
        $subject = "Account Created - Approval Required - Aetia Talent Agency";
        
        $content = "
        <h2>Welcome to Aetia Talent Agency, {$userName}!</h2>
        <p>Thank you for creating your account with us.</p>
        
        <div class='highlight-box'>
            <h3 style='color: #209cee; margin-top: 0;'>Important Notice</h3>
            <p><strong>All new accounts require approval from Aetia Talent Agency.</strong></p>
            
            <p>After creating your account, our team will contact you to discuss:</p>
            <ul>
                <li>Platform terms and conditions</li>
                <li>Commission structure and revenue sharing</li>
                <li>Business partnership agreements</li>
            </ul>
            
            <p><strong>You can login and access the website, but email and messaging services will be activated once your account is approved.</strong></p>
        </div>
        
        <p>We appreciate your patience during the approval process. Our team will be in touch with you soon.</p>
        
        <p>If you have any immediate questions, please contact us at <a href='mailto:talent@aetia.com.au'>talent@aetia.com.au</a></p>
        
        <br>
        <p>Best regards,<br>The Aetia Team</p>
        ";
        
        $body = $this->wrapInDarkTemplate($subject, $content);
        
        return $this->sendEmail($userEmail, $subject, $body, '', [], null, 'signup_notification');
    }
    
    /** Send password reset email */
    public function sendPasswordResetEmail($userEmail, $userName, $resetCode, $resetUrl) {
        $subject = "Password Reset Request - Aetia Talent Agency";
        
        $content = "
        <h2>Password Reset Request</h2>
        <p>Hello {$userName},</p>
        <p>We received a request to reset your password for your Aetia Talent Agency account.</p>
        <p>Click the button below to reset your password:</p>
        <p style='text-align: center; margin: 30px 0;'>
            <a href='{$resetUrl}' class='button-primary'>Reset Password</a>
        </p>
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p style='word-break: break-all;'><a href='{$resetUrl}'>{$resetUrl}</a></p>
        
        <div class='highlight-box'>
            <p><strong>Security Notice:</strong></p>
            <ul>
                <li>This link will expire in 1 hour for security reasons</li>
                <li>If you didn't request this password reset, please ignore this email</li>
                <li>Your account remains secure</li>
            </ul>
        </div>
        
        <br>
        <p>Best regards,<br>The Aetia Team</p>
        ";
        
        $body = $this->wrapInDarkTemplate($subject, $content);
        
        return $this->sendEmail($userEmail, $subject, $body, '', [], null, 'password_reset');
    }
    
    /** Send new message notification email to user */
    public function sendNewMessageNotification($userEmail, $userName, $messageSubject, $messagePriority = 'normal') {
        $subject = "New Message: " . $messageSubject;
        
        // Set priority indicator and styling
        $priorityText = '';
        $priorityClass = 'priority-normal';
        switch ($messagePriority) {
            case 'urgent':
                $priorityText = '[URGENT] ';
                $priorityClass = 'priority-urgent';
                break;
            case 'high':
                $priorityText = '[HIGH PRIORITY] ';
                $priorityClass = 'priority-high';
                break;
            case 'normal':
                $priorityClass = 'priority-normal';
                break;
            case 'low':
                $priorityClass = 'priority-low';
                break;
        }
        
        $subject = $priorityText . $subject;
        
        $content = "
        <h2>You Have a New Message</h2>
        
        <div class='highlight-box'>
            <p><strong>Hello " . htmlspecialchars($userName) . ",</strong></p>
            <p>You have received a new message from <strong>Aetia Talent Agency</strong>.</p>
            
            <div style='margin: 15px 0;'>
                <p><strong>Subject:</strong> " . htmlspecialchars($messageSubject) . "</p>
                <p><strong>Priority:</strong> <span class='{$priorityClass}'>" . ucfirst($messagePriority) . "</span></p>
            </div>
        </div>
        
        <p><strong>To view and respond to this message:</strong></p>
        <ol>
            <li>Log in to your Aetia account</li>
            <li>Go to your Messages section</li>
            <li>Click on the new message to read and respond</li>
        </ol>
        
        <p style='text-align: center; margin: 30px 0;'>
            <a href='https://aetia.com/login.php' class='button-primary'>Log In to View Message</a>
        </p>
        
        <div class='highlight-box' style='background-color: #2a2d2e; border-left-color: #ffdd57;'>
            <p style='color: #b0b3b5; font-size: 14px; margin: 0;'>
                <strong>Note:</strong> This is an automated notification. Please do not reply to this email directly. 
                Use the message system on the website to respond.
            </p>
        </div>
        ";
        
        $body = $this->wrapInDarkTemplate($subject, $content);
        
        return $this->sendEmail($userEmail, $subject, $body, '', [], null, 'new_message');
    }
    
    /** Send notification email to admins */
    public function sendAdminNotification($subject, $message, $adminEmails = []) {
        if (empty($adminEmails)) {
            // Get admin emails from database if not provided
            $adminEmails = $this->getAdminEmails();
        }
        
        $content = "
        <h2>Admin Notification</h2>
        <div class='highlight-box'>
            <p>{$message}</p>
        </div>
        <br>
        <p style='color: #b0b3b5; font-size: 14px;'>This is an automated notification from the Aetia Talent Agency system.</p>
        ";
        
        $body = $this->wrapInDarkTemplate($subject, $content);
        
        foreach ($adminEmails as $email) {
            $this->sendEmail($email, $subject, $body);
        }
        
        return true;
    }
    
    /** Send contact form notification */
    public function sendContactFormNotification($contactData) {
        $subject = "New Contact Form Submission - Aetia Talent Agency";
        
        $content = "
        <h2>New Contact Form Submission</h2>
        
        <div class='highlight-box'>
            <p><strong>Name:</strong> " . htmlspecialchars($contactData['name']) . "</p>
            <p><strong>Email:</strong> <a href='mailto:" . htmlspecialchars($contactData['email']) . "'>" . htmlspecialchars($contactData['email']) . "</a></p>
            <p><strong>Subject:</strong> " . htmlspecialchars($contactData['subject']) . "</p>
        </div>
        
        <h3>Message:</h3>
        <div class='highlight-box' style='border-left-color: #ffdd57;'>
            <p>" . nl2br(htmlspecialchars($contactData['message'])) . "</p>
        </div>
        
        <br>
        <p style='color: #b0b3b5; font-size: 14px;'>Submitted on: " . date('Y-m-d H:i:s') . "</p>
        ";
        
        $body = $this->wrapInDarkTemplate($subject, $content);
        
        // Send to admin emails
        $adminEmails = $this->getAdminEmails();
        foreach ($adminEmails as $email) {
            $this->sendEmail($email, $subject, $body, '', [], $contactData['email']);
        }
        
        return true;
    }
    
    private function getAdminEmails() {
        // This method would typically query the database for admin emails
        // For now, return a default admin email
        return [$this->config['from_email']];
    }
    
    /** Test email configuration */
    public function testConfiguration() {
        try {
            // Capture debug output
            ob_start();
            $this->mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
            $success = $this->mail->smtpConnect();
            $this->mail->smtpClose();
            $debugOutput = ob_get_clean();
            $this->mail->SMTPDebug = 0;
            // Clean up the debug output for console display
            $cleanOutput = strip_tags($debugOutput); // Remove HTML tags
            $cleanOutput = html_entity_decode($cleanOutput); // Decode HTML entities
            // Add proper line breaks for better readability
            $cleanOutput = preg_replace('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', "\n$1", $cleanOutput);
            $cleanOutput = str_replace('220-', "\n220-", $cleanOutput);
            $cleanOutput = str_replace('250-', "\n250-", $cleanOutput);
            $cleanOutput = str_replace('250 ', "\n250 ", $cleanOutput);
            $cleanOutput = str_replace('334 ', "\n334 ", $cleanOutput);
            $cleanOutput = str_replace('235 ', "\n235 ", $cleanOutput);
            $cleanOutput = str_replace('221 ', "\n221 ", $cleanOutput);
            $cleanOutput = trim($cleanOutput); // Remove extra whitespace
            // Store cleaned debug output in session for JavaScript to access
            $_SESSION['smtp_debug_output'] = $cleanOutput;
            return $success;
        } catch (Exception $e) {
            error_log("Email configuration test failed: " . $e->getMessage());
            return false;
        }
    }
}
