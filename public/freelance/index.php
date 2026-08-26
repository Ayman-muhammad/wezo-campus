<?php
/**
 * WEZO CAMPUS HUB - Student Freelancing Platform
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

// Check if user has freelancer profile
$freelancer = $db->fetch("
    SELECT * FROM freelancer_profiles 
    WHERE user_id = ? AND status = 'active'
", [$user['id']]);

// Get filter parameters
$category = $_GET['category'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$budget_min = $_GET['budget_min'] ?? '';
$budget_max = $_GET['budget_max'] ?? '';
$campus = $_GET['campus'] ?? ($user['campus_id'] ?? 'all');
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$where = ["fj.status = 'active'"];
$params = [];

if ($category !== 'all') {
    $where[] = "fj.category_id = ?";
    $params[] = $category;
}

if ($type !== 'all') {
    $where[] = "fj.job_type = ?";
    $params[] = $type;
}

if ($budget_min !== '') {
    $where[] = "fj.budget >= ?";
    $params[] = floatval($budget_min);
}

if ($budget_max !== '') {
    $where[] = "fj.budget <= ?";
    $params[] = floatval($budget_max);
}

if ($campus !== 'all') {
    $where[] = "fj.campus_id = ?";
    $params[] = $campus;
}

if (!empty($search)) {
    $where[] = "(MATCH(fj.title, fj.description, fj.skills_required) AGAINST(? IN NATURAL LANGUAGE MODE) OR fj.title LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Sort order
$orderBy = "fj.created_at DESC";
switch ($sort) {
    case 'budget_high': $orderBy = "fj.budget DESC"; break;
    case 'budget_low': $orderBy = "fj.budget ASC"; break;
    case 'deadline': $orderBy = "fj.deadline ASC"; break;
    case 'applications': $orderBy = "application_count DESC"; break;
}

// Get total count
$total = $db->fetch("
    SELECT COUNT(*) as total
    FROM freelance_jobs fj
    $whereClause
", $params);

$totalPages = ceil($total['total'] / $limit);

// Get jobs
$jobs = $db->fetchAll("
    SELECT fj.*, 
           fc.name as category_name, fc.icon as category_icon,
           c.name as campus_name,
           u.first_name, u.last_name, u.username, u.avatar, u.rating as employer_rating,
           (SELECT COUNT(*) FROM job_applications WHERE job_id = fj.id AND status != 'withdrawn') as application_count,
           (SELECT COUNT(*) FROM job_applications WHERE job_id = fj.id AND user_id = ?) as has_applied
    FROM freelance_jobs fj
    LEFT JOIN freelance_categories fc ON fj.category_id = fc.id
    LEFT JOIN campuses c ON fj.campus_id = c.id
    LEFT JOIN users u ON fj.user_id = u.id
    $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
", array_merge($params, [$user['id'], $limit, $offset]));

// Get categories
$categories = $db->fetchAll("SELECT * FROM freelance_categories ORDER BY name");

// Get campuses
$campuses = $db->fetchAll("SELECT * FROM campuses ORDER BY name");

$pageTitle = "Freelance Jobs";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Freelance Jobs</h1>
                    <p class="text-muted mb-0">Find freelance work or post opportunities</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-plus"></i> Post a Job
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="post_job.php">
                                <i class="fas fa-briefcase"></i> Post a Job
                            </a>
                        </li>
                        <?php if ($freelancer): ?>
                            <li>
                                <a class="dropdown-item" href="my_profile.php">
                                    <i class="fas fa-user-tie"></i> My Freelancer Profile
                                </a>
                            </li>
                        <?php else: ?>
                            <li>
                                <a class="dropdown-item" href="create_profile.php">
                                    <i class="fas fa-user-plus"></i> Become a Freelancer
                                </a>
                            </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="my_jobs.php">
                                <i class="fas fa-clipboard-list"></i> My Jobs
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="applications.php">
                                <i class="fas fa-file-alt"></i> My Applications
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Stats Cards -->
            <?php if ($freelancer): ?>
                <div class="row mb-4">
                    <div class="col-md-3 col-6">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h2 class="mb-0"><?= $freelancer['completed_jobs'] ?></h2>
                                <p class="mb-0">Jobs Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h2 class="mb-0"><?= $freelancer['rating'] ?>/5</h2>
                                <p class="mb-0">Rating</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h2 class="mb-0"><?= $freelancer['earnings'] ?></h2>
                                <p class="mb-0">Total Earnings</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="card bg-warning text-dark">
                            <div class="card-body text-center">
                                <h2 class="mb-0">
                                    <?= $db->fetch("SELECT COUNT(*) as count FROM job_applications WHERE user_id = ? AND status = 'submitted'", 
                                        [$user['id']])['count'] ?>
                                </h2>
                                <p class="mb-0">Active Applications</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search & Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search jobs..." value="<?= htmlspecialchars($search) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="category" class="form-select">
                                <option value="all">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="type" class="form-select">
                                <option value="all">All Types</option>
                                <option value="one_time" <?= $type == 'one_time' ? 'selected' : '' ?>>One-time</option>
                                <option value="ongoing" <?= $type == 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                <option value="hourly" <?= $type == 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                <option value="fixed" <?= $type == 'fixed' ? 'selected' : '' ?>>Fixed Price</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="sort" class="form-select">
                                <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>>Newest First</option>
                                <option value="budget_high" <?= $sort == 'budget_high' ? 'selected' : '' ?>>Budget (High to Low)</option>
                                <option value="budget_low" <?= $sort == 'budget_low' ? 'selected' : '' ?>>Budget (Low to High)</option>
                                <option value="deadline" <?= $sort == 'deadline' ? 'selected' : '' ?>>Near Deadline</option>
                                <option value="applications" <?= $sort == 'applications' ? 'selected' : '' ?>>Most Applications</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                        
                        <!-- Advanced Filters -->
                        <div class="col-md-12">
                            <a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#advancedFilters">
                                <i class="fas fa-sliders-h"></i> Advanced Filters
                            </a>
                            <div class="collapse mt-3" id="advancedFilters">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Min Budget (KES)</label>
                                        <input type="number" name="budget_min" class="form-control" 
                                               value="<?= htmlspecialchars($budget_min) ?>" 
                                               min="0" placeholder="0">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Max Budget (KES)</label>
                                        <input type="number" name="budget_max" class="form-control" 
                                               value="<?= htmlspecialchars($budget_max) ?>" 
                                               min="0" placeholder="100000">
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
                                    <div class="col-md-3 d-flex align-items-end">
                                        <a href="index.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-redo"></i> Clear All
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Job Listings -->
            <div class="row">
                <?php if (empty($jobs)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                            <h4>No jobs found</h4>
                            <p class="text-muted">Try adjusting your filters or be the first to post a job!</p>
                            <a href="post_job.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Post a Job
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 job-card">
                                <div class="card-body">
                                    <!-- Job Header -->
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <span class="badge bg-primary mb-2">
                                                <i class="fas fa-<?= $job['category_icon'] ?>"></i>
                                                <?= htmlspecialchars($job['category_name']) ?>
                                            </span>
                                            <span class="badge bg-<?= $job['job_type'] == 'ongoing' ? 'success' : 'info' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?>
                                            </span>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="job.php?id=<?= $job['id'] ?>">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-bookmark"></i> Save Job
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-share"></i> Share
                                                    </a>
                                                </li>
                                                <?php if ($user['role'] === 'admin' || $user['id'] == $job['user_id']): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item" href="edit_job.php?id=<?= $job['id'] ?>">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Job Title -->
                                    <h5 class="card-title">
                                        <a href="job.php?id=<?= $job['id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </a>
                                    </h5>
                                    
                                    <!-- Job Description -->
                                    <p class="card-text text-muted small mb-3">
                                        <?= substr(strip_tags($job['description']), 0, 100) ?>...
                                    </p>
                                    
                                    <!-- Skills -->
                                    <?php if ($job['skills_required']): ?>
                                        <div class="mb-3">
                                            <?php 
                                            $skills = explode(',', $job['skills_required']);
                                            foreach (array_slice($skills, 0, 3) as $skill): 
                                                $skill = trim($skill);
                                                if ($skill):
                                            ?>
                                                <span class="badge bg-secondary me-1 mb-1"><?= htmlspecialchars($skill) ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            if (count($skills) > 3): ?>
                                                <span class="badge bg-light text-dark">+<?= count($skills) - 3 ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Job Details -->
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Budget</small>
                                            <strong class="text-success">KSh <?= number_format($job['budget'], 2) ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Deadline</small>
                                            <strong class="<?= strtotime($job['deadline']) < strtotime('+3 days') ? 'text-danger' : 'text-dark' ?>">
                                                <?= date('M j', strtotime($job['deadline'])) ?>
                                            </strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Campus</small>
                                            <small><?= htmlspecialchars($job['campus_name']) ?></small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Applications</small>
                                            <small><?= $job['application_count'] ?> applied</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Employer Info -->
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="<?= htmlspecialchars($job['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                             alt="<?= htmlspecialchars($job['username']) ?>" 
                                             class="rounded-circle me-2" width="30" height="30">
                                        <div>
                                            <small class="d-block">
                                                <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
                                            </small>
                                            <small class="text-muted">
                                                @<?= htmlspecialchars($job['username']) ?>
                                                <?php if ($job['employer_rating']): ?>
                                                    • <i class="fas fa-star text-warning"></i> <?= $job['employer_rating'] ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="d-grid gap-2">
                                        <?php if ($job['has_applied']): ?>
                                            <button class="btn btn-success" disabled>
                                                <i class="fas fa-check"></i> Applied
                                            </button>
                                        <?php elseif ($freelancer): ?>
                                            <a href="apply.php?job_id=<?= $job['id'] ?>" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i> Apply Now
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-primary" onclick="showFreelancerRequired()">
                                                <i class="fas fa-user-tie"></i> Apply Now
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Job pagination">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
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

            <!-- Become Freelancer Banner -->
            <?php if (!$freelancer): ?>
                <div class="card bg-gradient mt-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title">Start Earning as a Freelancer</h5>
                                <p class="card-text">
                                    Create a freelancer profile to start applying for jobs, 
                                    showcase your skills, and earn money while studying.
                                </p>
                                <ul class="mb-0">
                                    <li>Apply for campus jobs</li>
                                    <li>Build your portfolio</li>
                                    <li>Get paid for your skills</li>
                                    <li>Flexible work hours</li>
                                </ul>
                            </div>
                            <div class="col-md-4 text-center">
                                <i class="fas fa-user-tie fa-5x text-primary mb-3"></i>
                                <br>
                                <a href="create_profile.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-rocket"></i> Get Started
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showFreelancerRequired() {
    Swal.fire({
        title: 'Freelancer Profile Required',
        text: 'You need to create a freelancer profile before applying for jobs. Would you like to create one now?',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Create Profile',
        cancelButtonText: 'Maybe Later'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'create_profile.php';
        }
    });
}

// Job card hover effect
document.querySelectorAll('.job-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-5px)';
        this.style.boxShadow = '0 10px 20px rgba(0,0,0,0.1)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = 'none';
    });
});

// Save job function
function saveJob(jobId) {
    fetch('/api/jobs/save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ job_id: jobId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Job Saved!',
                text: 'Job has been added to your saved list.',
                icon: 'success',
                timer: 2000
            });
        }
    });
}
</script>

<style>
.job-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #dee2e6;
}
.job-card:hover {
    border-color: #0d6efd;
}
.bg-gradient {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
.bg-gradient .card-title,
.bg-gradient .card-text,
.bg-gradient ul {
    color: white;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>