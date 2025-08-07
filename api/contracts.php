<?php
// api/contracts.php - Contract management API
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
require_once __DIR__ . '/../services/ContractService.php';

$userModel = new User();
$contractService = new ContractService();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin privileges required']);
    exit;
}

// Get request parameters
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Get all contracts
            $contracts = $contractService->getAllContracts();
            echo json_encode([
                'success' => true,
                'contracts' => $contracts
            ]);
            break;
            
        case 'get':
            // Get single contract
            $contractId = intval($_GET['id'] ?? 0);
            if ($contractId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid contract ID']);
                exit;
            }
            
            $contract = $contractService->getContract($contractId);
            if (!$contract) {
                http_response_code(404);
                echo json_encode(['error' => 'Contract not found']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'contract' => $contract
            ]);
            break;
            
        case 'user_contracts':
            // Get contracts for specific user
            $userId = intval($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            
            $contracts = $contractService->getUserContracts($userId);
            echo json_encode([
                'success' => true,
                'contracts' => $contracts
            ]);
            break;
            
        case 'template':
            // Get default contract template
            $template = $contractService->getDefaultContractTemplate();
            echo json_encode([
                'success' => true,
                'template' => $template
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Contract API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
