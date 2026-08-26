<?php
/**
 * WEZO CAMPUS HUB - Study Resources
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

// Get filter parameters
$category = $_GET['category'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$course = $_GET['course'] ?? '';
$semester = $_GET['semester'] ?? '';
$year = $_GET['year'] ?? '';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT r.*, 
          u.username, u.first_name, u.last_name,
          c.name as course_name,
          cat.name as category_name,
          (SELECT COUNT(*) FROM resource_downloads WHERE resource_id = r.id) as download_count,
          (SELECT COUNT(*) FROM resource_ratings WHERE resource_id = r.id) as rating_count,
          (SELECT AVG(rating) FROM resource_ratings WHERE resource_id = r.id) as avg_rating
          FROM resources r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN courses c ON r.course_id = c.id
          LEFT JOIN resource_categories cat ON r.category_id = cat.id
          WHERE r.status = 'published' AND r.is_approved = 1";
$params = [];

if ($category !== 'all') {
    $query .= " AND r.category_id = ?";
    $params[] = $category;
}

if ($type !== 'all') {
    $query .= " AND r.type = ?";
    $params[] = $type;
}

if ($course) {
    $query .= " AND r.course_id = ?";
    $params[] = $course;
}

if ($semester) {
    $query .= " AND r.semester = ?";
    $params[] = $semester;
}

if ($year) {
    $query .= " AND r.academic_year = ?";
    $params[] = $year;
}

if ($search) {
    $query .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.tags LIKE ? OR r.author LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

// Get total count
$countQuery = str_replace(
    "SELECT r.*, 
     u.username, u.first_name, u.last_name,
     c.name as course_name,
     cat.name as category_name,
     (SELECT COUNT(*) FROM resource_downloads WHERE resource_id = r.id) as download_count,
     (SELECT COUNT(*) FROM resource_ratings WHERE resource_id = r.id) as rating_count,
     (SELECT AVG(rating) FROM resource_ratings WHERE resource_id = r.id) as avg_rating",
    "SELECT COUNT(*) as total", 
    $query
);
$totalResources = $db->fetchColumn($countQuery, $params);
$totalPages = ceil($totalResources / $limit);

// Sorting
$sort = $_GET['sort'] ?? 'recent';
switch ($sort) {
    case 'popular':
        $query .= " ORDER BY download_count DESC";
        break;
    case 'rated':
        $query .= " ORDER BY avg_rating DESC";
        break;
    case 'title':
        $query .= " ORDER BY r.title ASC";
        break;
    case 'recent':
    default:
        $query .= " ORDER BY r.created_at DESC";
        break;
}

// Add pagination
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$resources = $db->fetchAll($query, $params);

// Get categories and courses
$categories = $db->fetchAll("SELECT * FROM resource_categories ORDER BY name");
$courses = $db->fetchAll("SELECT * FROM courses ORDER BY name");

$pageTitle = "Study Resources - WEZO CAMPUS HUB";
include '../../templates/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 mb-2">
                <i class="fas fa-book text-primary me-2"></i> Study Resources
            </h1>
            <p class="text-muted mb-0">
                Access past papers, textbooks, research papers, and other study materials
            </p>
        </div>
        <?php if (Auth::isLoggedIn()): ?>
        <div class="col-md-4 text-end">
            <a href="upload.php" class="btn btn-primary">
                <i class="fas fa-upload me-2"></i> Upload Resource
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search resources...">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="category">
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
                    <select class="form-select" name="type">
                        <option value="all">All Types</option>
                        <option value="past_paper" <?php echo $type == 'past_paper' ? 'selected' : ''; ?>>Past Papers</option>
                        <option value="textbook" <?php echo $type == 'textbook' ? 'selected' : ''; ?>>Textbooks</option>
                        <option value="research_paper" <?php echo $type == 'research_paper' ? 'selected' : ''; ?>>Research Papers</option>
                        <option value="slides" <?php echo $type == 'slides' ? 'selected' : ''; ?>>Slides</option>
                        <option value="notes" <?php echo $type == 'notes' ? 'selected' : ''; ?>>Notes</option>
                        <option value="other" <?php echo $type == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="course">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $courseItem): ?>
                        <option value="<?php echo $courseItem['id']; ?>" 
                                <?php echo $course == $courseItem['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($courseItem['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select" name="sort">
                        <option value="recent" <?php echo $sort == 'recent' ? 'selected' : ''; ?>>Most Recent</option>
                        <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="rated" <?php echo $sort == 'rated' ? 'selected' : ''; ?>>Highest Rated</option>
                        <option value="title" <?php echo $sort == 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                    </select>
                </div>
            </form>
            
            <!-- Advanced Filters -->
            <div class="mt-3 collapse" id="advancedFilters">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" name="semester">
                            <option value="">All Semesters</option>
                            <option value="1" <?php echo $semester == '1' ? 'selected' : ''; ?>>Semester 1</option>
                            <option value="2" <?php echo $semester == '2' ? 'selected' : ''; ?>>Semester 2</option>
                            <option value="3" <?php echo $semester == '3' ? 'selected' : ''; ?>>Semester 3</option>
                            <option value="4" <?php echo $semester == '4' ? 'selected' : ''; ?>>Semester 4</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="year">
                            <option value="">All Years</option>
                            <?php for ($y = date('Y'); $y >= 2010; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?> / <?php echo $y + 1; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mt-2">
                <button class="btn btn-sm btn-outline-secondary" type="button" 
                        data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                    <i class="fas fa-filter me-1"></i> Advanced Filters
                </button>
            </div>
        </div>
    </div>

    <!-- Resources Grid -->
    <?php if (empty($resources)): ?>
    <div class="text-center py-5">
        <i class="fas fa-book fa-4x text-muted mb-4"></i>
        <h3>No Resources Found</h3>
        <p class="text-muted mb-4">
            <?php echo $search ? 'No resources match your search. Try different keywords.' : 'No resources have been uploaded yet.'; ?>
        </p>
        <?php if (Auth::isLoggedIn()): ?>
        <a href="upload.php" class="btn btn-primary">
            <i class="fas fa-upload me-2"></i> Upload Resources
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
        <?php foreach ($resources as $resource): 
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
        ?>
        <div class="col">
            <div class="card h-100 shadow-sm hover-shadow">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-primary mb-2">
                                <?php echo htmlspecialchars($resource['category_name']); ?>
                            </span>
                            <span class="badge bg-secondary mb-2">
                                <?php echo ucfirst(str_replace('_', ' ', $resource['type'])); ?>
                            </span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary border-0" 
                                    type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="view.php?id=<?php echo $resource['id']; ?>">
                                        <i class="fas fa-eye me-2"></i> View Details
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="download.php?id=<?php echo $resource['id']; ?>">
                                        <i class="fas fa-download me-2"></i> Download
                                    </a>
                                </li>
                                <li>
                                    <button class="dropdown-item" onclick="shareResource(<?php echo $resource['id']; ?>, '<?php echo htmlspecialchars($resource['title']); ?>')">
                                        <i class="fas fa-share me-2"></i> Share
                                    </button>
                                </li>
                                <?php if ($user && ($user['id'] == $resource['user_id'] || $user['role'] == 'admin')): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="edit.php?id=<?php echo $resource['id']; ?>">
                                        <i class="fas fa-edit me-2"></i> Edit
                                    </a>
                                </li>
                                <li>
                                    <button class="dropdown-item text-danger" 
                                            onclick="deleteResource(<?php echo $resource['id']; ?>)">
                                        <i class="fas fa-trash me-2"></i> Delete
                                    </button>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3">
                            <i class="fas fa-<?php echo $fileIcon; ?> fa-2x text-<?php 
                                echo $fileExt == 'pdf' ? 'danger' : 
                                    (in_array($fileExt, ['doc', 'docx']) ? 'primary' : 
                                    (in_array($fileExt, ['ppt', 'pptx']) ? 'warning' : 
                                    (in_array($fileExt, ['xls', 'xlsx']) ? 'success' : 'secondary'))); 
                            ?>"></i>
                        </div>
                        <div>
                            <h5 class="card-title mb-1">
                                <a href="view.php?id=<?php echo $resource['id']; ?>" 
                                   class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($resource['title']); ?>
                                </a>
                            </h5>
                            <small class="text-muted">
                                <?php echo strtoupper($fileExt); ?> • 
                                <?php echo formatFileSize(filesize(__DIR__ . '/../../uploads/resources/' . basename($resource['file_path']))); ?>
                            </small>
                        </div>
                    </div>
                    
                    <p class="card-text small text-muted mb-3">
                        <?php 
                        $description = strip_tags($resource['description']);
                        echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description;
                        ?>
                    </p>
                    
                    <div class="small text-muted mb-3">
                        <?php if ($resource['author']): ?>
                        <div class="mb-1">
                            <i class="fas fa-user-pen me-1"></i>
                            <?php echo htmlspecialchars($resource['author']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($resource['course_name']): ?>
                        <div class="mb-1">
                            <i class="fas fa-graduation-cap me-1"></i>
                            <?php echo htmlspecialchars($resource['course_name']); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($resource['semester']): ?>
                        <div class="mb-1">
                            <i class="fas fa-calendar me-1"></i>
                            Semester <?php echo $resource['semester']; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($resource['academic_year']): ?>
                        <div class="mb-1">
                            <i class="fas fa-calendar-alt me-1"></i>
                            <?php echo $resource['academic_year']; ?> / <?php echo $resource['academic_year'] + 1; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <img src="/uploads/avatars/<?php echo $resource['avatar'] ?? 'default.jpg'; ?>" 
                                 class="rounded-circle me-2" width="24" height="24">
                            <small class="text-muted">
                                <?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?>
                            </small>
                        </div>
                        <div class="d-flex align-items-center">
                            <?php if ($resource['avg_rating']): ?>
                            <small class="text-warning me-2">
                                <i class="fas fa-star"></i>
                                <?php echo round($resource['avg_rating'], 1); ?>
                            </small>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class="fas fa-download ms-2"></i> <?php echo $resource['download_count']; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <?php echo timeAgo($resource['created_at']); ?>
                        </small>
                        <div>
                            <a href="view.php?id=<?php echo $resource['id']; ?>" 
                               class="btn btn-sm btn-outline-primary me-1">
                                Details
                            </a>
                            <a href="download.php?id=<?php echo $resource['id']; ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-download me-1"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php 
                    echo $search ? '&search=' . urlencode($search) : '';
                    echo $category !== 'all' ? '&category=' . $category : '';
                    echo $type !== 'all' ? '&type=' . $type : '';
                    echo $sort !== 'recent' ? '&sort=' . $sort : '';
                ?>">
                    Previous
                </a>
            </li>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                    echo $search ? '&search=' . urlencode($search) : '';
                    echo $category !== 'all' ? '&category=' . $category : '';
                    echo $type !== 'all' ? '&type=' . $type : '';
                    echo $sort !== 'recent' ? '&sort=' . $sort : '';
                ?>">
                    <?php echo $i; ?>
                </a>
            </li>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php 
                    echo $search ? '&search=' . urlencode($search) : '';
                    echo $category !== 'all' ? '&category=' . $category : '';
                    echo $type !== 'all' ? '&type=' . $type : '';
                    echo $sort !== 'recent' ? '&sort=' . $sort : '';
                ?>">
                    Next
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Resource Categories -->
    <div class="card shadow-sm mt-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-folder me-2"></i> Resource Categories
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php 
                $categoryStats = $db->fetchAll("
                    SELECT c.*, COUNT(r.id) as resource_count
                    FROM resource_categories c
                    LEFT JOIN resources r ON c.id = r.category_id AND r.status = 'published'
                    GROUP BY c.id
                    ORDER BY resource_count DESC
                    LIMIT 6
                ");
                ?>
                <?php foreach ($categoryStats as $cat): ?>
                <div class="col-md-4 col-lg-2 mb-3">
                    <a href="?category=<?php echo $cat['id']; ?>" 
                       class="text-decoration-none">
                        <div class="card border-0 bg-light text-center p-3 hover-lift">
                            <i class="fas fa-<?php 
                                $catIcons = [
                                    'Past Papers' => 'file-alt',
                                    'Textbooks' => 'book',
                                    'Research Papers' => 'file-contract',
                                    'Slides' => 'presentation',
                                    'Notes' => 'sticky-note',
                                    'Other' => 'file'
                                ];
                                echo $catIcons[$cat['name']] ?? 'folder';
                            ?> fa-2x mb-2 text-primary"></i>
                            <h6 class="mb-1"><?php echo htmlspecialchars($cat['name']); ?></h6>
                            <small class="text-muted"><?php echo $cat['resource_count']; ?> resources</small>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function shareResource(resourceId, resourceTitle) {
    const url = window.location.origin + '/resources/view.php?id=' + resourceId;
    const text = 'Check out this resource: ' + resourceTitle;
    
    if (navigator.share) {
        navigator.share({
            title: resourceTitle,
            text: text,
            url: url
        });
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}

function deleteResource(resourceId) {
    if (confirm('Are you sure you want to delete this resource? This action cannot be undone.')) {
        fetch('delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo Auth::csrfToken(); ?>'
            },
            body: JSON.stringify({ id: resourceId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Resource deleted successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Quick search
const searchInput = document.querySelector('input[name="search"]');
let searchTimeout;
searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});
</script>

<?php include '../../templates/footer.php'; ?>