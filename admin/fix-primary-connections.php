<?php
// admin/fix-primary-connections.php - Fix users with multiple primary social connections
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
    http_response_code(403);
    echo '<h1>Access Denied</h1><p>Admin privileges required</p>';
    exit;
}

$messages = [];
$fix_mode = isset($_POST['fix_issues']) && $_POST['fix_issues'] === 'yes';

try {
    // Get all users with multiple primary social connections
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $mysqli = $database->getConnection();
    
    // Find users with multiple primary connections
    $query = "
        SELECT user_id, COUNT(*) as primary_count,
               GROUP_CONCAT(platform ORDER BY created_at ASC) as platforms,
               GROUP_CONCAT(CONCAT(platform, ':', created_at) ORDER BY created_at ASC) as platform_dates
        FROM social_connections 
        WHERE is_primary = 1 
        GROUP BY user_id 
        HAVING COUNT(*) > 1
        ORDER BY user_id
    ";
    
    $result = $mysqli->query($query);
    $issues = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $issues[] = $row;
        }
    }
    
    if (empty($issues)) {
        $messages[] = "✅ No users found with multiple primary social connections.";
    } else {
        $messages[] = "⚠️ Found " . count($issues) . " user(s) with multiple primary connections:";
        
        foreach ($issues as $issue) {
            $user = $userModel->getUserById($issue['user_id']);
            $username = $user ? $user['username'] : 'Unknown';
            $messages[] = "   - User ID {$issue['user_id']} ({$username}): {$issue['primary_count']} primary connections [{$issue['platforms']}]";
            
            if ($fix_mode) {
                // Fix this user by keeping the oldest connection as primary
                $platformDates = explode(',', $issue['platform_dates']);
                $oldestPlatform = explode(':', $platformDates[0])[0]; // Get the platform from oldest entry
                
                // Start transaction
                $mysqli->begin_transaction();
                
                try {
                    // Set all connections to non-primary
                    $stmt = $mysqli->prepare("UPDATE social_connections SET is_primary = 0 WHERE user_id = ?");
                    $stmt->bind_param("i", $issue['user_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Set the oldest connection as primary
                    $stmt = $mysqli->prepare("UPDATE social_connections SET is_primary = 1 WHERE user_id = ? AND platform = ?");
                    $stmt->bind_param("is", $issue['user_id'], $oldestPlatform);
                    $stmt->execute();
                    $affected = $mysqli->affected_rows;
                    $stmt->close();
                    
                    if ($affected > 0) {
                        $mysqli->commit();
                        $messages[] = "   ✅ Fixed: Set {$oldestPlatform} as primary for user {$username}";
                    } else {
                        $mysqli->rollback();
                        $messages[] = "   ❌ Failed to fix user {$username}";
                    }
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $messages[] = "   ❌ Error fixing user {$username}: " . $e->getMessage();
                }
            }
        }
        
        if (!$fix_mode) {
            $messages[] = "";
            $messages[] = "To fix these issues, click the 'Fix Issues' button below.";
        }
    }
    
    // Also check for users with no primary connections
    $query2 = "
        SELECT DISTINCT sc.user_id, u.username
        FROM social_connections sc
        JOIN users u ON sc.user_id = u.id
        WHERE sc.user_id NOT IN (
            SELECT user_id FROM social_connections WHERE is_primary = 1
        )
        AND u.account_type != 'manual'
        ORDER BY sc.user_id
    ";
    
    $result2 = $mysqli->query($query2);
    $no_primary_issues = [];
    
    if ($result2 && $result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            $no_primary_issues[] = $row;
        }
    }
    
    if (!empty($no_primary_issues)) {
        $messages[] = "";
        $messages[] = "⚠️ Found " . count($no_primary_issues) . " social user(s) with NO primary connection:";
        
        foreach ($no_primary_issues as $issue) {
            $messages[] = "   - User ID {$issue['user_id']} ({$issue['username']})";
            
            if ($fix_mode) {
                // Fix by setting the oldest connection as primary
                $stmt = $mysqli->prepare("
                    UPDATE social_connections 
                    SET is_primary = 1 
                    WHERE user_id = ? 
                    ORDER BY created_at ASC 
                    LIMIT 1
                ");
                $stmt->bind_param("i", $issue['user_id']);
                $success = $stmt->execute();
                $affected = $mysqli->affected_rows;
                $stmt->close();
                
                if ($success && $affected > 0) {
                    $messages[] = "   ✅ Fixed: Set oldest connection as primary for user {$issue['username']}";
                } else {
                    $messages[] = "   ❌ Failed to fix user {$issue['username']}";
                }
            }
        }
    }
    
} catch (Exception $e) {
    $messages[] = "❌ Error: " . $e->getMessage();
}

$pageTitle = 'Fix Primary Social Connections | Aetia Admin';
ob_start();
?>

<div class="content">
    <!-- Breadcrumbs -->
    <nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="margin-bottom: 20px;">
        <ul>
            <li><a href="../index.php"><span class="icon is-small"><i class="fas fa-home"></i></span><span>Home</span></a></li>
            <li><a href="index.php"><span class="icon is-small"><i class="fas fa-shield-alt"></i></span><span>Admin</span></a></li>
            <li><a href="users.php"><span class="icon is-small"><i class="fas fa-users-cog"></i></span><span>Users</span></a></li>
            <li><a href="messages.php"><span class="icon is-small"><i class="fas fa-envelope-open-text"></i></span><span>Messages</span></a></li>
            <li><a href="archived-messages.php"><span class="icon is-small"><i class="fas fa-archive"></i></span><span>Archived Messages</span></a></li>
            <li><a href="create-message.php"><span class="icon is-small"><i class="fas fa-plus"></i></span><span>New Message</span></a></li>
            <li><a href="send-emails.php"><span class="icon is-small"><i class="fas fa-paper-plane"></i></span><span>Send Emails</span></a></li>
            <li><a href="email-logs.php"><span class="icon is-small"><i class="fas fa-chart-line"></i></span><span>Email Logs</span></a></li>
            <li><a href="sms-logs.php"><span class="icon is-small"><i class="fas fa-sms"></i></span><span>SMS Logs</span></a></li>
            <li><a href="contact-form.php"><span class="icon is-small"><i class="fas fa-envelope"></i></span><span>Contact Forms</span></a></li>
            <li><a href="contracts.php"><span class="icon is-small"><i class="fas fa-file-contract"></i></span><span>Contracts</span></a></li>
            <li><a href="generate-bills.php"><span class="icon is-small"><i class="fas fa-receipt"></i></span><span>Generate Bills</span></a></li>
            <li class="is-active"><a href="#" aria-current="page"><span class="icon is-small"><i class="fas fa-tools"></i></span><span>Fix Social Connections</span></a></li>
        </ul>
    </nav>

    <h2 class="title is-2 has-text-info mb-4">
        <span class="icon"><i class="fas fa-tools"></i></span>
        Fix Primary Social Connections
    </h2>
    
    <div class="box has-background-dark has-text-light">
        <h3 class="title is-4 has-text-warning">Diagnostic Results</h3>
        
        <?php foreach ($messages as $message): ?>
            <p class="mb-2" style="font-family: monospace; white-space: pre-wrap;"><?= htmlspecialchars($message) ?></p>
        <?php endforeach; ?>
        
        <?php if (!$fix_mode && (!empty($issues) || !empty($no_primary_issues))): ?>
            <div class="mt-5">
                <form method="POST" onsubmit="return confirm('Are you sure you want to fix these primary connection issues? This action cannot be undone.');">
                    <input type="hidden" name="fix_issues" value="yes">
                    <button type="submit" class="button is-warning is-medium">
                        <span class="icon"><i class="fas fa-wrench"></i></span>
                        <span>Fix Issues</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>
        
        <?php if ($fix_mode): ?>
            <div class="mt-5">
                <a href="fix-primary-connections.php" class="button is-info">
                    <span class="icon"><i class="fas fa-redo"></i></span>
                    <span>Run Diagnostic Again</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="box has-background-dark has-text-light">
        <h3 class="title is-4 has-text-info">About This Tool</h3>
        <p class="mb-3">This tool checks for and fixes issues with primary social connections:</p>
        <ul class="mb-3">
            <li><strong>Multiple Primary Connections:</strong> Users should only have one primary social connection. When multiple exist, the oldest connection is kept as primary.</li>
            <li><strong>No Primary Connection:</strong> Social users must have at least one primary connection. The oldest connection is automatically set as primary.</li>
        </ul>
        <p><strong>Note:</strong> Manual users (with passwords) are not affected by these checks as they don't require primary social connections.</p>
    </div>
    
    <div class="mt-4">
        <a href="index.php" class="button is-primary">
            <span class="icon"><i class="fas fa-arrow-left"></i></span>
            <span>Back to Admin Dashboard</span>
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
