<?php
// reset-password.php - Password reset page for Aetia Talent Agency
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/models/User.php';

$error_message = '';
$success_message = '';
$resetCode = $_GET['resetcode'] ?? '';

if (empty($resetCode)) {
    $error_message = 'Invalid or missing reset code.';
    $validToken = false;
} else {
    $userModel = new User();
    $tokenValidation = $userModel->validatePasswordResetToken($resetCode);
    $validToken = $tokenValidation['success'];
    
    if (!$validToken) {
        $error_message = $tokenValidation['message'];
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error_message = 'Please fill in all fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } else {
        $result = $userModel->resetPasswordWithToken($resetCode, $newPassword);
        
        if ($result['success']) {
            $success_message = 'Password reset successfully! You can now login with your new password.';
            $validToken = false; // Prevent form from showing again
        } else {
            $error_message = $result['message'];
        }
    }
}

$pageTitle = 'Reset Password | Aetia Talent Agency';
ob_start();
?>
<div class="columns is-centered">
    <div class="column is-6-tablet is-5-desktop is-4-widescreen">
        <div class="card login-card">
            <div class="card-content">
                <h2 class="title is-4 has-text-centered mb-5">
                    <span class="icon has-text-primary"><i class="fas fa-lock-open"></i></span>
                    Reset Password
                </h2>
                
                <?php if ($error_message): ?>
                <div class="notification is-danger is-light mb-4">
                    <button class="delete"></button>
                    <?= htmlspecialchars($error_message) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="notification is-success is-light mb-4">
                    <button class="delete"></button>
                    <?= htmlspecialchars($success_message) ?>
                </div>
                <?php endif; ?>
                
                <?php if ($validToken && !$success_message): ?>
                <div class="notification is-info is-light mb-4">
                    <div class="content">
                        <p>Please enter your new password below.</p>
                        <p>Your password must be at least 8 characters long.</p>
                    </div>
                </div>
                
                <form method="POST" action="reset-password.php?resetcode=<?= htmlspecialchars($resetCode) ?>">
                    <div class="field">
                        <label class="label">New Password</label>
                        <div class="control has-icons-left">
                            <input class="input" type="password" name="password" placeholder="Enter new password" minlength="8" required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Confirm New Password</label>
                        <div class="control has-icons-left">
                            <input class="input" type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" required>
                            <span class="icon is-small is-left">
                                <i class="fas fa-lock"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary is-fullwidth" type="submit">
                                <span class="icon"><i class="fas fa-check"></i></span>
                                <span>Reset Password</span>
                            </button>
                        </div>
                    </div>
                </form>
                <?php elseif (!$validToken && !$success_message): ?>
                <div class="notification is-warning is-light mb-4">
                    <div class="content">
                        <p>This reset link is invalid or has expired.</p>
                        <p>Password reset links expire after 1 hour for security reasons.</p>
                    </div>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="has-text-centered">
                    <?php if ($success_message): ?>
                    <p class="is-size-7">
                        <a href="login.php" class="has-text-primary">
                            <span class="icon"><i class="fas fa-sign-in-alt"></i></span>
                            Login Now
                        </a>
                    </p>
                    <?php else: ?>
                    <p class="is-size-7">
                        Need a new reset link? 
                        <a href="forgot-password.php" class="has-text-primary">
                            <span class="icon"><i class="fas fa-key"></i></span>
                            Request Reset
                        </a>
                    </p>
                    <p class="is-size-7 mt-2">
                        Remember your password? 
                        <a href="login.php" class="has-text-primary">
                            <span class="icon"><i class="fas fa-sign-in-alt"></i></span>
                            Back to Login
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
