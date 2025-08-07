<?php
// admin/contracts.php - Contract management interface
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/ContractService.php';

$userModel = new User();
$contractService = new ContractService();

// Check if current user is admin
if (!$userModel->isUserAdmin($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'generate_contract':
            $userId = intval($_POST['user_id'] ?? 0);
            $userFirstName = trim($_POST['user_first_name'] ?? '');
            $userLastName = trim($_POST['user_last_name'] ?? '');
            $userAddress = trim($_POST['user_address'] ?? '');
            $userAbn = trim($_POST['user_abn'] ?? '');
            
            if ($userId > 0) {
                // First, update user profile if any data was provided
                $profileUpdated = false;
                $profileMessage = '';
                if (!empty($userFirstName) || !empty($userLastName) || !empty($userAddress) || !empty($userAbn)) {
                    $updateResult = $userModel->updateUserProfile($userId, [
                        'first_name' => $userFirstName,
                        'last_name' => $userLastName,
                        'address' => $userAddress,
                        'abn_acn' => $userAbn
                    ]);
                    
                    if ($updateResult['success']) {
                        $profileUpdated = true;
                        $profileMessage = ' User profile has been updated with the provided information.';
                    } else {
                        $profileMessage = ' Warning: ' . $updateResult['message'];
                    }
                }
                
                // Then generate the contract
                $result = $contractService->generatePersonalizedContract($userId);
                
                if ($result['success']) {
                    $message = $result['message'] . $profileMessage;
                    $messageType = 'success';
                } else {
                    $message = $result['message'] . $profileMessage;
                    $messageType = 'error';
                }
            } else {
                $message = 'Please select a user.';
                $messageType = 'error';
            }
            break;
            
        case 'update_contract':
            $contractId = intval($_POST['contract_id'] ?? 0);
            $contractContent = trim($_POST['contract_content'] ?? '');
            
            if ($contractId > 0 && !empty($contractContent)) {
                $result = $contractService->updateContract($contractId, $contractContent);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } else {
                $message = 'Invalid contract data.';
                $messageType = 'error';
            }
            break;
            
        case 'update_status':
            $contractId = intval($_POST['contract_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            $signedBy = trim($_POST['signed_by'] ?? '');
            
            if ($contractId > 0 && !empty($status)) {
                $result = $contractService->updateContractStatus($contractId, $status, $signedBy);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } else {
                $message = 'Invalid status update data.';
                $messageType = 'error';
            }
            break;
            
        case 'generate_pdf':
            $contractId = intval($_POST['contract_id'] ?? 0);
            
            if ($contractId > 0) {
                $result = $contractService->generateContractPDF($contractId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } else {
                $message = 'Invalid contract ID.';
                $messageType = 'error';
            }
            break;
            
        case 'delete_contract':
            $contractId = intval($_POST['contract_id'] ?? 0);
            
            if ($contractId > 0) {
                $result = $contractService->deleteContract($contractId, $_SESSION['user_id']);
                $message = $result ? 'Contract deleted successfully.' : 'Failed to delete contract.';
                $messageType = $result ? 'success' : 'error';
            } else {
                $message = 'Invalid contract ID.';
                $messageType = 'error';
            }
            break;
            
        case 'refresh_contract':
            $contractId = intval($_POST['contract_id'] ?? 0);
            
            if ($contractId > 0) {
                $result = $contractService->refreshContractWithUserData($contractId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } else {
                $message = 'Invalid contract ID.';
                $messageType = 'error';
            }
            break;
            
        case 'update_user_profile':
            $userId = intval($_POST['edit_user_id'] ?? 0);
            $firstName = trim($_POST['edit_first_name'] ?? '');
            $lastName = trim($_POST['edit_last_name'] ?? '');
            $address = trim($_POST['edit_address'] ?? '');
            $abnAcn = trim($_POST['edit_abn_acn'] ?? '');
            
            if ($userId > 0) {
                $profileData = [];
                if (!empty($firstName)) $profileData['first_name'] = $firstName;
                if (!empty($lastName)) $profileData['last_name'] = $lastName;
                if (!empty($address)) $profileData['address'] = $address;
                if (!empty($abnAcn)) $profileData['abn_acn'] = $abnAcn;
                
                if (!empty($profileData)) {
                    $result = $userModel->updateUserProfile($userId, $profileData);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                } else {
                    $message = 'No profile data provided for update.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Invalid user ID.';
                $messageType = 'error';
            }
            break;
            
        case 'send_contract':
            $contractId = intval($_POST['contract_id'] ?? 0);
            
            if ($contractId > 0) {
                $result = $contractService->markCompanyAccepted($contractId);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } else {
                $message = 'Invalid contract ID.';
                $messageType = 'error';
            }
            break;
    }
}

// Get all users for contract generation
$allUsers = $userModel->getAllActiveUsers();

// Get all contracts
$allContracts = $contractService->getAllContracts();

// Helper function for status colors
function getStatusColor($status) {
    switch ($status) {
        case 'draft': return 'dark';
        case 'sent': return 'info';
        case 'signed': return 'success';
        case 'completed': return 'primary';
        case 'cancelled': return 'danger';
        default: return 'dark';
    }
}

$pageTitle = 'Contract Management | Aetia Admin';
ob_start();
?>

<div class="container">
    <h1 class="title">Contract Management</h1>
    <p class="subtitle">Manage Communications Services Agreements</p>
    
    <?php if (!empty($message)): ?>
        <div class="notification is-<?= $messageType === 'success' ? 'success' : 'danger' ?>">
            <button class="delete"></button>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Generate New Contract -->
    <div class="box">
        <h2 class="title is-4">Generate New Contract</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_contract">
            
            <div class="columns">
                <div class="column is-half">
                    <div class="field">
                        <label class="label">Select User</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select name="user_id" required onchange="updateSelectedUserInfo()">
                                    <option value="">Choose a user...</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?= $user['id'] ?>" 
                                                data-first-name="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                                                data-last-name="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                                                data-abn="<?= htmlspecialchars($user['abn_acn'] ?? '') ?>"
                                                data-address="<?= htmlspecialchars($user['address'] ?? '') ?>"
                                                data-email="<?= htmlspecialchars($user['email']) ?>">
                                            <?= htmlspecialchars($user['username']) ?> 
                                            (<?= htmlspecialchars($user['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="field" id="selected-user-info" style="display: none;">
                        <label class="label">Contract Information</label>
                        <div class="control">
                            <div class="notification is-info is-light" id="user-info-notification">
                                <p><strong>User Profile Status:</strong></p>
                                <div id="profile-status">
                                    <p><span id="name-status">✓</span> <strong>Legal Name:</strong> <span id="user-legal-name">-</span></p>
                                    <p><span id="address-status">✓</span> <strong>Address:</strong> <span id="user-address">-</span></p>
                                    <p><span id="abn-status">✓</span> <strong>ABN/ACN:</strong> <span id="user-abn-status">-</span></p>
                                </div>
                                <div id="incomplete-notice" style="display: none;">
                                    <hr>
                                    <p class="has-text-weight-bold">⚠️ Profile Incomplete</p>
                                    <p class="is-size-7">Some required information is missing. You can complete it below and it will be saved to the user's profile.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="column is-half">
                    <!-- Editable fields for incomplete data -->
                    <div id="profile-completion-fields" style="display: none;">
                        <div class="field" id="completion-header" style="display: none;">
                            <label class="label">Complete User Profile</label>
                            <p class="is-size-7 has-text-info"><strong>Note:</strong> Completing the fields below will permanently update the user's profile information.</p>
                        </div>
                        
                        <div class="columns">
                            <div class="column">
                                <div class="field" id="edit-first-name-field" style="display: none;">
                                    <label class="label">First Name</label>
                                    <div class="control">
                                        <input class="input" type="text" name="user_first_name" id="user-first-name-input" placeholder="Enter first name">
                                    </div>
                                </div>
                            </div>
                            <div class="column">
                                <div class="field" id="edit-last-name-field" style="display: none;">
                                    <label class="label">Last Name</label>
                                    <div class="control">
                                        <input class="input" type="text" name="user_last_name" id="user-last-name-input" placeholder="Enter last name">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="field" id="edit-address-field" style="display: none;">
                            <label class="label">Address</label>
                            <div class="control">
                                <textarea class="textarea" name="user_address" id="user-address-input" rows="3" placeholder="Enter full address"></textarea>
                            </div>
                        </div>
                        
                        <div class="field" id="edit-abn-field" style="display: none;">
                            <label class="label">ABN/ACN (Optional)</label>
                            <div class="control">
                                <input class="input" type="text" name="user_abn" id="user-abn-input" placeholder="Enter ABN or ACN if applicable">
                            </div>
                        </div>
                    </div>
                </div>
            </div>            <div class="field is-grouped">
                <div class="control">
                    <button type="submit" class="button is-primary">
                        <span class="icon"><i class="fas fa-file-contract"></i></span>
                        <span>Generate Contract</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Existing Contracts -->
    <div class="box">
        <h2 class="title is-4">Existing Contracts</h2>
        
        <?php if (empty($allContracts)): ?>
            <div class="notification is-info">
                <p>No contracts have been generated yet.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table is-fullwidth is-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Talent Name</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allContracts as $contract): ?>
                            <tr>
                                <td><?= $contract['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($contract['user_username']) ?></strong><br>
                                    <small><?= htmlspecialchars($contract['user_email']) ?></small><br>
                                    <button class="button is-small is-link mt-1" onclick="editUserProfile(<?= $contract['user_id'] ?>, '<?= htmlspecialchars($contract['user_username']) ?>')">
                                        <span class="icon is-small"><i class="fas fa-user-edit"></i></span>
                                        <span>Edit Profile</span>
                                    </button>
                                </td>
                                <td><?= htmlspecialchars($contract['client_name']) ?></td>
                                <td>
                                    <span class="tag is-<?= getStatusColor($contract['status']) ?>">
                                        <?= ucfirst($contract['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($contract['created_at'])) ?></td>
                                <td>
                                    <div class="buttons">
                                        <button class="button is-small is-info" onclick="viewContract(<?= $contract['id'] ?>)">
                                            <span class="icon"><i class="fas fa-eye"></i></span>
                                            <span>View</span>
                                        </button>
                                        <button class="button is-small is-primary" onclick="refreshContract(<?= $contract['id'] ?>)" title="Update contract with latest user profile data">
                                            <span class="icon"><i class="fas fa-sync"></i></span>
                                            <span>Refresh</span>
                                        </button>
                                        <button class="button is-small is-warning" onclick="editContract(<?= $contract['id'] ?>)">
                                            <span class="icon"><i class="fas fa-edit"></i></span>
                                            <span>Edit</span>
                                        </button>
                                        <?php if ($contract['status'] === 'draft' && empty($contract['company_accepted_date'])): ?>
                                        <button class="button is-small is-primary" onclick="sendContract(<?= $contract['id'] ?>)" title="Generate and send PDF to user (marks company acceptance)">
                                            <span class="icon"><i class="fas fa-file-pdf"></i></span>
                                            <span>Generate & Send PDF</span>
                                        </button>
                                        <?php else: ?>
                                        <button class="button is-small is-success" onclick="generatePDF(<?= $contract['id'] ?>)" title="Download PDF copy">
                                            <span class="icon"><i class="fas fa-download"></i></span>
                                            <span>Download PDF</span>
                                        </button>
                                        <?php endif; ?>
                                        <button class="button is-small is-danger" onclick="deleteContract(<?= $contract['id'] ?>)">
                                            <span class="icon"><i class="fas fa-trash"></i></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Contract View Modal -->
<div class="modal" id="contract-modal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width: 90%; max-width: 1000px;">
        <header class="modal-card-head">
            <p class="modal-card-title">Contract Details</p>
            <button class="delete" aria-label="close" onclick="closeModal()"></button>
        </header>
        <section class="modal-card-body">
            <div id="contract-content"></div>
        </section>
        <footer class="modal-card-foot">
            <button class="button" onclick="closeModal()">Close</button>
        </footer>
    </div>
</div>

<!-- Contract Edit Modal -->
<div class="modal" id="edit-modal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width: 90%; max-width: 1000px;">
        <header class="modal-card-head">
            <p class="modal-card-title">Edit Contract</p>
            <button class="delete" aria-label="close" onclick="closeEditModal()"></button>
        </header>
        <section class="modal-card-body">
            <form id="edit-contract-form" method="POST" action="">
                <input type="hidden" name="action" value="update_contract">
                <input type="hidden" name="contract_id" id="edit-contract-id">
                
                <div class="columns">
                    <div class="column is-one-third">
                        <div class="field">
                            <label class="label">Legal Name (From User Profile)</label>
                            <div class="control">
                                <div class="notification is-info is-light">
                                    <p id="edit-legal-name-display">-</p>
                                    <p class="is-size-7">This information comes from the user's profile and cannot be changed here.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">Address (From User Profile)</label>
                            <div class="control">
                                <div class="notification is-info is-light">
                                    <p id="edit-address-display">-</p>
                                    <p class="is-size-7">This address comes from the user's profile and cannot be changed here.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">Status</label>
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select id="edit-status">
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="signed">Signed</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="column is-two-thirds">
                        <div class="field">
                            <label class="label">Contract Content</label>
                            <div class="control">
                                <textarea class="textarea" name="contract_content" id="edit-contract-content" rows="20" style="font-family: monospace; font-size: 12px;"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </section>
        <footer class="modal-card-foot">
            <button class="button is-primary" onclick="saveContract()">Save Changes</button>
            <button class="button" onclick="closeEditModal()">Cancel</button>
        </footer>
    </div>
</div>

<!-- Edit User Profile Modal -->
<div class="modal" id="edit-profile-modal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width: 90%; max-width: 600px;">
        <header class="modal-card-head">
            <p class="modal-card-title">Edit User Profile</p>
            <button class="delete" aria-label="close" onclick="closeEditProfileModal()"></button>
        </header>
        <section class="modal-card-body">
            <form id="edit-profile-form" method="POST" action="">
                <input type="hidden" name="action" value="update_user_profile">
                <input type="hidden" name="edit_user_id" id="edit-profile-user-id">
                
                <div class="field">
                    <label class="label">Username</label>
                    <div class="control">
                        <input class="input" type="text" id="edit-profile-username" readonly>
                        <p class="help">Username cannot be changed</p>
                    </div>
                </div>
                
                <div class="columns">
                    <div class="column">
                        <div class="field">
                            <label class="label">First Name</label>
                            <div class="control">
                                <input class="input" type="text" name="edit_first_name" id="edit-profile-first-name" placeholder="Enter first name">
                            </div>
                        </div>
                    </div>
                    <div class="column">
                        <div class="field">
                            <label class="label">Last Name</label>
                            <div class="control">
                                <input class="input" type="text" name="edit_last_name" id="edit-profile-last-name" placeholder="Enter last name">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="field">
                    <label class="label">Address</label>
                    <div class="control">
                        <textarea class="textarea" name="edit_address" id="edit-profile-address" rows="3" placeholder="Enter full address"></textarea>
                    </div>
                </div>
                
                <div class="field">
                    <label class="label">ABN/ACN (Optional)</label>
                    <div class="control">
                        <input class="input" type="text" name="edit_abn_acn" id="edit-profile-abn" placeholder="Enter ABN or ACN if applicable">
                    </div>
                </div>
            </form>
        </section>
        <footer class="modal-card-foot">
            <button class="button is-primary" onclick="saveUserProfile()">Save Changes</button>
            <button class="button" onclick="closeEditProfileModal()">Cancel</button>
        </footer>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-close notifications
    document.querySelectorAll('.notification .delete').forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });
});

// Update selected user info display
function updateSelectedUserInfo() {
    const select = document.querySelector('select[name="user_id"]');
    const selectedOption = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('selected-user-info');
    const completionDiv = document.getElementById('profile-completion-fields');
    const incompleteNotice = document.getElementById('incomplete-notice');
    const notification = document.getElementById('user-info-notification');
    
    if (select.value && selectedOption) {
        const firstName = selectedOption.getAttribute('data-first-name') || '';
        const lastName = selectedOption.getAttribute('data-last-name') || '';
        const address = selectedOption.getAttribute('data-address') || '';
        const abn = selectedOption.getAttribute('data-abn') || '';
        const fullName = (firstName + ' ' + lastName).trim();
        
        // Update status indicators and display
        let isComplete = true;
        
        // Check and update name status
        if (fullName && firstName && lastName) {
            document.getElementById('name-status').textContent = '✓';
            document.getElementById('name-status').style.color = 'green';
            document.getElementById('user-legal-name').textContent = fullName;
            document.getElementById('edit-first-name-field').style.display = 'none';
            document.getElementById('edit-last-name-field').style.display = 'none';
        } else {
            document.getElementById('name-status').textContent = '✗';
            document.getElementById('name-status').style.color = 'red';
            document.getElementById('user-legal-name').textContent = 'Missing - Enter below';
            document.getElementById('edit-first-name-field').style.display = 'block';
            document.getElementById('edit-last-name-field').style.display = 'block';
            document.getElementById('user-first-name-input').value = firstName;
            document.getElementById('user-last-name-input').value = lastName;
            isComplete = false;
        }
        
        // Check and update address status
        if (address) {
            document.getElementById('address-status').textContent = '✓';
            document.getElementById('address-status').style.color = 'green';
            document.getElementById('user-address').textContent = address;
            document.getElementById('edit-address-field').style.display = 'none';
        } else {
            document.getElementById('address-status').textContent = '✗';
            document.getElementById('address-status').style.color = 'red';
            document.getElementById('user-address').textContent = 'Missing - Enter below';
            document.getElementById('edit-address-field').style.display = 'block';
            document.getElementById('user-address-input').value = '';
            isComplete = false;
        }
        
        // Check and update ABN status (optional)
        if (abn) {
            document.getElementById('abn-status').textContent = '✓';
            document.getElementById('abn-status').style.color = 'green';
            document.getElementById('user-abn-status').textContent = abn;
            document.getElementById('edit-abn-field').style.display = 'none';
        } else {
            document.getElementById('abn-status').textContent = '○';
            document.getElementById('abn-status').style.color = 'orange';
            document.getElementById('user-abn-status').textContent = 'Optional - Can add below';
            document.getElementById('edit-abn-field').style.display = 'block';
            document.getElementById('user-abn-input').value = '';
        }
        
        // Update notification appearance and show/hide completion fields
        const completionHeader = document.getElementById('completion-header');
        if (isComplete) {
            notification.className = 'notification is-success is-light';
            incompleteNotice.style.display = 'none';
            completionDiv.style.display = 'none';
            if (completionHeader) completionHeader.style.display = 'none';
        } else {
            notification.className = 'notification is-warning is-light';
            incompleteNotice.style.display = 'block';
            completionDiv.style.display = 'block';
            if (completionHeader) completionHeader.style.display = 'block';
        }
        
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
        completionDiv.style.display = 'none';
        const completionHeader = document.getElementById('completion-header');
        if (completionHeader) completionHeader.style.display = 'none';
    }
}

// View contract
async function viewContract(contractId) {
    try {
        const response = await fetch(`../api/contracts.php?action=get&id=${contractId}`);
        const data = await response.json();
        
        if (data.success) {
            const content = data.contract.contract_content.replace(/\n/g, '<br>');
            document.getElementById('contract-content').innerHTML = `
                <div style="white-space: pre-wrap; font-family: 'Times New Roman', serif; line-height: 1.6;">
                    ${content}
                </div>
            `;
            document.getElementById('contract-modal').classList.add('is-active');
        } else {
            alert('Failed to load contract: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading contract:', error);
        alert('Failed to load contract');
    }
}

// Edit contract
async function editContract(contractId) {
    try {
        const response = await fetch(`../api/contracts.php?action=get&id=${contractId}`);
        const data = await response.json();
        
        if (data.success) {
            const contract = data.contract;
            document.getElementById('edit-contract-id').value = contract.id;
            document.getElementById('edit-legal-name-display').textContent = contract.client_name || 'Not specified';
            document.getElementById('edit-address-display').textContent = contract.client_address || 'Not specified';
            document.getElementById('edit-contract-content').value = contract.contract_content;
            document.getElementById('edit-status').value = contract.status;
            document.getElementById('edit-modal').classList.add('is-active');
        } else {
            alert('Failed to load contract: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading contract:', error);
        alert('Failed to load contract');
    }
}

// Save contract
function saveContract() {
    document.getElementById('edit-contract-form').submit();
}

// Generate PDF
async function generatePDF(contractId) {
    if (confirm('Generate and upload PDF for this contract? This will also mark the contract as "sent".')) {
        try {
            const formData = new FormData();
            formData.append('action', 'generate_pdf');
            formData.append('contract_id', contractId);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            location.reload(); // Reload to see the updated status
        } catch (error) {
            console.error('Error generating PDF:', error);
            alert('Failed to generate PDF');
        }
    }
}

// Delete contract
async function deleteContract(contractId) {
    if (confirm('Are you sure you want to delete this contract? This action cannot be undone.')) {
        try {
            const formData = new FormData();
            formData.append('action', 'delete_contract');
            formData.append('contract_id', contractId);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            location.reload(); // Reload to see the changes
        } catch (error) {
            console.error('Error deleting contract:', error);
            alert('Failed to delete contract');
        }
    }
}

// Refresh contract with latest user data
async function refreshContract(contractId) {
    if (confirm('Update this contract with the latest user profile information? This will overwrite the current contract content with fresh data from the user\'s profile.')) {
        try {
            const formData = new FormData();
            formData.append('action', 'refresh_contract');
            formData.append('contract_id', contractId);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            location.reload(); // Reload to see the changes
        } catch (error) {
            console.error('Error refreshing contract:', error);
            alert('Failed to refresh contract');
        }
    }
}

// Send contract (mark as company accepted)
async function sendContract(contractId) {
    // Create a custom modal instead of browser confirm
    const modal = document.createElement('div');
    modal.className = 'modal is-active';
    modal.innerHTML = `
        <div class="modal-background"></div>
        <div class="modal-card">
            <header class="modal-card-head">
                <p class="modal-card-title">
                    <span class="icon"><i class="fas fa-file-pdf"></i></span>
                    Generate and Send PDF Contract
                </p>
            </header>
            <section class="modal-card-body">
                <div class="content">
                    <p><strong>This action will:</strong></p>
                    <ul>
                        <li>Mark the contract as <strong>accepted by the company</strong></li>
                        <li>Generate an official PDF document with company acceptance date</li>
                        <li>Store the PDF in the user's documents</li>
                        <li>Change the contract status to <strong>"sent"</strong></li>
                        <li>Allow the user to view and accept the contract</li>
                    </ul>
                    <div class="notification is-info">
                        <p><strong>Note:</strong> Once generated, the user will see an "I Accept" button on their contracts page and the PDF will be available in their documents.</p>
                    </div>
                </div>
            </section>
            <footer class="modal-card-foot">
                <button class="button is-primary" id="confirm-send">
                    <span class="icon"><i class="fas fa-file-pdf"></i></span>
                    <span>Generate & Send PDF</span>
                </button>
                <button class="button" id="cancel-send">Cancel</button>
            </footer>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Handle confirm
    document.getElementById('confirm-send').addEventListener('click', async () => {
        document.body.removeChild(modal);
        
        try {
            const formData = new FormData();
            formData.append('action', 'send_contract');
            formData.append('contract_id', contractId);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            location.reload(); // Reload to see the changes
        } catch (error) {
            console.error('Error sending contract:', error);
            alert('Failed to send contract');
        }
    });
    
    // Handle cancel
    document.getElementById('cancel-send').addEventListener('click', () => {
        document.body.removeChild(modal);
    });
    
    // Handle background click
    modal.querySelector('.modal-background').addEventListener('click', () => {
        document.body.removeChild(modal);
    });
}

// Close modals
function closeModal() {
    document.getElementById('contract-modal').classList.remove('is-active');
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.remove('is-active');
}

function closeEditProfileModal() {
    document.getElementById('edit-profile-modal').classList.remove('is-active');
}

// Edit user profile
async function editUserProfile(userId, username) {
    try {
        // Get user data - we'll use the existing user data from the page or fetch it
        const response = await fetch(`../api/users.php?action=get&id=${userId}`);
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            document.getElementById('edit-profile-user-id').value = userId;
            document.getElementById('edit-profile-username').value = username;
            document.getElementById('edit-profile-first-name').value = user.first_name || '';
            document.getElementById('edit-profile-last-name').value = user.last_name || '';
            document.getElementById('edit-profile-address').value = user.address || '';
            document.getElementById('edit-profile-abn').value = user.abn_acn || '';
            document.getElementById('edit-profile-modal').classList.add('is-active');
        } else {
            // Fallback - open modal with empty fields except username
            document.getElementById('edit-profile-user-id').value = userId;
            document.getElementById('edit-profile-username').value = username;
            document.getElementById('edit-profile-first-name').value = '';
            document.getElementById('edit-profile-last-name').value = '';
            document.getElementById('edit-profile-address').value = '';
            document.getElementById('edit-profile-abn').value = '';
            document.getElementById('edit-profile-modal').classList.add('is-active');
        }
    } catch (error) {
        console.error('Error loading user data:', error);
        // Fallback - open modal with empty fields except username
        document.getElementById('edit-profile-user-id').value = userId;
        document.getElementById('edit-profile-username').value = username;
        document.getElementById('edit-profile-first-name').value = '';
        document.getElementById('edit-profile-last-name').value = '';
        document.getElementById('edit-profile-address').value = '';
        document.getElementById('edit-profile-abn').value = '';
        document.getElementById('edit-profile-modal').classList.add('is-active');
    }
}

// Save user profile
function saveUserProfile() {
    document.getElementById('edit-profile-form').submit();
}

// Close modal on background click
document.querySelectorAll('.modal-background').forEach(bg => {
    bg.addEventListener('click', function() {
        this.parentElement.classList.remove('is-active');
    });
});
</script>
<?php
$content = ob_get_clean();
include '../layout.php';
?>
