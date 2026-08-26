<?php
/**
 * WEZO CAMPUS HUB - Marketplace Categories
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

// Get all categories with item counts
$categories = $db->fetchAll("
    SELECT c.*, 
           (SELECT COUNT(*) FROM marketplace_items m 
            WHERE m.category_id = c.id 
            AND m.status = 'active' 
            AND m.is_approved = 1) as item_count
    FROM marketplace_categories c 
    ORDER BY c.name
");

// Get featured items from each category
$featuredItems = [];
foreach ($categories as $category) {
    $items = $db->fetchAll("
        SELECT m.*, u.username 
        FROM marketplace_items m 
        LEFT JOIN users u ON m.user_id = u.id 
        WHERE m.category_id = ? 
        AND m.status = 'active' 
        AND m.is_approved = 1 
        ORDER BY m.view_count DESC 
        LIMIT 3
    ", [$category['id']]);
    
    if ($items) {
        $featuredItems[$category['id']] = $items;
    }
}

// Get recently added items
$recentItems = $db->fetchAll("
    SELECT m.*, u.username, c.name as category_name, c.slug as category_slug 
    FROM marketplace_items m 
    LEFT JOIN users u ON m.user_id = u.id 
    LEFT JOIN marketplace_categories c ON m.category_id = c.id 
    WHERE m.status = 'active' 
    AND m.is_approved = 1 
    ORDER BY m.created_at DESC 
    LIMIT 12
");

// Set page title
$pageTitle = "Marketplace Categories - WEZO CAMPUS HUB";

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
                        <i class="fas fa-tags text-success me-2"></i> Marketplace Categories
                    </h1>
                    <p class="text-muted mb-0">
                        Browse items by category
                    </p>
                </div>
                <a href="/marketplace/" class="btn btn-outline-success">
                    <i class="fas fa-arrow-left me-1"></i> Back to Marketplace
                </a>
            </div>
        </div>
    </div>

    <!-- Categories Grid -->
    <div class="row mb-5">
        <?php foreach ($categories as $category): ?>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
            <div class="card h-100 shadow-sm border-hover">
                <div class="card-body text-center">
                    <!-- Category Icon -->
                    <div class="category-icon mb-3">
                        <i class="fas fa-<?php echo $category['icon'] ?? 'tag'; ?> fa-3x text-success"></i>
                    </div>
                    
                    <!-- Category Name -->
                    <h5 class="card-title mb-2">
                        <a href="/marketplace/?category=<?php echo urlencode($category['slug']); ?>" 
                           class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </a>
                    </h5>
                    
                    <!-- Item Count -->
                    <p class="text-muted mb-3">
                        <?php echo number_format($category['item_count']); ?> item<?php echo $category['item_count'] != 1 ? 's' : ''; ?> available
                    </p>
                    
                    <!-- Browse Button -->
                    <a href="/marketplace/?category=<?php echo urlencode($category['slug']); ?>" 
                       class="btn btn-outline-success btn-sm">
                        <i class="fas fa-search me-1"></i> Browse
                    </a>
                </div>
                
                <!-- Featured Items (if any) -->
                <?php if (isset($featuredItems[$category['id']])): ?>
                <div class="card-footer bg-white border-top-0 pt-0">
                    <h6 class="small text-muted mb-2">Featured Items:</h6>
                    <div class="featured-items">
                        <?php foreach ($featuredItems[$category['id']] as $item): ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="flex-shrink-0">
                                <?php 
                                $images = json_decode($item['images'] ?? '[]', true);
                                $firstImage = !empty($images) ? $images[0] : 'default-item.jpg';
                                ?>
                                <img src="/assets/uploads/marketplace/<?php echo htmlspecialchars($firstImage); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     class="rounded" 
                                     style="width: 40px; height: 40px; object-fit: cover;">
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>" 
                                   class="text-decoration-none small">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                                <div class="small text-success">
                                    KSh <?php echo number_format($item['price'], 2); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Recently Added Items -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-clock text-info me-2"></i> Recently Added Items
                    </h5>
                    <a href="/marketplace/?sort=newest" class="btn btn-sm btn-outline-info">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentItems)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-store fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No items have been listed yet.</p>
                        <?php if (Auth::isLoggedIn()): ?>
                        <a href="/marketplace/post.php" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i> Be the first to list
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($recentItems as $item): 
                            $images = json_decode($item['images'] ?? '[]', true);
                            $firstImage = !empty($images) ? $images[0] : 'default-item.jpg';
                        ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="card h-100 border">
                                <div class="position-relative">
                                    <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>">
                                        <img src="/assets/uploads/marketplace/<?php echo htmlspecialchars($firstImage); ?>" 
                                             class="card-img-top" 
                                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             style="height: 150px; object-fit: cover;">
                                    </a>
                                    <span class="position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-light text-dark small">
                                            <?php echo htmlspecialchars($item['category_name']); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                    </h6>
                                    <p class="card-text text-success mb-2">
                                        KSh <?php echo number_format($item['price'], 2); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($item['username']); ?>
                                        </small>
                                        <small class="text-muted">
                                            <?php echo date('M d', strtotime($item['created_at'])); ?>
                                        </small>
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

    <!-- Popular Categories -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-fire text-warning me-2"></i> Most Popular Categories
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        // Sort categories by item count
                        usort($categories, function($a, $b) {
                            return $b['item_count'] <=> $a['item_count'];
                        });
                        
                        // Get top 4 categories
                        $topCategories = array_slice($categories, 0, 4);
                        ?>
                        
                        <?php foreach ($topCategories as $category): ?>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="popular-category text-center">
                                <div class="popular-icon mb-2">
                                    <i class="fas fa-<?php echo $category['icon'] ?? 'tag'; ?> fa-2x text-warning"></i>
                                </div>
                                <h6 class="mb-1">
                                    <a href="/marketplace/?category=<?php echo urlencode($category['slug']); ?>" 
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </a>
                                </h6>
                                <div class="small text-muted">
                                    <?php echo number_format($category['item_count']); ?> items
                                </div>
                                <div class="progress mt-2" style="height: 5px;">
                                    <?php
                                    // Calculate percentage (max 100 items for scale)
                                    $percentage = min(100, ($category['item_count'] / 100) * 100);
                                    ?>
                                    <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-question-circle me-2"></i> How Marketplace Works
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="step-icon mb-2">
                                <i class="fas fa-search fa-2x text-primary"></i>
                            </div>
                            <h6>1. Browse</h6>
                            <p class="small text-muted mb-0">Find items by category or search</p>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="step-icon mb-2">
                                <i class="fas fa-comment fa-2x text-success"></i>
                            </div>
                            <h6>2. Contact</h6>
                            <p class="small text-muted mb-0">Message the seller directly</p>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="step-icon mb-2">
                                <i class="fas fa-handshake fa-2x text-warning"></i>
                            </div>
                            <h6>3. Meet</h6>
                            <p class="small text-muted mb-0">Meet in safe campus location</p>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="step-icon mb-2">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                            <h6>4. Complete</h6>
                            <p class="small text-muted mb-0">Inspect item and make payment</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-hover {
    transition: all 0.3s ease;
}

.border-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    border-color: #198754;
}

.category-icon {
    transition: transform 0.3s ease;
}

.category-icon:hover {
    transform: scale(1.1);
}

.step-icon {
    transition: transform 0.3s ease;
}

.step-icon:hover {
    transform: scale(1.2);
}

.popular-category {
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.popular-category:hover {
    background: #e9ecef;
    transform: translateY(-3px);
}
</style>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>