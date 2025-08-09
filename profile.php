<?php
// profile.php - User profile page for Aetia Talent Agency
session_start();

// Include timezone utilities
require_once __DIR__ . '/includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Message.php';
require_once __DIR__ . '/services/TwitchOAuth.php';
require_once __DIR__ . '/services/DiscordOAuth.php';
require_once __DIR__ . '/services/ImageUploadService.php';

$userModel = new User();
$messageModel = new Message();
$error_message = '';
$success_message = '';

// Check for linking success/error messages
if (isset($_SESSION['link_success'])) {
    $success_message = $_SESSION['link_success'];
    unset($_SESSION['link_success']);
}

if (isset($_SESSION['link_error'])) {
    $error_message = $_SESSION['link_error'];
    unset($_SESSION['link_error']);
}

// Get initial user data
$user = $userModel->getUserById($_SESSION['user_id']);
$socialConnections = $userModel->getUserSocialConnections($_SESSION['user_id']);

// Get user billing information for the last 6 months
$userBillingData = [];
$totalBillingAmount = 0;
$totalMessages = 0;
$totalManualReviews = 0;

if ($user['approval_status'] === 'approved') {
    try {
        // Get billing data for the last 6 months
        for ($i = 0; $i < 6; $i++) {
            $monthDate = new DateTime();
            $monthDate->modify("-$i months");
            $year = $monthDate->format('Y');
            $month = $monthDate->format('m');
            
            $firstDay = sprintf('%04d-%02d-01', $year, $month);
            $lastDay = $monthDate->format('Y-m-t');
            
            // Try to get saved billing report first
            $savedReport = $messageModel->getSavedBillingReport($firstDay, $lastDay);
            if ($savedReport) {
                // Find this user in the report data
                foreach ($savedReport['report_data'] as $clientData) {
                    if ($clientData['user_id'] == $_SESSION['user_id']) {
                        $userBillingData[] = [
                            'month' => $monthDate->format('F Y'),
                            'month_short' => $monthDate->format('M Y'),
                            'first_day' => $firstDay,
                            'last_day' => $lastDay,
                            'message_count' => $clientData['total_message_count'],
                            'manual_review_count' => $clientData['manual_review_count'],
                            'standard_fee' => $clientData['standard_fee'],
                            'manual_review_fee' => $clientData['manual_review_fee'],
                            'total_fee' => $clientData['total_fee']
                        ];
                        
                        $totalBillingAmount += $clientData['total_fee'];
                        $totalMessages += $clientData['total_message_count'];
                        $totalManualReviews += $clientData['manual_review_count'];
                        break;
                    }
                }
            } else {
                // No saved report, try to generate billing data on the fly
                $billingData = $messageModel->getBillingDataWithManualReview($firstDay, $lastDay);
                foreach ($billingData as $clientData) {
                    if ($clientData['user_id'] == $_SESSION['user_id']) {
                        $userBillingData[] = [
                            'month' => $monthDate->format('F Y'),
                            'month_short' => $monthDate->format('M Y'),
                            'first_day' => $firstDay,
                            'last_day' => $lastDay,
                            'message_count' => $clientData['total_message_count'],
                            'manual_review_count' => $clientData['manual_review_count'],
                            'standard_fee' => $clientData['standard_fee'],
                            'manual_review_fee' => $clientData['manual_review_fee'],
                            'total_fee' => $clientData['total_fee']
                        ];
                        
                        $totalBillingAmount += $clientData['total_fee'];
                        $totalMessages += $clientData['total_message_count'];
                        $totalManualReviews += $clientData['manual_review_count'];
                        break;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error getting user billing data: " . $e->getMessage());
        // Continue without billing data
    }
}

// Handle unlinking social accounts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlink_social') {
    $platform = $_POST['platform'] ?? '';
    if (!empty($platform)) {
        $result = $userModel->unlinkSocialAccount($_SESSION['user_id'], $platform);
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh data
            $socialConnections = $userModel->getUserSocialConnections($_SESSION['user_id']);
            $availablePlatforms = $userModel->getAvailablePlatformsForLinking($_SESSION['user_id']);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle setting primary social account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_primary_social') {
    $platform = $_POST['platform'] ?? '';
    if (!empty($platform)) {
        $result = $userModel->setPrimarySocialConnection($_SESSION['user_id'], $platform);
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh data
            $socialConnections = $userModel->getUserSocialConnections($_SESSION['user_id']);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle setting primary social account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_primary_social') {
    $platform = $_POST['platform'] ?? '';
    if (!empty($platform)) {
        $result = $userModel->setPrimarySocialConnection($_SESSION['user_id'], $platform);
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh data
            $socialConnections = $userModel->getUserSocialConnections($_SESSION['user_id']);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle setting password for social users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_password') {
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    if (empty($newPassword) || empty($confirmPassword)) {
        $error_message = 'Please fill in all password fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } else {
        $result = $userModel->setPasswordForSocialUser($_SESSION['user_id'], $newPassword);
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh user data to reflect password is now set
            $user = $userModel->getUserById($_SESSION['user_id']);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Process password change for users with passwords set
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    // Check if user has a password set (regardless of account type)
    if (empty($user['password_hash'])) {
        $error_message = 'No password is currently set for this account. Please set a password first.';
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
            // Use the new changeUserPassword method which includes current password verification
            $changeResult = $userModel->changeUserPassword($_SESSION['user_id'], $currentPassword, $newPassword);
            if ($changeResult['success']) {
                $success_message = $changeResult['message'];
                // If this is the auto-generated admin account, mark setup as complete
                if ($user['username'] === 'admin' && $user['approved_by'] === 'Auto-Generated') {
                    $setupResult = $userModel->markAdminSetupComplete($_SESSION['user_id']);
                    // Also set a session flag to immediately hide the warning
                    $_SESSION['admin_setup_complete'] = true;
                }
                // Refresh user data to reflect changes in the UI
                $user = $userModel->getUserById($_SESSION['user_id']);
            } else {
                $error_message = $changeResult['message'];
            }
        }
    }
}

// Handle profile update (first name and last name)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    if (empty($firstName) && empty($lastName)) {
        $error_message = 'Please provide at least one name field.';
    } else {
        $result = $userModel->updateUserProfile($_SESSION['user_id'], $firstName, $lastName);
        if ($result['success']) {
            $success_message = $result['message'];
            // Refresh user data to reflect changes in the UI
            $user = $userModel->getUserById($_SESSION['user_id']);
        } else {
            $error_message = $result['message'];
        }
    }
}

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_image') {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        try {
            $imageUploadService = new ImageUploadService();
            $uploadResult = $imageUploadService->uploadProfileImage($_FILES['profile_image'], $_SESSION['user_id']);
            if ($uploadResult['success']) {
                // Instead of storing the S3 URL, store a flag indicating the user has an image
                // The actual image will be served through our secure endpoint
                $profileImageFlag = 'user-' . $_SESSION['user_id'] . '-has-image';
                $updateResult = $userModel->updateProfileImage($_SESSION['user_id'], $profileImageFlag);
                if ($updateResult['success']) {
                    $success_message = 'Profile image uploaded successfully!';
                    // Refresh user data to reflect changes in the UI
                    $user = $userModel->getUserById($_SESSION['user_id']);
                } else {
                    $error_message = $updateResult['message'];
                }
            } else {
                $error_message = $uploadResult['message'];
            }
        } catch (Exception $e) {
            error_log("Profile image upload error: " . $e->getMessage());
            $error_message = 'An error occurred while uploading your profile image. Please try again.';
        }
    } else {
        $error_message = 'Please select a valid image file to upload.';
    }
}

// Handle profile image removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_image') {
    if (!empty($user['profile_image'])) {
        try {
            // Delete all profile images for this user from S3
            $imageUploadService = new ImageUploadService();
            $imageUploadService->deleteAllUserProfileImages($_SESSION['user_id']);
            // Remove from database
            $result = $userModel->removeProfileImage($_SESSION['user_id']);
            if ($result['success']) {
                $success_message = $result['message'];
                // Refresh user data to reflect changes in the UI
                $user = $userModel->getUserById($_SESSION['user_id']);
            } else {
                $error_message = $result['message'];
            }
        } catch (Exception $e) {
            error_log("Profile image removal error: " . $e->getMessage());
            $error_message = 'An error occurred while removing your profile image.';
        }
    } else {
        $error_message = 'No profile image to remove.';
    }
}

