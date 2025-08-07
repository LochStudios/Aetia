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
            $talentName = trim($_POST['talent_name'] ?? '');
            $talentAddress = trim($_POST['talent_address'] ?? '');
            $talentAbn = trim($_POST['talent_abn'] ?? '');
            
            if ($userId > 0 && !empty($talentName)) {
                $result = $contractService->generatePersonalizedContract($userId, $talentName, $talentAddress, $talentAbn);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
            } else {
                $message = 'Please select a user and provide talent name.';
                $messageType = 'error';
            }
            break;
            
        case 'update_contract':
            $contractId = intval($_POST['contract_id'] ?? 0);
            $contractContent = trim($_POST['contract_content'] ?? '');
            $talentName = trim($_POST['talent_name'] ?? '');
            $talentAddress = trim($_POST['talent_address'] ?? '');
            $talentAbn = trim($_POST['talent_abn'] ?? '');
            
            if ($contractId > 0 && !empty($contractContent)) {
                $result = $contractService->updateContract($contractId, $contractContent, $talentName, $talentAddress, $talentAbn);
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
    }
}

// Get all users for contract generation
$allUsers = $userModel->getAllActiveUsers();

// Get all contracts
$allContracts = $contractService->getAllContracts();

// Helper function for status colors
function getStatusColor($status) {
    switch ($status) {
        case 'draft': return 'light';
        case 'sent': return 'info';
        case 'signed': return 'success';
        case 'completed': return 'primary';
        case 'cancelled': return 'danger';
        default: return 'light';
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
                                <select name="user_id" required>
                                    <option value="">Choose a user...</option>
                                    <?php foreach ($allUsers as $user): ?>
                                        <option value="<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['username']) ?> 
                                            (<?= htmlspecialchars($user['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">Talent's Full Legal Name</label>
                        <div class="control">
                            <input class="input" type="text" name="talent_name" required placeholder="Enter full legal name">
                        </div>
                    </div>
                </div>
                
                <div class="column is-half">
                    <div class="field">
                        <label class="label">Talent's Address</label>
                        <div class="control">
                            <textarea class="textarea" name="talent_address" rows="3" placeholder="Enter full address"></textarea>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label class="label">ABN/ACN (Optional)</label>
                        <div class="control">
                            <input class="input" type="text" name="talent_abn" placeholder="Enter ABN or ACN if applicable">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="field is-grouped">
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
                                    <small><?= htmlspecialchars($contract['user_email']) ?></small>
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
                                        <button class="button is-small is-warning" onclick="editContract(<?= $contract['id'] ?>)">
                                            <span class="icon"><i class="fas fa-edit"></i></span>
                                            <span>Edit</span>
                                        </button>
                                        <button class="button is-small is-success" onclick="generatePDF(<?= $contract['id'] ?>)">
                                            <span class="icon"><i class="fas fa-file-pdf"></i></span>
                                            <span>PDF</span>
                                        </button>
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
                            <label class="label">Talent Name</label>
                            <div class="control">
                                <input class="input" type="text" name="talent_name" id="edit-talent-name">
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">Address</label>
                            <div class="control">
                                <textarea class="textarea" name="talent_address" id="edit-talent-address" rows="3"></textarea>
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">ABN/ACN</label>
                            <div class="control">
                                <input class="input" type="text" name="talent_abn" id="edit-talent-abn">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-close notifications
    document.querySelectorAll('.notification .delete').forEach(button => {
        button.addEventListener('click', function() {
            this.parentElement.style.display = 'none';
        });
    });
});

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
            document.getElementById('edit-talent-name').value = contract.client_name;
            document.getElementById('edit-talent-address').value = contract.client_address || '';
            document.getElementById('edit-talent-abn').value = '';
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

// Close modals
function closeModal() {
    document.getElementById('contract-modal').classList.remove('is-active');
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.remove('is-active');
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
