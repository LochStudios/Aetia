<?php
// admin/view-user-documents.php - View documents for a specific user (uses API)
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
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

// Get user ID from URL
$userId = intval($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    $_SESSION['error_message'] = 'Invalid user ID.';
    header('Location: users.php');
    exit;
}

$pageTitle = 'User Documents | Aetia Admin';
ob_start();
?>

<!-- Loading State -->
<div id="loading-state" class="content">
    <div class="has-text-centered">
        <span class="icon is-large has-text-info">
            <i class="fas fa-spinner fa-pulse fa-3x"></i>
        </span>
        <h3 class="title is-5 mt-3">Loading Documents...</h3>
    </div>
</div>

<!-- Error State -->
<div id="error-state" class="content" style="display: none;">
    <div class="notification is-danger">
        <span class="icon-text">
            <span class="icon"><i class="fas fa-exclamation-triangle"></i></span>
            <span id="error-message">An error occurred while loading documents.</span>
        </span>
    </div>
    <div class="buttons">
        <a href="users.php" class="button is-light">
            <span class="icon"><i class="fas fa-arrow-left"></i></span>
            <span>Back to Users</span>
        </a>
    </div>
</div>

<!-- Main Content -->
<div id="main-content" class="content" style="display: none;">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li><a href="users.php"><span class="icon is-small"><i class="fas fa-users-cog"></i></span><span>User Management</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Documents</span></a></li>
        </ul>
    </nav>
    
    <div class="level">
        <div class="level-left">
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fas fa-file-contract"></i></span>
                Documents for <span id="username"></span>
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="users.php" class="button is-light is-small">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    <span>Back to Users</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- User Info Card -->
    <div id="user-info-card" class="card mb-5">
        <!-- Will be populated by JavaScript -->
    </div>
    
    <!-- Upload Document Section -->
    <div class="card mb-5">
        <header class="card-header">
            <p class="card-header-title">
                <span class="icon"><i class="fas fa-cloud-upload-alt"></i></span>
                Upload New Document
            </p>
        </header>
        <div class="card-content">
            <form id="upload-form" enctype="multipart/form-data">
                <div class="field">
                    <label class="label">Document Type</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select name="document_type" required>
                                <option value="">Select document type...</option>
                                <option value="contract">Contract</option>
                                <option value="invoice">Invoice</option>
                                <option value="agreement">Agreement</option>
                                <option value="identification">Identification</option>
                                <option value="tax_document">Tax Document</option>
                                <option value="payment_info">Payment Information</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="field">
                    <label class="label">Description</label>
                    <div class="control">
                        <input class="input" type="text" name="description" placeholder="Brief description of the document...">
                    </div>
                </div>
                
                <div class="field">
                    <label class="label">Document File</label>
                    <div class="control">
                        <div class="file has-name is-fullwidth">
                            <label class="file-label">
                                <input class="file-input" type="file" name="document" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                                <span class="file-cta">
                                    <span class="file-icon">
                                        <i class="fas fa-upload"></i>
                                    </span>
                                    <span class="file-label">
                                        Choose a file…
                                    </span>
                                </span>
                                <span class="file-name">
                                    No file selected
                                </span>
                            </label>
                        </div>
                    </div>
                    <p class="help">Supported formats: PDF, DOC, DOCX, JPG, JPEG, PNG (Max 10MB)</p>
                </div>
                
                <div class="field">
                    <div class="control">
                        <button class="button is-primary" type="submit">
                            <span class="icon"><i class="fas fa-upload"></i></span>
                            <span>Upload Document</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Documents List -->
    <div class="card">
        <header class="card-header">
            <p class="card-header-title">
                <span class="icon"><i class="fas fa-file-alt"></i></span>
                Documents (<span id="document-count">0</span>)
            </p>
        </header>
        <div class="card-content">
            <div id="no-documents" class="notification is-info is-light" style="display: none;">
                <span class="icon-text">
                    <span class="icon"><i class="fas fa-info-circle"></i></span>
                    <span>No documents found for this user.</span>
                </span>
            </div>
            
            <div id="documents-table" class="table-container" style="display: none;">
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Uploaded</th>
                            <th>Uploaded By</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="documents-tbody">
                        <!-- Will be populated by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Start output buffering for scripts
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const userId = <?= $userId ?>;
    let documentsData = [];
    
    // Load documents on page load
    loadDocuments();
    
    // File input name display
    const fileInput = document.querySelector('.file-input');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'No file selected';
            const fileNameDisplay = document.querySelector('.file-name');
            if (fileNameDisplay) {
                fileNameDisplay.textContent = fileName;
            }
        });
    }
    
    // Upload form submission
    document.getElementById('upload-form').addEventListener('submit', function(e) {
        e.preventDefault();
        uploadDocument();
    });
    
    // Load documents from API
    async function loadDocuments() {
        try {
            const response = await fetch(`../api/view-documents.php?action=list&user_id=${userId}`);
            const data = await response.json();
            
            if (data.success) {
                documentsData = data.documents;
                displayUserInfo(data.user);
                displayDocuments(data.documents);
                document.getElementById('loading-state').style.display = 'none';
                document.getElementById('main-content').style.display = 'block';
            } else {
                showError(data.error || 'Failed to load documents');
            }
        } catch (error) {
            console.error('Error loading documents:', error);
            showError('Failed to load documents');
        }
    }
    
    // Display user information
    function displayUserInfo(user) {
        document.getElementById('username').textContent = user.username;
        
        const userInfoCard = document.getElementById('user-info-card');
        const profileImageSrc = user.account_type === 'manual' && user.profile_image 
            ? `view-user-profile-image.php?user_id=${user.id}`
            : (user.profile_image || '');
            
        userInfoCard.innerHTML = `
            <div class="card-content">
                <div class="media">
                    <div class="media-left">
                        <figure class="image is-64x64">
                            ${profileImageSrc ? 
                                `<img src="${profileImageSrc}" alt="Profile Picture" style="width:64px;height:64px;border-radius:50%;object-fit:cover;">` :
                                `<div class="has-background-light is-flex is-align-items-center is-justify-content-center" style="width:64px;height:64px;border-radius:50%;">
                                    <span class="icon is-large has-text-grey">
                                        <i class="fas fa-user fa-2x"></i>
                                    </span>
                                </div>`
                            }
                        </figure>
                    </div>
                    <div class="media-content">
                        <p class="title is-4">${user.username}</p>
                        <p class="subtitle is-6">
                            <span class="tag is-${user.account_type === 'manual' ? 'info' : 'link'}">
                                ${user.account_type.charAt(0).toUpperCase() + user.account_type.slice(1)} Account
                            </span>
                            ${user.is_admin == 1 ? '<span class="tag is-info"><span class="icon"><i class="fas fa-crown"></i></span><span>Administrator</span></span>' : ''}
                        </p>
                        <div class="content">
                            <p><strong>Email:</strong> ${user.email}</p>
                            ${user.first_name || user.last_name ? `<p><strong>Name:</strong> ${(user.first_name || '') + ' ' + (user.last_name || '')}</p>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Display documents
    function displayDocuments(documents) {
        const documentCount = document.getElementById('document-count');
        const noDocuments = document.getElementById('no-documents');
        const documentsTable = document.getElementById('documents-table');
        const tbody = document.getElementById('documents-tbody');
        
        documentCount.textContent = documents.length;
        
        if (documents.length === 0) {
            noDocuments.style.display = 'block';
            documentsTable.style.display = 'none';
        } else {
            noDocuments.style.display = 'none';
            documentsTable.style.display = 'block';
            
            tbody.innerHTML = documents.map(doc => {
                const extension = doc.original_filename.split('.').pop().toLowerCase();
                let iconClass, iconColor;
                
                switch (extension) {
                    case 'pdf':
                        iconClass = 'fa-file-pdf';
                        iconColor = 'has-text-danger';
                        break;
                    case 'doc':
                    case 'docx':
                        iconClass = 'fa-file-word';
                        iconColor = 'has-text-info';
                        break;
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                        iconClass = 'fa-file-image';
                        iconColor = 'has-text-success';
                        break;
                    default:
                        iconClass = 'fa-file';
                        iconColor = 'has-text-grey';
                }
                
                return `
                    <tr>
                        <td>
                            <span class="icon-text">
                                <span class="icon">
                                    <i class="fas ${iconClass} ${iconColor}"></i>
                                </span>
                                <span>${doc.original_filename}</span>
                            </span>
                        </td>
                        <td>
                            <span class="tag is-light">
                                ${doc.document_type.charAt(0).toUpperCase() + doc.document_type.slice(1).replace('_', ' ')}
                            </span>
                        </td>
                        <td>${doc.description || 'No description'}</td>
                        <td>${new Date(doc.uploaded_at).toLocaleDateString()}</td>
                        <td>${doc.uploaded_by_username || 'Unknown'}</td>
                        <td>${(doc.file_size / 1024).toFixed(1)} KB</td>
                        <td>
                            <div class="buttons">
                                <a href="../api/download-document.php?id=${doc.id}" class="button is-small is-info">
                                    <span class="icon"><i class="fas fa-download"></i></span>
                                    <span>Download</span>
                                </a>
                                <button class="button is-small is-danger" onclick="deleteDocument(${doc.id})">
                                    <span class="icon"><i class="fas fa-trash"></i></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    }
    
    // Upload document
    async function uploadDocument() {
        const form = document.getElementById('upload-form');
        const formData = new FormData(form);
        formData.append('user_id', userId);
        
        try {
            const response = await fetch(`../api/view-documents.php?action=upload&user_id=${userId}`, {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#48c78e'
                }).then(() => {
                    form.reset();
                    document.querySelector('.file-name').textContent = 'No file selected';
                    loadDocuments(); // Reload documents
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.error,
                    icon: 'error',
                    confirmButtonColor: '#f14668'
                });
            }
        } catch (error) {
            console.error('Upload error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'Failed to upload document',
                icon: 'error',
                confirmButtonColor: '#f14668'
            });
        }
    }
    
    // Delete document
    window.deleteDocument = async function(documentId) {
        const result = await Swal.fire({
            title: 'Delete Document?',
            text: 'Are you sure you want to delete this document? This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f14668',
            cancelButtonColor: '#dbdbdb',
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        });
        
        if (result.isConfirmed) {
            try {
                const formData = new FormData();
                formData.append('document_id', documentId);
                
                const response = await fetch(`../api/view-documents.php?action=delete&user_id=${userId}`, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'Deleted!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#48c78e'
                    }).then(() => {
                        loadDocuments(); // Reload documents
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.error,
                        icon: 'error',
                        confirmButtonColor: '#f14668'
                    });
                }
            } catch (error) {
                console.error('Delete error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to delete document',
                    icon: 'error',
                    confirmButtonColor: '#f14668'
                });
            }
        }
    };
    
    // Show error state
    function showError(message) {
        document.getElementById('loading-state').style.display = 'none';
        document.getElementById('error-message').textContent = message;
        document.getElementById('error-state').style.display = 'block';
    }
});
</script>
<?php
$scripts = ob_get_clean();
include '../layout.php';
?>
