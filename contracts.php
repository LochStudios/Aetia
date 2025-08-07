<?php
// contracts.php - User contract viewing page
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/services/ContractService.php';

$userModel = new User();
$contractService = new ContractService();

// Get user details
$user = $userModel->getUserWithAdminStatus($_SESSION['user_id']);
if (!$user) {
    header('Location: logout.php');
    exit;
}

// Get user contracts
$contracts = $contractService->getUserContracts($_SESSION['user_id']);

// Helper function for status colors
function getContractStatusColor($status) {
    switch ($status) {
        case 'draft': return 'light';
        case 'sent': return 'info';
        case 'signed': return 'success';
        case 'completed': return 'primary';
        case 'cancelled': return 'danger';
        default: return 'light';
    }
}

$pageTitle = 'My Contracts | Aetia Talent Agency';
ob_start();
?>

<div class="container">
    <h1 class="title">My Contracts</h1>
    <p class="subtitle">View your Communications Services Agreements</p>
    
    <?php if (empty($contracts)): ?>
        <div class="notification is-info">
            <p><strong>No contracts available.</strong></p>
            <p>You don't have any contracts yet. When Aetia generates a contract for you, it will appear here.</p>
        </div>
    <?php else: ?>
        <div class="columns is-multiline">
            <?php foreach ($contracts as $contract): ?>
                <div class="column is-full">
                    <div class="card">
                        <header class="card-header">
                            <p class="card-header-title">
                                <span class="icon"><i class="fas fa-file-contract"></i></span>
                                Communications Services Agreement
                            </p>
                            <span class="card-header-icon">
                                <span class="tag is-<?= getContractStatusColor($contract['status']) ?>">
                                    <?= ucfirst($contract['status']) ?>
                                </span>
                            </span>
                        </header>
                        <div class="card-content">
                            <div class="content">
                                <div class="columns">
                                    <div class="column is-two-thirds">
                                        <p><strong>Client Name:</strong> <?= htmlspecialchars($contract['client_name']) ?></p>
                                        <?php if (!empty($contract['client_address'])): ?>
                                            <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($contract['client_address'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="column">
                                        <p><strong>Created:</strong> <?= date('F j, Y', strtotime($contract['created_at'])) ?></p>
                                        <?php if ($contract['signed_date']): ?>
                                            <p><strong>Signed:</strong> <?= date('F j, Y', strtotime($contract['signed_date'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="card-footer">
                            <button class="card-footer-item button is-white" onclick="viewContract(<?= $contract['id'] ?>)">
                                <span class="icon"><i class="fas fa-eye"></i></span>
                                <span>View Contract</span>
                            </button>
                            <?php if ($contract['status'] === 'sent' && empty($contract['signed_date'])): ?>
                                <button class="card-footer-item button is-white" onclick="signContract(<?= $contract['id'] ?>)">
                                    <span class="icon"><i class="fas fa-signature"></i></span>
                                    <span>Sign Contract</span>
                                </button>
                            <?php endif; ?>
                        </footer>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Contract View Modal -->
<div class="modal" id="contract-modal">
    <div class="modal-background"></div>
    <div class="modal-card" style="width: 90%; max-width: 1000px;">
        <header class="modal-card-head">
            <p class="modal-card-title">Communications Services Agreement</p>
            <button class="delete" aria-label="close" onclick="closeModal()"></button>
        </header>
        <section class="modal-card-body">
            <div id="contract-content" style="white-space: pre-wrap; font-family: 'Times New Roman', serif; line-height: 1.6; max-height: 60vh; overflow-y: auto;"></div>
        </section>
        <footer class="modal-card-foot">
            <button class="button" onclick="closeModal()">Close</button>
        </footer>
    </div>
</div>

<!-- Contract Signing Modal -->
<div class="modal" id="signing-modal">
    <div class="modal-background"></div>
    <div class="modal-card">
        <header class="modal-card-head">
            <p class="modal-card-title">Sign Contract</p>
            <button class="delete" aria-label="close" onclick="closeSigningModal()"></button>
        </header>
        <section class="modal-card-body">
            <div class="notification is-warning">
                <p><strong>Electronic Signature Agreement</strong></p>
                <p>By providing your full legal name below and clicking "Sign Contract", you agree to the terms and conditions of this Communications Services Agreement and acknowledge that this constitutes a legally binding electronic signature.</p>
            </div>
            
            <div class="field">
                <label class="label">Full Legal Name</label>
                <div class="control">
                    <input class="input" type="text" id="signature-name" placeholder="Enter your full legal name" required>
                </div>
                <p class="help">This must match the name specified in the contract</p>
            </div>
            
            <div class="field">
                <div class="control">
                    <label class="checkbox">
                        <input type="checkbox" id="signature-confirm" required>
                        I acknowledge that I have read and agree to the terms of this Communications Services Agreement
                    </label>
                </div>
            </div>
        </section>
        <footer class="modal-card-foot">
            <button class="button is-primary" onclick="submitSignature()" id="sign-button" disabled>
                <span class="icon"><i class="fas fa-signature"></i></span>
                <span>Sign Contract</span>
            </button>
            <button class="button" onclick="closeSigningModal()">Cancel</button>
        </footer>
    </div>
</div>

<?php
$content = ob_get_clean();

// Start output buffering for scripts
ob_start();
?>
<script>
let currentContractId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Enable/disable sign button based on form completion
    const nameInput = document.getElementById('signature-name');
    const confirmCheck = document.getElementById('signature-confirm');
    const signButton = document.getElementById('sign-button');
    
    function updateSignButton() {
        const nameValid = nameInput.value.trim().length > 0;
        const confirmed = confirmCheck.checked;
        signButton.disabled = !(nameValid && confirmed);
    }
    
    nameInput.addEventListener('input', updateSignButton);
    confirmCheck.addEventListener('change', updateSignButton);
});

// View contract
async function viewContract(contractId) {
    try {
        const response = await fetch(`api/contracts.php?action=get&id=${contractId}`);
        const data = await response.json();
        
        if (data.success) {
            const content = data.contract.contract_content.replace(/\n/g, '<br>');
            document.getElementById('contract-content').innerHTML = content;
            document.getElementById('contract-modal').classList.add('is-active');
        } else {
            alert('Failed to load contract: ' + data.error);
        }
    } catch (error) {
        console.error('Error loading contract:', error);
        alert('Failed to load contract');
    }
}

// Sign contract
function signContract(contractId) {
    currentContractId = contractId;
    document.getElementById('signature-name').value = '';
    document.getElementById('signature-confirm').checked = false;
    document.getElementById('sign-button').disabled = true;
    document.getElementById('signing-modal').classList.add('is-active');
}

// Submit signature
async function submitSignature() {
    const signatureName = document.getElementById('signature-name').value.trim();
    const confirmed = document.getElementById('signature-confirm').checked;
    
    if (!signatureName || !confirmed) {
        alert('Please complete all required fields');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'sign_contract');
        formData.append('contract_id', currentContractId);
        formData.append('signature_name', signatureName);
        
        const response = await fetch('api/user-contracts.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Contract signed successfully!');
            location.reload();
        } else {
            alert('Failed to sign contract: ' + data.error);
        }
    } catch (error) {
        console.error('Error signing contract:', error);
        alert('Failed to sign contract');
    }
}

// Close modals
function closeModal() {
    document.getElementById('contract-modal').classList.remove('is-active');
}

function closeSigningModal() {
    document.getElementById('signing-modal').classList.remove('is-active');
    currentContractId = null;
}

// Close modal on background click
document.querySelectorAll('.modal-background').forEach(bg => {
    bg.addEventListener('click', function() {
        this.parentElement.classList.remove('is-active');
    });
});
</script>
<?php
$scripts = ob_get_clean();
include 'layout.php';
?>
