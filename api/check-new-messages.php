<?php
// api/check-new-messages.php - API endpoint to check for new messages and discussion items
session_start();

// Set JSON headers
header('Content-Type: application/json');

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../includes/timezone.php';
require_once __DIR__ . '/../includes/FileUploader.php';
require_once __DIR__ . '/../models/User.php';

$messageModel = new Message();
$userId = $_SESSION['user_id'];

// Block suspended users from accessing message APIs
$userModel = new User();
$currentUser = $userModel->getUserById($userId);
if ($currentUser && !empty($currentUser['is_suspended'])) {
    echo json_encode(['error' => 'Access denied: account suspended']);
    exit;
}

// Get parameters
$messageId = isset($_GET['message_id']) ? intval($_GET['message_id']) : 0;
$lastCheckTime = isset($_GET['last_check']) ? $_GET['last_check'] : null;

if ($messageId <= 0) {
    echo json_encode(['error' => 'Invalid message ID']);
    exit;
}

// Verify user has access to this message
$currentMessage = $messageModel->getMessage($messageId, $userId);
if (!$currentMessage) {
    echo json_encode(['error' => 'Message not found or access denied']);
    exit;
}

try {
    // Convert last check time to database format
    $lastCheckDateTime = null;
    if ($lastCheckTime) {
        $lastCheckDateTime = date('Y-m-d H:i:s', strtotime($lastCheckTime));
    }
    
    // Get new discussion items (comments and images) since last check
    $newItems = [];
    $newAttachments = [];
    if ($lastCheckDateTime) {
        $newItems = $messageModel->getNewDiscussionItems($messageId, $lastCheckDateTime);
        // Get new message attachments (non-image files uploaded to the main message)
        $newAttachments = $messageModel->getNewMessageAttachments($messageId, $lastCheckDateTime);
    }
    
    // Get current message status in case it changed
    $messageStatus = $messageModel->getMessageStatus($messageId);
    
    // Format the response
    $response = [
        'success' => true,
        'message_id' => $messageId,
        'message_status' => $messageStatus,
        'new_items' => [],
        'new_attachments' => [],
        'has_new_items' => count($newItems) > 0,
        'has_new_attachments' => count($newAttachments) > 0,
        'last_check' => date('c'), // ISO 8601 format
        'server_time' => date('Y-m-d H:i:s')
    ];
    
    // Format new items for display
    foreach ($newItems as $item) {
        $formattedItem = [
            'type' => $item['type'],
            'id' => $item['id'],
            'is_admin_comment' => (bool)$item['is_admin_comment'],
            'created_at' => $item['created_at'],
            'display_name' => $item['display_name'],
            'profile_image' => $item['profile_image'],
            'formatted_date' => formatDateForUser($item['created_at'])
        ];
        
        if ($item['type'] === 'comment') {
            $formattedItem['comment'] = $item['comment'];
        } elseif ($item['type'] === 'image') {
            $formattedItem['attachment_id'] = $item['attachment_id'];
            $formattedItem['original_filename'] = $item['original_filename'];
            $formattedItem['file_size'] = $item['file_size'];
            $formattedItem['formatted_file_size'] = FileUploader::formatFileSize($item['file_size']);
        }
        
        $response['new_items'][] = $formattedItem;
    }
    
    // Format new attachments for display
    foreach ($newAttachments as $attachment) {
        $formattedAttachment = [
            'id' => $attachment['id'],
            'original_filename' => $attachment['original_filename'],
            'file_size' => $attachment['file_size'],
            'mime_type' => $attachment['mime_type'],
            'uploaded_at' => $attachment['uploaded_at'],
            'formatted_file_size' => FileUploader::formatFileSize($attachment['file_size']),
            'formatted_date' => formatDateForUser($attachment['uploaded_at']),
            'is_image' => strpos($attachment['mime_type'], 'image/') === 0
        ];
        
        $response['new_attachments'][] = $formattedAttachment;
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Check new messages error: " . $e->getMessage());
    echo json_encode(['error' => 'Server error occurred']);
}
?>