$pageTitle = 'Profile | Aetia Talent Agency';
ob_start();
?>
<div class="content">
    <h2 class="title is-2 has-text-info mb-4">
        <span class="icon"><i class="fas fa-user-cog"></i></span>
        Profile Settings
    </h2>
    <?php if ($user['username'] === 'admin' && $user['approved_by'] === 'Auto-Generated' && !isset($_SESSION['admin_setup_complete'])): ?>
    <div class="notification is-warning is-dark has-text-white mb-4">
        <div class="content">
            <p><strong><i class="fas fa-shield-alt"></i> Admin Security Notice</strong></p>
            <p>You are logged in with the auto-generated admin account. For security purposes, please change your password immediately.</p>
            <p>Consider creating a personalized admin account and disabling this default account after setup is complete.</p>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($error_message): ?>
    <div class="notification is-danger is-dark has-text-white mb-4">
        <button class="delete"></button>
        <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>
    <?php if ($success_message): ?>
    <div class="notification is-success is-dark has-text-white mb-4">
        <button class="delete"></button>
        <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    <div class="columns">
        <div class="column is-4">
            <div class="card has-background-dark">
                <div class="card-content">
                    <div class="columns is-vcentered">
                        <!-- Profile Image Column -->
                        <div class="column is-4 has-text-centered">
                            <?php if ($user['profile_image']): ?>
                                <?php if ($user['account_type'] === 'manual'): ?>
                                    <!-- Manual account - use secure S3 endpoint -->
                                    <img id="profile-image-display" src="api/view-profile-image.php?user_id=<?= $_SESSION['user_id'] ?>" alt="Profile Picture" class="profile-image-preview" onerror="this.style.display='none'; document.getElementById('profile-placeholder').style.display='flex';">
                                    <div id="profile-placeholder" class="profile-image-placeholder" style="display:none;">
                                        <span class="icon is-large has-text-grey-light">
                                            <i class="fas fa-user fa-3x"></i>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <!-- Social account - use direct image URL from social platform -->
                                    <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile Picture" class="profile-image-preview">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="profile-image-placeholder">
                                    <span class="icon is-large has-text-grey-light">
                                        <i class="fas fa-user fa-3x"></i>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($user['account_type'] === 'manual'): ?>
                            <!-- Profile Image Upload Controls for Manual Accounts -->
                            <div class="profile-image-upload">
                                <?php if ($user['profile_image']): ?>
                                    <!-- Replace Image Button -->
                                    <button class="button is-small is-info mb-2" onclick="document.getElementById('imageUploadInput').click()">
                                        <span class="icon is-small">
                                            <i class="fas fa-upload"></i>
                                        </span>
                                        <span>Change Image</span>
                                    </button>
                                    <br>
                                    <!-- Remove Image Button -->
                                    <form method="POST" action="profile.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove your profile image?')">
                                        <input type="hidden" name="action" value="remove_image">
                                        <button type="submit" class="button is-small is-danger">
                                            <span class="icon is-small">
                                                <i class="fas fa-trash"></i>
                                            </span>
                                            <span>Remove</span>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- Upload Image Button -->
                                    <button class="button is-small is-success" onclick="document.getElementById('imageUploadInput').click()">
                                        <span class="icon is-small">
                                            <i class="fas fa-upload"></i>
                                        </span>
                                        <span>Upload Image</span>
                                    </button>
                                <?php endif; ?>
                                <!-- Hidden File Input -->
                                <form id="imageUploadForm" method="POST" action="profile.php" enctype="multipart/form-data" style="display:none;">
                                    <input type="hidden" name="action" value="upload_image">
                                    <input type="file" id="imageUploadInput" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" onchange="handleImageUpload(this)">
                                </form>
                                <!-- Upload Info -->
                                <p class="help has-text-grey-light is-size-7 mt-2">
                                    Maximum 5MB. JPEG, PNG, GIF, or WebP format.
                                </p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Profile Information Column -->
                        <div class="column is-8">
                            <h3 class="title is-4 has-text-light mb-2"><?= htmlspecialchars($user['username']) ?></h3>
                            <p class="subtitle is-6 has-text-grey-light mb-3">
                                <?= ucfirst($user['account_type']) ?> Account
                            </p>
                            <?php if ($user['first_name'] || $user['last_name']): ?>
                            <p class="has-text-grey-light mb-2">
                                <?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                            </p>
                            <?php endif; ?>
                            <?php if ($user['approval_status'] === 'approved' && !empty($user['approval_date'])): ?>
                            <p class="has-text-grey-light is-size-7 mb-1">
                                Member since <?= formatDateForUser($user['approval_date']) ?>
                            </p>
                            <?php endif; ?>
                            <p class="has-text-grey-light is-size-7">
                                Account created <?= formatDateForUser($user['created_at']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Billing Information Section -->
            <?php if ($user['approval_status'] === 'approved'): ?>
            <div class="card has-background-dark mt-4">
                <div class="card-content">
                    <h4 class="title is-6 has-text-light mb-3">
                        <span class="icon has-text-success"><i class="fas fa-chart-line"></i></span>
                        Activity Summary (Last 6 Months)
                    </h4>
                    
                    <!-- Summary Stats -->
                    <div class="columns is-mobile mb-3">
                        <div class="column has-text-centered">
                            <p class="heading has-text-grey-light is-size-7">Messages</p>
                            <p class="title is-6 has-text-info mb-0"><?= $totalMessages ?></p>
                        </div>
                        <div class="column has-text-centered">
                            <p class="heading has-text-grey-light is-size-7">Reviews</p>
                            <p class="title is-6 has-text-warning mb-0"><?= $totalManualReviews ?></p>
                        </div>
                        <div class="column has-text-centered">
                            <p class="heading has-text-grey-light is-size-7">Total Value</p>
                            <p class="title is-6 has-text-success mb-0">$<?= number_format($totalBillingAmount, 2) ?></p>
                        </div>
                    </div>
                    
                    <!-- Monthly Breakdown -->
                    <div class="content">
                        <p class="has-text-grey-light is-size-7 mb-2">Monthly Activity:</p>
                        <?php if (!empty($userBillingData)): ?>
                            <?php foreach (array_slice($userBillingData, 0, 3) as $monthData): ?>
                            <div class="level is-mobile mb-2 has-background-white-ter" style="padding: 8px 12px; border-radius: 4px;">
                                <div class="level-left">
                                    <div class="level-item">
                                        <span class="has-text-grey-dark is-size-7 has-text-weight-medium"><?= $monthData['month_short'] ?></span>
                                    </div>
                                </div>
                                <div class="level-right">
                                    <div class="level-item">
                                        <span class="tag is-small is-info"><?= $monthData['message_count'] ?> msgs</span>
                                        <?php if ($monthData['manual_review_count'] > 0): ?>
                                            <span class="tag is-small is-warning ml-1"><?= $monthData['manual_review_count'] ?> reviews</span>
                                        <?php endif; ?>
                                        <span class="has-text-success is-size-7 ml-2 has-text-weight-semibold">$<?= number_format($monthData['total_fee'], 2) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($userBillingData) > 3): ?>
                            <details class="mt-2">
                                <summary class="has-text-info is-size-7" style="cursor: pointer;">Show more months (<?= count($userBillingData) - 3 ?> more)</summary>
                                <div class="mt-2">
                                    <?php foreach (array_slice($userBillingData, 3) as $monthData): ?>
                                    <div class="level is-mobile mb-2 has-background-white-ter" style="padding: 8px 12px; border-radius: 4px;">
                                        <div class="level-left">
                                            <div class="level-item">
                                                <span class="has-text-grey-dark is-size-7 has-text-weight-medium"><?= $monthData['month_short'] ?></span>
                                            </div>
                                        </div>
                                        <div class="level-right">
                                            <div class="level-item">
                                                <span class="tag is-small is-info"><?= $monthData['message_count'] ?> msgs</span>
                                                <?php if ($monthData['manual_review_count'] > 0): ?>
                                                    <span class="tag is-small is-warning ml-1"><?= $monthData['manual_review_count'] ?> reviews</span>
                                                <?php endif; ?>
                                                <span class="has-text-success is-size-7 ml-2 has-text-weight-semibold">$<?= number_format($monthData['total_fee'], 2) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="level is-mobile mb-2 has-background-white-ter" style="padding: 8px 12px; border-radius: 4px;">
                                <div class="level-left">
                                    <div class="level-item">
                                        <span class="has-text-grey-dark is-size-7 has-text-weight-medium"><?= date('M Y') ?></span>
                                    </div>
                                </div>
                                <div class="level-right">
                                    <div class="level-item">
                                        <span class="tag is-small is-info">0 msgs</span>
                                        <span class="has-text-success is-size-7 ml-2 has-text-weight-semibold">$0.00</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="has-text-centered mt-3">
                        <a href="messages.php" class="button is-small is-info is-outlined">
                            <span class="icon"><i class="fas fa-envelope"></i></span>
                            <span>View Messages</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php elseif ($user['approval_status'] === 'pending'): ?>
            <div class="card has-background-dark mt-4">
                <div class="card-content">
                    <h4 class="title is-6 has-text-light mb-3">
                        <span class="icon has-text-warning"><i class="fas fa-clock"></i></span>
                        Activity Summary
                    </h4>
                    <div class="notification is-warning is-dark has-text-white">
                        <p class="has-text-dark"><strong>Pending Approval</strong></p>
                        <p class="has-text-dark is-size-7">Your activity summary will be available once your account is approved by our team.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($socialConnections)): ?>
            <!-- Connected Social Accounts under billing information -->
            <div class="card has-background-dark mt-4">
                <div class="card-content">
                    <h4 class="title is-6 has-text-light mb-2">
                        <span class="icon has-text-info"><i class="fab fa-connectdevelop"></i></span>
                        Connected Accounts
                    </h4>
                    <?php foreach ($socialConnections as $connection): ?>
                    <div class="mb-3" style="background: rgba(255, 255, 255, 0.05); padding: 12px; border-radius: 6px;">
                        <div class="level is-mobile">
                            <div class="level-left">
                                <div class="level-item">
                                    <span class="icon has-text-<?= $connection['platform'] === 'twitch' ? 'primary' : ($connection['platform'] === 'discord' ? 'info' : 'warning') ?>">
                                        <i class="fab fa-<?= $connection['platform'] ?>"></i>
                                    </span>
                                </div>
                                <div class="level-item">
                                    <div>
                                        <p class="is-size-7 has-text-light">
                                            <?= ucfirst($connection['platform']) ?>
                                            <?php if ($connection['is_primary']): ?>
                                                <span class="tag is-primary is-small ml-1" style="font-size: 0.6rem; padding: 2px 6px;">Primary</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="is-size-7 has-text-grey-light">@<?= htmlspecialchars($connection['social_username']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="level-right">
                                <div class="level-item">
                                    <div class="field is-grouped">
                                        <?php if (!$connection['is_primary']): ?>
                                        <div class="control">
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="set_primary_social">
                                                <input type="hidden" name="platform" value="<?= htmlspecialchars($connection['platform']) ?>">
                                                <button type="submit" class="button is-small is-warning" title="Set as primary">
                                                    <span class="icon is-small">
                                                        <i class="fas fa-star"></i>
                                                    </span>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="control">
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="unlink_social">
                                                <input type="hidden" name="platform" value="<?= htmlspecialchars($connection['platform']) ?>">
                                                <button type="submit" class="button is-small is-danger" 
                                                        title="Unlink account"
                                                        onclick="return confirm('Are you sure you want to unlink your <?= ucfirst($connection['platform']) ?> account?')">
                                                    <span class="icon is-small">
                                                        <i class="fas fa-unlink"></i>
                                                    </span>
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="column is-8">
            <div class="card has-background-dark">
                <div class="card-content">
                    <h4 class="title is-5 has-text-light mb-4">Account Information</h4>
                    <?php if (!empty($user['first_name']) && !empty($user['last_name'])): ?>
                    <div class="field">
                        <label class="label has-text-light">Full Name</label>
                        <div class="control">
                            <input class="input has-background-grey-darker has-text-light" type="text" value="<?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?>" readonly>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="field">
                        <label class="label has-text-light">Username</label>
                        <div class="control">
                            <input class="input has-background-grey-darker has-text-light" type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label has-text-light">Email Address</label>
                        <div class="control">
                            <input class="input has-background-grey-darker has-text-light" type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label has-text-light">
                            Public Contact Email
                            <span class="icon has-text-info" title="This is your public email address for client contact">
                                <i class="fas fa-info-circle"></i>
                            </span>
                        </label>
                        <div class="field has-addons">
                            <div class="control is-expanded">
                                <?php 
                                // Use custom public_email if set, otherwise default to username@aetia.com.au
                                $publicEmail = !empty($user['public_email']) ? $user['public_email'] : $user['username'] . '@aetia.com.au';
                                $isCustomEmail = !empty($user['public_email']);
                                ?>
                                <input class="input has-background-grey-darker has-text-light" 
                                       type="email" 
                                       value="<?= htmlspecialchars($publicEmail) ?>" 
                                       readonly
                                       id="publicEmail">
                            </div>
                            <div class="control">
                                <button class="button has-background-grey-darker has-text-light" 
                                        type="button" 
                                        onclick="copyPublicEmail()" 
                                        title="Click to copy email">
                                    <span class="icon">
                                        <i class="fas fa-copy" id="copy-icon"></i>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <p class="help has-text-grey-light">
                            <?php if ($user['approval_status'] === 'approved'): ?>
                                <?php if ($isCustomEmail): ?>
                                    This is your professional email address that clients can use to contact you directly.
                                    <br><span class="has-text-info"><i class="fas fa-star"></i> Custom email address assigned by our talent team</span>
                                <?php else: ?>
                                    This is your professional email address that clients can use to contact you directly.
                                    <br><span class="has-text-grey-lighter">You can request this to be changed to something that fits your brand by messaging our talent team directly via the <a href="messages.php" class="has-text-info">Messages</a> page.</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="has-text-warning"><i class="fas fa-clock"></i> When you're approved and part of our team, this will be your professional email address that you can make public.</span>
                                <?php if (!$isCustomEmail): ?>
                                <br><span class="has-text-grey-lighter">You can always request this to be changed to something that fits your brand once approved via the <a href="messages.php" class="has-text-info">Messages</a> page.</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="field">
                        <label class="label has-text-light">Account Default</label>
                        <div class="control">
                            <input class="input has-background-grey-darker has-text-light" type="text" value="<?= ucfirst($user['account_type']) ?>" readonly>
                        </div>
                    </div>
                    <div class="field">
                        <label class="label has-text-light">Account Status</label>
                        <div class="control">
                            <div class="tags">
                                <?php
                                // Approval status tag
                                $approvalClass = 'is-warning';
                                $approvalIcon = 'clock';
                                $approvalText = 'Pending Approval';
                                if ($user['approval_status'] === 'approved') {
                                    $approvalClass = 'is-success';
                                    $approvalIcon = 'check-circle';
                                    $approvalText = 'Approved';
                                } elseif ($user['approval_status'] === 'rejected') {
                                    $approvalClass = 'is-danger';
                                    $approvalIcon = 'times-circle';
                                    $approvalText = 'Rejected';
                                }
                                ?>
                                <span class="tag <?= $approvalClass ?>">
                                    <span class="icon"><i class="fas fa-<?= $approvalIcon ?>"></i></span>
                                    <span><?= $approvalText ?></span>
                                </span>
                                <?php
                                // Verification status tag
                                $verificationClass = $user['is_verified'] ? 'is-info' : 'is-warning';
                                $verificationIcon = $user['is_verified'] ? 'shield-alt' : 'clock';
                                $verificationText = $user['is_verified'] ? 'Verified' : 'Pending Verification';
                                ?>
                                <span class="tag <?= $verificationClass ?>">
                                    <span class="icon"><i class="fas fa-<?= $verificationIcon ?>"></i></span>
                                    <span><?= $verificationText ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (empty($user['first_name']) || empty($user['last_name'])): ?>
            <div class="card has-background-dark mt-4">
                <div class="card-content">
                    <h4 class="title is-5 has-text-light mb-4">
                        <span class="icon has-text-info"><i class="fas fa-user-edit"></i></span>
                        Complete Your Profile
                    </h4>
                    <div class="notification is-info mb-4">
                        <div class="content">
                            <p class="hast-text-black"><strong>Complete Your Profile:</strong> Please provide your first and last name to complete your profile.</p>
                            <p class="hast-text-black">This information helps us personalize your experience and improve our services.</p>
                        </div>
                    </div>
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="field">
                            <label class="label has-text-light">First Name</label>
                            <div class="control has-icons-left">
                                <input class="input has-background-grey-darker has-text-light" 
                                       type="text" 
                                       name="first_name" 
                                       placeholder="Enter your first name" 
                                       value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                       maxlength="50"
                                       <?= empty($user['first_name']) ? 'required' : '' ?>>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label has-text-light">Last Name</label>
                            <div class="control has-icons-left">
                                <input class="input has-background-grey-darker has-text-light" 
                                       type="text" 
                                       name="last_name" 
                                       placeholder="Enter your last name" 
                                       value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                       maxlength="50"
                                       <?= empty($user['last_name']) ? 'required' : '' ?>>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>
                        </div>
                        <div class="field">
                            <div class="control">
                                <button class="button is-info" type="submit">
                                    <span class="icon"><i class="fas fa-save"></i></span>
                                    <span>Update Profile</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($user['password_hash'])): ?>
            <div class="card has-background-dark mt-4">
                <div class="card-content">
                    <div class="level is-mobile" style="cursor: pointer;" onclick="togglePasswordChange()">
                        <div class="level-left">
                            <div class="level-item">
                                <h4 class="title is-5 has-text-light mb-0">
                                    <span class="icon has-text-warning"><i class="fas fa-key"></i></span>
                                    Change Password
                                </h4>
                            </div>
                        </div>
                        <div class="level-right">
                            <div class="level-item">
                                <span class="icon has-text-light" id="password-toggle-icon">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="password-change-form" style="display: none; margin-top: 1rem;">
                        <div class="notification is-info is-dark has-text-white mb-4">
                            <div class="content">
                                <p>For security, please enter your current password to confirm changes.</p>
                                <p>Your new password must be at least 8 characters long.</p>
                            </div>
                        </div>
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="action" value="change_password">
                            <div class="field">
                                <label class="label has-text-light">Current Password</label>
                                <div class="control has-icons-left">
                                    <input class="input has-background-grey-darker has-text-light" type="password" name="current_password" placeholder="Enter current password" required>
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label has-text-light">New Password</label>
                                <div class="control has-icons-left">
                                    <input class="input has-background-grey-darker has-text-light" type="password" name="new_password" placeholder="Enter new password" minlength="8" required>
                                    <span class="icon is-small is-left">
                                        <i class="fas fa-key"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label has-text-light">Confirm New Password</label>
                                <div class="control has-icons-left">
                                    <input class="input has-background-grey-darker has-text-light" type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" required>
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
            </div>
            <?php endif; ?>
            <?php if ($user['account_type'] !== 'manual' && empty($user['password_hash'])): ?>
            <div class="card has-background-dark mt-4">
                <div class="card-content">
                    <h4 class="title is-5 has-text-light mb-4">
                        <span class="icon has-text-info"><i class="fas fa-key"></i></span>
                        Set Manual Login Password
                    </h4>
                    <div class="notification is-info is-dark has-text-white mb-4">
                        <div class="content">
                            <p><strong>Enable Manual Login:</strong> Set a password to login with your username and password in addition to your social accounts.</p>
                            <p>This gives you an alternative way to access your account and is recommended for security.</p>
                            <p>Your password must be at least 8 characters long.</p>
                        </div>
                    </div>
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="set_password">
                        <div class="field">
                            <label class="label has-text-light">New Password</label>
                            <div class="control has-icons-left">
                                <input class="input has-background-grey-darker has-text-light" type="password" name="new_password" placeholder="Enter new password" minlength="8" required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label has-text-light">Confirm New Password</label>
                            <div class="control has-icons-left">
                                <input class="input has-background-grey-darker has-text-light" type="password" name="confirm_password" placeholder="Confirm new password" minlength="8" required>
                                <span class="icon is-small is-left">
                                    <i class="fas fa-key"></i>
                                </span>
                            </div>
                        </div>
                        <div class="field">
                            <div class="control">
                                <button class="button is-info" type="submit">
                                    <span class="icon"><i class="fas fa-plus"></i></span>
                                    <span>Set Password</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <!-- Link Additional Social Accounts -->
            <div class="card has-background-dark mt-4">
                <div class="card-content">
                    <h4 class="title is-5 has-text-light mb-4">
                        <span class="icon has-text-success"><i class="fas fa-plus-circle"></i></span>
                        Link Additional Social Accounts
                    </h4>
                    <div class="notification is-info is-dark has-text-white mb-4">
                        <div class="content">
                            <p><strong>Link Multiple Accounts:</strong> You can connect accounts from different platforms even if they use different email addresses.</p>
                            <p>This allows you to access your account through any of your connected social platforms.</p>
                        </div>
                    </div>
                    <div class="buttons">
                        <?php
                        // Check if user already has Twitch linked
                        $hasTwitch = false;
                        $hasDiscord = false;
                        foreach ($socialConnections as $conn) {
                            if ($conn['platform'] === 'twitch') $hasTwitch = true;
                            if ($conn['platform'] === 'discord') $hasDiscord = true;
                        }
                        ?>
                        <?php if (!$hasTwitch): ?>
                            <?php
                            try {
                                $twitchOAuth = new TwitchOAuth();
                                $twitchLinkUrl = $twitchOAuth->getLinkAuthorizationUrl();
                            } catch (Exception $e) {
                                $twitchLinkUrl = null;
                            }
                            ?>
                            <?php if ($twitchLinkUrl): ?>
                            <a href="<?= htmlspecialchars($twitchLinkUrl) ?>" class="button is-primary">
                                <span class="icon">
                                    <i class="fab fa-twitch"></i>
                                </span>
                                <span>Link Twitch</span>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!$hasDiscord): ?>
                            <?php
                            try {
                                $discordOAuth = new DiscordOAuth();
                                $discordLinkUrl = $discordOAuth->getLinkAuthorizationUrl();
                            } catch (Exception $e) {
                                $discordLinkUrl = null;
                            }
                            ?>
                            <?php if ($discordLinkUrl): ?>
                            <a href="<?= htmlspecialchars($discordLinkUrl) ?>" class="button is-info">
                                <span class="icon">
                                    <i class="fab fa-discord"></i>
                                </span>
                                <span>Link Discord</span>
                            </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyPublicEmail() {
    const emailInput = document.getElementById('publicEmail');
    const email = emailInput.value;
    
    // Modern clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(email).then(function() {
            showCopySuccess();
        }).catch(function(err) {
            fallbackCopyTextToClipboard(email);
        });
    } else {
        // Fallback for older browsers
        fallbackCopyTextToClipboard(email);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess();
        } else {
            showCopyError();
        }
    } catch (err) {
        showCopyError();
    }
    
    document.body.removeChild(textArea);
}

