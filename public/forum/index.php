<?php
/**
 * WEZO CAMPUS HUB - Campus Forum
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

// Get forum categories with stats
$categories = $db->fetchAll("
    SELECT fc.*, 
           (SELECT COUNT(*) FROM forum_threads 
            WHERE category_id = fc.id AND status = 'active') as thread_count,
           (SELECT COUNT(*) FROM forum_replies fr 
            JOIN forum_threads ft ON fr.thread_id = ft.id 
            WHERE ft.category_id = fc.id AND fr.status = 'active') as reply_count,
           (SELECT username FROM users u 
            JOIN forum_threads ft ON u.id = ft.user_id 
            WHERE ft.category_id = fc.id 
            ORDER BY ft.created_at DESC LIMIT 1) as last_poster,
           (SELECT created_at FROM forum_threads 
            WHERE category_id = fc.id 
            ORDER BY created_at DESC LIMIT 1) as last_post_date
    FROM forum_categories fc
    WHERE fc.is_private = FALSE OR ? = 'admin'
    ORDER BY fc.sort_order, fc.name
", [$user['role']]);

// Get recent threads
$recentThreads = $db->fetchAll("
    SELECT ft.*, 
           u.first_name, u.last_name, u.username, u.avatar, u.role,
           fc.name as category_name, fc.color as category_color,
           (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id AND status = 'active') as reply_count,
           (SELECT username FROM users u2 
            JOIN forum_replies fr ON u2.id = fr.user_id 
            WHERE fr.thread_id = ft.id 
            ORDER BY fr.created_at DESC LIMIT 1) as last_reply_by,
           (SELECT created_at FROM forum_replies 
            WHERE thread_id = ft.id 
            ORDER BY created_at DESC LIMIT 1) as last_reply_date
    FROM forum_threads ft
    LEFT JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.status = 'active'
    ORDER BY ft.last_reply_at DESC, ft.created_at DESC
    LIMIT 15
");

// Get trending threads (most viewed in last 7 days)
$trendingThreads = $db->fetchAll("
    SELECT ft.*, 
           u.first_name, u.last_name, u.username,
           fc.name as category_name,
           ft.views, 
           (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id AND status = 'active') as reply_count
    FROM forum_threads ft
    LEFT JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.status = 'active'
    AND ft.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY ft.views DESC, reply_count DESC
    LIMIT 5
");

// Get featured threads
$featuredThreads = $db->fetchAll("
    SELECT ft.*, 
           u.first_name, u.last_name, u.username,
           fc.name as category_name,
           (SELECT COUNT(*) FROM forum_replies WHERE thread_id = ft.id AND status = 'active') as reply_count
    FROM forum_threads ft
    LEFT JOIN users u ON ft.user_id = u.id
    LEFT JOIN forum_categories fc ON ft.category_id = fc.id
    WHERE ft.status = 'active' AND ft.is_featured = TRUE
    ORDER BY ft.created_at DESC
    LIMIT 3
");

// Get user's thread and reply counts
$userStats = $db->fetch("
    SELECT 
        COUNT(DISTINCT ft.id) as thread_count,
        COUNT(DISTINCT fr.id) as reply_count,
        COALESCE(SUM(ft.views), 0) as total_views,
        COALESCE(SUM(fr.likes), 0) as total_likes
    FROM users u
    LEFT JOIN forum_threads ft ON u.id = ft.user_id AND ft.status = 'active'
    LEFT JOIN forum_replies fr ON u.id = fr.user_id AND fr.status = 'active'
    WHERE u.id = ?
", [$user['id']]);

// Get latest announcements
$announcements = $db->fetchAll("
    SELECT a.*, 
           u.first_name, u.last_name, u.username, u.avatar
    FROM forum_announcements a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.status = 'active' 
    AND (a.expires_at IS NULL OR a.expires_at >= NOW())
    ORDER BY a.is_pinned DESC, a.created_at DESC
    LIMIT 3
");

// Handle search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    header('Location: search.php?q=' . urlencode($search));
    exit;
}

$pageTitle = "Campus Forum";
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
                <div>
                    <h1 class="h3 mb-0">Campus Forum</h1>
                    <p class="text-muted mb-0">Connect, discuss, and share with fellow students</p>
                </div>
                <a href="create-thread.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Thread
                </a>
            </div>

            <!-- Announcements -->
            <?php if (!empty($announcements)): ?>
                <div class="card border-warning mb-4">
                    <div class="card-header bg-warning bg-opacity-10 border-warning">
                        <h5 class="mb-0"><i class="fas fa-bullhorn text-warning"></i> Announcements</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <?php if ($announcement['is_pinned']): ?>
                                                    <span class="badge bg-warning me-2">
                                                        <i class="fas fa-thumbtack"></i> Pinned
                                                    </span>
                                                <?php endif; ?>
                                                <h6 class="mb-0"><?= htmlspecialchars($announcement['title']) ?></h6>
                                            </div>
                                            <p class="mb-2 small"><?= htmlspecialchars($announcement['content']) ?></p>
                                            <small class="text-muted">
                                                Posted by <?= htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']) ?>
                                                • <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?>
                                                <?php if ($announcement['expires_at']): ?>
                                                    • Expires: <?= date('M j, Y', strtotime($announcement['expires_at'])) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <div class="flex-shrink-0">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item text-danger" href="#">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= array_sum(array_column($categories, 'thread_count')) ?></h2>
                            <p class="mb-0">Threads</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= array_sum(array_column($categories, 'reply_count')) ?></h2>
                            <p class="mb-0">Replies</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center">
                            <h2 class="mb-0"><?= count($categories) ?></h2>
                            <p class="mb-0">Categories</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
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
                        <div class="input-group">
                            <input type="text" name="q" class="form-control" 
                                   placeholder="Search forum topics, questions, or users..." 
                                   value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <small class="text-muted">Quick search:</small>
                        <a href="search.php?q=assignment+help" class="badge bg-secondary text-decoration-none">Assignment Help</a>
                        <a href="search.php?q=exam+preparation" class="badge bg-secondary text-decoration-none">Exam Prep</a>
                        <a href="search.php?q=campus+events" class="badge bg-secondary text-decoration-none">Campus Events</a>
                        <a href="search.php?q=hostel+finder" class="badge bg-secondary text-decoration-none">Hostel Finder</a>
                        <a href="search.php?q=study+groups" class="badge bg-secondary text-decoration-none">Study Groups</a>
                    </div>
                </div>
            </div>

            <!-- Featured Threads -->
            <?php if (!empty($featuredThreads)): ?>
                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary bg-opacity-10 border-primary">
                        <h5 class="mb-0"><i class="fas fa-star text-primary"></i> Featured Discussions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($featuredThreads as $thread): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-primary">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="bg-primary bg-opacity-10 p-2 rounded me-2">
                                                    <i class="fas fa-star text-primary"></i>
                                                </div>
                                                <h6 class="mb-0">
                                                    <a href="thread.php?id=<?= $thread['id'] ?>" 
                                                       class="text-decoration-none">
                                                        <?= htmlspecialchars($thread['title']) ?>
                                                    </a>
                                                </h6>
                                            </div>
                                            <p class="card-text small text-muted mb-2">
                                                <?= substr(strip_tags($thread['content']), 0, 80) ?>...
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-comments"></i> <?= $thread['reply_count'] ?> replies
                                                </small>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($thread['category_name']) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Main Content -->
                <div class="col-md-8">
                    <!-- Forum Categories -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Forum Categories</h5>
                            <a href="categories.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($categories as $category): ?>
                                    <a href="category.php?id=<?= $category['id'] ?>" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3" style="width: 40px; height: 40px; background: <?= $category['color'] ?>; 
                                                 color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-<?= $category['icon'] ?? 'folder' ?>"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?= htmlspecialchars($category['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($category['description'] ?? '') ?></small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="d-flex flex-column align-items-end">
                                                <span class="badge bg-primary rounded-pill mb-1">
                                                    <?= $category['thread_count'] ?> threads
                                                </span>
                                                <span class="badge bg-secondary rounded-pill">
                                                    <?= $category['reply_count'] ?> replies
                                                </span>
                                                <?php if ($category['last_poster']): ?>
                                                    <small class="text-muted mt-1">
                                                        Latest: @<?= htmlspecialchars($category['last_poster']) ?>
                                                        <br>
                                                        <small><?= $category['last_post_date'] ? date('M j', strtotime($category['last_post_date'])) : '' ?></small>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Discussions -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Discussions</h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-sort"></i> Sort By
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?sort=newest">Newest</a></li>
                                    <li><a class="dropdown-item" href="?sort=popular">Most Popular</a></li>
                                    <li><a class="dropdown-item" href="?sort=unanswered">Unanswered</a></li>
                                    <li><a class="dropdown-item" href="?sort=solved">Solved</a></li>
                                </ul>
                            </div>
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
                                                    <div class="d-flex align-items-center mb-2">
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
                                                        <?php if ($thread['reply_count'] == 0): ?>
                                                            <span class="badge bg-danger me-2">New</span>
                                                        <?php endif; ?>
                                                        <span class="badge" style="background: <?= $thread['category_color'] ?>; color: white;">
                                                            <?= htmlspecialchars($thread['category_name']) ?>
                                                        </span>
                                                    </div>
                                                    <h6 class="mb-1"><?= htmlspecialchars($thread['title']) ?></h6>
                                                    <p class="mb-2 text-muted small">
                                                        <?= substr(strip_tags($thread['content']), 0, 120) ?>...
                                                    </p>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= htmlspecialchars($thread['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                                             alt="<?= htmlspecialchars($thread['username']) ?>" 
                                                             class="rounded-circle me-2" width="24" height="24">
                                                        <small class="text-muted">
                                                            <strong><?= htmlspecialchars($thread['first_name'] . ' ' . $thread['last_name']) ?></strong>
                                                            @<?= htmlspecialchars($thread['username']) ?>
                                                            <?php if ($thread['role'] === 'admin'): ?>
                                                                <span class="badge bg-danger ms-1">Admin</span>
                                                            <?php elseif ($thread['role'] === 'moderator'): ?>
                                                                <span class="badge bg-success ms-1">Mod</span>
                                                            <?php endif; ?>
                                                            • <?= date('M j, g:i A', strtotime($thread['created_at'])) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <div class="text-center">
                                                        <div class="bg-light rounded p-2 mb-2">
                                                            <div class="text-muted small">Replies</div>
                                                            <div class="fw-bold h5 mb-0"><?= $thread['reply_count'] ?></div>
                                                        </div>
                                                        <div class="bg-light rounded p-2">
                                                            <div class="text-muted small">Views</div>
                                                            <div class="fw-bold h5 mb-0"><?= $thread['views'] ?></div>
                                                        </div>
                                                    </div>
                                                    <?php if ($thread['last_reply_by']): ?>
                                                        <small class="text-muted d-block mt-2">
                                                            Last reply: @<?= htmlspecialchars($thread['last_reply_by']) ?>
                                                            <br>
                                                            <?= $thread['last_reply_date'] ? date('M j', strtotime($thread['last_reply_date'])) : '' ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($recentThreads)): ?>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Showing <?= count($recentThreads) ?> recent discussions</small>
                                    <a href="recent.php" class="btn btn-sm btn-outline-primary">View All Discussions</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Trending Threads -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-fire text-danger"></i> Trending Now</h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($trendingThreads)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($trendingThreads as $thread): ?>
                                        <a href="thread.php?id=<?= $thread['id'] ?>" 
                                           class="list-group-item list-group-item-action py-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0 small text-truncate" style="max-width: 70%;">
                                                    <?= htmlspecialchars($thread['title']) ?>
                                                </h6>
                                                <span class="badge bg-danger">
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
                                <p class="text-muted text-center py-3">No trending threads yet</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Forum Statistics -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-bar"></i> Forum Statistics</h6>
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
                            <div class="mb-3">
                                <small class="text-muted d-block">Your Activity</small>
                                <div class="d-flex justify-content-between">
                                    <span>Threads: <strong><?= $userStats['thread_count'] ?></strong></span>
                                    <span>Replies: <strong><?= $userStats['reply_count'] ?></strong></span>
                                </div>
                            </div>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-clock"></i> Server time: <?= date('g:i A') ?>
                            </small>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-link"></i> Quick Links</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="my_threads.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-list"></i> My Threads
                                </a>
                                <a href="my_replies.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-reply"></i> My Replies
                                </a>
                                <a href="subscriptions.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-bell"></i> Subscriptions
                                </a>
                                <a href="bookmarks.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-bookmark"></i> Bookmarks
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Forum Rules -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-gavel text-danger"></i> Forum Rules</h6>
                        </div>
                        <div class="card-body">
                            <ol class="small mb-0">
                                <li>Be respectful to all members</li>
                                <li>No spam or self-promotion</li>
                                <li>Keep discussions relevant</li>
                                <li>No offensive content</li>
                                <li>Respect privacy</li>
                                <li>Follow campus policies</li>
                            </ol>
                            <hr class="my-2">
                            <small class="text-muted">
                                Violations may result in warnings, suspensions, or bans.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CTA Banner -->
            <div class="card bg-gradient mt-4">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h5 class="card-title">Join the Conversation!</h5>
                            <p class="card-text">
                                Share your knowledge, ask questions, and connect with fellow students. 
                                The forum is the perfect place to discuss academics, campus life, and more.
                            </p>
                            <div class="d-flex gap-2">
                                <a href="create-thread.php" class="btn btn-light">
                                    <i class="fas fa-plus"></i> Start a Thread
                                </a>
                                <a href="categories.php" class="btn btn-outline-light">
                                    <i class="fas fa-folder"></i> Browse Categories
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <i class="fas fa-comments fa-5x text-white opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-update online users count
function updateOnlineUsers() {
    fetch('/api/forum/online_users.php')
        .then(response => response.json())
        .then(data => {
            if (data.count !== undefined) {
                document.querySelector('.online-count').textContent = data.count;
            }
        });
}

// Update every 30 seconds
setInterval(updateOnlineUsers, 30000);

// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Mark all as read
function markAllAsRead() {
    fetch('/api/forum/mark_read.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelector('.notification-count').remove();
            Swal.fire({
                title: 'Success!',
                text: 'All notifications marked as read',
                icon: 'success',
                timer: 2000
            });
        }
    });
}

// Quick search
document.querySelectorAll('.quick-search').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const searchTerm = this.textContent;
        document.querySelector('input[name="q"]').value = searchTerm;
        document.querySelector('form').submit();
    });
});
</script>

<style>
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.bg-gradient .card-title,
.bg-gradient .card-text {
    color: white;
}
.list-group-item:hover {
    background-color: #f8f9fa;
}
.list-group-item-action {
    transition: background-color 0.2s;
}
.category-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>