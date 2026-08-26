<?php
/**
 * WEZO CAMPUS HUB - Forum Thread View
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Helpers.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Helpers;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

$threadId = intval($_GET['id'] ?? 0);
if (!$threadId) {
    Session::setFlash('error', 'Thread not found');
    header('Location: index.php');
    exit;
}

// Increment view count
$db->query("UPDATE forum_threads SET views = views + 1 WHERE id = ?", [$threadId]);

// Get thread details
$thread = $db->fetch("
    SELECT ft.*, 
           u.first_name, u.last_name, u.username, u.avatar, u.role,
           fc.name as category_name, fc.color as category_color,
           c.name as campus_name
    FROM forum_threads ft
    LEFT JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    LEFT JOIN campuses c ON u.campus_id = c.id
    WHERE ft.id = ? AND ft.status = 'active'
", [$threadId]);

if (!$thread) {
    Session::setFlash('error', 'Thread not found or has been removed');
    header('Location: index.php');
    exit;
}

// Get replies with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$totalReplies = $db->fetch("
    SELECT COUNT(*) as total
    FROM forum_replies
    WHERE thread_id = ? AND status = 'active'
", [$threadId]);

$replies = $db->fetchAll("
    SELECT fr.*,
           u.first_name, u.last_name, u.username, u.avatar, u.role, u.reputation_score,
           c.name as campus_name,
           (SELECT COUNT(*) FROM forum_reactions WHERE reply_id = fr.id AND reaction_type = 'like') as like_count,
           (SELECT COUNT(*) FROM forum_reactions WHERE reply_id = fr.id AND reaction_type = 'helpful') as helpful_count,
           (SELECT COUNT(*) FROM forum_reactions WHERE reply_id = fr.id AND reaction_type = 'insightful') as insightful_count,
           (SELECT 1 FROM forum_reactions WHERE reply_id = fr.id AND user_id = ? AND reaction_type = 'like' LIMIT 1) as user_liked,
           (SELECT 1 FROM forum_reactions WHERE reply_id = fr.id AND user_id = ? AND reaction_type = 'helpful' LIMIT 1) as user_helpful,
           (SELECT 1 FROM forum_reactions WHERE reply_id = fr.id AND user_id = ? AND reaction_type = 'insightful' LIMIT 1) as user_insightful
    FROM forum_replies fr
    LEFT JOIN users u ON fr.user_id = u.id
    LEFT JOIN campuses c ON u.campus_id = c.id
    WHERE fr.thread_id = ? AND fr.status = 'active'
    ORDER BY fr.is_solution DESC, fr.created_at ASC
    LIMIT ? OFFSET ?
", [$user['id'], $user['id'], $user['id'], $threadId, $limit, $offset]);

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_content'])) {
    $content = Helpers::sanitizeForumContent($_POST['reply_content']);
    $parentId = intval($_POST['parent_id'] ?? 0);
    
    if (empty($content)) {
        Session::setFlash('error', 'Reply content cannot be empty');
    } elseif (strlen($content) < 10) {
        Session::setFlash('error', 'Reply must be at least 10 characters');
    } else {
        $replyId = $db->insert('forum_replies', [
            'thread_id' => $threadId,
            'user_id' => $user['id'],
            'parent_id' => $parentId ?: null,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Update thread reply count and last reply time
        $db->query("
            UPDATE forum_threads 
            SET reply_count = reply_count + 1, 
                last_reply_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ", [$threadId]);
        
        // Update category counts
        $db->query("
            UPDATE forum_categories 
            SET post_count = post_count + 1,
                last_post_id = ?
            WHERE id = ?
        ", [$replyId, $thread['category_id']]);
        
        // Notify thread owner if not the same user
        if ($thread['user_id'] != $user['id']) {
            $db->insert('notifications', [
                'user_id' => $thread['user_id'],
                'type' => 'forum_reply',
                'title' => 'New Reply to Your Thread',
                'message' => $user['username'] . ' replied to your thread: ' . $thread['title'],
                'data' => json_encode(['thread_id' => $threadId, 'reply_id' => $replyId]),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Notify mentioned users
        preg_match_all('/@(\w+)/', $content, $mentions);
        if (!empty($mentions[1])) {
            foreach ($mentions[1] as $mentionedUsername) {
                $mentionedUser = $db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", 
                    [$mentionedUsername, $user['id']]);
                if ($mentionedUser) {
                    $db->insert('notifications', [
                        'user_id' => $mentionedUser['id'],
                        'type' => 'forum_mention',
                        'title' => 'You were mentioned in a forum post',
                        'message' => $user['username'] . ' mentioned you in a reply',
                        'data' => json_encode(['thread_id' => $threadId, 'reply_id' => $replyId]),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
        }
        
        Session::setFlash('success', 'Reply posted successfully');
        header('Location: thread.php?id=' . $threadId . '&page=' . ceil(($totalReplies['total'] + 1) / $limit));
        exit;
    }
}

// Handle reply actions
if (isset($_GET['action']) && isset($_GET['reply_id'])) {
    $replyId = intval($_GET['reply_id']);
    $action = $_GET['action'];
    
    if ($action === 'like' || $action === 'helpful' || $action === 'insightful') {
        // Check if already reacted
        $existing = $db->fetch("
            SELECT id FROM forum_reactions 
            WHERE reply_id = ? AND user_id = ? AND reaction_type = ?
        ", [$replyId, $user['id'], $action]);
        
        if ($existing) {
            // Remove reaction
            $db->delete('forum_reactions', ['id' => $existing['id']]);
            Session::setFlash('success', 'Reaction removed');
        } else {
            // Add reaction
            $db->insert('forum_reactions', [
                'reply_id' => $replyId,
                'user_id' => $user['id'],
                'reaction_type' => $action
            ]);
            
            // Update user reputation
            $replyOwner = $db->fetch("SELECT user_id FROM forum_replies WHERE id = ?", [$replyId]);
            if ($replyOwner && $replyOwner['user_id'] != $user['id']) {
                $db->query("
                    UPDATE users 
                    SET reputation_score = reputation_score + 1 
                    WHERE id = ?
                ", [$replyOwner['user_id']]);
            }
            
            Session::setFlash('success', 'Reaction added');
        }
        
        header('Location: thread.php?id=' . $threadId);
        exit;
    } elseif ($action === 'solution' && $user['id'] == $thread['user_id']) {
        // Mark as solution
        $db->query("UPDATE forum_replies SET is_solution = FALSE WHERE thread_id = ?", [$threadId]);
        $db->query("UPDATE forum_replies SET is_solution = TRUE WHERE id = ?", [$replyId]);
        Session::setFlash('success', 'Marked as solution');
        header('Location: thread.php?id=' . $threadId);
        exit;
    }
}

// Check subscription status
$isSubscribed = $db->fetch("
    SELECT id FROM forum_subscriptions 
    WHERE user_id = ? AND thread_id = ?
", [$user['id'], $threadId]);

$pageTitle = $thread['title'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Thread Header -->
            <div class="card mb-4">
                <div class="card-body">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Forum</a></li>
                            <li class="breadcrumb-item"><a href="index.php?category=<?= $thread['category_id'] ?>">
                                <?= htmlspecialchars($thread['category_name']) ?>
                            </a></li>
                            <li class="breadcrumb-item active">Thread</li>
                        </ol>
                    </nav>
                    
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge" style="background: <?= $thread['category_color'] ?>; color: white;">
                                <?= htmlspecialchars($thread['category_name']) ?>
                            </span>
                            <?php if ($thread['is_pinned']): ?>
                                <span class="badge bg-warning"><i class="fas fa-thumbtack"></i> Pinned</span>
                            <?php endif; ?>
                            <?php if ($thread['is_featured']): ?>
                                <span class="badge bg-info"><i class="fas fa-star"></i> Featured</span>
                            <?php endif; ?>
                            <?php if ($thread['is_locked']): ?>
                                <span class="badge bg-danger"><i class="fas fa-lock"></i> Locked</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <form method="POST" action="subscribe.php" class="d-inline">
                                <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                                <button type="submit" class="btn btn-sm btn-outline-<?= $isSubscribed ? 'success' : 'secondary' ?>">
                                    <i class="fas fa-<?= $isSubscribed ? 'bell' : 'bell-slash' ?>"></i>
                                    <?= $isSubscribed ? 'Subscribed' : 'Subscribe' ?>
                                </button>
                            </form>
                            
                            <?php if ($user['role'] === 'admin' || $user['id'] == $thread['user_id']): ?>
                                <a href="edit.php?id=<?= $threadId ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['role'] === 'admin'): ?>
                                <a href="moderate.php?action=<?= $thread['is_pinned'] ? 'unpin' : 'pin' ?>&id=<?= $threadId ?>" 
                                   class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-thumbtack"></i> <?= $thread['is_pinned'] ? 'Unpin' : 'Pin' ?>
                                </a>
                                <a href="moderate.php?action=<?= $thread['is_locked'] ? 'unlock' : 'lock' ?>&id=<?= $threadId ?>" 
                                   class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-<?= $thread['is_locked'] ? 'unlock' : 'lock' ?>"></i> <?= $thread['is_locked'] ? 'Unlock' : 'Lock' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h1 class="h2 mb-3"><?= htmlspecialchars($thread['title']) ?></h1>
                    
                    <div class="d-flex align-items-center mb-4">
                        <div class="flex-shrink-0">
                            <img src="<?= htmlspecialchars($thread['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                 alt="<?= htmlspecialchars($thread['username']) ?>" 
                                 class="rounded-circle" width="50" height="50">
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0">
                                <?= htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']) ?>
                                <small class="text-muted">@<?= htmlspecialchars($thread['username']) ?></small>
                                <?php if ($thread['role'] === 'admin'): ?>
                                    <span class="badge bg-danger ms-1">Admin</span>
                                <?php elseif ($thread['role'] === 'moderator'): ?>
                                    <span class="badge bg-success ms-1">Mod</span>
                                <?php endif; ?>
                            </h6>
                            <small class="text-muted">
                                <?= htmlspecialchars($thread['campus_name']) ?> • 
                                Posted <?= date('F j, Y g:i A', strtotime($thread['created_at'])) ?>
                                • <?= $thread['views'] ?> views
                            </small>
                        </div>
                    </div>
                    
                    <div class="thread-content mb-4">
                        <?= Helpers::formatChatMessage($thread['content'], $thread) ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            <i class="fas fa-comments"></i> <?= $thread['reply_count'] ?> replies
                            <span class="ms-3"><i class="fas fa-eye"></i> <?= $thread['views'] ?> views</span>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="#reply-form" class="btn btn-primary">
                                <i class="fas fa-reply"></i> Reply
                            </a>
                            <button class="btn btn-outline-secondary" onclick="copyThreadLink()">
                                <i class="fas fa-share"></i> Share
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Replies -->
            <div class="mb-4">
                <h3 class="h4 mb-3">
                    Replies (<?= $totalReplies['total'] ?>)
                    <?php if ($thread['reply_count'] > 0): ?>
                        <small class="text-muted">Sorted by: <?= isset($_GET['sort']) ? ucfirst($_GET['sort']) : 'Oldest' ?></small>
                    <?php endif; ?>
                </h3>
                
                <?php if (empty($replies)): ?>
                    <div class="text-center py-5 border rounded">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <h4>No replies yet</h4>
                        <p class="text-muted">Be the first to reply to this thread</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($replies as $reply): ?>
                        <div class="card mb-3 <?= $reply['is_solution'] ? 'border-success border-2' : '' ?>">
                            <div class="card-body">
                                <div class="d-flex">
                                    <!-- User Info Sidebar -->
                                    <div class="flex-shrink-0 me-3 text-center" style="width: 80px;">
                                        <img src="<?= htmlspecialchars($reply['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                             alt="<?= htmlspecialchars($reply['username']) ?>" 
                                             class="rounded-circle mb-2" width="50" height="50">
                                        
                                        <h6 class="mb-0 small">
                                            <?= htmlspecialchars($reply['first_name']) ?>
                                        </h6>
                                        <small class="text-muted">@<?= htmlspecialchars($reply['username']) ?></small>
                                        
                                        <?php if ($reply['role'] === 'admin'): ?>
                                            <span class="badge bg-danger d-block mt-1">Admin</span>
                                        <?php elseif ($reply['role'] === 'moderator'): ?>
                                            <span class="badge bg-success d-block mt-1">Mod</span>
                                        <?php endif; ?>
                                        
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-star text-warning"></i> <?= $reply['reputation_score'] ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <!-- Reply Content -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <small class="text-muted">
                                                    <?= date('F j, Y g:i A', strtotime($reply['created_at'])) ?>
                                                    <?php if ($reply['campus_name']): ?>
                                                        • <?= htmlspecialchars($reply['campus_name']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($user['id'] == $reply['user_id'] || $user['role'] === 'admin'): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="#">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#">
                                                            <i class="fas fa-flag"></i> Report
                                                        </a>
                                                    </li>
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <?php if ($reply['is_solution']): ?>
                                            <div class="alert alert-success mb-3 py-2">
                                                <i class="fas fa-check-circle"></i> 
                                                <strong>Marked as solution</strong> by thread owner
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="reply-content mb-3">
                                            <?= Helpers::formatChatMessage($reply['content'], $reply) ?>
                                        </div>
                                        
                                        <!-- Reply Actions -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex gap-2">
                                                <a href="?id=<?= $threadId ?>&action=like&reply_id=<?= $reply['id'] ?>" 
                                                   class="btn btn-sm btn-outline-<?= $reply['user_liked'] ? 'primary' : 'secondary' ?>">
                                                    <i class="fas fa-thumbs-up"></i> Like (<?= $reply['like_count'] ?>)
                                                </a>
                                                <a href="?id=<?= $threadId ?>&action=helpful&reply_id=<?= $reply['id'] ?>" 
                                                   class="btn btn-sm btn-outline-<?= $reply['user_helpful'] ? 'primary' : 'secondary' ?>">
                                                    <i class="fas fa-hands-helping"></i> Helpful (<?= $reply['helpful_count'] ?>)
                                                </a>
                                                <a href="?id=<?= $threadId ?>&action=insightful&reply_id=<?= $reply['id'] ?>" 
                                                   class="btn btn-sm btn-outline-<?= $reply['user_insightful'] ? 'primary' : 'secondary' ?>">
                                                    <i class="fas fa-lightbulb"></i> Insightful (<?= $reply['insightful_count'] ?>)
                                                </a>
                                                
                                                <button class="btn btn-sm btn-outline-secondary" 
                                                        onclick="replyTo(<?= $reply['id'] ?>, '<?= htmlspecialchars($reply['username']) ?>')">
                                                    <i class="fas fa-reply"></i> Reply
                                                </button>
                                            </div>
                                            
                                            <?php if ($user['id'] == $thread['user_id'] && !$reply['is_solution']): ?>
                                                <a href="?id=<?= $threadId ?>&action=solution&reply_id=<?= $reply['id'] ?>" 
                                                   class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check"></i> Mark as Solution
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Nested Replies -->
                                        <?php 
                                        $nestedReplies = $db->fetchAll("
                                            SELECT fr.*, u.first_name, u.last_name, u.username, u.avatar
                                            FROM forum_replies fr
                                            LEFT JOIN users u ON fr.user_id = u.id
                                            WHERE fr.parent_id = ? AND fr.status = 'active'
                                            ORDER BY fr.created_at ASC
                                        ", [$reply['id']]);
                                        
                                        if (!empty($nestedReplies)): ?>
                                            <div class="mt-4 ps-4 border-start">
                                                <?php foreach ($nestedReplies as $nested): ?>
                                                    <div class="mb-3">
                                                        <div class="d-flex align-items-start">
                                                            <img src="<?= htmlspecialchars($nested['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                                                 alt="<?= htmlspecialchars($nested['username']) ?>" 
                                                                 class="rounded-circle me-2" width="30" height="30">
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-0 small">
                                                                    <?= htmlspecialchars($nested['first_name'] . ' ' . $nested['last_name']) ?>
                                                                    <small class="text-muted">@<?= htmlspecialchars($nested['username']) ?></small>
                                                                </h6>
                                                                <small class="text-muted">
                                                                    <?= date('M j, g:i A', strtotime($nested['created_at'])) ?>
                                                                </small>
                                                                <div class="mt-1 small">
                                                                    <?= Helpers::formatChatMessage($nested['content'], $nested) ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($totalReplies['total'] > $limit): ?>
                        <nav aria-label="Replies pagination">
                            <ul class="pagination justify-content-center">
                                <?php
                                $totalPages = ceil($totalReplies['total'] / $limit);
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                ?>
                                
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?id=<?= $threadId ?>&page=<?= $page - 1 ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                        <a class="page-link" href="?id=<?= $threadId ?>&page=<?= $i ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?id=<?= $threadId ?>&page=<?= $totalPages ?>">
                                            <?= $totalPages ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?id=<?= $threadId ?>&page=<?= $page + 1 ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Reply Form -->
            <?php if (!$thread['is_locked'] || $user['role'] === 'admin'): ?>
                <div class="card" id="reply-form">
                    <div class="card-header">
                        <h5 class="mb-0">Post a Reply</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <textarea name="reply_content" id="replyContent" class="form-control" 
                                          rows="6" placeholder="Type your reply here..." 
                                          required></textarea>
                                <input type="hidden" name="parent_id" id="parentId" value="0">
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted small">
                                    <span id="charCount">0</span> characters • Minimum 10 characters
                                    <div id="replyTo" class="mt-1" style="display: none;">
                                        Replying to: <strong id="replyToUsername"></strong>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-2" 
                                                onclick="clearReply()">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Post Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                // Character counter
                document.getElementById('replyContent').addEventListener('input', function() {
                    document.getElementById('charCount').textContent = this.value.length;
                });
                
                // Reply to specific user
                function replyTo(replyId, username) {
                    document.getElementById('parentId').value = replyId;
                    document.getElementById('replyToUsername').textContent = '@' + username;
                    document.getElementById('replyTo').style.display = 'block';
                    document.getElementById('replyContent').focus();
                    document.getElementById('replyContent').value = '@' + username + ' ';
                }
                
                function clearReply() {
                    document.getElementById('parentId').value = 0;
                    document.getElementById('replyTo').style.display = 'none';
                    document.getElementById('replyContent').value = '';
                }
                
                function copyThreadLink() {
                    navigator.clipboard.writeText(window.location.href);
                    alert('Thread link copied to clipboard!');
                }
                </script>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-lock"></i> This thread is locked. No new replies can be posted.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.thread-content, .reply-content {
    font-size: 1.1em;
    line-height: 1.6;
}
.thread-content img, .reply-content img {
    max-width: 100%;
    height: auto;
}
.thread-content pre, .reply-content pre {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
}
.thread-content code, .reply-content code {
    background: #f8f9fa;
    padding: 2px 5px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}
.thread-content a.mention, .reply-content a.mention {
    color: #667eea;
    text-decoration: none;
    font-weight: 500;
}
.thread-content a.mention:hover, .reply-content a.mention:hover {
    text-decoration: underline;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>