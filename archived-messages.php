<?php
// archived-messages.php - User interface for viewing archived messages
session_start();

// Include timezone utilities
require_once __DIR__ . '/includes/timezone.php';

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/models/Message.php';

$messageModel = new Message();
$userId = $_SESSION['user_id'];

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get archived messages
$archivedMessages = $messageModel->getArchivedMessages($userId, $limit, $offset);

$pageTitle = 'Archived Messages';
ob_start();
?>

<style>
.archived-badge {
    background-color: #ff8c00;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: bold;
}

.archive-reason {
    font-style: italic;
    color: #666;
    margin-top: 0.5rem;
}
</style>

<div class="columns is-fullheight" style="min-height: calc(100vh - 3.25rem);">
    <!-- Sidebar -->
    <div class="column is-one-quarter">
        <div class="box" style="height: 100%; position: sticky; top: 1rem;">
            <h3 class="title is-4 mb-4">
                <span class="icon"><i class="fas fa-archive"></i></span>
                Archived Messages
            </h3>
            
            <!-- Back to Messages -->
            <div class="field mb-4">
                <a href="messages.php" class="button is-primary is-fullwidth">
                    <span class="icon"><i class="fas fa-arrow-left"></i></span>
                    <span>Back to Messages</span>
                </a>
            </div>
            
            <?php if (empty($archivedMessages)): ?>
            <div class="has-text-centered has-text-grey">
                <span class="icon is-large">
                    <i class="fas fa-archive fa-3x"></i>
                </span>
                <p class="mt-2">No archived messages</p>
                <p class="is-size-7">Messages you archive will appear here.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="column">
        <div class="container">
            <h1 class="title">
                <span class="icon"><i class="fas fa-archive"></i></span>
                Archived Messages
            </h1>
            
            <?php if (!empty($archivedMessages)): ?>
            <div class="table-container">
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Tags</th>
                            <th>Original Date</th>
                            <th>Archived Date</th>
                            <th>Archived By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($archivedMessages as $msg): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                                <?php if (!empty($msg['archive_reason'])): ?>
                                <div class="archive-reason">
                                    Reason: <?= htmlspecialchars($msg['archive_reason']) ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="tag is-<?= match($msg['priority']) {
                                    'urgent' => 'danger',
                                    'high' => 'warning', 
                                    'normal' => 'info',
                                    'low' => 'light'
                                } ?>">
                                    <?= ucfirst($msg['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($msg['tags'])): ?>
                                    <?php foreach (array_map('trim', explode(',', $msg['tags'])) as $tag): ?>
                                        <?php if (!empty($tag)): ?>
                                        <span class="tag is-small is-<?= $tag === 'Internal' ? 'primary' : 'dark' ?>">
                                            <?= htmlspecialchars($tag) ?>
                                        </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                <span class="has-text-grey">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= formatDateForUser($msg['created_at']) ?></small>
                            </td>
                            <td>
                                <small><?= formatDateForUser($msg['archived_at']) ?></small>
                            </td>
                            <td>
                                <?php if ($msg['archiver_first_name']): ?>
                                <small><?= htmlspecialchars($msg['archiver_first_name'] . ' ' . $msg['archiver_last_name']) ?></small>
                                <?php else: ?>
                                <small class="has-text-grey">System</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="archived-message-view.php?id=<?= $msg['id'] ?>" 
                                   class="button is-small is-info">
                                    <span class="icon"><i class="fas fa-eye"></i></span>
                                    <span>View</span>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if (count($archivedMessages) === $limit): ?>
            <nav class="pagination is-centered mt-4" role="navigation" aria-label="pagination">
                <?php if ($page > 1): ?>
                <a class="pagination-previous" href="?page=<?= $page - 1 ?>">Previous</a>
                <?php endif; ?>
                
                <?php if (count($archivedMessages) === $limit): ?>
                <a class="pagination-next" href="?page=<?= $page + 1 ?>">Next</a>
                <?php endif; ?>
                
                <ul class="pagination-list">
                    <li><span class="pagination-link is-current">Page <?= $page ?></span></li>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="box has-text-centered">
                <span class="icon is-large has-text-grey">
                    <i class="fas fa-archive fa-3x"></i>
                </span>
                <h3 class="title is-4 has-text-grey">No Archived Messages</h3>
                <p class="has-text-grey">You don't have any archived messages yet.</p>
                <p class="has-text-grey">When you archive messages, they will appear here for future reference.</p>
                <br>
                <a href="messages.php" class="button is-primary">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>View Active Messages</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
