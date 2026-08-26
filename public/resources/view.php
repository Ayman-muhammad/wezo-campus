<?php
/**
 * WEZO CAMPUS HUB - View Resource
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

use Core\Auth;
use Core\Database;
use Core\Session;

Auth::init();
$db = Database::getInstance();
$user = Auth::isLoggedIn() ? Auth::user() : null;

// Get resource ID
$resourceId = $_GET['id'] ?? 0;
if (!$resourceId) {
    header('Location: index.php');
    exit;
}

// Fetch resource details
$resource = $db->fetch("
    SELECT r.*, 
           u.username, u.first_name, u.last_name, u.avatar,
           c.name as course_name,
           cat.name as category_name,
           (SELECT COUNT(*) FROM resource_downloads WHERE resource_id = r.id) as download_count,
           (SELECT COUNT(*) FROM resource_ratings WHERE resource_id = r.id) as rating_count,
           (SELECT AVG(rating) FROM resource_ratings WHERE resource_id = r.id) as avg_rating,
           (SELECT COUNT(*) FROM resource_comments WHERE resource_id = r.id) as comment_count,
           (SELECT COUNT(*) FROM resource_bookmarks WHERE resource_id = r.id AND user_id = ?) as is_bookmarked
    FROM resources r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN courses c ON r.course_id = c.id
    LEFT JOIN resource_categories cat ON r.category_id = cat.id
    WHERE r.id = ? AND r.status = 'published'
", [$user['id'] ?? 0, $resourceId]);

if (!$resource) {
    header('Location: index.php');
    exit;
}

// Check if user has permission to view
if ($resource['is_approved'] != 1 && (!$user || ($user['role'] != 'admin' && $user['id'] != $resource['user_id']))) {
    Session::setFlash('error', 'This resource is pending approval and not available for viewing.');
    header('Location: index.php');
    exit;
}

// Record view (only once per session)
$viewKey = 'resource_view_' . $resourceId;
if (!isset($_SESSION[$viewKey])) {
    $db->query("UPDATE resources SET views = views + 1 WHERE id = ?", [$resourceId]);
    $_SESSION[$viewKey] = true;
}

// Handle downloads
if (isset($_GET['download']) && $user) {
    $db->insert('resource_downloads', [
        'resource_id' => $resourceId,
        'user_id' => $user['id'],
        'downloaded_at' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    $filePath = __DIR__ . '/../../' . $resource['file_path'];
    if (file_exists($filePath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($resource['file_path']) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'rate':
                $rating = intval($_POST['rating']);
                if ($rating >= 1 && $rating <= 5) {
                    $db->query("
                        INSERT INTO resource_ratings (resource_id, user_id, rating, created_at)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE rating = ?, created_at = ?
                    ", [$resourceId, $user['id'], $rating, date('Y-m-d H:i:s'), $rating, date('Y-m-d H:i:s')]);
                    Session::setFlash('success', 'Rating submitted successfully!');
                }
                break;
                
            case 'comment':
                $comment = trim($_POST['comment']);
                if (!empty($comment)) {
                    $db->insert('resource_comments', [
                        'resource_id' => $resourceId,
                        'user_id' => $user['id'],
                        'comment' => $comment,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    Session::setFlash('success', 'Comment added successfully!');
                }
                break;
                
            case 'bookmark':
                if ($resource['is_bookmarked']) {
                    $db->query("DELETE FROM resource_bookmarks WHERE resource_id = ? AND user_id = ?", 
                              [$resourceId, $user['id']]);
                    Session::setFlash('success', 'Removed from bookmarks');
                } else {
                    $db->insert('resource_bookmarks', [
                        'resource_id' => $resourceId,
                        'user_id' => $user['id'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    Session::setFlash('success', 'Added to bookmarks');
                }
                break;
                
            case 'report':
                $reason = trim($_POST['reason']);
                $description = trim($_POST['description'] ?? '');
                if (!empty($reason)) {
                    $db->insert('reports', [
                        'item_type' => 'resource',
                        'item_id' => $resourceId,
                        'reporter_id' => $user['id'],
                        'reason' => $reason,
                        'description' => $description,
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    Session::setFlash('success', 'Report submitted. We will review it shortly.');
                }
                break;
        }
        
        header('Location: view.php?id=' . $resourceId);
        exit;
    }
}

// Fetch ratings
$ratings = $db->fetchAll("
    SELECT r.*, u.username, u.first_name, u.last_name, u.avatar
    FROM resource_ratings r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.resource_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
", [$resourceId]);

// Fetch comments
$comments = $db->fetchAll("
    SELECT c.*, u.username, u.first_name, u.last_name, u.avatar
    FROM resource_comments c
    LEFT JOIN users u ON c.user_id = u.id
    WHERE c.resource_id = ?
    ORDER BY c.created_at DESC
", [$resourceId]);

// Fetch similar resources
$similarResources = $db->fetchAll("
    SELECT r.id, r.title, r.file_type, r.download_count, r.avg_rating,
           u.username, u.first_name, u.last_name
    FROM resources r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.category_id = ? AND r.id != ? AND r.status = 'published' AND r.is_approved = 1
    ORDER BY r.download_count DESC
    LIMIT 5
", [$resource['category_id'], $resourceId]);

$pageTitle = $resource['title'] . " - WEZO CAMPUS HUB";
include '../../templates/header.php';
?>

<div class="container py-4">
    <!-- Resource Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Resources</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($resource['title']); ?></li>
                </ol>
            </nav>
            
            <h1 class="h2 mb-3"><?php echo htmlspecialchars($resource['title']); ?></h1>
            
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge bg-primary">
                    <?php echo htmlspecialchars($resource['category_name']); ?>
                </span>
                <span class="badge bg-secondary">
                    <?php echo ucfirst(str_replace('_', ' ', $resource['type'])); ?>
                </span>
                <?php if ($resource['course_name']): ?>
                <span class="badge bg-info">
                    <?php echo htmlspecialchars($resource['course_name']); ?>
                </span>
                <?php endif; ?>
                <?php if ($resource['semester']): ?>
                <span class="badge bg-warning">Semester <?php echo $resource['semester']; ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4 text-end">
            <div class="btn-group">
                <?php if ($user): ?>
                <a href="?id=<?php echo $resourceId; ?>&download=1" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i> Download
                </a>
                <?php else: ?>
                <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI'] . '&download=1'); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Login to Download
                </a>
                <?php endif; ?>
                
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" 
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($user): ?>
                    <li>
                        <button class="dropdown-item" onclick="toggleBookmark(<?php echo $resourceId; ?>)">
                            <i class="fas fa-bookmark me-2 <?php echo $resource['is_bookmarked'] ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php echo $resource['is_bookmarked'] ? 'Remove Bookmark' : 'Add to Bookmarks'; ?>
                        </button>
                    </li>
                    <?php endif; ?>
                    <li>
                        <button class="dropdown-item" onclick="shareResource()">
                            <i class="fas fa-share me-2"></i> Share
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="fas fa-flag me-2 text-danger"></i> Report Resource
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Resource Details -->
        <div class="col-lg-8">
            <!-- Resource Info Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="text-center">
                                <?php
                                $fileExt = strtolower(pathinfo($resource['file_path'], PATHINFO_EXTENSION));
                                $fileIcons = [
                                    'pdf' => 'file-pdf',
                                    'doc' => 'file-word',
                                    'docx' => 'file-word',
                                    'ppt' => 'file-powerpoint',
                                    'pptx' => 'file-powerpoint',
                                    'xls' => 'file-excel',
                                    'xlsx' => 'file-excel',
                                    'jpg' => 'file-image',
                                    'jpeg' => 'file-image',
                                    'png' => 'file-image',
                                    'zip' => 'file-archive'
                                ];
                                $fileIcon = $fileIcons[$fileExt] ?? 'file';
                                $fileColor = $fileExt == 'pdf' ? 'text-danger' : 
                                           (in_array($fileExt, ['doc', 'docx']) ? 'text-primary' : 
                                           (in_array($fileExt, ['ppt', 'pptx']) ? 'text-warning' : 
                                           (in_array($fileExt, ['xls', 'xlsx']) ? 'text-success' : 'text-secondary')));
                                ?>
                                <i class="fas fa-<?php echo $fileIcon; ?> fa-4x <?php echo $fileColor; ?> mb-3"></i>
                                <h6><?php echo strtoupper($fileExt); ?> File</h6>
                                <small class="text-muted"><?php echo formatFileSize($resource['file_size']); ?></small>
                            </div>
                        </div>
                        
                        <div class="col-md-9">
                            <h5 class="mb-3">Description</h5>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($resource['description'])); ?></p>
                            
                            <div class="row mt-4">
                                <?php if ($resource['author']): ?>
                                <div class="col-md-6 mb-3">
                                    <small class="text-muted">Author/Publisher</small>
                                    <div class="fw-medium"><?php echo htmlspecialchars($resource['author']); ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($resource['academic_year']): ?>
                                <div class="col-md-6 mb-3">
                                    <small class="text-muted">Academic Year</small>
                                    <div class="fw-medium"><?php echo $resource['academic_year']; ?> / <?php echo $resource['academic_year'] + 1; ?></div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($resource['tags']): ?>
                                <div class="col-12 mb-3">
                                    <small class="text-muted">Tags</small>
                                    <div>
                                        <?php 
                                        $tags = explode(',', $resource['tags']);
                                        foreach ($tags as $tag):
                                            $tag = trim($tag);
                                            if (!empty($tag)):
                                        ?>
                                        <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Stats -->
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="h4 mb-1"><?php echo $resource['download_count']; ?></div>
                            <small class="text-muted">Downloads</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 mb-1"><?php echo $resource['views']; ?></div>
                            <small class="text-muted">Views</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 mb-1"><?php echo $resource['rating_count']; ?></div>
                            <small class="text-muted">Ratings</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 mb-1"><?php echo $resource['comment_count']; ?></div>
                            <small class="text-muted">Comments</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ratings Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-star me-2 text-warning"></i> Ratings & Reviews
                    </h6>
                </div>
                <div class="card-body">
                    <!-- Overall Rating -->
                    <div class="row align-items-center mb-4">
                        <div class="col-md-4 text-center">
                            <?php if ($resource['avg_rating']): ?>
                            <div class="display-4 fw-bold text-warning"><?php echo round($resource['avg_rating'], 1); ?></div>
                            <div class="mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= floor($resource['avg_rating']) ? '' : ($i == ceil($resource['avg_rating']) && $resource['avg_rating'] - floor($resource['avg_rating']) >= 0.5 ? '-half-alt' : ''); ?> text-warning"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted"><?php echo $resource['rating_count']; ?> ratings</small>
                            <?php else: ?>
                            <div class="text-muted">No ratings yet</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Rating Distribution -->
                        <div class="col-md-8">
                            <?php
                            $ratingDistribution = $db->fetchAll("
                                SELECT rating, COUNT(*) as count
                                FROM resource_ratings
                                WHERE resource_id = ?
                                GROUP BY rating
                                ORDER BY rating DESC
                            ", [$resourceId]);
                            
                            $distribution = array_fill(1, 5, 0);
                            foreach ($ratingDistribution as $dist) {
                                $distribution[$dist['rating']] = $dist['count'];
                            }
                            
                            $totalRatings = array_sum($distribution);
                            ?>
                            
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="row align-items-center mb-2">
                                <div class="col-2">
                                    <small class="text-muted"><?php echo $i; ?> star</small>
                                </div>
                                <div class="col-8">
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: <?php echo $totalRatings > 0 ? ($distribution[$i] / $totalRatings * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <div class="col-2">
                                    <small class="text-muted"><?php echo $distribution[$i]; ?></small>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Rate Resource (if logged in) -->
                    <?php if ($user): ?>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="rate">
                        <div class="mb-3">
                            <label class="form-label">Rate this resource:</label>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <button type="button" class="star-btn" data-rating="<?php echo $i; ?>">
                                    <i class="far fa-star fa-2x"></i>
                                </button>
                                <?php endfor; ?>
                                <input type="hidden" name="rating" id="selectedRating" value="0">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-sm" id="submitRating" disabled>
                            Submit Rating
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <!-- Recent Ratings -->
                    <?php if (!empty($ratings)): ?>
                    <h6 class="mb-3">Recent Ratings</h6>
                    <div class="list-group list-group-flush">
                        <?php foreach ($ratings as $rating): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="d-flex align-items-center">
                                    <img src="/uploads/avatars/<?php echo $rating['avatar'] ?? 'default.jpg'; ?>" 
                                         class="rounded-circle me-3" width="40" height="40">
                                    <div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($rating['first_name'] . ' ' . $rating['last_name']); ?></div>
                                        <small class="text-muted">@<?php echo htmlspecialchars($rating['username']); ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="text-warning mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $rating['rating'] ? '' : ($i == $rating['rating'] + 0.5 ? '-half-alt' : ''); ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted"><?php echo timeAgo($rating['created_at']); ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-comments me-2 text-primary"></i> Comments
                    </h6>
                    <span class="badge bg-primary"><?php echo count($comments); ?></span>
                </div>
                <div class="card-body">
                    <!-- Add Comment (if logged in) -->
                    <?php if ($user): ?>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="comment">
                        <div class="mb-3">
                            <label class="form-label">Add a comment:</label>
                            <textarea class="form-control" name="comment" rows="3" 
                                      placeholder="Share your thoughts about this resource..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            Post Comment
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <!-- Comments List -->
                    <?php if (!empty($comments)): ?>
                    <div class="comments-list">
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-item border-bottom pb-3 mb-3">
                            <div class="d-flex">
                                <img src="/uploads/avatars/<?php echo $comment['avatar'] ?? 'default.jpg'; ?>" 
                                     class="rounded-circle me-3" width="48" height="48">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></h6>
                                            <small class="text-muted">@<?php echo htmlspecialchars($comment['username']); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo timeAgo($comment['created_at']); ?></small>
                                    </div>
                                    <p class="mb-2"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                    
                                    <!-- Comment Actions -->
                                    <div class="small">
                                        <button class="btn btn-sm btn-outline-secondary me-2" onclick="likeComment(<?php echo $comment['id']; ?>)">
                                            <i class="far fa-thumbs-up me-1"></i> Helpful
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="replyToComment(<?php echo $comment['id']; ?>, '<?php echo htmlspecialchars($comment['username']); ?>')">
                                            <i class="far fa-comment me-1"></i> Reply
                                        </button>
                                        
                                        <?php if ($user && ($user['id'] == $comment['user_id'] || $user['role'] == 'admin')): ?>
                                        <button class="btn btn-sm btn-outline-danger ms-2" 
                                                onclick="deleteComment(<?php echo $comment['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No comments yet. Be the first to comment!</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Sidebar -->
        <div class="col-lg-4">
            <!-- Uploader Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-user me-2"></i> Uploaded By
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <img src="/uploads/avatars/<?php echo $resource['avatar'] ?? 'default.jpg'; ?>" 
                             class="rounded-circle mb-3" width="80" height="80">
                        <h5><?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?></h5>
                        <p class="text-muted mb-2">@<?php echo htmlspecialchars($resource['username']); ?></p>
                        
                        <!-- Uploader Stats -->
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h6 mb-1">
                                    <?php
                                    $uploadCount = $db->fetchColumn("SELECT COUNT(*) FROM resources WHERE user_id = ? AND status = 'published'", 
                                                                    [$resource['user_id']]);
                                    echo $uploadCount;
                                    ?>
                                </div>
                                <small class="text-muted">Resources</small>
                            </div>
                            <div class="col-6">
                                <div class="h6 mb-1">
                                    <?php
                                    $totalDownloads = $db->fetchColumn("
                                        SELECT SUM(download_count) 
                                        FROM resources 
                                        WHERE user_id = ? AND status = 'published'
                                    ", [$resource['user_id']]);
                                    echo number_format($totalDownloads);
                                    ?>
                                </div>
                                <small class="text-muted">Total Downloads</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="/profile.php?id=<?php echo $resource['user_id']; ?>" 
                           class="btn btn-outline-primary">
                            <i class="fas fa-user-circle me-2"></i> View Profile
                        </a>
                        <a href="index.php?user=<?php echo $resource['user_id']; ?>" 
                           class="btn btn-outline-secondary">
                            <i class="fas fa-folder-open me-2"></i> View All Resources
                        </a>
                    </div>
                </div>
            </div>

            <!-- File Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i> File Information
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <small class="text-muted">File Type:</small>
                            <div class="fw-medium"><?php echo strtoupper($resource['file_type']); ?></div>
                        </li>
                        <li class="mb-2">
                            <small class="text-muted">File Size:</small>
                            <div class="fw-medium"><?php echo formatFileSize($resource['file_size']); ?></div>
                        </li>
                        <li class="mb-2">
                            <small class="text-muted">Uploaded:</small>
                            <div class="fw-medium"><?php echo date('F d, Y', strtotime($resource['created_at'])); ?></div>
                        </li>
                        <li class="mb-2">
                            <small class="text-muted">Last Updated:</small>
                            <div class="fw-medium"><?php echo date('F d, Y', strtotime($resource['updated_at'] ?? $resource['created_at'])); ?></div>
                        </li>
                        <li>
                            <small class="text-muted">Status:</small>
                            <div>
                                <?php if ($resource['is_approved'] == 1): ?>
                                <span class="badge bg-success">Approved</span>
                                <?php else: ?>
                                <span class="badge bg-warning">Pending Review</span>
                                <?php endif; ?>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Similar Resources -->
            <?php if (!empty($similarResources)): ?>
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-layer-group me-2"></i> Similar Resources
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($similarResources as $similar): ?>
                        <a href="view.php?id=<?php echo $similar['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 small"><?php echo htmlspecialchars($similar['title']); ?></h6>
                                <small class="text-muted"><?php echo strtoupper($similar['file_type']); ?></small>
                            </div>
                            <div class="d-flex justify-content-between small">
                                <span class="text-muted">
                                    <?php echo htmlspecialchars($similar['first_name'] . ' ' . $similar['last_name']); ?>
                                </span>
                                <span>
                                    <i class="fas fa-download me-1"></i> <?php echo $similar['download_count']; ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <?php if ($user): ?>
            <form method="POST">
                <input type="hidden" name="action" value="report">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="reportModalLabel">
                        <i class="fas fa-flag me-2"></i> Report Resource
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for reporting *</label>
                        <select class="form-select" name="reason" required>
                            <option value="">Select a reason</option>
                            <option value="copyright">Copyright violation</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="spam">Spam or advertisement</option>
                            <option value="wrong_info">Wrong information</option>
                            <option value="broken_file">Broken or corrupted file</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional details (optional)</label>
                        <textarea class="form-control" name="description" rows="3" 
                                  placeholder="Please provide more details about your report..."></textarea>
                    </div>
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        False reports may result in account restrictions.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Report</button>
                </div>
            </form>
            <?php else: ?>
            <div class="modal-body text-center py-4">
                <i class="fas fa-sign-in-alt fa-3x text-primary mb-3"></i>
                <h5>Login Required</h5>
                <p class="text-muted">You need to be logged in to report resources.</p>
                <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Login
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Rating stars interaction
const starButtons = document.querySelectorAll('.star-btn');
const selectedRating = document.getElementById('selectedRating');
const submitRating = document.getElementById('submitRating');

starButtons.forEach(button => {
    button.addEventListener('click', function() {
        const rating = parseInt(this.getAttribute('data-rating'));
        selectedRating.value = rating;
        
        // Update star display
        starButtons.forEach((btn, index) => {
            const starIcon = btn.querySelector('i');
            if (index < rating) {
                starIcon.className = 'fas fa-star fa-2x text-warning';
            } else {
                starIcon.className = 'far fa-star fa-2x';
            }
        });
        
        submitRating.disabled = false;
    });
    
    // Hover effect
    button.addEventListener('mouseenter', function() {
        const rating = parseInt(this.getAttribute('data-rating'));
        starButtons.forEach((btn, index) => {
            const starIcon = btn.querySelector('i');
            if (index < rating) {
                starIcon.className = 'fas fa-star fa-2x text-warning';
            }
        });
    });
    
    button.addEventListener('mouseleave', function() {
        const currentRating = parseInt(selectedRating.value);
        starButtons.forEach((btn, index) => {
            const starIcon = btn.querySelector('i');
            if (currentRating === 0) {
                starIcon.className = 'far fa-star fa-2x';
            } else if (index < currentRating) {
                starIcon.className = 'fas fa-star fa-2x text-warning';
            } else {
                starIcon.className = 'far fa-star fa-2x';
            }
        });
    });
});

// Toggle bookmark
function toggleBookmark(resourceId) {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': '<?php echo Auth::csrfToken(); ?>'
        },
        body: 'action=bookmark'
    })
    .then(response => response.text())
    .then(() => {
        window.location.reload();
    });
}

// Share resource
function shareResource() {
    const url = window.location.href;
    const title = document.title;
    const text = 'Check out this resource on WEZO CAMPUS HUB';
    
    if (navigator.share) {
        navigator.share({
            title: title,
            text: text,
            url: url
        });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}

// Like comment
function likeComment(commentId) {
    fetch('/api/comments/like.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?php echo Auth::csrfToken(); ?>'
        },
        body: JSON.stringify({ comment_id: commentId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Thank you for your feedback!');
        }
    });
}

// Reply to comment
function replyToComment(commentId, username) {
    const replyForm = document.createElement('div');
    replyForm.className = 'reply-form mt-3';
    replyForm.innerHTML = `
        <div class="card">
            <div class="card-body">
                <form method="POST" class="reply-form-inner">
                    <input type="hidden" name="parent_id" value="${commentId}">
                    <input type="hidden" name="action" value="reply">
                    <div class="mb-3">
                        <label class="form-label">Replying to @${username}</label>
                        <textarea class="form-control" name="comment" rows="2" 
                                  placeholder="Write your reply..." required></textarea>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary me-2" 
                                onclick="this.closest('.reply-form').remove()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary">
                            Post Reply
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    const commentItem = event.target.closest('.comment-item');
    commentItem.appendChild(replyForm);
}

// Delete comment
function deleteComment(commentId) {
    if (confirm('Are you sure you want to delete this comment?')) {
        fetch('/api/comments/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo Auth::csrfToken(); ?>'
            },
            body: JSON.stringify({ comment_id: commentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Comment deleted successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Format file size helper
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
</script>

<?php include '../../templates/footer.php'; ?>