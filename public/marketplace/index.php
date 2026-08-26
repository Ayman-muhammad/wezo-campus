<?php
/**
 * WEZO CAMPUS HUB - Marketplace
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
$condition = $_GET['condition'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$location = $_GET['location'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = Core\Config::MARKETPLACE_PER_PAGE;

// Build query
$where = "WHERE m.status = 'active' AND m.is_approved = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (m.title LIKE ? OR m.description LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

if (!empty($category) && $category !== 'all') {
    $where .= " AND c.slug = ?";
    $params[] = $category;
}

if (!empty($condition) && $condition !== 'all') {
    $where .= " AND m.condition = ?";
    $params[] = $condition;
}

if (!empty($minPrice) && is_numeric($minPrice)) {
    $where .= " AND m.price >= ?";
    $params[] = floatval($minPrice);
}

if (!empty($maxPrice) && is_numeric($maxPrice)) {
    $where .= " AND m.price <= ?";
    $params[] = floatval($maxPrice);
}

if (!empty($location)) {
    $where .= " AND m.location LIKE ?";
    $params[] = "%{$location}%";
}

// Get sorting
$orderBy = match($sort) {
    'price_low' => 'm.price ASC',
    'price_high' => 'm.price DESC',
    'popular' => 'm.view_count DESC',
    'oldest' => 'm.created_at ASC',
    default => 'm.created_at DESC'
};

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM marketplace_items m 
             LEFT JOIN marketplace_categories c ON m.category_id = c.id 
             $where";
$total = $db->fetchColumn($countSql, $params);
$totalPages = ceil($total / $perPage);

// Get items with pagination
$offset = ($page - 1) * $perPage;
$itemsSql = "SELECT m.*, u.username, u.profile_pic, c.name as category_name, c.slug as category_slug 
             FROM marketplace_items m 
             LEFT JOIN users u ON m.user_id = u.id 
             LEFT JOIN marketplace_categories c ON m.category_id = c.id 
             $where 
             ORDER BY $orderBy 
             LIMIT $perPage OFFSET $offset";

$items = $db->fetchAll($itemsSql, $params);

// Get all categories for filter
$categories = $db->fetchAll("SELECT * FROM marketplace_categories ORDER BY name");

// Set page title
$pageTitle = "Marketplace" . ($search ? " - Search: {$search}" : "") . " - WEZO CAMPUS HUB";

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
                        <i class="fas fa-store text-success me-2"></i> Marketplace
                    </h1>
                    <p class="text-muted mb-0">
                        Buy and sell items with fellow students
                    </p>
                </div>
                <?php if ($isLoggedIn): ?>
                <a href="/marketplace/post.php" class="btn btn-success">
                    <i class="fas fa-plus me-1"></i> Sell Item
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
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search items...">
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div class="col-md-2">
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
                
                <!-- Condition Filter -->
                <div class="col-md-2">
                    <select class="form-select" name="condition">
                        <option value="all">Any Condition</option>
                        <option value="new" <?php echo $condition === 'new' ? 'selected' : ''; ?>>New</option>
                        <option value="like_new" <?php echo $condition === 'like_new' ? 'selected' : ''; ?>>Like New</option>
                        <option value="good" <?php echo $condition === 'good' ? 'selected' : ''; ?>>Good</option>
                        <option value="fair" <?php echo $condition === 'fair' ? 'selected' : ''; ?>>Fair</option>
                        <option value="poor" <?php echo $condition === 'poor' ? 'selected' : ''; ?>>Poor</option>
                    </select>
                </div>
                
                <!-- Price Range -->
                <div class="col-md-2">
                    <input type="number" class="form-control" name="min_price" value="<?php echo htmlspecialchars($minPrice); ?>" 
                           placeholder="Min price" min="0" step="0.01">
                </div>
                
                <div class="col-md-2">
                    <input type="number" class="form-control" name="max_price" value="<?php echo htmlspecialchars($maxPrice); ?>" 
                           placeholder="Max price" min="0" step="0.01">
                </div>
                
                <!-- Submit Button -->
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
            
            <!-- Advanced Filters (Toggle) -->
            <div class="mt-3">
                <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#advancedFilters">
                    <i class="fas fa-sliders-h me-1"></i> Advanced Filters
                </a>
                
                <div class="collapse mt-3" id="advancedFilters">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="location" class="form-label small">Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($location); ?>" 
                                   placeholder="Enter location (e.g., Campus Hostels)">
                        </div>
                        <div class="col-md-3">
                            <label for="sort" class="form-label small">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">&nbsp;</label>
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="fas fa-search me-1"></i> Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Summary -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="mb-0">
                <?php if ($search || $category !== '' || $condition !== '' || $minPrice || $maxPrice): ?>
                <?php echo number_format($total); ?> item<?php echo $total != 1 ? 's' : ''; ?> found
                <?php if ($search): ?>
                for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
                <?php else: ?>
                All Marketplace Items
                <?php endif; ?>
            </h5>
        </div>
        <div>
            <small class="text-muted">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </small>
        </div>
    </div>

    <!-- Items Grid -->
    <?php if (empty($items)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-store fa-4x text-muted mb-4"></i>
            <h4 class="text-muted mb-3">No items found</h4>
            <p class="text-muted mb-4">
                <?php if ($search || $category || $condition || $minPrice || $maxPrice): ?>
                No items match your search criteria. Try different filters.
                <?php else: ?>
                No items are currently listed for sale.
                <?php endif; ?>
            </p>
            <?php if ($isLoggedIn): ?>
            <a href="/marketplace/post.php" class="btn btn-success">
                <i class="fas fa-plus me-1"></i> Be the first to sell
            </a>
            <?php else: ?>
            <a href="/login.php" class="btn btn-success">
                <i class="fas fa-sign-in-alt me-1"></i> Login to sell items
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($items as $item): 
            $images = json_decode($item['images'] ?? '[]', true);
            $firstImage = !empty($images) ? $images[0] : 'default-item.jpg';
            $conditionColors = [
                'new' => 'success',
                'like_new' => 'info',
                'good' => 'primary',
                'fair' => 'warning',
                'poor' => 'secondary'
            ];
        ?>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
            <div class="card h-100 shadow-sm border-hover">
                <!-- Image -->
                <div class="position-relative">
                    <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>">
                        <img src="/assets/uploads/marketplace/<?php echo htmlspecialchars($firstImage); ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                             style="height: 180px; object-fit: cover;">
                    </a>
                    
                    <!-- Status Badge -->
                    <span class="position-absolute top-0 end-0 m-2">
                        <span class="badge bg-<?php echo $conditionColors[$item['condition']] ?? 'secondary'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $item['condition'])); ?>
                        </span>
                    </span>
                    
                    <!-- Price Badge -->
                    <span class="position-absolute bottom-0 start-0 m-2">
                        <span class="badge bg-dark bg-opacity-75">
                            KSh <?php echo number_format($item['price'], 2); ?>
                        </span>
                    </span>
                </div>
                
                <!-- Card Body -->
                <div class="card-body">
                    <!-- Category -->
                    <div class="mb-2">
                        <a href="?category=<?php echo urlencode($item['category_slug']); ?>" 
                           class="badge bg-light text-dark border text-decoration-none small">
                            <?php echo htmlspecialchars($item['category_name']); ?>
                        </a>
                    </div>
                    
                    <!-- Title -->
                    <h6 class="card-title mb-2">
                        <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>" class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </a>
                    </h6>
                    
                    <!-- Location -->
                    <p class="card-text small text-muted mb-2">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <?php echo htmlspecialchars($item['location'] ?? 'Location not specified'); ?>
                    </p>
                    
                    <!-- Seller Info -->
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <?php if ($item['profile_pic'] && $item['profile_pic'] != 'default.png'): ?>
                            <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($item['profile_pic']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['username']); ?>" 
                                 class="rounded-circle me-2" width="20" height="20">
                            <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                 style="width: 20px; height: 20px;">
                                <i class="fas fa-user text-white" style="font-size: 10px;"></i>
                            </div>
                            <?php endif; ?>
                            <span class="small"><?php echo htmlspecialchars($item['username']); ?></span>
                        </div>
                        <small class="text-muted">
                            <?php echo date('M d', strtotime($item['created_at'])); ?>
                        </small>
                    </div>
                </div>
                
                <!-- Card Footer -->
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-flex justify-content-between">
                        <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                        <?php if ($item['negotiable']): ?>
                        <span class="badge bg-warning align-self-center">
                            <i class="fas fa-handshake me-1"></i> Negotiable
                        </span>
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
            <?php endforeach; ?>
            
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
                        <i class="fas fa-tags me-2"></i> Browse by Category
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($categories as $categoryItem): 
                            $itemCount = $db->fetchColumn(
                                "SELECT COUNT(*) FROM marketplace_items WHERE category_id = ? AND status = 'active' AND is_approved = 1",
                                [$categoryItem['id']]
                            );
                        ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="?category=<?php echo urlencode($categoryItem['slug']); ?>" 
                               class="category-card text-decoration-none">
                                <div class="card border h-100 text-center hover-shadow">
                                    <div class="card-body">
                                        <div class="category-icon mb-3">
                                            <i class="fas fa-<?php echo $categoryItem['icon'] ?? 'tag'; ?> fa-3x text-success"></i>
                                        </div>
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($categoryItem['name']); ?></h6>
                                        <p class="card-text small text-muted mb-0">
                                            <?php echo number_format($itemCount); ?> items
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

    <!-- Safety Guidelines -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i> Safety Guidelines
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-3 mb-md-0">
                                <li class="mb-2">Meet in safe, public places on campus</li>
                                <li class="mb-2">Inspect items before purchasing</li>
                                <li class="mb-2">Use campus security escort service if needed</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul>
                                <li class="mb-2">Never share personal information unnecessarily</li>
                                <li class="mb-2">Report suspicious listings or users</li>
                                <li class="mb-0">Trust your instincts - if something feels wrong, it probably is</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Quick search functionality
const searchInput = document.querySelector('input[name="q"]');
if (searchInput) {
    let debounceTimer;
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
}

// Price range validation
document.querySelector('input[name="min_price"]').addEventListener('change', function() {
    const minPrice = parseFloat(this.value);
    const maxPrice = parseFloat(document.querySelector('input[name="max_price"]').value);
    
    if (minPrice && maxPrice && minPrice > maxPrice) {
        alert('Minimum price cannot be greater than maximum price.');
        this.value = '';
    }
});

document.querySelector('input[name="max_price"]').addEventListener('change', function() {
    const maxPrice = parseFloat(this.value);
    const minPrice = parseFloat(document.querySelector('input[name="min_price"]').value);
    
    if (minPrice && maxPrice && maxPrice < minPrice) {
        alert('Maximum price cannot be less than minimum price.');
        this.value = '';
    }
});

// Clear filters
function clearFilters() {
    document.querySelector('input[name="q"]').value = '';
    document.querySelector('select[name="category"]').value = 'all';
    document.querySelector('select[name="condition"]').value = 'all';
    document.querySelector('input[name="min_price"]').value = '';
    document.querySelector('input[name="max_price"]').value = '';
    document.querySelector('input[name="location"]').value = '';
    document.querySelector('select[name="sort"]').value = 'newest';
}

// Add clear filters button if filters are active
if (window.location.search.includes('?') && window.location.search.length > 1) {
    const filterForm = document.querySelector('form');
    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'btn btn-sm btn-outline-danger mt-3';
    clearBtn.innerHTML = '<i class="fas fa-times me-1"></i> Clear All Filters';
    clearBtn.onclick = function() {
        clearFilters();
        window.location.href = '/marketplace/';
    };
    filterForm.appendChild(clearBtn);
}
</script>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>