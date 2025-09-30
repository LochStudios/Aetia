<?php
// admin/users.php - Unified admin interface for managing all users
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

// Handle user management actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = intval($_POST['user_id']);
    $currentUser = $userModel->getUserWithAdminStatus($_SESSION['user_id']);
    $adminName = $currentUser['username'] ?? 'Admin';
    switch ($_POST['action']) {
        case 'approve':
            if ($userModel->approveUser($userId, $adminName)) {
                $message = 'User approved successfully!';
            } else {
                $message = 'Error approving user.';
            }
            break;
        case 'reject':
            $rejectionReason = trim($_POST['rejection_reason'] ?? 'No reason provided');
            if ($userModel->rejectUser($userId, $rejectionReason, $adminName)) {
                $message = 'User rejected successfully!';
            } else {
                $message = 'Error rejecting user.';
            }
            break;
        case 'mark_contact':
            $contactNotes = trim($_POST['contact_notes'] ?? '');
            if ($userModel->markContactAttempt($userId, $contactNotes)) {
                $message = 'Contact attempt marked successfully!';
            } else {
                $message = 'Error marking contact attempt.';
            }
            break;
        case 'mark_admin':
            if ($userModel->markUserAsAdmin($userId, $adminName)) {
                $message = 'User marked as admin and approved successfully!';
            } else {
                $message = 'Error marking user as admin.';
            }
            break;
        case 'verify':
            // Generate and send a 6-digit email verification code to the user instead of immediate verify
            $gen = $userModel->generateEmailVerificationCode($userId);
            if ($gen['success']) {
                $message = 'Verification code sent to user email. They must enter the code within 1 hour to verify their email.';
            } else {
                $message = 'Error sending verification code: ' . ($gen['message'] ?? 'Unknown error');
            }
            break;
        case 'deactivate':
            $deactivationReason = trim($_POST['deactivation_reason'] ?? 'No reason provided');
            if ($userModel->deactivateUser($userId, $deactivationReason, $adminName)) {
                $message = 'User deactivated successfully!';
            } else {
                $message = 'Error deactivating user.';
            }
            break;
        case 'suspend':
            $suspensionReason = trim($_POST['suspension_reason'] ?? 'No reason provided');
            if ($userModel->suspendUser($userId, $suspensionReason, $adminName)) {
                $message = 'User suspended successfully!';
            } else {
                $message = 'Error suspending user.';
            }
            break;
        case 'unsuspend':
            if ($userModel->unsuspendUser($userId, $adminName)) {
                $message = 'User unsuspended successfully!';
            } else {
                $message = 'Error unsuspending user.';
            }
            break;
        case 'make_admin':
            if ($userModel->makeUserAdmin($userId, $adminName)) {
                $message = 'User granted admin privileges successfully!';
            } else {
                $message = 'Error granting admin privileges.';
            }
            break;
        case 'remove_admin':
            if ($userModel->removeUserAdmin($userId, $adminName)) {
                $message = 'Admin privileges removed successfully!';
            } else {
                $message = 'Error removing admin privileges.';
            }
            break;
        case 'update_email':
            $newEmail = trim($_POST['new_email'] ?? '');
            if ($userModel->updatePublicEmail($userId, $newEmail, $adminName)) {
                $message = 'Public email updated successfully!';
            } else {
                $message = 'Error updating public email.';
            }
            break;
    }
}

// Get filter parameter
$filter = $_GET['filter'] ?? 'all';

$pageTitle = 'User Management | Aetia Admin';
ob_start();
?>

