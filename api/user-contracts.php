<?php
// api/user-contracts.php - User contract API (for signing contracts)
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

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'sign_contract':
                $contractId = intval($_POST['contract_id'] ?? 0);
                $signatureName = trim($_POST['signature_name'] ?? '');
                
                if ($contractId <= 0 || empty($signatureName)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid contract data']);
                    exit;
                }
                
                // Verify contract belongs to user and is in 'sent' status
                $contract = $contractService->getContract($contractId);
                if (!$contract) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Contract not found']);
                    exit;
                }
                
                if ($contract['user_id'] != $_SESSION['user_id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Access denied']);
                    exit;
                }
                
                if ($contract['contract_status'] !== 'sent') {
                    http_response_code(400);
                    echo json_encode(['error' => 'Contract is not available for signing']);
                    exit;
                }
                
                // Update contract status to signed
                $result = $contractService->updateContractStatus($contractId, 'signed', $signatureName);
                
                if ($result['success']) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Contract signed successfully'
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode([
                        'error' => $result['message']
                    ]);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    error_log("User contract API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
