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
        $result = $userModel->generatePasswordResetToken($email);
        
        if ($result['success']) {
            // In a real application, you would send an email here
            // For now, we'll display the reset link directly
            $resetLink = "http" . (isset($_SERVER['HTTPS']) ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset-password.php?token=" . $result['token'];
            
            $success_message = "Password reset instructions have been generated for this account.";
            
            // For development purposes, show the reset link
            $success_message .= "<br><br><strong>Development Note:</strong> Since email is not configured, here is your reset link:<br>";
            $success_message .= "<a href='" . $resetLink . "' class='button is-primary is-small mt-2'>Reset Password</a>";
            $success_message .= "<br><small>This link expires in 1 hour.</small>";
        } else {
            // Don't reveal if email exists or not for security
            $success_message = "If an account with that email exists, password reset instructions have been sent.";
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
