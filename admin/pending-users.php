<?php
// admin/pending-users.php - Admin interface for managing pending user approvals
session_start();

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

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = intval($_POST['user_id']);
    $currentUser = $userModel->getUserWithAdminStatus($_SESSION['user_id']);
    $adminName = $currentUser['username'] ?? 'Admin';
    
    if ($_POST['action'] === 'approve') {
        if ($userModel->approveUser($userId, $adminName)) {
            $message = 'User approved successfully!';
        } else {
            $message = 'Error approving user.';
        }
    } elseif ($_POST['action'] === 'reject') {
        $rejectionReason = trim($_POST['rejection_reason'] ?? 'No reason provided');
        if ($userModel->rejectUser($userId, $rejectionReason, $adminName)) {
            $message = 'User rejected successfully!';
        } else {
            $message = 'Error rejecting user.';
        }
    } elseif ($_POST['action'] === 'mark_contact') {
        $contactNotes = trim($_POST['contact_notes'] ?? '');
        if ($userModel->markContactAttempt($userId, $contactNotes)) {
            $message = 'Contact attempt marked successfully!';
        } else {
            $message = 'Error marking contact attempt.';
        }
    } elseif ($_POST['action'] === 'mark_admin') {
        if ($userModel->markUserAsAdmin($userId, $adminName)) {
            $message = 'User marked as admin and approved successfully!';
        } else {
            $message = 'Error marking user as admin.';
        }
    }
}

$pageTitle = 'Pending User Approvals | Aetia Admin';
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
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fas fa-users-cog"></i></span>
                Pending User Approvals
            </h2>
        </div>
        <div class="level-right">
            <a href="../index.php" class="button is-light">
                <span class="icon"><i class="fas fa-home"></i></span>
                <span>Back to Website</span>
            </a>
        </div>
    </div>
    
    <div class="notification is-warning is-light">
        <p><strong>Important:</strong> These users are waiting for approval to access the Aetia Talant Agency platform. Contact each user to discuss platform terms, commission structure, and business agreements before approving their accounts.</p>
    </div>
    
    <?php 
    $pendingUsers = $userModel->getPendingUsers();
    if (empty($pendingUsers)): 
    ?>
    <div class="notification is-success is-light">
        <span class="icon-text">
            <span class="icon"><i class="fas fa-check-circle"></i></span>
            <span>No pending user approvals at this time.</span>
        </span>
    </div>
    
    <?php else: ?>
    
    <?php foreach ($pendingUsers as $user): ?>
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
                        <span class="tag is-info"><?= ucfirst($user['account_type']) ?> Account</span>
                        <?php if ($user['contact_attempted']): ?>
                        <span class="tag is-warning">Contact Attempted</span>
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
                        <p><strong>Signed up:</strong> <?= date('M j, Y g:i A', strtotime($user['created_at'])) ?></p>
                        <?php if ($user['contact_attempted'] && $user['contact_date']): ?>
                        <p><strong>Last contact:</strong> <?= date('M j, Y g:i A', strtotime($user['contact_date'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="buttons">
                <!-- Mark Contact Attempt -->
                <form method="POST" style="display:inline;" id="contact-form-<?= $user['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="mark_contact">
                    <input type="hidden" name="contact_notes" id="contact-notes-<?= $user['id'] ?>">
                    <button class="button is-warning is-small contact-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                        <span class="icon"><i class="fas fa-phone"></i></span>
                        <span>Mark Contact</span>
                    </button>
                </form>
                
                <!-- Approve User -->
                <form method="POST" style="display:inline;" id="approve-form-<?= $user['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="button is-success is-small approve-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                        <span class="icon"><i class="fas fa-check"></i></span>
                        <span>Approve</span>
                    </button>
                </form>
                
                <!-- Mark as Admin -->
                <form method="POST" style="display:inline;" id="admin-form-<?= $user['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="mark_admin">
                    <button class="button is-info is-small admin-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                        <span class="icon"><i class="fas fa-crown"></i></span>
                        <span>Mark as Admin</span>
                    </button>
                </form>
                
                <!-- Reject User -->
                <form method="POST" style="display:inline;" id="reject-form-<?= $user['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="rejection_reason" id="rejection-reason-<?= $user['id'] ?>">
                    <button class="button is-danger is-small reject-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                        <span class="icon"><i class="fas fa-times"></i></span>
                        <span>Reject</span>
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
    // Handle Mark Contact buttons
    document.querySelectorAll('.contact-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username;
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || username;
            
            Swal.fire({
                title: 'Mark Contact Attempt',
                html: `Record contact attempt for <strong style="color: #333;">${decodedUsername}</strong>:<br><br>`,
                icon: 'info',
                input: 'textarea',
                inputPlaceholder: 'Enter contact notes (optional)...',
                showCancelButton: true,
                confirmButtonColor: '#ffdd57',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-phone"></i> Mark Contact',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-warning',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`contact-notes-${userId}`).value = result.value || '';
                    document.getElementById(`contact-form-${userId}`).submit();
                }
            });
        });
    });
    
    // Handle Approve User buttons
    document.querySelectorAll('.approve-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username;
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || username;
            
            Swal.fire({
                title: 'Approve User?',
                html: `Are you sure you want to approve <strong style="color: #333;">${decodedUsername}</strong>?<br><br>They will be able to access their account immediately.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#48c78e',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-check"></i> Yes, Approve',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-success',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`approve-form-${userId}`).submit();
                }
            });
        });
    });
    
    // Handle Mark as Admin buttons
    document.querySelectorAll('.admin-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            
            Swal.fire({
                title: 'Mark as Admin?',
                html: `Are you sure you want to mark <strong style="color: #333;">${decodedUsername}</strong> as an administrator?<br><br><strong>This will:</strong><br>• Automatically approve their account<br>• Grant full admin privileges<br>• Allow them to manage other users`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3e8ed0',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-crown"></i> Yes, Make Admin',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-info',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`admin-form-${userId}`).submit();
                }
            });
        });
    });
    
    // Handle Reject User buttons
    document.querySelectorAll('.reject-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username;
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || username;
            
            Swal.fire({
                title: 'Reject User?',
                html: `Are you sure you want to reject <strong style="color: #333;">${decodedUsername}</strong>?<br><br>Please provide a reason for rejection:`,
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Enter rejection reason...',
                inputValidator: (value) => {
                    if (!value || value.trim() === '') {
                        return 'You must provide a rejection reason!';
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#f14668',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-times"></i> Yes, Reject',
                cancelButtonText: '<i class="fas fa-arrow-left"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-danger',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`rejection-reason-${userId}`).value = result.value;
                    document.getElementById(`reject-form-${userId}`).submit();
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
