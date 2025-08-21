<?php
// verify-email.php - Page to verify user email using 6-digit code sent by admin
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/models/User.php';

$error_message = '';
$success_message = '';
$emailPrefill = isset($_GET['email']) ? trim($_GET['email']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $userModel = new User();
    $res = $userModel->validateEmailVerificationCode($email, $code);
    if ($res['success']) {
        $success_message = 'Email verified successfully. You may now log in.';
    } else {
        $error_message = $res['message'] ?? 'Invalid or expired code.';
    }
}

$pageTitle = 'Verify Email | Aetia Talent Agency';
ob_start();
?>

<div class="columns is-centered">
    <div class="column is-6-tablet is-5-desktop is-4-widescreen">
        <div class="card login-card">
            <div class="card-content">
                <h2 class="title is-4 has-text-centered mb-5">
                    <span class="icon has-text-primary"><i class="fas fa-envelope-open"></i></span>
                    Verify Your Email
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
                <?php if (!$success_message): ?>
                <div class="notification is-info is-light mb-4">
                    <div class="content">
                        <p>Please enter the 6-digit verification code sent to your email address.</p>
                        <p>The code will expire in 1 hour.</p>
                    </div>
                </div>
                <form method="post" action="verify-email.php">
                    <div class="field">
                        <label class="label">Email</label>
                        <div class="control has-icons-left">
                            <input class="input" type="email" name="email" value="<?= htmlspecialchars($emailPrefill) ?>" required>
                            <span class="icon is-small is-left"><i class="fas fa-envelope"></i></span>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label">6-digit code</label>
                        <div class="control has-icons-left">
                            <input class="input" type="text" name="code" pattern="\d{6}" maxlength="6" placeholder="123456" required>
                            <span class="icon is-small is-left"><i class="fas fa-key"></i></span>
                        </div>
                    </div>
                    <div class="field">
                        <div class="control">
                            <button class="button is-primary is-fullwidth" type="submit">
                                <span class="icon"><i class="fas fa-check"></i></span>
                                <span>Verify Email</span>
                            </button>
                        </div>
                    </div>
                </form>
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
                        Need a new verification code? Please contact support or ask an admin to resend.
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
