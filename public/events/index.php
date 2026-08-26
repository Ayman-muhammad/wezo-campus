<?php
/**
 * WEZO CAMPUS HUB - Events Listing
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

// Get filter parameters
$category = $_GET['category'] ?? 'all';
$campus = $_GET['campus'] ?? ($user['campus_id'] ?? 'all');
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'upcoming';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$where = ["e.status = 'approved'"];
$params = [];

if ($category !== 'all') {
    $where[] = "e.category_id = ?";
    $params[] = $category;
}

if ($campus !== 'all') {
    $where[] = "e.campus_id = ?";
    $params[] = $campus;
}

if ($type !== 'all') {
    $where[] = "e.event_type = ?";
    $params[] = $type;
}

if ($status === 'upcoming') {
    $where[] = "e.start_date >= CURDATE()";
} elseif ($status === 'past') {
    $where[] = "e.start_date < CURDATE()";
} elseif ($status === 'today') {
    $where[] = "e.start_date = CURDATE()";
} elseif ($status === 'thisweek') {
    $where[] = "e.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
}

if (!empty($search)) {
    $where[] = "(MATCH(e.title, e.description, e.location) AGAINST(? IN NATURAL LANGUAGE MODE) OR e.title LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$total = $db->fetch("
    SELECT COUNT(*) as total
    FROM events e
    $whereClause
", $params);

$totalPages = ceil($total['total'] / $limit);

// Get events
$events = $db->fetchAll("
    SELECT e.*, 
           ec.name as category_name, ec.color as category_color,
           c.name as campus_name,
           u.first_name, u.last_name, u.username, u.avatar,
           (SELECT COUNT(*) FROM event_attendees WHERE event_id = e.id AND status = 'registered') as attendees_count,
           (SELECT COUNT(*) FROM event_comments WHERE event_id = e.id) as comments_count
    FROM events e
    LEFT JOIN event_categories ec ON e.category_id = ec.id
    LEFT JOIN campuses c ON e.campus_id = c.id
    LEFT JOIN users u ON e.user_id = u.id
    $whereClause
    ORDER BY e.start_date ASC, e.start_time ASC
    LIMIT ? OFFSET ?
", array_merge($params, [$limit, $offset]));

// Get categories for filter
$categories = $db->fetchAll("SELECT * FROM event_categories ORDER BY name");
$campuses = $db->fetchAll("SELECT * FROM campuses ORDER BY name");

// Get user's registered events
$userEvents = $db->fetchAll("
    SELECT event_id FROM event_attendees 
    WHERE user_id = ? AND status = 'registered'
", [$user['id']]);
$registeredEventIds = array_column($userEvents, 'event_id');

$pageTitle = "Campus Events";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Campus Events</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Event
                </a>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Campus</label>
                            <select name="campus" class="form-select">
                                <option value="all">All Campuses</option>
                                <?php foreach ($campuses as $camp): ?>
                                    <option value="<?= $camp['id'] ?>" <?= $campus == $camp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($camp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="all">All Types</option>
                                <option value="academic" <?= $type == 'academic' ? 'selected' : '' ?>>Academic</option>
                                <option value="social" <?= $type == 'social' ? 'selected' : '' ?>>Social</option>
                                <option value="club" <?= $type == 'club' ? 'selected' : '' ?>>Club</option>
                                <option value="workshop" <?= $type == 'workshop' ? 'selected' : '' ?>>Workshop</option>
                                <option value="sports" <?= $type == 'sports' ? 'selected' : '' ?>>Sports</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="upcoming" <?= $status == 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                <option value="today" <?= $status == 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="thisweek" <?= $status == 'thisweek' ? 'selected' : '' ?>>This Week</option>
                                <option value="past" <?= $status == 'past' ? 'selected' : '' ?>>Past Events</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Events Grid -->
            <?php if (empty($events)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h4>No events found</h4>
                    <p class="text-muted">Try adjusting your filters or create a new event</p>
                    <a href="create.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus"></i> Create Your First Event
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($events as $event): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 event-card">
                                <?php if ($event['cover_image']): ?>
                                    <img src="<?= htmlspecialchars($event['cover_image']) ?>" 
                                         class="card-img-top" 
                                         alt="<?= htmlspecialchars($event['title']) ?>"
                                         style="height: 180px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center" 
                                         style="height: 180px; background: <?= $event['category_color'] ?>; color: white;">
                                        <i class="fas fa-calendar-alt fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge" style="background: <?= $event['category_color'] ?>; color: white;">
                                            <?= htmlspecialchars($event['category_name']) ?>
                                        </span>
                                        <small class="text-muted">
                                            <?= date('M j', strtotime($event['start_date'])) ?>
                                            <?php if ($event['start_time']): ?>
                                                at <?= date('g:i A', strtotime($event['start_time'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    
                                    <h5 class="card-title">
                                        <a href="view.php?id=<?= $event['id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($event['title']) ?>
                                        </a>
                                    </h5>
                                    
                                    <p class="card-text text-muted small">
                                        <?= substr(strip_tags($event['description']), 0, 100) ?>...
                                    </p>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                            <small><?= htmlspecialchars($event['location']) ?></small>
                                        </div>
                                        <div>
                                            <i class="fas fa-users text-muted me-1"></i>
                                            <small><?= $event['attendees_count'] ?> attending</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent border-top-0">
                                    <div class="d-flex justify-content-between">
                                        <a href="view.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            View Details
                                        </a>
                                        
                                        <?php if (in_array($event['id'], $registeredEventIds)): ?>
                                            <button class="btn btn-sm btn-success" disabled>
                                                <i class="fas fa-check"></i> Registered
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" action="register.php" class="d-inline">
                                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-user-plus"></i> Register
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Events pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= min(5, $totalPages); $i++): ?>
                                <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($totalPages > 5): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                        <?= $totalPages ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.event-card {
    transition: transform 0.2s, box-shadow 0.2s;
}
.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.event-card .card-img-top {
    border-bottom: 3px solid var(--bs-primary);
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>