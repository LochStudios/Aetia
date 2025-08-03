<?php
// forgot-password.php - Password reset request page for Aetia Talant Agency
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/models/User.php';

$error_message = '';
$success_message = '';

$userModel = new User();

// Process password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if the email belongs to a valid client account
        $emailCheck = $userModel->checkEmailExists($email);
        // Always show the same success message for security (prevent email enumeration)
        $success_message = "If an account with that email exists, you will receive an email with instructions within the next few minutes.";
        $success_message .= "<br><small>Please check your inbox and spam folder. The reset link will expire in 1 hour for security reasons.</small>";
        // Only actually send the email if the account exists and can be reset
        if ($emailCheck['exists'] && $emailCheck['canReset']) {
            $result = $userModel->generatePasswordResetToken($email);
            if ($result['success']) {
                // Generate the reset URL
                $resetLink = "https://aetia.com.au/reset-password.php?resetcode=" . $result['token'];
                // Send the password reset email
                try {
                    require_once __DIR__ . '/services/EmailService.php';
                    $emailService = new EmailService();
                    $emailService->sendPasswordResetEmail(
                        $email, 
                        $result['username'], 
                        $result['token'], 
                        $resetLink
                    );
                    // Email sent successfully - but we don't change the message
                    error_log("Password reset email sent to: " . $email);
                } catch (Exception $e) {
                    error_log('Password reset email failed for ' . $email . ': ' . $e->getMessage());
                    // Don't show email failure to user for security reasons
                    // The generic success message will still be displayed
                }
            } else {
                error_log("Password reset token generation failed for: " . $email);
                // Don't reveal token generation failure to user
            }
        } else {
            // Log the reason why reset was not allowed (for admin debugging)
            if (!$emailCheck['exists']) {
                error_log("Password reset requested for non-existent email: " . $email);
            } else {
                error_log("Password reset requested for ineligible account: " . $email . " - " . $emailCheck['message']);
            }
        }
    }
}

$pageTitle = 'Forgot Password | Aetia Talant Agency';
ob_start();
?>
<div class="columns is-centered">
    <div class="column is-6-tablet is-5-desktop is-4-widescreen">
        <div class="card login-card">
            <div class="card-content">
                <h2 class="title is-4 has-text-centered mb-5">
                    <span class="icon has-text-primary"><i class="fas fa-key"></i></span>
                    Reset Password
                </h2>
                
                <div class="notification is-info is-light mb-4">
                    <div class="content">
                        <p>Enter your email address and we'll send you instructions to reset your password.</p>
                        <p><strong>Note:</strong> Password reset is only available for manual accounts (not social logins).</p>
                    </div>
                </div>
                
                <?php if ($error_message): ?>
                <div class="notification is-danger is-light mb-4">
                    <button class="delete"></button>
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="notification is-success is-light mb-4">
                    <button class="delete"></button>
                    <?= $success_message ?>
                </div>
                <?php endif; ?>
                
                <?php if (!$success_message): ?>
                <form method="POST" action="forgot-password.php">
                    <div class="field">
                        <label class="label">Email Address</label>
                        <div class="control has-icons-left">
                            <input class="input" type="email" name="email" placeholder="Enter your email address" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-envelope"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary is-fullwidth" type="submit">
                                <span class="icon"><i class="fas fa-paper-plane"></i></span>
                                <span>Send Reset Instructions</span>
                            </button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <hr>
                
                <div class="has-text-centered">
                    <p class="is-size-7">
                        Remember your password? 
                        <a href="login.php" class="has-text-primary">
                            <span class="icon"><i class="fas fa-sign-in-alt"></i></span>
                            Back to Login
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