function showCopySuccess() {
    // Change the icon temporarily to show success
    const iconElement = document.getElementById('copy-icon');
    if (iconElement) {
        const originalClass = iconElement.className;
        iconElement.className = 'fas fa-check';
        iconElement.style.color = '#48c78e';
        // Reset after 2 seconds
        setTimeout(function() {
            iconElement.className = originalClass;
            iconElement.style.color = '';
        }, 2000);
    }
}

function showCopyError() {
    // Change the icon temporarily to show error
    const iconElement = document.getElementById('copy-icon');
    if (iconElement) {
        const originalClass = iconElement.className;
        iconElement.className = 'fas fa-times';
        iconElement.style.color = '#ff3b30';
        // Reset after 2 seconds
        setTimeout(function() {
            iconElement.className = originalClass;
            iconElement.style.color = '';
        }, 2000);
    }
}

function togglePasswordChange() {
    const form = document.getElementById('password-change-form');
    const icon = document.getElementById('password-toggle-icon');
    const iconElement = icon.querySelector('i');
    
    if (form.style.display === 'none' || form.style.display === '') {
        // Expand
        form.style.display = 'block';
        iconElement.className = 'fas fa-chevron-up';
    } else {
        // Collapse
        form.style.display = 'none';
        iconElement.className = 'fas fa-chevron-down';
    }
}

function handleImageUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Please select a valid image file (JPEG, PNG, GIF, or WebP).');
            input.value = '';
            return;
        }
        
        // Validate file size (5MB max)
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (file.size > maxSize) {
            alert('Image file must be smaller than 5MB.');
            input.value = '';
            return;
        }
        
        // Show confirmation dialog
        if (confirm('Are you sure you want to upload this image as your profile picture?')) {
            // Show loading state
            const uploadButtons = document.querySelectorAll('.button');
            uploadButtons.forEach(btn => {
                btn.classList.add('is-loading');
                btn.disabled = true;
            });
            
            // Submit the form
            document.getElementById('imageUploadForm').submit();
        } else {
            // Reset the input if user cancels
            input.value = '';
        }
    }
}

// Handle profile image loading with secure endpoint (for manual accounts only)
function loadSecureProfileImage() {
    const profileImg = document.getElementById('profile-image-display');
    const placeholder = document.getElementById('profile-placeholder');
    
    // Only handle secure loading if both elements exist (manual accounts only)
    if (profileImg && placeholder) {
        profileImg.onerror = function() {
            this.style.display = 'none';
            placeholder.style.display = 'flex';
        };
        
        profileImg.onload = function() {
            this.style.display = 'block';
            placeholder.style.display = 'none';
        };
    }
}

// Initialize secure image loading when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadSecureProfileImage();
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
