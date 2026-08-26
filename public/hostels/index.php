<?php
/**
 * WEZO CAMPUS HUB - Hostels Finder
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
$location = $_GET['location'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$amenities = $_GET['amenities'] ?? [];
$sort = $_GET['sort'] ?? 'rating';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;

// Build query
$where = "WHERE is_approved = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (name LIKE ? OR description LIKE ? OR location LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($location)) {
    $where .= " AND location LIKE ?";
    $params[] = "%{$location}%";
}

if (!empty($minPrice) && is_numeric($minPrice)) {
    $where .= " AND price_per_month >= ?";
    $params[] = floatval($minPrice);
}

if (!empty($maxPrice) && is_numeric($maxPrice)) {
    $where .= " AND price_per_month <= ?";
    $params[] = floatval($maxPrice);
}

// Handle amenities filter
if (!empty($amenities) && is_array($amenities)) {
    foreach ($amenities as $amenity) {
        $where .= " AND amenities LIKE ?";
        $params[] = "%\"$amenity\"%";
    }
}

// Get sorting
$orderBy = match($sort) {
    'price_low' => 'price_per_month ASC',
    'price_high' => 'price_per_month DESC',
    'available' => 'available_rooms DESC',
    'name' => 'name ASC',
    default => 'rating DESC, review_count DESC'
};

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM hostels $where";
$total = $db->fetchColumn($countSql, $params);
$totalPages = ceil($total / $perPage);

// Get hostels with pagination
$offset = ($page - 1) * $perPage;
$hostelsSql = "SELECT * FROM hostels $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$hostels = $db->fetchAll($hostelsSql, $params);

// Get unique locations for filter
$locations = $db->fetchAll("SELECT DISTINCT location FROM hostels WHERE location IS NOT NULL AND location != '' ORDER BY location");

// Common amenities
$commonAmenities = [
    'WiFi' => 'wifi',
    '24/7 Security' => 'security',
    'Laundry' => 'laundry',
    'Study Room' => 'study',
    'Hot Water' => 'hot-water',
    'Parking' => 'parking',
    'Gym' => 'gym',
    'Cafeteria' => 'cafeteria',
    'TV Room' => 'tv',
    'Cleaning Service' => 'cleaning'
];

// Set page title
$pageTitle = "Find Hostels" . ($search ? " - Search: {$search}" : "") . " - WEZO CAMPUS HUB";

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
                        <i class="fas fa-bed text-info me-2"></i> Find Hostels
                    </h1>
                    <p class="text-muted mb-0">
                        Browse and compare student accommodation near campus
                    </p>
                </div>
                <?php if (Auth::isAdmin() || Auth::isModerator()): ?>
                <a href="/hostels/add.php" class="btn btn-info">
                    <i class="fas fa-plus me-1"></i> Add Hostel
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
                               placeholder="Search hostels by name or description...">
                    </div>
                </div>
                
                <!-- Location Filter -->
                <div class="col-md-3">
                    <select class="form-select" name="location">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc['location']); ?>" 
                                <?php echo $location === $loc['location'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc['location']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Price Range -->
                <div class="col-md-2">
                    <input type="number" class="form-control" name="min_price" value="<?php echo htmlspecialchars($minPrice); ?>" 
                           placeholder="Min price" min="0" step="100">
                </div>
                
                <div class="col-md-2">
                    <input type="number" class="form-control" name="max_price" value="<?php echo htmlspecialchars($maxPrice); ?>" 
                           placeholder="Max price" min="0" step="100">
                </div>
                
                <!-- Submit Button -->
                <div class="col-md-1">
                    <button type="submit" class="btn btn-info w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
            
            <!-- Advanced Filters -->
            <div class="mt-3">
                <a class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" href="#advancedFilters">
                    <i class="fas fa-sliders-h me-1"></i> Advanced Filters
                </a>
                
                <div class="collapse mt-3" id="advancedFilters">
                    <!-- Amenities -->
                    <div class="mb-3">
                        <label class="form-label small">Amenities</label>
                        <div class="row">
                            <?php foreach ($commonAmenities as $name => $value): ?>
                            <div class="col-md-3 col-sm-4 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="amenities[]" 
                                           value="<?php echo $value; ?>" 
                                           id="amenity_<?php echo $value; ?>"
                                           <?php echo in_array($value, $amenities) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="amenity_<?php echo $value; ?>">
                                        <?php echo $name; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Sort Options -->
                    <div class="row">
                        <div class="col-md-3">
                            <label for="sort" class="form-label small">Sort By</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="available" <?php echo $sort === 'available' ? 'selected' : ''; ?>>Most Available Rooms</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                            </select>
                        </div>
                        <div class="col-md-3 align-self-end">
                            <button type="submit" class="btn btn-outline-info w-100">
                                <i class="fas fa-search me-1"></i> Apply Filters
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
                <?php if ($search || $location || $minPrice || $maxPrice || !empty($amenities)): ?>
                <?php echo number_format($total); ?> hostel<?php echo $total != 1 ? 's' : ''; ?> found
                <?php if ($search): ?>
                for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
                <?php else: ?>
                Available Hostels
                <?php endif; ?>
            </h5>
        </div>
        <div>
            <small class="text-muted">
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </small>
        </div>
    </div>

    <!-- Hostels Grid -->
    <?php if (empty($hostels)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-bed fa-4x text-muted mb-4"></i>
            <h4 class="text-muted mb-3">No hostels found</h4>
            <p class="text-muted mb-4">
                <?php if ($search || $location || $minPrice || $maxPrice): ?>
                No hostels match your search criteria. Try different filters.
                <?php else: ?>
                No hostels are currently listed.
                <?php endif; ?>
            </p>
            <?php if (Auth::isAdmin() || Auth::isModerator()): ?>
            <a href="/hostels/add.php" class="btn btn-info">
                <i class="fas fa-plus me-1"></i> Add First Hostel
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <?php foreach ($hostels as $hostel): 
            $images = json_decode($hostel['images'] ?? '[]', true);
            $firstImage = !empty($images) ? $images[0] : 'default-hostel.jpg';
            $amenitiesList = json_decode($hostel['amenities'] ?? '[]', true);
            $availableAmenities = array_slice($amenitiesList, 0, 3);
        ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card h-100 shadow-sm border-hover">
                <!-- Hostel Image -->
                <div class="position-relative">
                    <a href="/hostels/details.php?id=<?php echo $hostel['id']; ?>">
                        <img src="/assets/uploads/hostels/<?php echo htmlspecialchars($firstImage); ?>" 
                             class="card-img-top" 
                             alt="<?php echo htmlspecialchars($hostel['name']); ?>"
                             style="height: 200px; object-fit: cover;">
                    </a>
                    
                    <!-- Featured Badge -->
                    <?php if ($hostel['is_featured']): ?>
                    <span class="position-absolute top-0 end-0 m-2">
                        <span class="badge bg-warning">
                            <i class="fas fa-star me-1"></i> Featured
                        </span>
                    </span>
                    <?php endif; ?>
                    
                    <!-- Price Badge -->
                    <span class="position-absolute bottom-0 start-0 m-2">
                        <span class="badge bg-dark bg-opacity-75">
                            KSh <?php echo number_format($hostel['price_per_month']); ?>/month
                        </span>
                    </span>
                </div>
                
                <!-- Card Body -->
                <div class="card-body">
                    <!-- Hostel Name -->
                    <h5 class="card-title mb-2">
                        <a href="/hostels/details.php?id=<?php echo $hostel['id']; ?>" 
                           class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($hostel['name']); ?>
                        </a>
                    </h5>
                    
                    <!-- Location -->
                    <p class="card-text text-muted mb-2">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <?php echo htmlspecialchars($hostel['location']); ?>
                    </p>
                    
                    <!-- Rating -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= floor($hostel['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-muted small">
                            <?php echo number_format($hostel['rating'], 1); ?> (<?php echo $hostel['review_count']; ?> reviews)
                        </span>
                    </div>
                    
                    <!-- Available Rooms -->
                    <div class="mb-3">
                        <span class="badge bg-<?php echo $hostel['available_rooms'] > 0 ? 'success' : 'danger'; ?>">
                            <i class="fas fa-door-open me-1"></i>
                            <?php echo $hostel['available_rooms']; ?> rooms available
                        </span>
                    </div>
                    
                    <!-- Amenities Preview -->
                    <?php if (!empty($availableAmenities)): ?>
                    <div class="mb-3">
                        <h6 class="small text-muted mb-2">Amenities:</h6>
                        <div class="amenities-preview">
                            <?php foreach ($availableAmenities as $amenity): ?>
                            <span class="badge bg-light text-dark border small me-1 mb-1">
                                <i class="fas fa-check text-success me-1"></i> <?php echo htmlspecialchars($amenity); ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if (count($amenitiesList) > 3): ?>
                            <span class="badge bg-light text-dark border small">
                                +<?php echo count($amenitiesList) - 3; ?> more
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Card Footer -->
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-flex justify-content-between">
                        <a href="/hostels/details.php?id=<?php echo $hostel['id']; ?>" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-eye me-1"></i> View Details
                        </a>
                        <?php if ($isLoggedIn): ?>
                        <button class="btn btn-sm btn-outline-danger favorite-btn" data-hostel-id="<?php echo $hostel['id']; ?>">
                            <i class="far fa-heart"></i> Save
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

    <!-- Compare Hostels -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-balance-scale text-primary me-2"></i> Compare Hostels
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="compareSelected()">
                        <i class="fas fa-chart-bar me-1"></i> Compare Selected
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Select up to 3 hostels to compare side by side:</p>
                    
                    <div class="row" id="compareList">
                        <?php foreach ($hostels as $hostel): ?>
                        <div class="col-md-4 mb-3">
                            <div class="form-check">
                                <input class="form-check-input compare-checkbox" 
                                       type="checkbox" 
                                       name="compare[]" 
                                       value="<?php echo $hostel['id']; ?>" 
                                       id="compare_<?php echo $hostel['id']; ?>"
                                       data-hostel-name="<?php echo htmlspecialchars($hostel['name']); ?>"
                                       onchange="updateCompareCount()">
                                <label class="form-check-label w-100" for="compare_<?php echo $hostel['id']; ?>">
                                    <div class="d-flex align-items-center">
                                        <?php 
                                        $images = json_decode($hostel['images'] ?? '[]', true);
                                        $firstImage = !empty($images) ? $images[0] : 'default-hostel.jpg';
                                        ?>
                                        <img src="/assets/uploads/hostels/<?php echo htmlspecialchars($firstImage); ?>" 
                                             alt="<?php echo htmlspecialchars($hostel['name']); ?>" 
                                             class="rounded me-3" 
                                             style="width: 50px; height: 50px; object-fit: cover;">
                                        <div>
                                            <div class="small"><?php echo htmlspecialchars($hostel['name']); ?></div>
                                            <div class="small text-muted">
                                                KSh <?php echo number_format($hostel['price_per_month']); ?>/month
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center">
                        <small class="text-muted" id="compareCount">0 hostels selected</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Map View -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-map-marked-alt text-success me-2"></i> Hostel Locations
                    </h5>
                </div>
                <div class="card-body">
                    <div id="hostelMap" style="height: 300px; border-radius: 8px; background: #f8f9fa;" 
                         class="d-flex align-items-center justify-content-center">
                        <div class="text-center">
                            <i class="fas fa-map fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">Interactive map coming soon</p>
                            <small class="text-muted">(Will show hostel locations with prices and availability)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Favorite button functionality
document.querySelectorAll('.favorite-btn').forEach(button => {
    button.addEventListener('click', function() {
        const hostelId = this.dataset.hostelId;
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
                item_type: 'hostel',
                item_id: hostelId
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
});

// Compare functionality
let selectedHostels = [];

function updateCompareCount() {
    const checkboxes = document.querySelectorAll('.compare-checkbox:checked');
    selectedHostels = Array.from(checkboxes).map(cb => ({
        id: cb.value,
        name: cb.dataset.hostelName
    }));
    
    document.getElementById('compareCount').textContent = 
        `${selectedHostels.length} hostel${selectedHostels.length !== 1 ? 's' : ''} selected`;
}

function compareSelected() {
    if (selectedHostels.length < 2) {
        alert('Please select at least 2 hostels to compare.');
        return;
    }
    
    if (selectedHostels.length > 3) {
        alert('Maximum 3 hostels can be compared at once.');
        return;
    }
    
    // Redirect to compare page with selected hostel IDs
    const hostelIds = selectedHostels.map(h => h.id).join(',');
    window.location.href = `/hostels/compare.php?ids=${hostelIds}`;
}

// Check favorite status for logged-in users
<?php if ($isLoggedIn): ?>
document.addEventListener('DOMContentLoaded', function() {
    const hostelIds = Array.from(document.querySelectorAll('[data-hostel-id]'))
        .map(el => el.dataset.hostelId);
    
    if (hostelIds.length > 0) {
        // Batch check favorite status
        fetch('/api/favorites.php?item_type=hostel&ids=' + hostelIds.join(','))
            .then(response => response.json())
            .then(data => {
                data.favorites.forEach(fav => {
                    const button = document.querySelector(`[data-hostel-id="${fav.item_id}"]`);
                    if (button) {
                        const icon = button.querySelector('i');
                        icon.classList.remove('far');
                        icon.classList.add('fas', 'text-danger');
                        button.classList.remove('btn-outline-danger');
                        button.classList.add('btn-danger');
                    }
                });
            });
    }
});
<?php endif; ?>

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

// Clear all filters
function clearFilters() {
    window.location.href = '/hostels/';
}
</script>

<style>
.border-hover {
    transition: all 0.3s ease;
}

.border-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    border-color: #0dcaf0;
}

.amenities-preview {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.compare-checkbox:checked + label {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 5px;
}
</style>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>