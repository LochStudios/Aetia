<?php
// admin/unverified-users.php - Admin interface for managing unverified users
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/User.php';

$userModel = new User();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    // Not an admin - redirect to homepage
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

$message = '';

// Handle verification/deactivation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = intval($_POST['user_id']);
    $currentUser = $userModel->getUserWithAdminStatus($_SESSION['user_id']);
    $adminName = $currentUser['username'] ?? 'Admin';
    
    if ($_POST['action'] === 'verify') {
        if ($userModel->verifyUser($userId, $adminName)) {
            $message = 'User verified successfully!';
        } else {
            $message = 'Error verifying user.';
        }
    } elseif ($_POST['action'] === 'deactivate') {
        $deactivationReason = trim($_POST['deactivation_reason'] ?? 'No reason provided');
        if ($userModel->deactivateUser($userId, $deactivationReason, $adminName)) {
            $message = 'User deactivated successfully!';
        } else {
            $message = 'Error deactivating user.';
        }
    }
}

$pageTitle = 'Unverified Users | Aetia Admin';
ob_start();
?>

<?php if ($message): ?>
<div class="notification is-info is-light">
    <button class="delete"></button>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="content">
    <div class="level">
        <div class="level-left">
            <h2 class="title is-2 has-text-warning">
                <span class="icon"><i class="fas fa-user-check"></i></span>
                Unverified Users
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="contact-form.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                    <span>Contact Forms</span>
                </a>
                <a href="pending-users.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-users-cog"></i></span>
                    <span>Pending Users</span>
                </a>
                <a href="messages.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                    <span>Messages</span>
                </a>
                <a href="../index.php" class="button is-light is-small">
                    <span class="icon"><i class="fas fa-home"></i></span>
                    <span>Back to Website</span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="notification is-warning is-light">
        <p><strong>Important:</strong> These users have accounts but have not completed email verification or identity verification. Review each user carefully and verify legitimate accounts or deactivate suspicious ones.</p>
    </div>
    
    <?php 
    $unverifiedUsers = $userModel->getUnverifiedUsers();
    if (empty($unverifiedUsers)): 
    ?>
    <div class="notification is-success is-light">
        <span class="icon-text">
            <span class="icon"><i class="fas fa-check-circle"></i></span>
            <span>No unverified users at this time. All users are verified!</span>
        </span>
    </div>
    
    <?php else: ?>
    
    <?php foreach ($unverifiedUsers as $user): ?>
    <div class="card mb-4">
        <div class="card-content">
            <div class="media">
                <div class="media-left">
                    <figure class="image is-64x64">
                        <?php if ($user['profile_image']): ?>
                            <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile Picture" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
                        <?php else: ?>
                            <div class="has-background-light is-flex is-align-items-center is-justify-content-center" style="width:64px;height:64px;border-radius:50%;">
                                <span class="icon is-large has-text-grey">
                                    <i class="fas fa-user fa-2x"></i>
                                </span>
                            </div>
                        <?php endif; ?>
                    </figure>
                </div>
                <div class="media-content">
                    <p class="title is-4"><?= htmlspecialchars($user['username']) ?></p>
                    <p class="subtitle is-6">
                        <span class="tag is-warning">Unverified</span>
                        <span class="tag is-<?= $user['account_type'] === 'manual' ? 'info' : 'link' ?>"><?= ucfirst($user['account_type']) ?> Account</span>
                        <?php if ($user['approval_status'] === 'approved'): ?>
                        <span class="tag is-success">Approved</span>
                        <?php elseif ($user['approval_status'] === 'pending'): ?>
                        <span class="tag is-warning">Pending Approval</span>
                        <?php endif; ?>
                    </p>
                    <div class="content">
                        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <?php if ($user['first_name'] || $user['last_name']): ?>
                        <p><strong>Name:</strong> <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></p>
                        <?php endif; ?>
                        <?php if ($user['social_username']): ?>
                        <p><strong>Social Username:</strong> <?= htmlspecialchars($user['social_username']) ?></p>
                        <?php endif; ?>
                        <p><strong>Account Created:</strong> <?= formatDateForUser($user['created_at']) ?></p>
                        <p><strong>Status:</strong> 
                            <span class="tag is-warning">
                                <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                                <span>Not Verified</span>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="buttons">
                <!-- Verify User -->
                <form method="POST" style="display:inline;" id="verify-form-<?= $user['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="verify">
                    <button class="button is-success is-small verify-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                        <span class="icon"><i class="fas fa-check-circle"></i></span>
                        <span>Verify User</span>
                    </button>
                </form>
                
                <!-- Deactivate User -->
                <form method="POST" style="display:inline;" id="deactivate-form-<?= $user['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="deactivation_reason" id="deactivation-reason-<?= $user['id'] ?>">
                    <button class="button is-danger is-small deactivate-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                        <span class="icon"><i class="fas fa-user-slash"></i></span>
                        <span>Deactivate</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

// Start output buffering for scripts
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle Verify User buttons
    document.querySelectorAll('.verify-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            
            Swal.fire({
                title: 'Verify User?',
                html: `Are you sure you want to verify <strong style="color: #333;">${decodedUsername}</strong>?<br><br>This will mark their account as verified and trusted.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#48c78e',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-check-circle"></i> Yes, Verify',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-success',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`verify-form-${userId}`).submit();
                }
            });
        });
    });
    
    // Handle Deactivate User buttons
    document.querySelectorAll('.deactivate-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            
            Swal.fire({
                title: 'Deactivate User?',
                html: `Are you sure you want to deactivate <strong style="color: #333;">${decodedUsername}</strong>?<br><br>Please provide a reason for deactivation:`,
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Enter deactivation reason...',
                inputValidator: (value) => {
                    if (!value || value.trim() === '') {
                        return 'You must provide a deactivation reason!';
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#f14668',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-user-slash"></i> Yes, Deactivate',
                cancelButtonText: '<i class="fas fa-arrow-left"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-danger',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`deactivation-reason-${userId}`).value = result.value;
                    document.getElementById(`deactivate-form-${userId}`).submit();
                }
            });
        });
    });
});
</script>
<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
