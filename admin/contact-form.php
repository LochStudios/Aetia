<?php
// admin/contact-form.php - Admin interface for managing contact form submissions
session_start();

// Include timezone utilities
require_once __DIR__ . '/../includes/timezone.php';
require_once __DIR__ . '/../includes/FormTokenManager.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../models/User.php';

$contactModel = new Contact();
$userModel = new User();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Validate form token to prevent duplicate submissions
        $formName = $_POST['form_name'] ?? '';
        $formToken = $_POST['form_token'] ?? '';
        
        if (empty($formName)) {
            $error = 'Invalid form submission: Missing form name. Please refresh the page and try again.';
        } elseif (empty($formToken)) {
            $error = 'Invalid form submission: Missing form token. Please refresh the page and try again.';
        } elseif (!FormTokenManager::validateToken($formName, $formToken)) {
            $error = 'This form has already been submitted or has expired. Please refresh the page and try again.';
        } elseif (FormTokenManager::isRecentSubmission($formName)) {
            $error = 'Please wait a moment before submitting again.';
        } else {
            $contactId = intval($_POST['contact_id']);
            $action = $_POST['action'];
            
            switch ($action) {
                case 'update_status':
                    $status = $_POST['status'];
                    $userId = ($status === 'responded') ? $_SESSION['user_id'] : null;
                    if ($contactModel->updateStatus($contactId, $status, $userId)) {
                        $message = 'Contact status updated successfully!';
                        FormTokenManager::recordSubmission($formName);
                    } else {
                        $error = 'Failed to update contact status.';
                    }
                    break;
                
                case 'update_priority':
                    $priority = $_POST['priority'];
                    if ($contactModel->updatePriority($contactId, $priority)) {
                        $message = 'Contact priority updated successfully!';
                        FormTokenManager::recordSubmission($formName);
                    } else {
                        $error = 'Failed to update contact priority.';
                    }
                    break;
                
                case 'add_response':
                    $responseNotes = trim($_POST['response_notes']);
                    if (!empty($responseNotes)) {
                        if ($contactModel->addResponseNotes($contactId, $responseNotes, $_SESSION['user_id'])) {
                            $message = 'Response notes added successfully!';
                            FormTokenManager::recordSubmission($formName);
                        } else {
                            $error = 'Failed to add response notes.';
                        }
                    } else {
                        $error = 'Response notes cannot be empty.';
                    }
                    break;
                
                case 'delete':
                    if ($contactModel->deleteContactSubmission($contactId)) {
                        $message = 'Contact submission deleted successfully!';
                        FormTokenManager::recordSubmission($formName);
                    } else {
                        $error = 'Failed to delete contact submission.';
                    }
                    break;
            }
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? null;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get current contact submission if viewing one
$currentContact = null;
$contactId = isset($_GET['id']) ? intval($_GET['id']) : null;

if ($contactId) {
    $currentContact = $contactModel->getContactSubmission($contactId);
    if (!$currentContact) {
        // Contact not found, redirect to list
        header('Location: contact-form.php');
        exit;
    }
    
    // Mark as read if it's new
    if ($currentContact['status'] === 'new') {
        $contactModel->updateStatus($contactId, 'read');
        $currentContact['status'] = 'read'; // Update local copy
    }
}

// Get contact submissions for admin view
$contactSubmissions = $contactModel->getAllContactSubmissions($limit, $offset, $statusFilter);
$contactStats = $contactModel->getContactStats();

$pageTitle = $currentContact ? 'Contact: ' . htmlspecialchars($currentContact['subject'] ?: 'No Subject') . ' | Admin' : 'Contact Form Management | Admin';
ob_start();
?>

<div class="content">
    <?php if ($message): ?>
    <div class="notification is-success is-light">
        <button class="delete"></button>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="notification is-danger is-light">
        <button class="delete"></button>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="level mb-4">
        <div class="level-left">
            <h2 class="title is-2 has-text-primary">
                <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                Contact Form Management
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="messages.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-comments"></i></span>
                    <span>User Messages</span>
                </a>
                <a href="pending-users.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-users-cog"></i></span>
                    <span>Pending Users</span>
                </a>
                <a href="unverified-users.php" class="button is-warning is-small">
                    <span class="icon"><i class="fas fa-user-check"></i></span>
                    <span>Unverified Users</span>
                </a>
                <a href="../index.php" class="button is-light is-small">
                    <span class="icon"><i class="fas fa-home"></i></span>
                    <span>Back to Website</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="columns mb-4">
        <div class="column is-3">
            <div class="card has-background-info-light">
                <div class="card-content has-text-centered">
                    <p class="title is-4"><?= $contactStats['total'] ?? 0 ?></p>
                    <p class="subtitle is-6">Total Submissions</p>
                </div>
            </div>
        </div>
        <div class="column is-3">
            <div class="card has-background-warning-light">
                <div class="card-content has-text-centered">
                    <p class="title is-4"><?= $contactStats['status']['new'] ?? 0 ?></p>
                    <p class="subtitle is-6">New Submissions</p>
                </div>
            </div>
        </div>
        <div class="column is-3">
            <div class="card has-background-success-light">
                <div class="card-content has-text-centered">
                    <p class="title is-4"><?= $contactStats['today'] ?? 0 ?></p>
                    <p class="subtitle is-6">Today</p>
                </div>
            </div>
        </div>
        <div class="column is-3">
            <div class="card has-background-primary-light">
                <div class="card-content has-text-centered">
                    <p class="title is-4"><?= $contactStats['this_week'] ?? 0 ?></p>
                    <p class="subtitle is-6">This Week</p>
                </div>
            </div>
        </div>
    </div>

    <div class="columns">
        <!-- Contact Submissions List Sidebar -->
        <div class="column is-4">
            <div class="box">
                <!-- Status Filter -->
                <div class="field">
                    <label class="label">Filter by Status</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select onchange="updateFilters('status', this.value)">
                                <option value="">All Statuses</option>
                                <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>New</option>
                                <option value="read" <?= $statusFilter === 'read' ? 'selected' : '' ?>>Read</option>
                                <option value="responded" <?= $statusFilter === 'responded' ? 'selected' : '' ?>>Responded</option>
                                <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($contactSubmissions)): ?>
                <div class="has-text-centered has-text-grey">
                    <span class="icon is-large">
                        <i class="fas fa-inbox fa-2x"></i>
                    </span>
                    <p class="mt-2">No contact submissions found</p>
                    <?php if ($statusFilter): ?>
                    <a href="contact-form.php" class="button is-small is-light mt-2">View All</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                
                <!-- Contact Submissions List -->
                <div class="panel">
                    <?php foreach ($contactSubmissions as $contact): ?>
                    <a class="panel-block <?= $contactId == $contact['id'] ? 'is-active' : '' ?>" 
                       href="?id=<?= $contact['id'] ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?>">
                        <div class="is-flex is-justify-content-space-between is-align-items-center" style="width: 100%;">
                            <div>
                                <div class="is-flex is-align-items-center mb-1">
                                    <!-- Status Badge -->
                                    <?php 
                                    $statusClass = [
                                        'new' => 'is-danger',
                                        'read' => 'is-info', 
                                        'responded' => 'is-success',
                                        'closed' => 'is-dark'
                                    ][$contact['status']] ?? 'is-dark';
                                    ?>
                                    <span class="tag is-small <?= $statusClass ?> mr-2 has-text-weight-bold">
                                        <?= strtoupper($contact['status']) ?>
                                    </span>
                                    
                                    <!-- Priority Badge -->
                                    <?php if ($contact['priority'] !== 'normal'): ?>
                                    <?php 
                                    $priorityClass = [
                                        'high' => 'is-danger',
                                        'low' => 'is-dark'
                                    ][$contact['priority']] ?? 'is-dark';
                                    ?>
                                    <span class="tag is-small <?= $priorityClass ?> has-text-weight-bold">
                                        <?= strtoupper($contact['priority']) ?> PRIORITY
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p class="has-text-weight-semibold">
                                    <?= htmlspecialchars($contact['name']) ?>
                                </p>
                                <p class="is-size-7 has-text-grey">
                                    <?= htmlspecialchars($contact['email']) ?>
                                </p>
                                <?php if ($contact['subject']): ?>
                                <p class="is-size-7">
                                    <?= htmlspecialchars(substr($contact['subject'], 0, 50)) ?><?= strlen($contact['subject']) > 50 ? '...' : '' ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="has-text-right">
                                <p class="is-size-7 has-text-grey">
                                    <?= convertToUserTimezone($contact['created_at']) ?>
                                </p>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if (count($contactSubmissions) === $limit || $page > 1): ?>
                <nav class="pagination is-small mt-4" role="navigation" aria-label="pagination">
                    <?php if ($page > 1): ?>
                    <a class="pagination-previous" href="?page=<?= $page - 1 ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $contactId ? '&id=' . $contactId : '' ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php if (count($contactSubmissions) === $limit): ?>
                    <a class="pagination-next" href="?page=<?= $page + 1 ?><?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $contactId ? '&id=' . $contactId : '' ?>">Next</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contact Detail -->
        <div class="column is-8">
            <?php if ($currentContact): ?>
            <div class="box">
                <!-- Contact Header -->
                <div class="level mb-4">
                    <div class="level-left">
                        <div>
                            <h3 class="title is-4 mb-1">
                                <?= htmlspecialchars($currentContact['name']) ?>
                            </h3>
                            <p class="subtitle is-6 mb-2">
                                <a href="mailto:<?= htmlspecialchars($currentContact['email']) ?>">
                                    <?= htmlspecialchars($currentContact['email']) ?>
                                </a>
                            </p>
                            <?php if ($currentContact['subject']): ?>
                            <p class="has-text-weight-semibold">
                                Subject: <?= htmlspecialchars($currentContact['subject']) ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="level-right">
                        <div class="tags">
                            <?php 
                            $statusClass = [
                                'new' => 'is-danger',
                                'read' => 'is-info', 
                                'responded' => 'is-success',
                                'closed' => 'is-dark'
                            ][$currentContact['status']] ?? 'is-dark';
                            ?>
                            <span class="tag <?= $statusClass ?>">
                                Status: <?= ucfirst($currentContact['status']) ?>
                            </span>
                            
                            <?php 
                            $priorityClass = [
                                'high' => 'is-danger',
                                'normal' => 'is-info',
                                'low' => 'is-dark'
                            ][$currentContact['priority']] ?? 'is-info';
                            ?>
                            <span class="tag <?= $priorityClass ?>">
                                Priority: <?= ucfirst($currentContact['priority']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Details -->
                <div class="content">
                    <div class="columns is-gapless mb-3">
                        <div class="column is-half">
                            <p><strong>Submitted:</strong> <?= convertToUserTimezone($currentContact['created_at']) ?></p>
                            <?php if ($currentContact['ip_address']): ?>
                            <p><strong>IP Address:</strong> <?= htmlspecialchars($currentContact['ip_address']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="column is-half">
                            <?php if ($currentContact['responded_at']): ?>
                            <p><strong>Responded:</strong> <?= convertToUserTimezone($currentContact['responded_at']) ?></p>
                            <p><strong>Responded By:</strong> <?= htmlspecialchars($currentContact['responded_by_username'] ?? 'Unknown') ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Message Content -->
                <div class="content">
                    <h5 class="title is-6">Message:</h5>
                    <div class="box has-background-light">
                        <?= nl2br(htmlspecialchars($currentContact['message'])) ?>
                    </div>
                </div>
                
                <!-- Response Notes -->
                <?php if ($currentContact['response_notes']): ?>
                <div class="content">
                    <h5 class="title is-6">Response Notes:</h5>
                    <div class="box has-background-success-light">
                        <?= nl2br(htmlspecialchars($currentContact['response_notes'])) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Forms -->
                <div class="columns">
                    <!-- Update Status -->
                    <div class="column is-4">
                        <div class="box">
                            <h6 class="title is-6">Update Status</h6>
                            <form method="POST" class="admin-form">
                                <input type="hidden" name="form_name" value="update_status_<?= $currentContact['id'] ?>">
                                <input type="hidden" name="form_token" value="<?= FormTokenManager::generateToken('update_status_' . $currentContact['id']) ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="contact_id" value="<?= $currentContact['id'] ?>">
                                <div class="field">
                                    <div class="control">
                                        <div class="select is-fullwidth">
                                            <select name="status" required>
                                                <option value="new" <?= $currentContact['status'] === 'new' ? 'selected' : '' ?>>New</option>
                                                <option value="read" <?= $currentContact['status'] === 'read' ? 'selected' : '' ?>>Read</option>
                                                <option value="responded" <?= $currentContact['status'] === 'responded' ? 'selected' : '' ?>>Responded</option>
                                                <option value="closed" <?= $currentContact['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="control">
                                    <button type="submit" class="button is-primary is-small is-fullwidth">
                                        Update Status
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Update Priority -->
                    <div class="column is-4">
                        <div class="box">
                            <h6 class="title is-6">Update Priority</h6>
                            <form method="POST" class="admin-form">
                                <input type="hidden" name="form_name" value="update_priority_<?= $currentContact['id'] ?>">
                                <input type="hidden" name="form_token" value="<?= FormTokenManager::generateToken('update_priority_' . $currentContact['id']) ?>">
                                <input type="hidden" name="action" value="update_priority">
                                <input type="hidden" name="contact_id" value="<?= $currentContact['id'] ?>">
                                <div class="field">
                                    <div class="control">
                                        <div class="select is-fullwidth">
                                            <select name="priority" required>
                                                <option value="low" <?= $currentContact['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                                                <option value="normal" <?= $currentContact['priority'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                                                <option value="high" <?= $currentContact['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="control">
                                    <button type="submit" class="button is-warning is-small is-fullwidth">
                                        Update Priority
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Delete Contact -->
                    <div class="column is-4">
                        <div class="box">
                            <h6 class="title is-6">Delete Contact</h6>
                            <p class="is-size-7 has-text-grey mb-3">Permanently delete this contact submission.</p>
                            <button class="button is-danger is-small is-fullwidth" onclick="deleteContact(<?= $currentContact['id'] ?>)">
                                <span class="icon"><i class="fas fa-trash"></i></span>
                                <span>Delete</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Add Response Notes -->
                <div class="box">
                    <h6 class="title is-6">Add Response Notes</h6>
                    <form method="POST" class="admin-form">
                        <input type="hidden" name="form_name" value="add_response_<?= $currentContact['id'] ?>">
                        <input type="hidden" name="form_token" value="<?= FormTokenManager::generateToken('add_response_' . $currentContact['id']) ?>">
                        <input type="hidden" name="action" value="add_response">
                        <input type="hidden" name="contact_id" value="<?= $currentContact['id'] ?>">
                        <div class="field">
                            <div class="control">
                                <textarea class="textarea" name="response_notes" rows="4" 
                                          placeholder="Add notes about your response to this contact submission..."><?= htmlspecialchars($currentContact['response_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="control">
                            <button type="submit" class="button is-success">
                                <span class="icon"><i class="fas fa-save"></i></span>
                                <span>Save Response Notes</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Quick Actions -->
                <div class="field is-grouped">
                    <div class="control">
                        <a href="mailto:<?= htmlspecialchars($currentContact['email']) ?>?subject=Re: <?= htmlspecialchars($currentContact['subject'] ?: 'Your inquiry') ?>" 
                           class="button is-info">
                            <span class="icon"><i class="fas fa-reply"></i></span>
                            <span>Reply via Email</span>
                        </a>
                    </div>
                    <div class="control">
                        <a href="contact-form.php" class="button is-light">
                            <span class="icon"><i class="fas fa-list"></i></span>
                            <span>Back to List</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- No contact selected -->
            <div class="panel-block is-flex-direction-column has-text-centered p-5">
                <span class="icon is-large has-text-grey mb-4">
                    <i class="fas fa-envelope-open-text fa-3x"></i>
                </span>
                <p class="title is-5 has-text-grey">Select a contact submission to view details</p>
                <p class="subtitle is-6 has-text-grey">Choose a contact from the list on the left to manage and respond to inquiries.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Start output buffering for scripts
ob_start();
?>
<script>
// Form tokens for JavaScript submissions
const formTokens = {
    deleteContact: '<?= FormTokenManager::generateToken('delete_contact') ?>'
};

function updateFilters(filterType, value) {
    const url = new URL(window.location);
    
    // Update the specified filter
    if (value) {
        url.searchParams.set(filterType, value);
    } else {
        url.searchParams.delete(filterType);
    }
    
    // Preserve the current contact ID if viewing one
    <?php if ($contactId): ?>
    url.searchParams.set('id', '<?= $contactId ?>');
    <?php endif; ?>
    
    // Reset page when changing filters
    url.searchParams.delete('page');
    
    // Navigate to the updated URL
    window.location.href = url.toString();
}

// Delete contact function
function deleteContact(contactId) {
    Swal.fire({
        title: 'Delete Contact Submission?',
        text: 'This action cannot be undone. The contact submission and all associated data will be permanently deleted.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f14668',
        cancelButtonColor: '#dbdbdb',
        confirmButtonText: '<i class="fas fa-trash"></i> Yes, Delete',
        cancelButtonText: '<i class="fas fa-times"></i> Cancel',
        customClass: {
            confirmButton: 'button is-danger',
            cancelButton: 'button is-light'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Create and submit delete form
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="form_name" value="delete_contact_${contactId}">
                <input type="hidden" name="form_token" value="${formTokens.deleteContact}">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="contact_id" value="${contactId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Prevent double submissions by disabling submit buttons after click
    document.querySelectorAll('.admin-form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="icon"><i class="fas fa-spinner fa-pulse"></i></span><span>Processing...</span>';
                
                // Re-enable after timeout (in case of validation errors)
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = submitButton.dataset.originalText || 'Submit';
                }, 10000);
            }
        });
    });
    
    // Auto-refresh contact list every 60 seconds when viewing contact list
    <?php if (!$contactId): ?>
    setInterval(function() {
        // Check for new contact submissions
        fetch('contact-form.php')
            .then(response => response.text())
            .then(html => {
                // You could implement AJAX refresh here if needed
            })
            .catch(error => console.log('Auto-refresh error:', error));
    }, 60000);
    <?php endif; ?>
});
</script>

<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
