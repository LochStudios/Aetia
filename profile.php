<?php
// profile.php - User profile page for Aetia Talant Agency
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/models/User.php';

$userModel = new User();
$user = $userModel->getUserById($_SESSION['user_id']);
$socialConnections = $userModel->getUserSocialConnections($_SESSION['user_id']);

$error_message = '';
$success_message = '';

// Process password change for manual accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if ($user['account_type'] !== 'manual') {
        $error_message = 'Password change is only available for manual accounts.';
    } else {
        $currentPassword = trim($_POST['current_password'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error_message = 'Please fill in all password fields.';
        } elseif ($newPassword !== $confirmPassword) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $error_message = 'New password must be at least 8 characters long.';
        } else {
            // Verify current password
            $authResult = $userModel->authenticateManualUser($user['username'], $currentPassword);
            
            if (!$authResult['success']) {
                $error_message = 'Current password is incorrect.';
            } else {
                // Change password
                $changeResult = $userModel->changePassword($_SESSION['user_id'], $newPassword);
                
                if ($changeResult) {
                    // If this is the auto-generated admin account, mark setup as complete
                    if ($user['username'] === 'admin' && $user['approved_by'] === 'Auto-Generated') {
                        $userModel->markAdminSetupComplete($_SESSION['user_id']);
                    }
                    
                    $success_message = 'Password changed successfully!';
                    
                    // Refresh user data to reflect changes in the UI
                    $user = $userModel->getUserById($_SESSION['user_id']);
                } else {
                    $error_message = 'Failed to change password. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Profile | Aetia Talant Agency';
ob_start();
?>
<div class="content">
    <h2 class="title is-2 has-text-info mb-4">
        <span class="icon"><i class="fas fa-user-cog"></i></span>
        Profile Settings
    </h2>
    
    <?php if ($user['username'] === 'admin' && $user['approved_by'] === 'Auto-Generated'): ?>
    <div class="notification is-warning is-light mb-4">
        <div class="content">
            <p><strong><i class="fas fa-shield-alt"></i> Admin Security Notice</strong></p>
            <p>You are logged in with the auto-generated admin account. For security purposes, please change your password immediately.</p>
            <p>Consider creating a personalized admin account and disabling this default account after setup is complete.</p>
        </div>
    </div>
    <?php endif; ?>
    
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
    
    <div class="columns">
        <div class="column is-4">
            <div class="card">
                <div class="card-content has-text-centered">
                    <?php if ($user['profile_image']): ?>
                        <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile Picture" style="width:120px;height:120px;border-radius:50%;object-fit:cover;margin-bottom:1rem;">
                    <?php else: ?>
                        <div style="width:120px;height:120px;border-radius:50%;background:#f5f5f5;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                            <span class="icon is-large has-text-grey">
                                <i class="fas fa-user fa-3x"></i>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="title is-4"><?= htmlspecialchars($user['username']) ?></h3>
                    <p class="subtitle is-6 has-text-grey">
                        <?= ucfirst($user['account_type']) ?> Account
                    </p>
                    
                    <?php if ($user['first_name'] || $user['last_name']): ?>
                    <p class="has-text-grey">
                        <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                    </p>
                    <?php endif; ?>
                    
                    <p class="has-text-grey is-size-7">
                        Member since <?= date('M j, Y', strtotime($user['created_at'])) ?>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="column is-8">
            <div class="card">
                <div class="card-content">
                    <h4 class="title is-5 mb-4">Account Information</h4>
                    
                    <div class="field">
                        <label class="label">Username</label>
                        <div class="control">
                            <input class="input" type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Email</label>
                        <div class="control">
                            <input class="input" type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Account Type</label>
                        <div class="control">
                            <input class="input" type="text" value="<?= ucfirst($user['account_type']) ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Account Status</label>
                        <div class="control">
                            <span class="tag <?= $user['is_verified'] ? 'is-success' : 'is-warning' ?>">
                                <span class="icon"><i class="fas fa-<?= $user['is_verified'] ? 'check-circle' : 'clock' ?>"></i></span>
                                <span><?= $user['is_verified'] ? 'Verified' : 'Pending Verification' ?></span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($user['account_type'] === 'manual'): ?>
            <div class="card mt-4">
                <div class="card-content">
                    <h4 class="title is-5 mb-4">
                        <span class="icon has-text-warning"><i class="fas fa-key"></i></span>
                        Change Password
                    </h4>
                    
                    <div class="notification is-info is-light mb-4">
                        <div class="content">
                            <p>For security, please enter your current password to confirm changes.</p>
                            <p>Your new password must be at least 8 characters long.</p>
                        </div>
                    </div>
                    
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="field">
                            <label class="label">Current Password</label>
                            <div class="control has-icons-left">
                                <input class="input" type="password" name="current_password" placeholder="Enter current password" required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-lock"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">New Password</label>
                            <div class="control has-icons-left">
                                <input class="input" type="password" name="new_password" placeholder="Enter new password" minlength="8" required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">Confirm New Password</label>
                            <div class="control has-icons-left">
                                <input class="input" type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                        </div>
                        
                        <div class="field">
                            <div class="control">
                                <button class="button is-warning" type="submit">
                                    <span class="icon"><i class="fas fa-check"></i></span>
                                    <span>Change Password</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($socialConnections)): ?>
            <div class="card mt-4">
                <div class="card-content">
                    <h4 class="title is-5 mb-4">Connected Social Accounts</h4>
                    
                    <?php foreach ($socialConnections as $connection): ?>
                    <div class="notification is-light mb-3">
                        <div class="level">
                            <div class="level-left">
                                <div class="level-item">
                                    <span class="icon has-text-<?= $connection['platform'] === 'twitch' ? 'link' : 'primary' ?>">
                                        <i class="fab fa-<?= $connection['platform'] ?> fa-2x"></i>
                                    </span>
                                </div>
                                <div class="level-item">
                                    <div>
                                        <p class="title is-6"><?= ucfirst($connection['platform']) ?></p>
                                        <p class="subtitle is-7">@<?= htmlspecialchars($connection['social_username']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="level-item">
                                    <?php if ($connection['is_primary']): ?>
                                        <span class="tag is-primary">Primary</span>
                                    <?php endif; ?>
                                    <span class="tag is-success">Connected</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
include 'layout.php';
?>
