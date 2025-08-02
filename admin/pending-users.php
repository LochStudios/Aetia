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
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="mark_contact">
                    <div class="field has-addons" style="margin-bottom:0;">
                        <div class="control">
                            <input class="input is-small" type="text" name="contact_notes" placeholder="Contact notes..." style="min-width:200px;">
                        </div>
                        <div class="control">
                            <button class="button is-warning is-small" type="submit">
                                <span class="icon"><i class="fas fa-phone"></i></span>
                                <span>Mark Contact</span>
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Approve User -->
                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this user? They will be able to access their account.')">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="button is-success is-small" type="submit">
                        <span class="icon"><i class="fas fa-check"></i></span>
                        <span>Approve</span>
                    </button>
                </form>
                
                <!-- Reject User -->
                <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this user? Please provide a reason.')">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <div class="field has-addons" style="margin-bottom:0;">
                        <div class="control">
                            <input class="input is-small" type="text" name="rejection_reason" placeholder="Rejection reason..." required style="min-width:200px;">
                        </div>
                        <div class="control">
                            <button class="button is-danger is-small" type="submit">
                                <span class="icon"><i class="fas fa-times"></i></span>
                                <span>Reject</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
