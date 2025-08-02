<?php
// admin/archived-messages.php - Admin interface for viewing archived messages
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../models/User.php';

$messageModel = new Message();
$userModel = new User();

// Check if current user is admin
$isAdmin = $userModel->isUserAdmin($_SESSION['user_id']);

if (!$isAdmin) {
    $_SESSION['error_message'] = 'Access denied. Administrator privileges required.';
    header('Location: ../index.php');
    exit;
}

// Get filter parameters
$tagFilter = $_GET['tag'] ?? null;
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get archived messages
$archivedMessages = $messageModel->getAllArchivedMessages($limit, $offset, $tagFilter);

// Get available tags for filter
$availableTags = $messageModel->getAvailableTags();

$pageTitle = 'Archived Messages - Admin';
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
    font-size: 0.9rem;
}
</style>

<div class="container">
    <!-- Header -->
    <div class="level mb-4">
        <div class="level-left">
            <h2 class="title is-2 has-text-info">
                <span class="icon"><i class="fas fa-archive"></i></span>
                Archived Messages
            </h2>
        </div>
        <div class="level-right">
            <div class="buttons">
                <a href="messages.php" class="button is-primary">
                    <span class="icon"><i class="fas fa-envelope"></i></span>
                    <span>Active Messages</span>
                </a>
                <a href="create-message.php" class="button is-info is-small">
                    <span class="icon"><i class="fas fa-plus"></i></span>
                    <span>New Message</span>
                </a>
                <a href="../index.php" class="button is-light is-small">
                    <span class="icon"><i class="fas fa-home"></i></span>
                    <span>Back to Website</span>
                </a>
            </div>
        </div>
    </div>

    <div class="columns">
        <!-- Filters Sidebar -->
        <div class="column is-one-quarter">
            <div class="box">
                <h3 class="title is-5 mb-4">Filters</h3>
                
                <!-- Tag Filter -->
                <div class="field mb-4">
                    <label class="label">Filter by Tag</label>
                    <div class="control">
                        <div class="select is-fullwidth">
                            <select onchange="updateFilters('tag', this.value)">
                                <option value="">All Tags</option>
                                <?php foreach ($availableTags as $tag): ?>
                                <option value="<?= htmlspecialchars($tag) ?>" <?= $tagFilter === $tag ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tag) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($tagFilter): ?>
                    <p class="help">
                        Showing messages tagged with "<?= htmlspecialchars($tagFilter) ?>"
                        <a href="javascript:updateFilters('tag', '')" class="has-text-link">Clear filter</a>
                    </p>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($archivedMessages)): ?>
                <div class="has-text-centered has-text-grey">
                    <span class="icon is-large">
                        <i class="fas fa-archive fa-3x"></i>
                    </span>
                    <p class="mt-2">No archived messages</p>
                    <?php if ($tagFilter): ?>
                    <p class="is-size-7">No archived messages found with the selected filter.</p>
                    <?php else: ?>
                    <p class="is-size-7">Archived messages will appear here.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="column">
            <?php if (!empty($archivedMessages)): ?>
            <div class="table-container">
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Message Details</th>
                            <th>User</th>
                            <th>Priority</th>
                            <th>Tags</th>
                            <th>Original Date</th>
                            <th>Archived</th>
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
                                <?php if ($msg['owner_first_name']): ?>
                                <strong><?= htmlspecialchars($msg['owner_first_name'] . ' ' . $msg['owner_last_name']) ?></strong>
                                <?php else: ?>
                                <span class="has-text-grey">Unknown User</span>
                                <?php endif; ?>
                                <br>
                                <small class="has-text-grey">
                                    Created by: <?= htmlspecialchars($msg['creator_first_name'] . ' ' . $msg['creator_last_name']) ?>
                                </small>
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
                                <br>
                                <small class="has-text-grey">
                                    by <?= htmlspecialchars($msg['archiver_first_name'] . ' ' . $msg['archiver_last_name']) ?>
                                </small>
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
                <a class="pagination-previous" href="?page=<?= $page - 1 ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?>">Previous</a>
                <?php endif; ?>
                
                <?php if (count($archivedMessages) === $limit): ?>
                <a class="pagination-next" href="?page=<?= $page + 1 ?><?= $tagFilter ? '&tag=' . urlencode($tagFilter) : '' ?>">Next</a>
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
                <?php if ($tagFilter): ?>
                <p class="has-text-grey">No archived messages found with the selected filter.</p>
                <p class="has-text-grey">Try adjusting your filters or check different criteria.</p>
                <?php else: ?>
                <p class="has-text-grey">No messages have been archived yet.</p>
                <p class="has-text-grey">When messages are archived, they will appear here for reference.</p>
                <?php endif; ?>
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

<script>
function updateFilters(filterType, value) {
    const url = new URL(window.location);
    
    // Update the specified filter
    if (value) {
        url.searchParams.set(filterType, value);
    } else {
        url.searchParams.delete(filterType);
    }
    
    // Reset page when changing filters
    url.searchParams.delete('page');
    
    // Navigate to the updated URL
    window.location.href = url.toString();
}
</script>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
