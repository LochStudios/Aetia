<?php
// documents.php - User interface for viewing their own documents (uses API)
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'My Documents | Aetia';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>My Documents</span></a></li>
        </ul>
    </nav>
</div>

<!-- Loading State -->
<div id="loading-state" class="content">
    <div class="has-text-centered">
        <span class="icon is-large has-text-white">
            <i class="fas fa-spinner fa-pulse fa-3x"></i>
        </span>
        <h3 class="title is-5 mt-3">Loading Your Documents...</h3>
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
        <a href="index.php" class="button">
            <span class="icon"><i class="fas fa-home"></i></span>
            <span>Back to Home</span>
        </a>
    </div>
</div>

<!-- Main Content -->
<div id="main-content" class="content" style="display: none;">
    <div class="level">
        <div class="level-left">
            <h2 class="title is-2 has-text-white">
                <span class="icon"><i class="fas fa-file-contract"></i></span>
                My Documents
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="profile.php" class="button is-small">
                    <span class="icon"><i class="fas fa-user"></i></span>
                    <span>My Profile</span>
                </a>
                <a href="messages.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>Messages</span>
                </a>
            </div>
        </div>
    </div>
    
    <!-- User Info -->
    <div id="user-welcome" class="notification is-info mb-5">
        <!-- Will be populated by JavaScript -->
    </div>
    
    <!-- Document Statistics -->
    <div id="document-stats" class="columns is-multiline mb-5" style="display: none;">
        <!-- Will be populated by JavaScript -->
    </div>
    
    <!-- Documents Section -->
    <div class="card">
        <header class="card-header">
            <p class="card-header-title">
                <span class="icon"><i class="fas fa-file-alt"></i></span>
                My Documents
                <span id="document-count-badge" class="tag is-info ml-2" style="display: none;">0</span>
            </p>
        </header>
        <div class="card-content">
            <div id="no-documents" class="notification is-info" style="display: none;">
                <div class="has-text-centered">
                    <span class="icon is-large has-text-black">
                        <i class="fa-solid fa-folder-open fa-3x"></i>
                    </span>
                    <h3 class="title is-5 mt-3">No Documents Yet</h3>
                    <p class="subtitle is-6">
                        You don't have any documents uploaded to your account yet.<br>
                        Documents such as contracts, invoices, and agreements will appear here once they are uploaded by our team.
                    </p>
                    <p class="content">
                        <strong>Need a document uploaded?</strong><br>
                        Contact our team through the <a href="messages.php">messages system</a> or reach out to your account manager.
                    </p>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div id="filter-tabs" class="tabs is-boxed mb-4" style="display: none;">
                <ul>
                    <li class="is-active" data-filter="all">
                        <a>
                            <span class="icon is-small"><i class="fas fa-list"></i></span>
                            <span>All Documents</span>
                        </a>
                    </li>
                    <li data-filter="contract">
                        <a>
                            <span class="icon is-small"><i class="fas fa-file-contract"></i></span>
                            <span>Contracts</span>
                        </a>
                    </li>
                    <li data-filter="invoice">
                        <a>
                            <span class="icon is-small"><i class="fas fa-file-invoice"></i></span>
                            <span>Invoices</span>
                        </a>
                    </li>
                    <li data-filter="agreement">
                        <a>
                            <span class="icon is-small"><i class="fas fa-handshake"></i></span>
                            <span>Agreements</span>
                        </a>
                    </li>
                    <li data-filter="other">
                        <a>
                            <span class="icon is-small"><i class="fas fa-folder"></i></span>
                            <span>Other</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Documents Grid -->
            <div id="documents-container" class="columns is-multiline" style="display: none;">
                <!-- Will be populated by JavaScript -->
            </div>
            
            <!-- No documents message for filtered views -->
            <div id="no-filtered-documents" class="notification is-info" style="display: none;">
                <div class="has-text-centered">
                    <span class="icon is-large has-text-black">
                        <i class="fas fa-search fa-2x"></i>
                    </span>
                    <h3 class="title is-5 mt-3">No Documents Found</h3>
                    <p class="subtitle is-6">No documents match the selected filter.</p>
                </div>
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
    let documentsData = [];
    
    // Load documents on page load
    loadDocuments();
    
    // Load documents from API
    async function loadDocuments() {
        try {
            const response = await fetch('api/view-documents.php?action=list');
            const data = await response.json();
            
            if (data.success) {
                documentsData = data.documents;
                displayUserWelcome(data.user);
                displayDocumentStats(data.stats);
                displayDocuments(data.documents);
                setupFilters();
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
    
    // Display user welcome message
    function displayUserWelcome(user) {
        const userWelcome = document.getElementById('user-welcome');
        const profileImageSrc = user.account_type === 'manual' && user.profile_image 
            ? `api/view-profile-image.php?user_id=${user.id}`
            : (user.profile_image || '');
            
        userWelcome.innerHTML = `
            <div class="media">
                <div class="media-left">
                    <figure class="image is-48x48">
                        ${profileImageSrc ? 
                            `<img src="${profileImageSrc}" alt="Profile Picture" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">` :
                            `<div class="has-background-light is-flex is-align-items-center is-justify-content-center" style="width:48px;height:48px;border-radius:50%;">
                                <span class="icon has-text-grey">
                                    <i class="fas fa-user"></i>
                                </span>
                            </div>`
                        }
                    </figure>
                </div>
                <div class="media-content">
                    <p class="title is-5">Welcome, ${user.username}!</p>
                    <p class="subtitle is-6">Here you can view all documents that have been uploaded to your account.<br>
                        These may include contracts, invoices, agreements, and other important files related to your work with Aetia Talent Agency.</p>
                </div>
            </div>
        `;
    }
    
    // Display document statistics
    function displayDocumentStats(stats) {
        if (stats.total === 0) return;
        
        const documentStats = document.getElementById('document-stats');
        documentStats.style.display = 'flex';
        documentStats.innerHTML = `
            <div class="column is-3">
                <div class="box has-text-centered">
                    <p class="title is-4 has-text-white">${stats.total}</p>
                    <p class="subtitle is-6">Total Documents</p>
                </div>
            </div>
            <div class="column is-3">
                <div class="box has-text-centered">
                    <p class="title is-4 has-text-success">${stats.contracts}</p>
                    <p class="subtitle is-6">Contracts</p>
                </div>
            </div>
            <div class="column is-3">
                <div class="box has-text-centered">
                    <p class="title is-4 has-text-warning">${stats.invoices}</p>
                    <p class="subtitle is-6">Invoices</p>
                </div>
            </div>
            <div class="column is-3">
                <div class="box has-text-centered">
                    <p class="title is-4 has-text-primary">${stats.other}</p>
                    <p class="subtitle is-6">Other</p>
                </div>
            </div>
        `;
    }
    
    // Display documents
    function displayDocuments(documents) {
        const documentCountBadge = document.getElementById('document-count-badge');
        const noDocuments = document.getElementById('no-documents');
        const filterTabs = document.getElementById('filter-tabs');
        const documentsContainer = document.getElementById('documents-container');
        
        if (documents.length === 0) {
            noDocuments.style.display = 'block';
            filterTabs.style.display = 'none';
            documentsContainer.style.display = 'none';
            documentCountBadge.style.display = 'none';
        } else {
            noDocuments.style.display = 'none';
            filterTabs.style.display = 'block';
            documentsContainer.style.display = 'flex';
            documentCountBadge.style.display = 'inline';
            documentCountBadge.textContent = documents.length;
            
            renderDocuments(documents);
        }
    }
    
    // Render documents in grid
    function renderDocuments(documents) {
        const documentsContainer = document.getElementById('documents-container');
        
        documentsContainer.innerHTML = documents.map(doc => {
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
                    iconColor = 'has-text-black';
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
            const tagColor = doc.document_type === 'contract' ? 'success' : 
                            (doc.document_type === 'invoice' ? 'warning' : 
                            (doc.document_type === 'agreement' ? 'info' : 
                            (doc.document_type === 'identification' ? 'link' :
                            (doc.document_type === 'tax_document' ? 'primary' :
                            (doc.document_type === 'payment_info' ? 'dark' : 'info')))));
            const archivedTag = doc.archived ? 
                `<span class="tag is-dark">
                    <span class="icon"><i class="fas fa-archive"></i></span>
                    <span>Archived</span>
                </span>` : '';
            const archivedClass = doc.archived ? 'has-background-grey-lighter' : '';
            const previewButton = ['jpg', 'jpeg', 'png'].includes(extension) ? 
                `<div class="control">
                    <button class="button is-info preview-btn" data-id="${doc.id}" data-filename="${doc.original_filename}">
                        <span class="icon"><i class="fas fa-eye"></i></span>
                        <span>Preview</span>
                    </button>
                </div>` : '';
            
            return `
                <div class="column is-half document-item" data-type="${doc.document_type}">
                    <div class="card ${archivedClass}">
                        <div class="card-content">
                            <div class="media">
                                <div class="media-left">
                                    <span class="icon is-large">
                                        <i class="fas ${iconClass} fa-2x ${iconColor}"></i>
                                    </span>
                                </div>
                                <div class="media-content">
                                    <p class="title is-5">${doc.original_filename}</p>
                                    <p class="subtitle is-6">
                                        <span class="tag is-${tagColor}">
                                            ${doc.document_type.charAt(0).toUpperCase() + doc.document_type.slice(1).replace('_', ' ')}
                                        </span>
                                        <span class="tag is-dark">
                                            ${(doc.file_size / 1024).toFixed(1)} KB
                                        </span>
                                        ${archivedTag}
                                    </p>
                                </div>
                            </div>
                            
                            <div class="content">
                                ${doc.description ? `<p><strong>Description:</strong> ${doc.description}</p>` : ''}
                                ${doc.archived && doc.archived_reason ? `<p><strong>Archive Reason:</strong> ${doc.archived_reason}</p>` : ''}
                                <p><strong>Uploaded:</strong> ${new Date(doc.uploaded_at).toLocaleDateString()}</p>
                                ${doc.uploaded_by_username ? `<p><strong>Uploaded by:</strong> ${doc.uploaded_by_username}</p>` : ''}
                            </div>
                            
                            <div class="field is-grouped">
                                <div class="control">
                                    <a href="api/download-document.php?id=${doc.id}" class="button is-primary">
                                        <span class="icon"><i class="fas fa-download"></i></span>
                                        <span>Download</span>
                                    </a>
                                </div>
                                ${previewButton}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Setup preview buttons
        setupPreviewButtons();
    }
    
    // Setup filter functionality
    function setupFilters() {
        const filterTabs = document.querySelectorAll('.tabs li');
        
        filterTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all tabs
                filterTabs.forEach(t => t.classList.remove('is-active'));
                
                // Add active class to clicked tab
                this.classList.add('is-active');
                
                // Get filter type
                const filter = this.getAttribute('data-filter');
                
                // Filter documents
                let filteredDocuments = documentsData;
                
                if (filter !== 'all') {
                    if (filter === 'other') {
                        filteredDocuments = documentsData.filter(doc => 
                            !['contract', 'invoice', 'agreement'].includes(doc.document_type)
                        );
                    } else {
                        filteredDocuments = documentsData.filter(doc => 
                            doc.document_type === filter
                        );
                    }
                }
                
                // Show/hide filtered documents
                if (filteredDocuments.length === 0) {
                    document.getElementById('documents-container').style.display = 'none';
                    document.getElementById('no-filtered-documents').style.display = 'block';
                } else {
                    document.getElementById('documents-container').style.display = 'flex';
                    document.getElementById('no-filtered-documents').style.display = 'none';
                    renderDocuments(filteredDocuments);
                }
            });
        });
    }
    
    // Setup preview buttons
    function setupPreviewButtons() {
        const previewButtons = document.querySelectorAll('.preview-btn');
        previewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const documentId = this.getAttribute('data-id');
                const filename = this.getAttribute('data-filename');
                
                // Create modal for image preview
                const modal = document.createElement('div');
                modal.className = 'modal is-active';
                modal.innerHTML = `
                    <div class="modal-background"></div>
                    <div class="modal-content">
                        <p class="image">
                            <img src="api/download-document.php?id=${documentId}&preview=1" alt="${filename}">
                        </p>
                    </div>
                    <button class="modal-close is-large" aria-label="close"></button>
                `;
                
                document.body.appendChild(modal);
                
                // Close modal functionality
                const closeModal = () => {
                    document.body.removeChild(modal);
                };
                
                modal.querySelector('.modal-background').addEventListener('click', closeModal);
                modal.querySelector('.modal-close').addEventListener('click', closeModal);
                
                // Close on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeModal();
                    }
                }, { once: true });
            });
        });
    }
    
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
include 'layout.php';
?>
