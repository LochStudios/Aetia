<?php
// api/users.php - User management API
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

$userModel = new User();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin privileges required']);
    exit;
}

// Get request parameters
$action = $_GET['action'] ?? 'get';

try {
    switch ($action) {
        case 'get':
            // Get single user
            $userId = intval($_GET['id'] ?? 0);
            if ($userId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid user ID']);
                exit;
            }
            
            $user = $userModel->getUserById($userId);
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'user' => $user
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'User not found']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Users API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
