<?php
/**
 * WEZO CAMPUS HUB - Forum Dashboard
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

// Get categories
$categories = $db->fetchAll("
    SELECT fc.*, 
           (SELECT COUNT(*) FROM forum_threads WHERE category_id = fc.id AND status = 'active') as thread_count,
           (SELECT COUNT(*) FROM forum_replies fr 
            JOIN forum_threads ft ON fr.thread_id = ft.id 
            WHERE ft.category_id = fc.id AND fr.status = 'active') as reply_count,
           (SELECT username FROM users u 
            JOIN forum_threads ft ON u.id = ft.user_id 
            WHERE ft.category_id = fc.id 
            ORDER BY ft.created_at DESC LIMIT 1) as last_poster
    FROM forum_categories fc
    WHERE fc.is_private = FALSE OR ? = 'admin'
    ORDER BY fc.sort_order, fc.name
", [$user['role']]);

// Get recent threads
$recentThreads = $db->fetchAll("
    SELECT ft.*, 
           u.first_name, u.last_name, u.username, u.avatar,
           fc.name as category_name, fc.color as category_color,
           (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id AND status = 'active') as reply_count,
           (SELECT username FROM users u2 
            JOIN forum_replies fr ON u2.id = fr.user_id 
            WHERE fr.thread_id = ft.id 
            ORDER BY fr.created_at DESC LIMIT 1) as last_reply_by
    FROM forum_threads ft
    LEFT JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.status = 'active'
    ORDER BY ft.last_reply_at DESC, ft.created_at DESC
    LIMIT 10
");

// Get popular threads
$popularThreads = $db->fetchAll("
    SELECT ft.*, 
           u.first_name, u.last_name, u.username,
           fc.name as category_name,
           ft.views, 
           (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id AND status = 'active') as reply_count
    FROM forum_threads ft
    LEFT JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.status = 'active'
    ORDER BY ft.views DESC
    LIMIT 5
");

// Get user's thread count
$userStats = $db->fetch("
    SELECT 
        COUNT(DISTINCT ft.id) as thread_count,
        COUNT(DISTINCT fr.id) as reply_count
    FROM users u
    LEFT JOIN forum_threads ft ON u.id = ft.user_id AND ft.status = 'active'
    LEFT JOIN forum_replies fr ON u.id = fr.user_id AND fr.status = 'active'
    WHERE u.id = ?
", [$user['id']]);

$pageTitle = "Forum";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Forum Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Campus Forum</h1>
                <a href="create-thread.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Thread
                </a>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= array_sum(array_column($categories, 'thread_count')) ?></h2>
                            <p class="mb-0">Threads</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= array_sum(array_column($categories, 'reply_count')) ?></h2>
                            <p class="mb-0">Replies</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= count($categories) ?></h2>
                            <p class="mb-0">Categories</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= $userStats['thread_count'] + $userStats['reply_count'] ?></h2>
                            <p class="mb-0">Your Posts</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="search.php" class="d-flex">
                        <input type="text" name="q" class="form-control me-2" 
                               placeholder="Search forum topics...">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </form>
                </div>
            </div>

            <!-- Categories -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Forum Categories</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($categories as $category): ?>
                            <a href="category.php?id=<?= $category['id'] ?>" 
                               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="me-3" style="width: 30px; height: 30px; background: <?= $category['color'] ?>; 
                                         color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-<?= $category['icon'] ?? 'folder' ?> fa-sm"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($category['name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($category['description'] ?? '') ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary rounded-pill"><?= $category['thread_count'] ?> threads</span>
                                    <span class="badge bg-secondary rounded-pill ms-1"><?= $category['reply_count'] ?> replies</span>
                                    <?php if ($category['last_poster']): ?>
                                        <small class="d-block text-muted mt-1">
                                            Latest: @<?= htmlspecialchars($category['last_poster']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Threads -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Discussions</h5>
                            <a href="recent.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($recentThreads)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                    <h5>No discussions yet</h5>
                                    <p class="text-muted">Be the first to start a discussion!</p>
                                    <a href="create-thread.php" class="btn btn-primary">Start a Thread</a>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recentThreads as $thread): ?>
                                        <a href="thread.php?id=<?= $thread['id'] ?>" 
                                           class="list-group-item list-group-item-action">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1 me-3">
                                                    <div class="d-flex align-items-center mb-1">
                                                        <?php if ($thread['is_pinned']): ?>
                                                            <span class="badge bg-warning me-2">
                                                                <i class="fas fa-thumbtack fa-xs"></i> Pinned
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($thread['is_featured']): ?>
                                                            <span class="badge bg-info me-2">
                                                                <i class="fas fa-star fa-xs"></i> Featured
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="badge" style="background: <?= $thread['category_color'] ?>; color: white;">
                                                            <?= htmlspecialchars($thread['category_name']) ?>
                                                        </span>
                                                    </div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($thread['title']) ?></h6>
                                                    <small class="text-muted">
                                                        Started by 
                                                        <?= htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']) ?>
                                                        @<?= htmlspecialchars($thread['username']) ?>
                                                        • <?= date('M j, g:i A', strtotime($thread['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <div class="text-center">
                                                        <div class="bg-light rounded p-1 mb-1">
                                                            <small class="text-muted">Replies</small>
                                                            <div class="fw-bold"><?= $thread['reply_count'] ?></div>
                                                        </div>
                                                        <div class="bg-light rounded p-1">
                                                            <small class="text-muted">Views</small>
                                                            <div class="fw-bold"><?= $thread['views'] ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php if ($thread['last_reply_by']): ?>
                                                <small class="text-muted d-block mt-2">
                                                    <i class="fas fa-reply"></i> Last reply by @<?= htmlspecialchars($thread['last_reply_by']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Popular Threads & Stats -->
                <div class="col-md-4">
                    <!-- Popular Threads -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Popular Threads</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($popularThreads)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($popularThreads as $thread): ?>
                                        <a href="thread.php?id=<?= $thread['id'] ?>" 
                                           class="list-group-item list-group-item-action py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 small text-truncate" style="max-width: 70%;">
                                                    <?= htmlspecialchars($thread['title']) ?>
                                                </h6>
                                                <span class="badge bg-secondary">
                                                    <?= $thread['views'] ?> views
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($thread['category_name']) ?> • 
                                                <?= $thread['reply_count'] ?> replies
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No popular threads yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Forum Statistics -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Forum Statistics</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">Total Members</small>
                                <strong><?= $db->fetch("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'] ?></strong>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Newest Member</small>
                                <strong>
                                    <?php 
                                    $newest = $db->fetch("
                                        SELECT username FROM users 
                                        WHERE status = 'active' 
                                        ORDER BY created_at DESC LIMIT 1
                                    ");
                                    echo '@' . htmlspecialchars($newest['username'] ?? 'None');
                                    ?>
                                </strong>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted d-block">Online Now</small>
                                <strong>
                                    <?php 
                                    $online = $db->fetch("
                                        SELECT COUNT(DISTINCT user_id) as count FROM user_sessions 
                                        WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                                    ");
                                    echo $online['count'] ?? 0;
                                    ?>
                                </strong>
                            </div>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Server time: <?= date('g:i A') ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.list-group-item:hover {
    background-color: #f8f9fa;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>