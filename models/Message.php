<?php
// models/Message.php - Message model for handling user messages and comments

require_once __DIR__ . '/../config/database.php';

class Message {
    private $database;
    private $mysqli;
    
    public function __construct() {
        $this->database = new Database();
        $this->mysqli = $this->database->getConnection();
    }
    
    // Ensure database connection is active
    private function ensureConnection() {
        if (!$this->mysqli || $this->mysqli->ping() === false) {
            $this->database = new Database();
            $this->mysqli = $this->database->getConnection();
        }
    }
    
    // Create a new message
    public function createMessage($userId, $subject, $message, $createdBy, $priority = 'normal', $tags = null) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                INSERT INTO messages (user_id, subject, message, priority, created_by, tags) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->bind_param("isssis", $userId, $subject, $message, $priority, $createdBy, $tags);
            $result = $stmt->execute();
            
            if ($result) {
                $messageId = $this->mysqli->insert_id;
                $stmt->close();
                return ['success' => true, 'message_id' => $messageId];
            } else {
                $stmt->close();
                return ['success' => false, 'message' => 'Failed to create message'];
            }
        } catch (Exception $e) {
            error_log("Create message error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Get messages for a specific user
    public function getUserMessages($userId, $limit = 20, $offset = 0, $tagFilter = null, $priorityFilter = null) {
        try {
            $this->ensureConnection();
            
            $baseQuery = "
                SELECT m.id, m.subject, m.message, m.priority, m.status, m.tags, m.created_at, m.updated_at,
                       u.username as created_by_username,
                       (SELECT COUNT(*) FROM message_comments mc WHERE mc.message_id = m.id) as comment_count
                FROM messages m
                LEFT JOIN users u ON m.created_by = u.id
                WHERE (m.user_id = ? OR m.created_by = ?)";
            
            $params = [$userId, $userId];
            $types = "ii";
            
            if ($tagFilter) {
                $baseQuery .= " AND m.tags LIKE ?";
                $params[] = "%{$tagFilter}%";
                $types .= "s";
            }
            
            if ($priorityFilter) {
                $baseQuery .= " AND m.priority = ?";
                $params[] = $priorityFilter;
                $types .= "s";
            }
            
            $baseQuery .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $this->mysqli->prepare($baseQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            
            $stmt->close();
            return $messages;
        } catch (Exception $e) {
            error_log("Get user messages error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get a specific message with details
    public function getMessage($messageId, $userId = null) {
        try {
            $this->ensureConnection();
            
            $query = "
                SELECT m.id, m.user_id, m.subject, m.message, m.priority, m.status, m.tags, m.created_at, m.updated_at,
                       u.username as created_by_username, target.username as target_username
                FROM messages m
                LEFT JOIN users u ON m.created_by = u.id
                LEFT JOIN users target ON m.user_id = target.id
                WHERE m.id = ?
            ";
            
            if ($userId !== null) {
                $query .= " AND m.user_id = ?";
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param("ii", $messageId, $userId);
            } else {
                $stmt = $this->mysqli->prepare($query);
                $stmt->bind_param("i", $messageId);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            $message = $result->fetch_assoc();
            $stmt->close();
            
            return $message;
        } catch (Exception $e) {
            error_log("Get message error: " . $e->getMessage());
            return null;
        }
    }
    
    // Get comments for a message
    public function getMessageComments($messageId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT mc.id, mc.comment, mc.is_admin_comment, mc.created_at,
                       u.username, u.profile_image
                FROM message_comments mc
                LEFT JOIN users u ON mc.user_id = u.id
                WHERE mc.message_id = ?
                ORDER BY mc.created_at ASC
            ");
            
            $stmt->bind_param("i", $messageId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $comments = [];
            while ($row = $result->fetch_assoc()) {
                $comments[] = $row;
            }
            
            $stmt->close();
            return $comments;
        } catch (Exception $e) {
            error_log("Get message comments error: " . $e->getMessage());
            return [];
        }
    }
    
    // Add a comment to a message
    public function addComment($messageId, $userId, $comment, $isAdminComment = false) {
        try {
            $this->ensureConnection();
            
            // Start transaction
            $this->mysqli->begin_transaction();
            
            // Insert comment
            $stmt = $this->mysqli->prepare("
                INSERT INTO message_comments (message_id, user_id, comment, is_admin_comment) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->bind_param("iisi", $messageId, $userId, $comment, $isAdminComment);
            $commentResult = $stmt->execute();
            $stmt->close();
            
            if ($commentResult) {
                // Update message status
                $newStatus = $isAdminComment ? 'responded' : 'read';
                $updateStmt = $this->mysqli->prepare("
                    UPDATE messages 
                    SET status = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $updateStmt->bind_param("si", $newStatus, $messageId);
                $updateResult = $updateStmt->execute();
                $updateStmt->close();
                
                if ($updateResult) {
                    $this->mysqli->commit();
                    return ['success' => true];
                } else {
                    $this->mysqli->rollback();
                    return ['success' => false, 'message' => 'Failed to update message status'];
                }
            } else {
                $this->mysqli->rollback();
                return ['success' => false, 'message' => 'Failed to add comment'];
            }
        } catch (Exception $e) {
            $this->mysqli->rollback();
            error_log("Add comment error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    // Update message status
    public function updateMessageStatus($messageId, $status, $userId = null) {
        try {
            $this->ensureConnection();
            
            $query = "UPDATE messages SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $params = [$status, $messageId];
            $types = "si";
            
            if ($userId !== null) {
                $query .= " AND user_id = ?";
                $params[] = $userId;
                $types .= "i";
            }
            
            $stmt = $this->mysqli->prepare($query);
            $stmt->bind_param($types, ...$params);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Update message status error: " . $e->getMessage());
            return false;
        }
    }
    
    // Get all messages for admin view
    public function getAllMessages($limit = 50, $offset = 0, $status = null, $tagFilter = null) {
        try {
            $this->ensureConnection();
            
            $query = "
                SELECT m.id, m.subject, m.priority, m.status, m.tags, m.created_at, m.updated_at,
                       u.username as target_username, creator.username as created_by_username,
                       (SELECT COUNT(*) FROM message_comments mc WHERE mc.message_id = m.id) as comment_count
                FROM messages m
                LEFT JOIN users u ON m.user_id = u.id
                LEFT JOIN users creator ON m.created_by = creator.id
            ";
            
            $params = [];
            $types = "";
            $conditions = [];
            
            if ($status !== null) {
                $conditions[] = "m.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if ($tagFilter !== null) {
                $conditions[] = "m.tags LIKE ?";
                $params[] = "%{$tagFilter}%";
                $types .= "s";
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $this->mysqli->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }
            
            $stmt->close();
            return $messages;
        } catch (Exception $e) {
            error_log("Get all messages error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get message counts by status for a user
    public function getUserMessageCounts($userId) {
        try {
            $this->ensureConnection();
            
            $stmt = $this->mysqli->prepare("
                SELECT status, COUNT(*) as count
                FROM messages 
                WHERE user_id = ? OR created_by = ?
                GROUP BY status
            ");
            
            $stmt->bind_param("ii", $userId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $counts = [
                'unread' => 0,
                'read' => 0,
                'responded' => 0,
                'closed' => 0
            ];
            
            while ($row = $result->fetch_assoc()) {
                $counts[$row['status']] = (int)$row['count'];
            }
            
            $stmt->close();
            return $counts;
        } catch (Exception $e) {
            error_log("Get user message counts error: " . $e->getMessage());
            return ['unread' => 0, 'read' => 0, 'responded' => 0, 'closed' => 0];
        }
    }
    
    // Search users for admin message creation
    public function searchUsers($query, $limit = 10) {
        try {
            $this->ensureConnection();
            
            $searchTerm = '%' . $query . '%';
            $stmt = $this->mysqli->prepare("
                SELECT id, username, email, first_name, last_name, account_type
                FROM users 
                WHERE (username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
                AND is_active = 1
                ORDER BY username ASC
                LIMIT ?
            ");
            
            $stmt->bind_param("ssssi", $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $users = [];
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            
            $stmt->close();
            return $users;
        } catch (Exception $e) {
            error_log("Search users error: " . $e->getMessage());
            return [];
        }
    }
    
    // Get all unique tags used in messages
    public function getAvailableTags($userId = null) {
        try {
            $this->ensureConnection();
            
            $baseQuery = "SELECT DISTINCT tags FROM messages WHERE tags IS NOT NULL AND tags != ''";
            $params = [];
            $types = "";
            
            if ($userId) {
                $baseQuery .= " AND user_id = ?";
                $params[] = $userId;
                $types = "i";
            }
            
            $baseQuery .= " ORDER BY tags ASC";
            
            if ($types) {
                $stmt = $this->mysqli->prepare($baseQuery);
                $stmt->bind_param($types, ...$params);
            } else {
                $stmt = $this->mysqli->prepare($baseQuery);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tags = [];
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['tags'])) {
                    // Split comma-separated tags and add to array
                    $splitTags = array_map('trim', explode(',', $row['tags']));
                    foreach ($splitTags as $tag) {
                        if (!empty($tag) && !in_array($tag, $tags)) {
                            $tags[] = $tag;
                        }
                    }
                }
            }
            
            $stmt->close();
            sort($tags); // Sort alphabetically
            return $tags;
        } catch (Exception $e) {
            error_log("Get available tags error: " . $e->getMessage());
            return [];
        }
    }
}
?>
