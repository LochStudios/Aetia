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
    
    /** Add footer to email body */
    private function addEmailFooter($body) {
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
        
        $body = "
        <h2>Welcome to Aetia Talent Agency, {$userName}!</h2>
        <p>Thank you for joining our platform. We're excited to have you as part of our community.</p>
        <p>You can now access all features of our platform by logging in to your account.</p>
        <p>If you have any questions or need assistance, please don't hesitate to contact our support team.</p>
        <br>
        <p>Best regards,<br>The Aetia Team</p>
        ";
        
        return $this->sendEmail($userEmail, $subject, $body, '', [], null, 'welcome');
    }
    
    /** Send signup notification email to new users */
    public function sendSignupNotificationEmail($userEmail, $userName) {
        $subject = "Account Created - Approval Required - Aetia Talent Agency";
        
        $body = "
        <h2>Welcome to Aetia Talent Agency, {$userName}!</h2>
        <p>Thank you for creating your account with us.</p>
        
        <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
            <h3 style='color: #495057; margin-top: 0;'>Important Notice</h3>
            <p><strong>All new accounts require approval from Aetia Talent Agency.</strong></p>
            
            <p>After creating your account, our team will contact you to discuss:</p>
            <ul>
                <li>Platform terms and conditions</li>
                <li>Commission structure and revenue sharing</li>
                <li>Business partnership agreements</li>
            </ul>
            
            <p><strong>You will be able to access your account once approved.</strong></p>
        </div>
        
        <p>We appreciate your patience during the approval process. Our team will be in touch with you soon.</p>
        
        <p>If you have any immediate questions, please contact us at <a href='mailto:talant@aetia.com.au'>talant@aetia.com.au</a></p>
        
        <br>
        <p>Best regards,<br>The Aetia Team</p>
        ";
        
        return $this->sendEmail($userEmail, $subject, $body, '', [], null, 'signup_notification');
    }
    
    /** Send password reset email */
    public function sendPasswordResetEmail($userEmail, $userName, $resetCode, $resetUrl) {
        $subject = "Password Reset Request - Aetia Talent Agency";
        
        $body = "
        <h2>Password Reset Request</h2>
        <p>Hello {$userName},</p>
        <p>We received a request to reset your password for your Aetia Talent Agency account.</p>
        <p>Click the link below to reset your password:</p>
        <p><a href='{$resetUrl}' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p>{$resetUrl}</p>
        <p>This link will expire in 1 hour for security reasons.</p>
        <p>If you didn't request this password reset, please ignore this email.</p>
        <br>
        <p>Best regards,<br>The Aetia Team</p>
        ";
        
        return $this->sendEmail($userEmail, $subject, $body, '', [], null, 'password_reset');
    }
    
    /** Send notification email to admins */
    public function sendAdminNotification($subject, $message, $adminEmails = []) {
        if (empty($adminEmails)) {
            // Get admin emails from database if not provided
            $adminEmails = $this->getAdminEmails();
        }
        
        $body = "
        <h2>Admin Notification</h2>
        <p>{$message}</p>
        <br>
        <p>This is an automated notification from the Aetia Talent Agency system.</p>
        ";
        
        foreach ($adminEmails as $email) {
            $this->sendEmail($email, $subject, $body);
        }
        
        return true;
    }
    
    /** Send contact form notification */
    public function sendContactFormNotification($contactData) {
        $subject = "New Contact Form Submission - Aetia Talent Agency";
        
        $body = "
        <h2>New Contact Form Submission</h2>
        <p><strong>Name:</strong> {$contactData['name']}</p>
        <p><strong>Email:</strong> {$contactData['email']}</p>
        <p><strong>Subject:</strong> {$contactData['subject']}</p>
        <p><strong>Message:</strong></p>
        <p>{$contactData['message']}</p>
        <br>
        <p>Submitted on: " . date('Y-m-d H:i:s') . "</p>
        ";
        
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
