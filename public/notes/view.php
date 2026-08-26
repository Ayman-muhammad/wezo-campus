<?php
/**
 * WEZO CAMPUS HUB - View Notes
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

use Core\Auth;
use Core\Database;
use Core\Session;

// Initialize
Auth::init();
$db = Database::getInstance();
$isLoggedIn = Auth::isLoggedIn();
$user = Auth::user();

// Get note ID
$noteId = intval($_GET['id'] ?? 0);
if (!$noteId) {
    header('Location: /notes/');
    exit;
}

// Get note details
$note = $db->fetch("
    SELECT n.*, u.username, u.profile_pic, u.email, c.name as category_name, c.slug as category_slug 
    FROM notes n 
    LEFT JOIN users u ON n.user_id = u.id 
    LEFT JOIN note_categories c ON n.category_id = c.id 
    WHERE n.id = ? 
    AND (n.is_approved = 1 OR ? = 1 OR n.user_id = ?)
", [$noteId, Auth::isAdmin() || Auth::isModerator(), $isLoggedIn ? $user['id'] : 0]);

if (!$note) {
    Session::flash('error', 'Note not found or not approved yet.');
    header('Location: /notes/');
    exit;
}

// Check if user has purchased if it's a paid note
$hasAccess = true;
if (!$note['is_free'] && $isLoggedIn && $note['user_id'] != $user['id']) {
    // Check if user has purchased (this would be integrated with wallet system)
    $hasPurchased = false; // Placeholder for purchase check
    $hasAccess = $hasPurchased || Auth::isAdmin() || Auth::isModerator();
}

// Handle download request
if (isset($_GET['download']) && $hasAccess) {
    if ($note['file_path']) {
        $filePath = __DIR__ . '/../../public/assets/uploads/notes/' . $note['file_path'];
        
        if (file_exists($filePath)) {
            // Update download count
            $db->update('notes', 
                ['download_count' => $note['download_count'] + 1], 
                'id = ?', 
                [$noteId]
            );
            
            // Log download
            if ($isLoggedIn) {
                $db->insert('activity_logs', [
                    'user_id' => $user['id'],
                    'action' => 'note_download',
                    'description' => 'Downloaded note: ' . $note['title'],
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
            }
            
            // Set headers for download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($note['file_path']) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }
    }
    Session::flash('error', 'File not found.');
}

// Update view count (only once per session)
if (!isset($_SESSION['viewed_note_' . $noteId])) {
    $db->update('notes', 
        ['view_count' => $note['view_count'] + 1], 
        'id = ?', 
        [$noteId]
    );
    $_SESSION['viewed_note_' . $noteId] = true;
}

// Get similar notes
$similarNotes = $db->fetchAll("
    SELECT n.*, u.username, c.name as category_name 
    FROM notes n 
    LEFT JOIN users u ON n.user_id = u.id 
    LEFT JOIN note_categories c ON n.category_id = c.id 
    WHERE n.category_id = ? 
    AND n.id != ? 
    AND n.is_approved = 1 
    ORDER BY n.download_count DESC 
    LIMIT 4
", [$note['category_id'], $noteId]);

// Get reviews
$reviews = $db->fetchAll("
    SELECT r.*, u.username, u.profile_pic 
    FROM note_reviews r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE r.note_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 10
", [$noteId]);

// Calculate average rating
$avgRating = $db->fetchColumn("
    SELECT AVG(rating) FROM note_reviews WHERE note_id = ?
", [$noteId]);

// Check if current user has reviewed
$userReview = null;
if ($isLoggedIn) {
    $userReview = $db->fetch("
        SELECT * FROM note_reviews 
        WHERE note_id = ? AND user_id = ?
    ", [$noteId, $user['id']]);
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_review') {
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
            Session::flash('error', 'Security token invalid.');
        } elseif ($userReview) {
            Session::flash('error', 'You have already reviewed this note.');
        } else {
            $rating = intval($_POST['rating'] ?? 0);
            $review = trim($_POST['review'] ?? '');
            
            if ($rating < 1 || $rating > 5) {
                Session::flash('error', 'Please select a valid rating.');
            } else {
                try {
                    $db->insert('note_reviews', [
                        'note_id' => $noteId,
                        'user_id' => $user['id'],
                        'rating' => $rating,
                        'review' => $review,
                        'is_verified_purchase' => true // Placeholder
                    ]);
                    
                    // Update note like count
                    $newLikeCount = $note['like_count'] + ($rating >= 4 ? 1 : 0);
                    $db->update('notes', 
                        ['like_count' => $newLikeCount], 
                        'id = ?', 
                        [$noteId]
                    );
                    
                    Session::flash('success', 'Thank you for your review!');
                    header('Location: /notes/view.php?id=' . $noteId);
                    exit;
                } catch (Exception $e) {
                    Session::flash('error', 'Failed to submit review.');
                }
            }
        }
    }
}

// Set page title
$pageTitle = htmlspecialchars($note['title']) . " - WEZO CAMPUS HUB";

// Include header
include __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <!-- Note Header -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/notes/">Notes</a></li>
                    <li class="breadcrumb-item"><a href="?category=<?php echo urlencode($note['category_slug']); ?>">
                        <?php echo htmlspecialchars($note['category_name']); ?>
                    </a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($note['title']); ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Note Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Note Header -->
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <div class="mb-2">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($note['category_name']); ?></span>
                                <?php if ($note['is_featured']): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-star me-1"></i> Featured
                                </span>
                                <?php endif; ?>
                                <?php if (!$note['is_free']): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-tag me-1"></i> Premium
                                </span>
                                <?php endif; ?>
                            </div>
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($note['title']); ?></h1>
                            
                            <!-- Author Info -->
                            <div class="d-flex align-items-center mb-3">
                                <?php if ($note['profile_pic'] && $note['profile_pic'] != 'default.png'): ?>
                                <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($note['profile_pic']); ?>" 
                                     alt="<?php echo htmlspecialchars($note['username']); ?>" 
                                     class="rounded-circle me-2" width="32" height="32">
                                <?php else: ?>
                                <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                     style="width: 32px; height: 32px;">
                                    <i class="fas fa-user text-white"></i>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <div class="small">
                                        <strong><?php echo htmlspecialchars($note['username']); ?></strong>
                                        <span class="text-muted ms-2">
                                            <?php echo date('F d, Y', strtotime($note['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="small text-muted">
                                        <?php if ($note['user_id'] == ($user['id'] ?? 0)): ?>
                                        <span class="text-primary">Your note</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats -->
                        <div class="text-end">
                            <div class="d-flex flex-column align-items-end">
                                <div class="mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= floor($avgRating) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-1">(<?php echo count($reviews); ?>)</small>
                                </div>
                                <div class="small text-muted">
                                    <i class="fas fa-download me-1"></i> <?php echo $note['download_count']; ?> downloads
                                </div>
                                <div class="small text-muted">
                                    <i class="fas fa-eye me-1"></i> <?php echo $note['view_count']; ?> views
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <h5 class="mb-2">Description</h5>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['description'])); ?></p>
                    </div>
                    
                    <!-- Tags -->
                    <?php if (!empty($note['tags'])): 
                        $tags = explode(',', $note['tags']);
                    ?>
                    <div class="mb-4">
                        <h6 class="mb-2">Tags</h6>
                        <div>
                            <?php foreach ($tags as $tag): ?>
                            <a href="/notes/?q=<?php echo urlencode(trim($tag)); ?>" 
                               class="badge bg-light text-dark border me-1 mb-1 text-decoration-none">
                                <?php echo htmlspecialchars(trim($tag)); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Content/File -->
                    <div class="mb-4">
                        <?php if ($note['content']): ?>
                        <h5 class="mb-3">Content Preview</h5>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                        </div>
                        <?php elseif ($note['file_path']): ?>
                        <div class="file-preview border rounded p-4 text-center">
                            <i class="fas fa-file-pdf fa-4x text-danger mb-3"></i>
                            <h5>File Available for Download</h5>
                            <p class="text-muted mb-3">
                                <?php 
                                $fileType = $note['file_type'] ?? 'Document';
                                $fileSize = $note['file_size'] ? round($note['file_size'] / 1024, 2) . ' KB' : 'Unknown size';
                                echo "{$fileType} • {$fileSize}";
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center border-top pt-4">
                        <div>
                            <?php if ($isLoggedIn): ?>
                            <button class="btn btn-outline-danger me-2 favorite-btn" data-note-id="<?php echo $noteId; ?>">
                                <i class="far fa-heart"></i> Favorite
                            </button>
                            <?php endif; ?>
                            
                            <!-- Report Button -->
                            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reportModal">
                                <i class="fas fa-flag"></i> Report
                            </button>
                        </div>
                        
                        <div>
                            <?php if ($note['user_id'] == ($user['id'] ?? 0) || Auth::isAdmin() || Auth::isModerator()): ?>
                            <a href="/notes/edit.php?id=<?php echo $noteId; ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php endif; ?>
                            
                            <!-- Download/Purchase Button -->
                            <?php if ($hasAccess): ?>
                            <a href="/notes/view.php?id=<?php echo $noteId; ?>&download=1" class="btn btn-success">
                                <i class="fas fa-download me-1"></i> 
                                <?php if ($note['is_free']): ?>
                                Download Free
                                <?php else: ?>
                                Download (KSh <?php echo number_format($note['price'], 2); ?>)
                                <?php endif; ?>
                            </a>
                            <?php elseif (!$isLoggedIn): ?>
                            <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to Download
                            </a>
                            <?php else: ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#purchaseModal">
                                <i class="fas fa-shopping-cart me-1"></i> Purchase (KSh <?php echo number_format($note['price'], 2); ?>)
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reviews Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-star text-warning me-2"></i> Reviews
                        <small class="text-muted">(<?php echo count($reviews); ?>)</small>
                    </h5>
                    <?php if ($isLoggedIn && !$userReview && $hasAccess): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                        <i class="fas fa-plus me-1"></i> Add Review
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <!-- Average Rating -->
                    <div class="text-center mb-4">
                        <div class="display-4 text-warning mb-2"><?php echo number_format($avgRating, 1); ?></div>
                        <div class="mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= floor($avgRating) ? 'text-warning' : 'text-muted'; ?> fa-lg"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted mb-0">Based on <?php echo count($reviews); ?> review<?php echo count($reviews) != 1 ? 's' : ''; ?></p>
                    </div>
                    
                    <!-- Reviews List -->
                    <?php if (empty($reviews)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No reviews yet. Be the first to review!</p>
                        <?php if ($isLoggedIn && $hasAccess): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            <i class="fas fa-star me-1"></i> Write Review
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-item border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center">
                                    <?php if ($review['profile_pic'] && $review['profile_pic'] != 'default.png'): ?>
                                    <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($review['profile_pic']); ?>" 
                                         alt="<?php echo htmlspecialchars($review['username']); ?>" 
                                         class="rounded-circle me-2" width="32" height="32">
                                    <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                         style="width: 32px; height: 32px;">
                                        <i class="fas fa-user text-white small"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                        <?php if ($review['is_verified_purchase']): ?>
                                        <span class="badge bg-success small ms-2">Verified</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <?php if (!empty($review['review'])): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($reviews) > 5): ?>
                    <div class="text-center mt-3">
                        <a href="/notes/reviews.php?id=<?php echo $noteId; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i> View All Reviews
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Author Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-circle me-2"></i> About the Author
                    </h5>
                </div>
                <div class="card-body text-center">
                    <?php if ($note['profile_pic'] && $note['profile_pic'] != 'default.png'): ?>
                    <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($note['profile_pic']); ?>" 
                         alt="<?php echo htmlspecialchars($note['username']); ?>" 
                         class="rounded-circle mb-3" width="80" height="80">
                    <?php else: ?>
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-user text-white fa-2x"></i>
                    </div>
                    <?php endif; ?>
                    
                    <h5><?php echo htmlspecialchars($note['username']); ?></h5>
                    
                    <?php
                    $authorStats = $db->fetch("
                        SELECT 
                            COUNT(*) as total_notes,
                            SUM(download_count) as total_downloads,
                            AVG((SELECT AVG(rating) FROM note_reviews nr WHERE nr.note_id IN (
                                SELECT id FROM notes WHERE user_id = ?
                            ))) as avg_rating
                        FROM notes 
                        WHERE user_id = ? AND is_approved = 1
                    ", [$note['user_id'], $note['user_id']]);
                    ?>
                    
                    <div class="row text-center mt-3">
                        <div class="col-6 mb-3">
                            <div class="h5 text-primary"><?php echo $authorStats['total_notes'] ?? 0; ?></div>
                            <div class="small text-muted">Notes</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h5 text-success"><?php echo $authorStats['total_downloads'] ?? 0; ?></div>
                            <div class="small text-muted">Downloads</div>
                        </div>
                    </div>
                    
                    <a href="/profile.php?user=<?php echo $note['user_id']; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-user me-1"></i> View Profile
                    </a>
                </div>
            </div>
            
            <!-- Similar Notes -->
            <?php if (!empty($similarNotes)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-book me-2"></i> Similar Notes
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($similarNotes as $similar): ?>
                        <a href="/notes/view.php?id=<?php echo $similar['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 small"><?php echo htmlspecialchars($similar['title']); ?></h6>
                                <small class="text-muted"><?php echo $similar['download_count']; ?>↓</small>
                            </div>
                            <small class="text-muted d-block mb-1">
                                <?php echo htmlspecialchars($similar['category_name']); ?>
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($similar['username']); ?>
                            </small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Share Note -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-share-alt me-2"></i> Share
                    </h5>
                </div>
                <div class="card-body">
                    <div class="share-buttons">
                        <?php
                        $shareUrl = urlencode(Core\Config::APP_URL . $_SERVER['REQUEST_URI']);
                        $shareTitle = urlencode($note['title']);
                        ?>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $shareUrl; ?>" 
                           target="_blank" class="btn btn-outline-primary btn-sm me-2 mb-2">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?php echo $shareUrl; ?>&text=<?php echo $shareTitle; ?>" 
                           target="_blank" class="btn btn-outline-info btn-sm me-2 mb-2">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo $shareUrl; ?>" 
                           target="_blank" class="btn btn-outline-primary btn-sm me-2 mb-2">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="whatsapp://send?text=<?php echo $shareTitle . ' ' . $shareUrl; ?>" 
                           class="btn btn-outline-success btn-sm me-2 mb-2">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <button class="btn btn-outline-secondary btn-sm mb-2" onclick="copyToClipboard()">
                            <i class="fas fa-link"></i>
                        </button>
                    </div>
                    
                    <!-- Copy Link -->
                    <div class="input-group mt-2">
                        <input type="text" class="form-control form-control-sm" 
                               value="<?php echo Core\Config::APP_URL . $_SERVER['REQUEST_URI']; ?>" 
                               id="shareLink" readonly>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyToClipboard()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<?php if ($isLoggedIn && !$userReview && $hasAccess): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Write a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="submit_review">
                    
                    <!-- Rating -->
                    <div class="mb-3">
                        <label class="form-label">Rating *</label>
                        <div class="rating-input">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" 
                                   class="d-none" required>
                            <label for="star<?php echo $i; ?>" class="star-label">
                                <i class="far fa-star fa-2x"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <div class="invalid-feedback">Please select a rating.</div>
                    </div>
                    
                    <!-- Review -->
                    <div class="mb-3">
                        <label for="reviewText" class="form-label">Review (Optional)</label>
                        <textarea class="form-control" id="reviewText" name="review" rows="4" 
                                  placeholder="Share your experience with these notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchase Notes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-shopping-cart fa-3x text-success mb-3"></i>
                <h5><?php echo htmlspecialchars($note['title']); ?></h5>
                <p class="text-muted">by <?php echo htmlspecialchars($note['username']); ?></p>
                
                <div class="display-4 text-success mb-4">KSh <?php echo number_format($note['price'], 2); ?></div>
                
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-2"></i>
                    After purchase, you'll have unlimited access to download these notes.
                </div>
                
                <form id="purchaseForm">
                    <input type="hidden" name="note_id" value="<?php echo $noteId; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-lock me-2"></i> Purchase with Wallet
                        </button>
                        <button type="button" class="btn btn-outline-primary">
                            <i class="fas fa-mobile-alt me-2"></i> Pay with M-Pesa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="reportForm">
                <div class="modal-header">
                    <h5 class="modal-title">Report Content</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_type" value="note">
                    <input type="hidden" name="item_id" value="<?php echo $noteId; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for reporting *</label>
                        <select class="form-select" name="reason" required>
                            <option value="">Select a reason</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="copyright">Copyright violation</option>
                            <option value="inaccurate">Inaccurate information</option>
                            <option value="spam">Spam or advertising</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reportDescription" class="form-label">Additional details (optional)</label>
                        <textarea class="form-control" id="reportDescription" name="description" rows="3" 
                                  placeholder="Please provide more details about your report..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Favorite button
const favoriteBtn = document.querySelector('.favorite-btn');
if (favoriteBtn) {
    favoriteBtn.addEventListener('click', function() {
        const noteId = this.dataset.noteId;
        const icon = this.querySelector('i');
        
        // Toggle visual state
        if (icon.classList.contains('far')) {
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-danger');
            this.classList.remove('btn-outline-danger');
            this.classList.add('btn-danger');
        } else {
            icon.classList.remove('fas', 'text-danger');
            icon.classList.add('far');
            this.classList.remove('btn-danger');
            this.classList.add('btn-outline-danger');
        }
        
        // Send AJAX request
        fetch('/api/favorites.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                action: 'toggle',
                item_type: 'note',
                item_id: noteId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // Revert visual state if failed
                if (icon.classList.contains('fas')) {
                    icon.classList.remove('fas', 'text-danger');
                    icon.classList.add('far');
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-outline-danger');
                } else {
                    icon.classList.remove('far');
                    icon.classList.add('fas', 'text-danger');
                    this.classList.remove('btn-outline-danger');
                    this.classList.add('btn-danger');
                }
            }
        });
    });
}

// Star rating in review modal
document.querySelectorAll('.star-label').forEach((label, index) => {
    label.addEventListener('mouseover', function() {
        const starIndex = index + 1;
        document.querySelectorAll('.star-label').forEach((l, i) => {
            const icon = l.querySelector('i');
            if (i < starIndex) {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-warning');
            } else {
                icon.classList.remove('fas', 'text-warning');
                icon.classList.add('far');
            }
        });
    });
    
    label.addEventListener('click', function() {
        const starIndex = index + 1;
        document.querySelectorAll('.star-label').forEach((l, i) => {
            const icon = l.querySelector('i');
            if (i < starIndex) {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-warning');
            } else {
                icon.classList.remove('fas', 'text-warning');
                icon.classList.add('far');
            }
        });
    });
});

// Reset stars when modal closes
const reviewModal = document.getElementById('reviewModal');
if (reviewModal) {
    reviewModal.addEventListener('hidden.bs.modal', function() {
        document.querySelectorAll('.star-label').forEach(label => {
            const icon = label.querySelector('i');
            icon.classList.remove('fas', 'text-warning');
            icon.classList.add('far');
        });
    });
}

// Copy to clipboard
function copyToClipboard() {
    const copyText = document.getElementById("shareLink");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // For mobile devices
    
    navigator.clipboard.writeText(copyText.value).then(() => {
        // Show success message
        const originalText = event.target.innerHTML;
        event.target.innerHTML = '<i class="fas fa-check"></i> Copied!';
        setTimeout(() => {
            event.target.innerHTML = originalText;
        }, 2000);
    });
}

// Report form submission
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
    
    fetch('/api/reports.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Report submitted successfully. Thank you!');
            bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Purchase form submission
document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
    
    fetch('/api/purchases.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Purchase successful! You can now download the notes.');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Check if note is already favorited
<?php if ($isLoggedIn): ?>
fetch('/api/favorites.php?item_type=note&item_id=<?php echo $noteId; ?>')
    .then(response => response.json())
    .then(data => {
        if (data.is_favorited && favoriteBtn) {
            const icon = favoriteBtn.querySelector('i');
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-danger');
            favoriteBtn.classList.remove('btn-outline-danger');
            favoriteBtn.classList.add('btn-danger');
        }
    });
<?php endif; ?>
</script>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>