<?php if ($message): ?>
<div class="notification is-info is-light">
    <button class="delete"></button>
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-users-cog"></i></span><span>Users</span></a></li>
            <li><a href="messages.php"><span class="icon is-small"><i class="fas fa-envelope-open-text"></i></span><span>Messages</span></a></li>
            <li><a href="archived-messages.php"><span class="icon is-small"><i class="fas fa-archive"></i></span><span>Archived Messages</span></a></li>
            <li><a href="create-message.php"><span class="icon is-small"><i class="fas fa-plus"></i></span><span>New Message</span></a></li>
            <li><a href="send-emails.php"><span class="icon is-small"><i class="fas fa-paper-plane"></i></span><span>Send Emails</span></a></li>
            <li><a href="email-logs.php"><span class="icon is-small"><i class="fas fa-chart-line"></i></span><span>Email Logs</span></a></li>
            <li><a href="sms-logs.php"><span class="icon is-small"><i class="fas fa-sms"></i></span><span>SMS Logs</span></a></li>
            <li><a href="contact-form.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Contact Forms</span></a></li>
            <li><a href="contracts.php"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Contracts</span></a></li>
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
            <li><a href="fix-primary-connections.php"><span class="icon is-small"><i class="fas fa-tools"></i></span><span>Fix Social Connections</span></a></li>
        </ul>
    </nav>
    </nav>
    
    <div class="level">
        <div class="level-left">
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fas fa-users-cog"></i></span>
                User Management
            </h2>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="tabs is-boxed">
        <ul>
            <li class="<?= $filter === 'all' ? 'is-active' : '' ?>">
                <a href="?filter=all">
                    <span class="icon is-small"><i class="fas fa-users"></i></span>
                    <span>All Users</span>
                </a>
            </li>
            <li class="<?= $filter === 'pending' ? 'is-active' : '' ?>">
                <a href="?filter=pending">
                    <span class="icon is-small"><i class="fas fa-user-clock"></i></span>
                    <span>Pending Approval</span>
                </a>
            </li>
            <li class="<?= $filter === 'unverified' ? 'is-active' : '' ?>">
                <a href="?filter=unverified">
                    <span class="icon is-small"><i class="fas fa-user-check"></i></span>
                    <span>Unverified</span>
                </a>
            </li>
            <li class="<?= $filter === 'active' ? 'is-active' : '' ?>">
                <a href="?filter=active">
                    <span class="icon is-small"><i class="fas fa-user-check"></i></span>
                    <span>Active Users</span>
                </a>
            </li>
            <li class="<?= $filter === 'inactive' ? 'is-active' : '' ?>">
                <a href="?filter=inactive">
                    <span class="icon is-small"><i class="fas fa-user-slash"></i></span>
                    <span>Inactive Users</span>
                </a>
            </li>
            <li class="<?= $filter === 'suspended' ? 'is-active' : '' ?>">
                <a href="?filter=suspended">
                    <span class="icon is-small"><i class="fas fa-ban"></i></span>
                    <span>Suspended Users</span>
                </a>
            </li>
            <li class="<?= $filter === 'admins' ? 'is-active' : '' ?>">
                <a href="?filter=admins">
                    <span class="icon is-small"><i class="fas fa-crown"></i></span>
                    <span>Administrators</span>
                </a>
            </li>
        </ul>
    </div>
    
    <?php 
    // Get all users and apply filter
    $allUsers = $userModel->getAllUsersForAdmin();
    $filteredUsers = [];
    
    foreach ($allUsers as $user) {
        switch ($filter) {
            case 'pending':
                if ($user['approval_status'] === 'pending') {
                    $filteredUsers[] = $user;
                }
                break;
            case 'unverified':
                if ($user['is_verified'] == 0 && $user['is_active'] == 1) {
                    $filteredUsers[] = $user;
                }
                break;
            case 'active':
                if ($user['is_active'] == 1 && $user['is_verified'] == 1 && $user['approval_status'] === 'approved') {
                    $filteredUsers[] = $user;
                }
                break;
            case 'inactive':
                if ($user['is_active'] == 0) {
                    $filteredUsers[] = $user;
                }
                break;
            case 'suspended':
                if ($user['is_suspended'] == 1) {
                    $filteredUsers[] = $user;
                }
                break;
            case 'admins':
                if ($user['is_admin'] == 1) {
                    $filteredUsers[] = $user;
                }
                break;
            default: // 'all'
                $filteredUsers[] = $user;
                break;
        }
    }
    
    if (empty($filteredUsers)): 
    ?>
    <div class="notification is-info is-light">
        <span class="icon-text">
            <span class="icon"><i class="fas fa-info-circle"></i></span>
            <span>No users found for the selected filter.</span>
        </span>
    </div>
    
    <?php else: ?>
    
    <!-- User Statistics -->
    <div class="columns is-multiline mb-4">
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-info"><?= count($allUsers) ?></p>
                <p class="subtitle is-6">Total Users</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-warning">
                    <?= count(array_filter($allUsers, fn($u) => $u['approval_status'] === 'pending')) ?>
                </p>
                <p class="subtitle is-6">Pending</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-warning">
                    <?= count(array_filter($allUsers, fn($u) => $u['is_verified'] == 0 && $u['is_active'] == 1)) ?>
                </p>
                <p class="subtitle is-6">Unverified</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-success">
                    <?= count(array_filter($allUsers, fn($u) => $u['is_active'] == 1 && $u['is_verified'] == 1 && $u['approval_status'] === 'approved')) ?>
                </p>
                <p class="subtitle is-6">Active</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-danger">
                    <?= count(array_filter($allUsers, fn($u) => $u['is_active'] == 0)) ?>
                </p>
                <p class="subtitle is-6">Inactive</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-warning">
                    <?= count(array_filter($allUsers, fn($u) => $u['is_suspended'] == 1)) ?>
                </p>
                <p class="subtitle is-6">Suspended</p>
            </div>
        </div>
        <div class="column is-2">
            <div class="box has-text-centered">
                <p class="title is-4 has-text-info">
                    <?= count(array_filter($allUsers, fn($u) => $u['is_admin'] == 1)) ?>
                </p>
                <p class="subtitle is-6">Admins</p>
            </div>
        </div>
    </div>
    
    <?php if ($filter === 'pending'): ?>
    <div class="notification is-warning is-light">
        <p><strong>Important:</strong> These users are waiting for approval to access the Aetia Talent Agency platform. Contact each user to discuss platform terms, commission structure, and business agreements before approving their accounts.</p>
    </div>
    <?php elseif ($filter === 'unverified'): ?>
    <div class="notification is-warning is-light">
        <p><strong>Important:</strong> These users have accounts but have not completed email verification or identity verification. Review each user carefully and verify legitimate accounts or deactivate suspicious ones.</p>
    </div>
    <?php elseif ($filter === 'suspended'): ?>
    <div class="notification is-warning is-light">
        <p><strong>Important:</strong> These users are currently suspended and cannot access their accounts. Review suspension reasons and consider unsuspending users who have resolved the issues that led to their suspension.</p>
    </div>
    <?php endif; ?>
    
    <!-- Users Grid (2x2 Layout) -->
    <div class="columns is-multiline">
    <?php foreach ($filteredUsers as $user): ?>
        <div class="column is-half">
            <div class="card">
                <div class="card-content">
                    <div class="media">
                        <div class="media-left">
                            <figure class="image is-64x64">
                                <?php if ($user['account_type'] === 'manual'): ?>
                                    <!-- Manual account - check if they have an image -->
                                    <?php if ($user['profile_image']): ?>
                                        <img src="view-user-profile-image.php?user_id=<?= $user['id'] ?>" alt="Profile Picture" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="has-background-light is-flex is-align-items-center is-justify-content-center" style="width:64px;height:64px;border-radius:50%;">
                                            <span class="icon is-large has-text-white">
                                                <i class="fas fa-user fa-2x"></i>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <!-- Social account - use profile image URL from database -->
                                    <?php if ($user['profile_image']): ?>
                                        <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile Picture" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="has-background-light is-flex is-align-items-center is-justify-content-center" style="width:64px;height:64px;border-radius:50%;">
                                            <span class="icon is-large has-text-white">
                                                <i class="fas fa-user fa-2x"></i>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </figure>
                        </div>
                        <div class="media-content">
                            <p class="title is-5"><?= htmlspecialchars($user['username']) ?></p>
                            <p class="subtitle is-6">
                        <!-- Account Type -->
                        <span class="tag is-<?= $user['account_type'] === 'manual' ? 'info' : 'link' ?>">
                            <?= ucfirst($user['account_type']) ?> Account
                        </span>
                        
                        <!-- Approval Status -->
                        <?php if ($user['approval_status'] === 'pending'): ?>
                        <span class="tag is-warning">
                            <span class="icon"><i class="fas fa-clock"></i></span>
                            <span>Pending Approval</span>
                        </span>
                        <?php elseif ($user['approval_status'] === 'approved'): ?>
                        <span class="tag is-success">
                            <span class="icon"><i class="fas fa-check"></i></span>
                            <span>Approved</span>
                        </span>
                        <?php elseif ($user['approval_status'] === 'rejected'): ?>
                        <span class="tag is-danger">
                            <span class="icon"><i class="fas fa-times"></i></span>
                            <span>Rejected</span>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Verification Status -->
                        <?php if ($user['is_verified'] == 0): ?>
                        <span class="tag is-warning">
                            <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
                            <span>Unverified</span>
                        </span>
                        <?php else: ?>
                        <span class="tag is-success">
                            <span class="icon"><i class="fas fa-check-circle"></i></span>
                            <span>Verified</span>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Admin Status -->
                        <?php if ($user['is_admin'] == 1): ?>
                        <span class="tag is-info">
                            <span class="icon"><i class="fas fa-crown"></i></span>
                            <span>Administrator</span>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Active Status -->
                        <?php if ($user['is_active'] == 0): ?>
                        <span class="tag is-danger">
                            <span class="icon"><i class="fas fa-user-slash"></i></span>
                            <span>Inactive</span>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Suspended Status -->
                        <?php if ($user['is_suspended'] == 1): ?>
                        <span class="tag is-warning">
                            <span class="icon"><i class="fas fa-ban"></i></span>
                            <span>Suspended</span>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Contact Attempted -->
                        <?php if ($user['contact_attempted']): ?>
                        <span class="tag is-warning">
                            <span class="icon"><i class="fas fa-phone"></i></span>
                            <span>Contact Attempted</span>
                        </span>
                        <?php endif; ?>
                    </p>
                    <div class="content">
                        <p><strong>Private Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                        <p><strong>Public Email:</strong> 
                        <?php 
                        $displayEmail = $user['public_email'] ?? ($user['username'] . '@aetia.com.au');
                        echo htmlspecialchars($displayEmail);
                        ?>
                        </p>
                        <?php if ($user['first_name'] || $user['last_name']): ?>
                        <p><strong>Name:</strong> <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></p>
                        <?php endif; ?>
                        <p><strong>Account Created:</strong> <?= formatDateForUser($user['created_at']) ?></p>
                        <?php if (isset($user['contact_attempted']) && $user['contact_attempted'] && isset($user['contact_date']) && $user['contact_date']): ?>
                        <p><strong>Last Contact:</strong> <?= formatDateForUser($user['contact_date']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($user['approval_date']) && $user['approval_date']): ?>
                        <p><strong>Approved:</strong> <?= formatDateForUser($user['approval_date']) ?>
                        <?php if (isset($user['approved_by']) && $user['approved_by']): ?> by <?= htmlspecialchars($user['approved_by']) ?><?php endif; ?></p>
                        <?php endif; ?>
                        <?php if (isset($user['verified_date']) && $user['verified_date']): ?>
                        <p><strong>Verified:</strong> <?= formatDateForUser($user['verified_date']) ?>
                        <?php if (isset($user['verified_by']) && $user['verified_by']): ?> by <?= htmlspecialchars($user['verified_by']) ?><?php endif; ?></p>
                        <?php endif; ?>
                        <?php if (isset($user['rejection_reason']) && $user['rejection_reason']): ?>
                        <p><strong>Rejection Reason:</strong> <?= htmlspecialchars($user['rejection_reason']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($user['deactivation_reason']) && $user['deactivation_reason']): ?>
                        <p><strong>Deactivation Reason:</strong> <?= htmlspecialchars($user['deactivation_reason']) ?></p>
                        <p><strong>Deactivated:</strong> <?= formatDateForUser($user['deactivation_date']) ?>
                        <?php if (isset($user['deactivated_by']) && $user['deactivated_by']): ?> by <?= htmlspecialchars($user['deactivated_by']) ?><?php endif; ?></p>
                        <?php endif; ?>
                        <?php if (isset($user['suspension_reason']) && $user['suspension_reason']): ?>
                        <p><strong>Suspension Reason:</strong> <?= htmlspecialchars($user['suspension_reason']) ?></p>
                        <p><strong>Suspended:</strong> <?= formatDateForUser($user['suspended_date']) ?>
                        <?php if (isset($user['suspended_by']) && $user['suspended_by']): ?> by <?= htmlspecialchars($user['suspended_by']) ?><?php endif; ?></p>
                        <?php endif; ?>
                        <?php $socialConnections = $userModel->getUserSocialConnections($user['id']); ?>
                        <?php if (!empty($socialConnections)): ?>
                        <div class="social-accounts mt-4">
                            <p><strong>Connected Social Accounts:</strong></p>
                            <div class="tags">
                                <?php foreach ($socialConnections as $connection): ?>
                                    <?php
                                    $platform = strtolower($connection['platform']);
                                    $platformIcon = 'fas fa-user';
                                    $platformColor = 'is-info';
                                    $displayName = ucfirst($connection['platform']);
                                    // Set platform-specific styling
                                    switch ($platform) {
                                        case 'twitch':
                                            $platformIcon = 'fab fa-twitch';
                                            $platformColor = 'is-primary';
                                            break;
                                        case 'discord':
                                            $platformIcon = 'fab fa-discord';
                                            $platformColor = 'is-link';
                                            break;
                                        case 'google':
                                            $platformIcon = 'fab fa-google';
                                            $platformColor = 'is-danger';
                                            break;
                                        case 'twitter':
                                        case 'x':
                                            $platformIcon = 'fab fa-twitter';
                                            $platformColor = 'is-info';
                                            $displayName = 'Twitter/X';
                                            break;
                                        case 'instagram':
                                            $platformIcon = 'fab fa-instagram';
                                            $platformColor = 'is-warning';
                                            break;
                                        case 'tiktok':
                                            $platformIcon = 'fab fa-tiktok';
                                            $platformColor = 'is-dark';
                                            break;
                                        case 'facebook':
                                            $platformIcon = 'fab fa-facebook';
                                            $platformColor = 'is-primary';
                                            break;
                                        case 'linkedin':
                                            $platformIcon = 'fab fa-linkedin';
                                            $platformColor = 'is-info';
                                            break;
                                    }
                                    // Add primary indicator styling
                                    $isPrimary = $connection['is_primary'] ? ' is-outlined' : '';
                                    $primaryText = $connection['is_primary'] ? ' (Primary)' : '';
                                    ?>
                                    <span class="tag <?= $platformColor ?><?= $isPrimary ?> is-medium">
                                        <span class="icon">
                                            <i class="<?= $platformIcon ?>"></i>
                                        </span>
                                        <span><?= htmlspecialchars($displayName) ?>: <?= htmlspecialchars($connection['social_username']) ?><?= $primaryText ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Action Buttons -->
            <div class="buttons">
                <?php if ($user['approval_status'] === 'pending'): ?>
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
                <?php endif; ?>
                <?php if ($user['is_verified'] == 0 && $user['is_active'] == 1): ?>
                    <!-- Verify User -->
                    <form method="POST" style="display:inline;" id="verify-form-<?= $user['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="verify">
                        <button class="button is-success is-small verify-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                            <span class="icon"><i class="fas fa-check-circle"></i></span>
                            <span>Verify User</span>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($user['is_active'] == 1): ?>
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
                <?php endif; ?>
                <?php if ($user['is_active'] == 1 && $user['is_suspended'] == 0): ?>
                    <!-- Suspend User -->
                    <form method="POST" style="display:inline;" id="suspend-form-<?= $user['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="suspend">
                        <input type="hidden" name="suspension_reason" id="suspension-reason-<?= $user['id'] ?>">
                        <button class="button is-warning is-small suspend-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                            <span class="icon"><i class="fas fa-ban"></i></span>
                            <span>Suspend</span>
                        </button>
                    </form>
                <?php elseif ($user['is_suspended'] == 1): ?>
                    <!-- Unsuspend User -->
                    <form method="POST" style="display:inline;" id="unsuspend-form-<?= $user['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="unsuspend">
                        <button class="button is-success is-small unsuspend-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                            <span class="icon"><i class="fas fa-check"></i></span>
                            <span>Unsuspend</span>
                        </button>
                    </form>
                <?php endif; ?>
                <!-- Admin Management Buttons -->
                <?php if ($user['is_admin'] == 0): ?>
                    <!-- Make Admin -->
                    <form method="POST" style="display:inline;" id="make-admin-form-<?= $user['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="make_admin">
                        <button class="button is-info is-small make-admin-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                            <span class="icon"><i class="fas fa-crown"></i></span>
                            <span>Make Admin</span>
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Remove Admin -->
                    <form method="POST" style="display:inline;" id="remove-admin-form-<?= $user['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <input type="hidden" name="action" value="remove_admin">
                        <button class="button is-warning is-small remove-admin-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>">
                            <span class="icon"><i class="fas fa-user-minus"></i></span>
                            <span>Remove Admin</span>
                        </button>
                    </form>
                <?php endif; ?>
                <!-- Edit Email Button -->
                <form method="POST" style="display:inline;" id="email-form-<?= $user['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                    <input type="hidden" name="action" value="update_email">
                    <input type="hidden" name="new_email" id="new-email-<?= $user['id'] ?>">
                    <button class="button is-light is-small edit-email-btn" type="button" data-user-id="<?= $user['id'] ?>" data-username="<?= htmlspecialchars($user['username']) ?>" data-current-email="<?= htmlspecialchars($user['public_email'] ?? '') ?>">
                        <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                        <span>Edit Email</span>
                    </button>
                </form>
                <!-- View Documents Button -->
                <a href="view-user-documents.php?user_id=<?= $user['id'] ?>" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-file-contract"></i></span>
                    <span>View Documents</span>
                </a>
                <!-- Billing History Button -->
                <a href="billing-history.php?user_id=<?= $user['id'] ?>" class="button is-link is-small">
                    <span class="icon"><i class="fas fa-file-invoice-dollar"></i></span>
                    <span>Billing</span>
                </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
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
    // Handle Make Admin buttons (for non-admin users)
    document.querySelectorAll('.make-admin-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            Swal.fire({
                title: 'Make Admin?',
                html: `Are you sure you want to make <strong style="color: #333;">${decodedUsername}</strong> an administrator?<br><br><strong>This will grant them:</strong><br>• Full admin privileges<br>• Ability to manage other users<br>• Access to admin-only features`,
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
                    document.getElementById(`make-admin-form-${userId}`).submit();
                }
            });
        });
    });
    // Handle Remove Admin buttons (for admin users)
    document.querySelectorAll('.remove-admin-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            Swal.fire({
                title: 'Remove Admin Privileges?',
                html: `Are you sure you want to remove admin privileges from <strong style="color: #333;">${decodedUsername}</strong>?<br><br><strong>This will:</strong><br>• Remove all admin privileges<br>• Restrict access to admin features<br>• Convert them to a regular user`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ffdd57',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-user-minus"></i> Yes, Remove Admin',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-warning',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`remove-admin-form-${userId}`).submit();
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
    // Handle Suspend User buttons
    document.querySelectorAll('.suspend-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            Swal.fire({
                title: 'Suspend User?',
                html: `Are you sure you want to suspend <strong style="color: #333;">${decodedUsername}</strong>?<br><br>Please provide a reason for suspension:`,
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Enter suspension reason...',
                inputValidator: (value) => {
                    if (!value || value.trim() === '') {
                        return 'You must provide a suspension reason!';
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#ffdd57',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-ban"></i> Yes, Suspend',
                cancelButtonText: '<i class="fas fa-arrow-left"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-warning',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`suspension-reason-${userId}`).value = result.value;
                    document.getElementById(`suspend-form-${userId}`).submit();
                }
            });
        });
    });
    // Handle Unsuspend User buttons
    document.querySelectorAll('.unsuspend-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            Swal.fire({
                title: 'Unsuspend User?',
                html: `Are you sure you want to unsuspend <strong style="color: #333;">${decodedUsername}</strong>?<br><br>They will be able to access their account immediately.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#48c78e',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-check"></i> Yes, Unsuspend',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-success',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`unsuspend-form-${userId}`).submit();
                }
            });
        });
    });
    // Handle Edit Email buttons
    document.querySelectorAll('.edit-email-btn').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            const currentEmail = this.dataset.currentEmail;
            // Create a temporary element to decode HTML entities
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = username || 'Unknown User';
            const decodedUsername = tempDiv.textContent || tempDiv.innerText || 'Unknown User';
            // Default email if current is empty
            const defaultEmail = currentEmail || `${decodedUsername}@aetia.com.au`;
            Swal.fire({
                title: 'Edit Public Email',
                html: `Update public email for <strong style="color: #333;">${decodedUsername}</strong>:<br><br>`,
                icon: 'info',
                input: 'email',
                inputValue: currentEmail,
                inputPlaceholder: `${decodedUsername}@aetia.com.au`,
                inputValidator: (value) => {
                    if (!value || value.trim() === '') {
                        return 'Email address is required!';
                    }
                    // Basic email validation
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(value)) {
                        return 'Please enter a valid email address!';
                    }
                },
                showCancelButton: true,
                confirmButtonColor: '#3e8ed0',
                cancelButtonColor: '#dbdbdb',
                confirmButtonText: '<i class="fas fa-envelope-open-text"></i> Update Email',
                cancelButtonText: '<i class="fas fa-times"></i> Cancel',
                customClass: {
                    confirmButton: 'button is-info',
                    cancelButton: 'button is-light'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById(`new-email-${userId}`).value = result.value;
                    document.getElementById(`email-form-${userId}`).submit();
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
