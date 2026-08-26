<?php
/**
 * WEZO CAMPUS HUB - Study Notes
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

// Get search parameters
$search = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = Core\Config::NOTES_PER_PAGE;

// Build query
$where = "WHERE n.is_approved = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (n.title LIKE ? OR n.description LIKE ? OR n.tags LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($category) && $category !== 'all') {
    $where .= " AND c.slug = ?";
    $params[] = $category;
}

// Get sorting
$orderBy = match($sort) {
    'popular' => 'n.download_count DESC',
    'views' => 'n.view_count DESC',
    'rating' => 'n.like_count DESC',
    'oldest' => 'n.created_at ASC',
    default => 'n.created_at DESC'
};

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM notes n 
             LEFT JOIN note_categories c ON n.category_id = c.id 
             $where";
$total = $db->fetchColumn($countSql, $params);
$totalPages = ceil($total / $perPage);

// Get notes with pagination
$offset = ($page - 1) * $perPage;
$notesSql = "SELECT n.*, u.username, u.profile_pic, c.name as category_name, c.slug as category_slug 
             FROM notes n 
             LEFT JOIN users u ON n.user_id = u.id 
             LEFT JOIN note_categories c ON n.category_id = c.id 
             $where 
             ORDER BY $orderBy 
             LIMIT $perPage OFFSET $offset";

$notes = $db->fetchAll($notesSql, $params);

// Get all categories for filter
$categories = $db->fetchAll("SELECT * FROM note_categories ORDER BY name");

// Set page title
$pageTitle = "Study Notes" . ($search ? " - Search: {$search}" : "") . " - WEZO CAMPUS HUB";

// Include header
include __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-2">
                        <i class="fas fa-book text-primary me-2"></i> Study Notes
                    </h1>
                    <p class="text-muted mb-0">
                        Browse and download study materials shared by students
                    </p>
                </div>
                <?php if ($isLoggedIn): ?>
                <a href="/notes/create.php" class="btn btn-primary">
                    <i class="fas fa-upload me-1"></i> Upload Notes
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <!-- Search Input -->
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search notes by title, description, or tags...">
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['slug']); ?>" 
                                <?php echo $category === $cat['slug'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Sort Filter -->
                <div class="col-md-2">
                    <select class="form-select" name="sort">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                        <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
                
                <!-- Submit Button -->
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0">
                <?php if ($search || $category !== ''): ?>
                <?php echo number_format($total); ?> note<?php echo $total != 1 ? 's' : ''; ?> found
                <?php if ($search): ?>
                for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
                <?php if ($category && $category !== 'all'): ?>
                in <span class="badge bg-primary"><?php 
                    foreach ($categories as $cat) {
                        if ($cat['slug'] === $category) {
                            echo htmlspecialchars($cat['name']);
                            break;
                        }
                    }
                ?></span>
                <?php endif; ?>
                <?php else: ?>
                All Study Notes
                <?php endif; ?>
            </h5>
        </div>
        <div>
            <small class="text-muted">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </small>
        </div>
    </div>

    <!-- Notes Grid -->
    <?php if (empty($notes)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-book fa-4x text-muted mb-4"></i>
            <h4 class="text-muted mb-3">No notes found</h4>
            <p class="text-muted mb-4">
                <?php if ($search): ?>
                No notes match your search criteria. Try different keywords.
                <?php else: ?>
                No study notes have been uploaded yet.
                <?php endif; ?>
            </p>
            <?php if ($isLoggedIn): ?>
            <a href="/notes/create.php" class="btn btn-primary">
                <i class="fas fa-upload me-1"></i> Be the first to upload notes
            </a>
            <?php else: ?>
            <a href="/login.php" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-1"></i> Login to upload notes
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($notes as $note): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-hover">
                <div class="card-body">
                    <!-- Category Badge -->
                    <div class="mb-2">
                        <a href="?category=<?php echo urlencode($note['category_slug']); ?>" 
                           class="badge bg-primary text-decoration-none">
                            <?php echo htmlspecialchars($note['category_name']); ?>
                        </a>
                        <?php if ($note['is_featured']): ?>
                        <span class="badge bg-warning">
                            <i class="fas fa-star me-1"></i> Featured
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Title -->
                    <h5 class="card-title">
                        <a href="/notes/view.php?id=<?php echo $note['id']; ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($note['title']); ?>
                        </a>
                    </h5>
                    
                    <!-- Description -->
                    <p class="card-text text-muted small mb-3">
                        <?php 
                        $description = strip_tags($note['description']);
                        echo strlen($description) > 120 ? substr($description, 0, 120) . '...' : $description;
                        ?>
                    </p>
                    
                    <!-- Tags -->
                    <?php if (!empty($note['tags'])): 
                        $tags = explode(',', $note['tags']);
                        $tags = array_slice($tags, 0, 3);
                    ?>
                    <div class="mb-3">
                        <?php foreach ($tags as $tag): ?>
                        <span class="badge bg-light text-dark border me-1 mb-1">
                            <?php echo htmlspecialchars(trim($tag)); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Stats -->
                    <div class="d-flex justify-content-between small text-muted mb-3">
                        <div>
                            <i class="fas fa-download me-1"></i> <?php echo $note['download_count']; ?>
                        </div>
                        <div>
                            <i class="fas fa-eye me-1"></i> <?php echo $note['view_count']; ?>
                        </div>
                        <div>
                            <i class="fas fa-heart me-1"></i> <?php echo $note['like_count']; ?>
                        </div>
                    </div>
                    
                    <!-- Author and Date -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <?php if ($note['profile_pic'] && $note['profile_pic'] != 'default.png'): ?>
                            <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($note['profile_pic']); ?>" 
                                 alt="<?php echo htmlspecialchars($note['username']); ?>" 
                                 class="rounded-circle me-2" width="24" height="24">
                            <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                 style="width: 24px; height: 24px;">
                                <i class="fas fa-user text-white small"></i>
                            </div>
                            <?php endif; ?>
                            <span class="small"><?php echo htmlspecialchars($note['username']); ?></span>
                        </div>
                        <span class="small text-muted">
                            <?php echo date('M d, Y', strtotime($note['created_at'])); ?>
                        </span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-flex justify-content-between">
                        <a href="/notes/view.php?id=<?php echo $note['id']; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                        <a href="/notes/view.php?id=<?php echo $note['id']; ?>&download=1" 
                           class="btn btn-sm btn-outline-success">
                            <i class="fas fa-download me-1"></i> Download
                        </a>
                        <?php if ($isLoggedIn): ?>
                        <button class="btn btn-sm btn-outline-danger favorite-btn" data-note-id="<?php echo $note['id']; ?>">
                            <i class="far fa-heart"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <!-- Previous Page -->
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php 
                    echo http_build_query(array_merge($_GET, ['page' => $page - 1]));
                ?>">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
            
            <!-- Page Numbers -->
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?<?php 
                    echo http_build_query(array_merge($_GET, ['page' => $i]));
                ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <!-- Next Page -->
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php 
                    echo http_build_query(array_merge($_GET, ['page' => $page + 1]));
                ?>">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Categories Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-folder me-2"></i> Browse by Category
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($categories as $categoryItem): 
                            $noteCount = $db->fetchColumn(
                                "SELECT COUNT(*) FROM notes WHERE category_id = ? AND is_approved = 1",
                                [$categoryItem['id']]
                            );
                        ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="?category=<?php echo urlencode($categoryItem['slug']); ?>" 
                               class="category-card text-decoration-none">
                                <div class="card border h-100 text-center hover-shadow">
                                    <div class="card-body">
                                        <div class="category-icon mb-3">
                                            <i class="fas fa-<?php echo $categoryItem['icon'] ?? 'book'; ?> fa-3x text-primary"></i>
                                        </div>
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($categoryItem['name']); ?></h6>
                                        <p class="card-text small text-muted mb-0">
                                            <?php echo number_format($noteCount); ?> notes
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <?php if ($isLoggedIn): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body text-center py-4">
                    <h4 class="text-primary mb-3">
                        <i class="fas fa-share-alt me-2"></i> Share Your Knowledge
                    </h4>
                    <p class="mb-4">
                        Help fellow students by sharing your study notes, summaries, and resources.
                        Your contributions make WEZO CAMPUS HUB better for everyone.
                    </p>
                    <a href="/notes/create.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-upload me-1"></i> Upload Your Notes
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Favorite button functionality
document.querySelectorAll('.favorite-btn').forEach(button => {
    button.addEventListener('click', function() {
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
        
        // Send AJAX request to toggle favorite
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
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
});

// Quick search functionality
const searchInput = document.querySelector('input[name="q"]');
if (searchInput) {
    // Debounce function for search
    let debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            // Auto-submit form after typing stops
            this.form.submit();
        }, 500);
    });
}
</script>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>