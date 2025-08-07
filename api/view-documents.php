<?php
// api/view-documents.php - Unified document viewing API
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/DocumentService.php';

$userModel = new User();
$documentService = new DocumentService();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

// Get request parameters
$action = $_GET['action'] ?? 'list';
$userId = intval($_GET['user_id'] ?? $_SESSION['user_id']);

// Security check: Non-admin users can only view their own documents
if (!$isAdmin && $userId !== $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            // Get documents for the specified user
            $documents = $documentService->getUserDocuments($userId);
            
            // Get user info if admin is viewing another user's documents
            $userInfo = null;
            if ($isAdmin && $userId !== $_SESSION['user_id']) {
                $userInfo = $userModel->getUserWithAdminStatus($userId);
                if (!$userInfo) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
            } else {
                $userInfo = $userModel->getUserWithAdminStatus($_SESSION['user_id']);
            }
            
            // Calculate statistics
            $stats = [
                'total' => count($documents),
                'contracts' => count(array_filter($documents, fn($d) => $d['document_type'] === 'contract')),
                'invoices' => count(array_filter($documents, fn($d) => $d['document_type'] === 'invoice')),
                'agreements' => count(array_filter($documents, fn($d) => $d['document_type'] === 'agreement')),
                'other' => count(array_filter($documents, fn($d) => !in_array($d['document_type'], ['contract', 'invoice', 'agreement'])))
            ];
            
            echo json_encode([
                'success' => true,
                'documents' => $documents,
                'user' => $userInfo,
                'stats' => $stats,
                'is_admin' => $isAdmin,
                'viewing_own' => $userId === $_SESSION['user_id']
            ]);
            break;
            
        case 'upload':
            // Handle document upload (admin only)
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Upload requires admin privileges']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded or upload error']);
                exit;
            }
            
            $documentType = trim($_POST['document_type'] ?? 'general');
            $description = trim($_POST['description'] ?? '');
            
            $result = $documentService->uploadUserDocument($userId, $_FILES['document'], $documentType, $description, $_SESSION['user_id']);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message']
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'error' => $result['message']
                ]);
            }
            break;
            
        case 'delete':
            // Handle document deletion (admin only)
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['error' => 'Delete requires admin privileges']);
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                exit;
            }
            
            $documentId = intval($_POST['document_id'] ?? 0);
            if ($documentId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid document ID']);
                exit;
            }
            
            if ($documentService->deleteUserDocument($documentId, $_SESSION['user_id'])) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Document deleted successfully'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Failed to delete document'
                ]);
            }
            break;
            
        case 'stats':
            // Get document statistics
            $documents = $documentService->getUserDocuments($userId);
            
            $typeStats = [];
            $monthlyStats = [];
            
            foreach ($documents as $document) {
                // Type statistics
                $type = $document['document_type'];
                $typeStats[$type] = ($typeStats[$type] ?? 0) + 1;
                
                // Monthly statistics
                $month = date('Y-m', strtotime($document['uploaded_at']));
                $monthlyStats[$month] = ($monthlyStats[$month] ?? 0) + 1;
            }
            
            echo json_encode([
                'success' => true,
                'total_documents' => count($documents),
                'by_type' => $typeStats,
                'by_month' => $monthlyStats,
                'total_size' => array_sum(array_column($documents, 'file_size'))
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Document API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
