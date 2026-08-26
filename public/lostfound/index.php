<?php
/**
 * WEZO CAMPUS HUB - Lost & Found System
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

// Initialize authentication
Auth::init();
$db = Database::getInstance();
$user = Auth::isLoggedIn() ? Auth::user() : null;

// Get filter parameters
$type = $_GET['type'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$status = $_GET['status'] ?? 'active';
$campus = $_GET['campus'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT lf.*, 
          u.first_name, u.last_name, u.username, u.avatar,
          c.name as campus_name,
          cat.name as category_name,
          (SELECT COUNT(*) FROM lostfound_matches WHERE lostfound_id = lf.id) as match_count,
          (SELECT COUNT(*) FROM lostfound_comments WHERE lostfound_id = lf.id) as comment_count
          FROM lostfound lf
          LEFT JOIN users u ON lf.user_id = u.id
          LEFT JOIN campuses c ON lf.campus_id = c.id
          LEFT JOIN lostfound_categories cat ON lf.category_id = cat.id
          WHERE 1=1";
$params = [];

// Apply filters
if ($type !== 'all') {
    $query .= " AND lf.type = ?";
    $params[] = $type;
}

if ($category !== 'all') {
    $query .= " AND lf.category_id = ?";
    $params[] = $category;
}

if ($status !== 'all') {
    $query .= " AND lf.status = ?";
    $params[] = $status;
}

if ($campus) {
    $query .= " AND lf.campus_id = ?";
    $params[] = $campus;
}

if ($search) {
    $query .= " AND (lf.title LIKE ? OR lf.description LIKE ? OR lf.location LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm);
}

// Get total count
$countQuery = str_replace(
    "SELECT lf.*, 
     u.first_name, u.last_name, u.username, u.avatar,
     c.name as campus_name,
     cat.name as category_name,
     (SELECT COUNT(*) FROM lostfound_matches WHERE lostfound_id = lf.id) as match_count,
     (SELECT COUNT(*) FROM lostfound_comments WHERE lostfound_id = lf.id) as comment_count",
    "SELECT COUNT(*) as total",
    $query
);
$totalItems = $db->fetchColumn($countQuery, $params);
$totalPages = ceil($totalItems / $limit);

// Order and pagination
$query .= " ORDER BY 
    CASE WHEN lf.status = 'found' THEN 1 ELSE 2 END,
    CASE WHEN lf.status = 'active' THEN 1 ELSE 2 END,
    lf.created_at DESC
    LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$items = $db->fetchAll($query, $params);

// Get categories and campuses for filter
$categories = $db->fetchAll("SELECT * FROM lostfound_categories ORDER BY name");
$campuses = $db->fetchAll("SELECT * FROM campuses ORDER BY name");

// Get statistics
$stats = $db->fetch("
    SELECT 
        (SELECT COUNT(*) FROM lostfound WHERE status = 'active' AND type = 'lost') as active_lost,
        (SELECT COUNT(*) FROM lostfound WHERE status = 'active' AND type = 'found') as active_found,
        (SELECT COUNT(*) FROM lostfound WHERE status = 'resolved') as resolved,
        (SELECT COUNT(*) FROM lostfound WHERE status = 'matched') as matched,
        (SELECT COUNT(*) FROM lostfound WHERE DATE(created_at) = CURDATE()) as today_reports
");

$pageTitle = "Lost & Found - WEZO CAMPUS HUB";
include '../../templates/header.php';
?>

<div class="container-fluid px-4 py-5">
    <!-- Page Header -->
    <div class="row align-items-center mb-5">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Lost & Found</li>
                </ol>
            </nav>
            <h1 class="display-5 fw-bold mb-3">
                <i class="fas fa-search text-primary me-3"></i>Lost & Found Center
            </h1>
            <p class="lead text-muted mb-0">
                Reuniting lost items with their owners. Report lost or found items to help our campus community.
            </p>
        </div>
        <div class="col-md-4 text-end">
            <?php if ($user): ?>
            <a href="report.php" class="btn btn-primary btn-lg px-4 py-3">
                <i class="fas fa-plus-circle me-2"></i>Report Item
            </a>
            <?php else: ?>
            <a href="/login.php?redirect=/lostfound/report.php" class="btn btn-outline-primary btn-lg px-4 py-3">
                <i class="fas fa-sign-in-alt me-2"></i>Login to Report
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="row mb-5">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start-primary border-3 border-start-4 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs fw-semibold text-primary text-uppercase mb-1">
                                Active Lost Items
                            </div>
                            <div class="h2 fw-bold text-gray-800"><?php echo $stats['active_lost']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-search-location fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start-success border-3 border-start-4 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs fw-semibold text-success text-uppercase mb-1">
                                Active Found Items
                            </div>
                            <div class="h2 fw-bold text-gray-800"><?php echo $stats['active_found']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding-heart fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start-info border-3 border-start-4 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs fw-semibold text-info text-uppercase mb-1">
                                Successfully Matched
                            </div>
                            <div class="h2 fw-bold text-gray-800"><?php echo $stats['matched']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-handshake fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start-warning border-3 border-start-4 h-100">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs fw-semibold text-warning text-uppercase mb-1">
                                Today's Reports
                            </div>
                            <div class="h2 fw-bold text-gray-800"><?php echo $stats['today_reports']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Section -->
    <div class="card shadow-lg border-0 mb-5">
        <div class="card-header bg-white py-4">
            <h5 class="mb-0">
                <i class="fas fa-filter text-primary me-2"></i>Search & Filter
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-4">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search Items</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by title, description, or location...">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Item Type</label>
                    <select class="form-select" name="type" onchange="this.form.submit()">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="lost" <?php echo $type === 'lost' ? 'selected' : ''; ?>>Lost Items</option>
                        <option value="found" <?php echo $type === 'found' ? 'selected' : ''; ?>>Found Items</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Category</label>
                    <select class="form-select" name="category" onchange="this.form.submit()">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" 
                                <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Status</label>
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="resolved" <?php echo $status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    </select>
                </div>
            </form>
            
            <!-- Quick Stats -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?type=lost&status=active" 
                           class="btn btn-sm <?php echo $type === 'lost' && $status === 'active' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                            <i class="fas fa-search me-1"></i> Lost Items (<?php echo $stats['active_lost']; ?>)
                        </a>
                        <a href="?type=found&status=active" 
                           class="btn btn-sm <?php echo $type === 'found' && $status === 'active' ? 'btn-success' : 'btn-outline-success'; ?>">
                            <i class="fas fa-hand-holding me-1"></i> Found Items (<?php echo $stats['active_found']; ?>)
                        </a>
                        <a href="?status=matched" 
                           class="btn btn-sm <?php echo $status === 'matched' ? 'btn-info' : 'btn-outline-info'; ?>">
                            <i class="fas fa-handshake me-1"></i> Matched (<?php echo $stats['matched']; ?>)
                        </a>
                        <a href="?status=resolved" 
                           class="btn btn-sm <?php echo $status === 'resolved' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-check-circle me-1"></i> Resolved (<?php echo $stats['resolved']; ?>)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Grid -->
    <?php if (empty($items)): ?>
    <div class="text-center py-5 my-5">
        <div class="mb-4">
            <i class="fas fa-search fa-4x text-muted"></i>
        </div>
        <h3 class="h4 text-muted mb-3">
            <?php echo $search ? 'No items match your search' : 'No items reported yet'; ?>
        </h3>
        <p class="text-muted mb-4">
            <?php if ($search): ?>
            Try different search terms or browse all items.
            <?php else: ?>
            Be the first to report a lost or found item and help our community.
            <?php endif; ?>
        </p>
        <?php if ($user): ?>
        <a href="report.php" class="btn btn-primary px-4">
            <i class="fas fa-plus-circle me-2"></i>Report First Item
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-5">
        <?php foreach ($items as $item): 
            $typeColor = $item['type'] === 'lost' ? 'danger' : 'success';
            $statusColor = $item['status'] === 'active' ? 'primary' : ($item['status'] === 'matched' ? 'info' : 'secondary');
            $timeAgo = Helpers::timeAgo($item['created_at']);
        ?>
        <div class="col">
            <div class="card h-100 border-0 shadow-sm hover-lift transition-all">
                <!-- Item Header -->
                <div class="card-header bg-white border-bottom-0 pb-0">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-<?php echo $typeColor; ?> mb-2 px-3 py-2">
                                <i class="fas fa-<?php echo $item['type'] === 'lost' ? 'search' : 'hand-holding'; ?> me-1"></i>
                                <?php echo ucfirst($item['type']); ?>
                            </span>
                            <span class="badge bg-<?php echo $statusColor; ?> mb-2 px-3 py-2">
                                <?php echo ucfirst($item['status']); ?>
                            </span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary border-0" type="button" 
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="view.php?id=<?php echo $item['id']; ?>">
                                        <i class="fas fa-eye me-2"></i>View Details
                                    </a>
                                </li>
                                <?php if ($user && ($user['id'] == $item['user_id'] || $user['role'] === 'admin')): ?>
                                <li>
                                    <a class="dropdown-item" href="edit.php?id=<?php echo $item['id']; ?>">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </a>
                                </li>
                                <li>
                                    <button class="dropdown-item text-danger" 
                                            onclick="deleteItem(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <h5 class="card-title mb-3">
                        <a href="view.php?id=<?php echo $item['id']; ?>" class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($item['title']); ?>
                        </a>
                    </h5>
                </div>
                
                <!-- Card Body -->
                <div class="card-body pt-0">
                    <!-- Category & Campus -->
                    <div class="mb-3">
                        <span class="badge bg-light text-dark me-2 mb-2">
                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['category_name']); ?>
                        </span>
                        <?php if ($item['campus_name']): ?>
                        <span class="badge bg-light text-dark mb-2">
                            <i class="fas fa-university me-1"></i><?php echo htmlspecialchars($item['campus_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Description -->
                    <p class="card-text text-muted small mb-4">
                        <?php echo Helpers::strLimit(strip_tags($item['description']), 120); ?>
                    </p>
                    
                    <!-- Location & Date -->
                    <div class="small text-muted mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                            <span><?php echo htmlspecialchars($item['location']); ?></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="far fa-calendar me-2 text-primary"></i>
                            <span>Reported <?php echo $timeAgo; ?></span>
                        </div>
                    </div>
                    
                    <!-- Item Metadata -->
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <div class="d-flex align-items-center">
                            <img src="/uploads/avatars/<?php echo $item['avatar'] ?? 'default.jpg'; ?>" 
                                 class="rounded-circle me-2" width="32" height="32" alt="User">
                            <div>
                                <small class="d-block text-dark fw-semibold">
                                    <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                </small>
                                <small class="text-muted">@<?php echo htmlspecialchars($item['username']); ?></small>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="d-flex gap-3">
                                <small class="text-muted" title="Matches">
                                    <i class="fas fa-handshake me-1"></i><?php echo $item['match_count']; ?>
                                </small>
                                <small class="text-muted" title="Comments">
                                    <i class="far fa-comment me-1"></i><?php echo $item['comment_count']; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card Footer -->
                <div class="card-footer bg-white border-top-0 pt-0">
                    <div class="d-grid">
                        <a href="view.php?id=<?php echo $item['id']; ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-info-circle me-2"></i>View Details & Match
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mb-5">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php echo Helpers::buildQueryString(['page' => $page - 1]); ?>">
                    <i class="fas fa-chevron-left me-1"></i>Previous
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): 
                if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo Helpers::buildQueryString(['page' => $i]); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?<?php echo Helpers::buildQueryString(['page' => $page + 1]); ?>">
                    Next<i class="fas fa-chevron-right ms-1"></i>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- How It Works -->
    <div class="card border-0 bg-light mb-5">
        <div class="card-body p-5">
            <h3 class="h4 mb-4 text-center">How the Lost & Found System Works</h3>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-4 d-inline-block mb-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-primary"></i>
                        </div>
                        <h5 class="h6 mb-2">1. Report Lost/Found</h5>
                        <p class="small text-muted mb-0">Submit detailed information about the item including location, time, and description.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="bg-success bg-opacity-10 rounded-circle p-4 d-inline-block mb-3">
                            <i class="fas fa-search fa-2x text-success"></i>
                        </div>
                        <h5 class="h6 mb-2">2. Automatic Matching</h5>
                        <p class="small text-muted mb-0">Our system automatically suggests potential matches based on item details and location.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center p-4">
                        <div class="bg-info bg-opacity-10 rounded-circle p-4 d-inline-block mb-3">
                            <i class="fas fa-handshake fa-2x text-info"></i>
                        </div>
                        <h5 class="h6 mb-2">3. Reunite & Verify</h5>
                        <p class="small text-muted mb-0">Connect with the other party, verify ownership, and arrange for item return.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Delete item confirmation
function deleteItem(itemId) {
    if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo Auth::csrfToken(); ?>'
            },
            body: JSON.stringify({ id: itemId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Item deleted successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred', 'danger');
        });
    }
}

// Quick search
const searchInput = document.querySelector('input[name="search"]');
let searchTimer;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        this.form.submit();
    }, 800);
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Auto-refresh for active items
if (window.location.search.includes('status=active')) {
    setInterval(() => {
        fetch('/api/lostfound/updates')
            .then(response => response.json())
            .then(data => {
                if (data.updates > 0) {
                    showToast(`New ${data.updates} item(s) reported`, 'info');
                }
            });
    }, 60000); // Check every minute
}
</script>

<?php include '../../templates/footer.php'; ?>