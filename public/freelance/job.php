<?php
/**
 * WEZO CAMPUS HUB - Job Details
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

$jobId = intval($_GET['id'] ?? 0);
if (!$jobId) {
    Session::setFlash('error', 'Job not found');
    header('Location: index.php');
    exit;
}

// Get job details
$job = $db->fetch("
    SELECT fj.*, 
           fc.name as category_name, fc.icon as category_icon,
           c.name as campus_name,
           u.first_name, u.last_name, u.username, u.avatar, u.email, u.phone,
           u.rating as employer_rating, u.completed_jobs as employer_completed,
           fp.title as freelancer_title, fp.hourly_rate, fp.skills as freelancer_skills
    FROM freelance_jobs fj
    LEFT JOIN freelance_categories fc ON fj.category_id = fc.id
    LEFT JOIN campuses c ON fj.campus_id = c.id
    LEFT JOIN users u ON fj.user_id = u.id
    LEFT JOIN freelancer_profiles fp ON u.id = fp.user_id
    WHERE fj.id = ? AND fj.status = 'active'
", [$jobId]);

if (!$job) {
    Session::setFlash('error', 'Job not found or has been removed');
    header('Location: index.php');
    exit;
}

// Check if user has applied
$hasApplied = $db->fetch("
    SELECT id, status FROM job_applications 
    WHERE job_id = ? AND user_id = ? AND status != 'withdrawn'
", [$jobId, $user['id']]);

// Get applications count
$applications = $db->fetchAll("
    SELECT ja.*, 
           u.first_name, u.last_name, u.username, u.avatar,
           fp.title as freelancer_title, fp.rating as freelancer_rating,
           fp.completed_jobs as freelancer_completed
    FROM job_applications ja
    LEFT JOIN users u ON ja.user_id = u.id
    LEFT JOIN freelancer_profiles fp ON u.id = fp.user_id
    WHERE ja.job_id = ? AND ja.status != 'withdrawn'
    ORDER BY ja.created_at DESC
", [$jobId]);

// Get similar jobs
$similarJobs = $db->fetchAll("
    SELECT fj.*, 
           fc.name as category_name,
           c.name as campus_name,
           u.first_name, u.last_name,
           (SELECT COUNT(*) FROM job_applications WHERE job_id = fj.id) as application_count
    FROM freelance_jobs fj
    LEFT JOIN freelance_categories fc ON fj.category_id = fc.id
    LEFT JOIN campuses c ON fj.campus_id = c.id
    LEFT JOIN users u ON fj.user_id = u.id
    WHERE fj.category_id = ? 
    AND fj.id != ?
    AND fj.status = 'active'
    AND fj.deadline > NOW()
    ORDER BY fj.created_at DESC
    LIMIT 3
", [$job['category_id'], $jobId]);

// Handle application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_job'])) {
    // Check if user is freelancer
    $freelancer = $db->fetch("
        SELECT id FROM freelancer_profiles 
        WHERE user_id = ? AND status = 'active'
    ", [$user['id']]);
    
    if (!$freelancer) {
        Session::setFlash('error', 'You need a freelancer profile to apply for jobs');
        header('Location: create_profile.php');
        exit;
    }
    
    // Check if already applied
    if ($hasApplied) {
        Session::setFlash('error', 'You have already applied for this job');
        header('Location: job.php?id=' . $jobId);
        exit;
    }
    
    // Create application
    $proposal = trim($_POST['proposal'] ?? '');
    $bidAmount = floatval($_POST['bid_amount'] ?? $job['budget']);
    $timeline = intval($_POST['timeline'] ?? 7);
    
    if (empty($proposal) || strlen($proposal) < 50) {
        Session::setFlash('error', 'Proposal must be at least 50 characters');
    } elseif ($bidAmount < 1) {
        Session::setFlash('error', 'Invalid bid amount');
    } else {
        $applicationId = $db->insert('job_applications', [
            'job_id' => $jobId,
            'user_id' => $user['id'],
            'proposal' => $proposal,
            'bid_amount' => $bidAmount,
            'timeline_days' => $timeline,
            'status' => 'submitted',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($applicationId) {
            // Send notification to employer
            $db->insert('notifications', [
                'user_id' => $job['user_id'],
                'type' => 'job_application',
                'title' => 'New Job Application',
                'message' => $user['username'] . ' applied for your job: ' . $job['title'],
                'data' => json_encode(['job_id' => $jobId, 'application_id' => $applicationId]),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Session::setFlash('success', 'Application submitted successfully!');
            header('Location: job.php?id=' . $jobId);
            exit;
        } else {
            Session::setFlash('error', 'Failed to submit application');
        }
    }
}

// Increment view count
$db->query("UPDATE freelance_jobs SET views = views + 1 WHERE id = ?", [$jobId]);

$pageTitle = $job['title'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Job Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Freelance Jobs</a></li>
                            <li class="breadcrumb-item active">Job Details</li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0"><?= htmlspecialchars($job['title']) ?></h1>
                    <p class="text-muted mb-0">
                        Posted <?= date('F j, Y', strtotime($job['created_at'])) ?> • 
                        <?= $job['views'] ?> views • <?= count($applications) ?> applications
                    </p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-h"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="#" onclick="shareJob()">
                                <i class="fas fa-share"></i> Share
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="saveJob(<?= $jobId ?>)">
                                <i class="fas fa-bookmark"></i> Save Job
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="#">
                                <i class="fas fa-flag"></i> Report Job
                            </a>
                        </li>
                        <?php if ($user['id'] == $job['user_id'] || $user['role'] === 'admin'): ?>
                            <li>
                                <a class="dropdown-item" href="edit_job.php?id=<?= $jobId ?>">
                                    <i class="fas fa-edit"></i> Edit Job
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="row">
                <!-- Main Content -->
                <div class="col-md-8">
                    <!-- Job Details Card -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <!-- Job Meta -->
                            <div class="d-flex flex-wrap gap-2 mb-4">
                                <span class="badge bg-primary">
                                    <i class="fas fa-<?= $job['category_icon'] ?>"></i>
                                    <?= htmlspecialchars($job['category_name']) ?>
                                </span>
                                <span class="badge bg-<?= $job['job_type'] == 'ongoing' ? 'success' : 'info' ?>">
                                    <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?>
                                </span>
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-clock"></i> 
                                    Deadline: <?= date('M j, Y', strtotime($job['deadline'])) ?>
                                </span>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?= htmlspecialchars($job['campus_name']) ?>
                                </span>
                            </div>

                            <!-- Job Description -->
                            <div class="mb-4">
                                <h5 class="h6">Job Description</h5>
                                <div class="job-description">
                                    <?= nl2br(htmlspecialchars($job['description'])) ?>
                                </div>
                            </div>

                            <!-- Skills Required -->
                            <?php if ($job['skills_required']): ?>
                                <div class="mb-4">
                                    <h5 class="h6">Skills Required</h5>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php 
                                        $skills = explode(',', $job['skills_required']);
                                        foreach ($skills as $skill): 
                                            $skill = trim($skill);
                                            if ($skill):
                                        ?>
                                            <span class="badge bg-primary"><?= htmlspecialchars($skill) ?></span>
                                        <?php 
                                            endif;
                                        endforeach; 
                                        ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Deliverables -->
                            <?php if ($job['deliverables']): ?>
                                <div class="mb-4">
                                    <h5 class="h6">Expected Deliverables</h5>
                                    <div class="deliverables">
                                        <?= nl2br(htmlspecialchars($job['deliverables'])) ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Job Timeline & Budget -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-money-bill-wave text-success"></i> 
                                                Budget Range
                                            </h6>
                                            <h3 class="text-success mb-0">KSh <?= number_format($job['budget'], 2) ?></h3>
                                            <small class="text-muted">
                                                <?= $job['job_type'] == 'hourly' ? 'Per hour' : 'Fixed price' ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-calendar-alt text-primary"></i> 
                                                Expected Timeline
                                            </h6>
                                            <h3 class="text-primary mb-0">
                                                <?= $job['expected_timeline'] ? $job['expected_timeline'] . ' days' : 'Flexible' ?>
                                            </h3>
                                            <small class="text-muted">
                                                Deadline: <?= date('M j, Y', strtotime($job['deadline'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Applications -->
                    <?php if ($user['id'] == $job['user_id']): ?>
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Applications (<?= count($applications) ?>)</h5>
                                <?php if (!empty($applications)): ?>
                                    <a href="applications.php?job_id=<?= $jobId ?>" class="btn btn-sm btn-outline-primary">
                                        Manage Applications
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($applications)): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5>No applications yet</h5>
                                        <p class="text-muted">Applications will appear here when freelancers apply</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach (array_slice($applications, 0, 3) as $application): ?>
                                            <div class="list-group-item list-group-item-action">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1 me-3">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <img src="<?= htmlspecialchars($application['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                                                 alt="<?= htmlspecialchars($application['username']) ?>" 
                                                                 class="rounded-circle me-2" width="40" height="40">
                                                            <div>
                                                                <h6 class="mb-0">
                                                                    <?= htmlspecialchars($application['first_name'] . ' ' . $application['last_name']) ?>
                                                                    <small class="text-muted">@<?= htmlspecialchars($application['username']) ?></small>
                                                                </h6>
                                                                <small class="text-muted">
                                                                    <?= $application['freelancer_title'] ?> • 
                                                                    <i class="fas fa-star text-warning"></i> <?= $application['freelancer_rating'] ?>
                                                                    • <?= $application['freelancer_completed'] ?> jobs completed
                                                                </small>
                                                            </div>
                                                        </div>
                                                        <p class="mb-2 small">
                                                            <?= substr(strip_tags($application['proposal']), 0, 150) ?>...
                                                        </p>
                                                        <div class="d-flex gap-3">
                                                            <small class="text-muted">
                                                                <i class="fas fa-money-bill"></i> 
                                                                KSh <?= number_format($application['bid_amount'], 2) ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <i class="fas fa-clock"></i> 
                                                                <?= $application['timeline_days'] ?> days
                                                            </small>
                                                            <small class="text-muted">
                                                                Applied <?= date('M j', strtotime($application['created_at'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <span class="badge bg-<?= 
                                                            $application['status'] == 'submitted' ? 'warning' : 
                                                            ($application['status'] == 'accepted' ? 'success' : 
                                                            ($application['status'] == 'rejected' ? 'danger' : 'secondary')) 
                                                        ?>">
                                                            <?= ucfirst($application['status']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($applications) > 3): ?>
                                            <div class="text-center mt-3">
                                                <a href="applications.php?job_id=<?= $jobId ?>" class="btn btn-outline-primary">
                                                    View All <?= count($applications) ?> Applications
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Employer Info -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Employer Information</h5>
                            <div class="d-flex align-items-start mb-3">
                                <img src="<?= htmlspecialchars($job['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                     alt="<?= htmlspecialchars($job['username']) ?>" 
                                     class="rounded-circle me-3" width="60" height="60">
                                <div>
                                    <h6 class="mb-0">
                                        <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
                                    </h6>
                                    <small class="text-muted d-block">@<?= htmlspecialchars($job['username']) ?></small>
                                    <?php if ($job['employer_rating']): ?>
                                        <div class="mt-1">
                                            <i class="fas fa-star text-warning"></i>
                                            <strong><?= $job['employer_rating'] ?></strong>
                                            <small class="text-muted">(<?= $job['employer_completed'] ?> jobs posted)</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <?php if ($job['freelancer_title']): ?>
                                    <small class="text-muted d-block">Also a freelancer</small>
                                    <p class="mb-1"><?= htmlspecialchars($job['freelancer_title']) ?></p>
                                    <?php if ($job['hourly_rate']): ?>
                                        <small class="text-muted">Rate: KSh <?= number_format($job['hourly_rate'], 2) ?>/hr</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mt-3">
                                <button class="btn btn-outline-primary btn-sm w-100" 
                                        data-bs-toggle="modal" data-bs-target="#contactModal">
                                    <i class="fas fa-envelope"></i> Contact Employer
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Apply Card -->
                    <?php if ($user['id'] != $job['user_id']): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Apply for This Job</h5>
                                
                                <?php if ($hasApplied): ?>
                                    <div class="alert alert-info">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="alert-heading">Application Submitted</h6>
                                                <p class="mb-0">
                                                    Status: <span class="badge bg-<?= 
                                                        $hasApplied['status'] == 'submitted' ? 'warning' : 
                                                        ($hasApplied['status'] == 'accepted' ? 'success' : 
                                                        ($hasApplied['status'] == 'rejected' ? 'danger' : 'secondary')) 
                                                    ?>">
                                                        <?= ucfirst($hasApplied['status']) ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <a href="application.php?id=<?= $hasApplied['id'] ?>" class="btn btn-outline-primary w-100">
                                        View Your Application
                                    </a>
                                <?php else: ?>
                                    <?php 
                                    // Check if user has freelancer profile
                                    $freelancer = $db->fetch("
                                        SELECT id FROM freelancer_profiles 
                                        WHERE user_id = ? AND status = 'active'
                                    ", [$user['id']]);
                                    ?>
                                    
                                    <?php if (!$freelancer): ?>
                                        <div class="alert alert-warning">
                                            <p class="mb-0">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                You need a freelancer profile to apply for jobs.
                                            </p>
                                        </div>
                                        <a href="create_profile.php" class="btn btn-primary w-100">
                                            <i class="fas fa-user-plus"></i> Create Profile
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-primary w-100" 
                                                data-bs-toggle="modal" data-bs-target="#applyModal">
                                            <i class="fas fa-paper-plane"></i> Apply Now
                                        </button>
                                        <small class="text-muted d-block mt-2 text-center">
                                            <?= count($applications) ?> freelancers have applied
                                        </small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Job Stats -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Job Statistics</h5>
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted small">Views</div>
                                        <div class="h5 mb-0"><?= $job['views'] ?></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted small">Applications</div>
                                        <div class="h5 mb-0"><?= count($applications) ?></div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted small">Avg. Bid</div>
                                        <div class="h5 mb-0">
                                            <?php 
                                            $avgBid = $db->fetch("
                                                SELECT AVG(bid_amount) as avg_bid 
                                                FROM job_applications 
                                                WHERE job_id = ?
                                            ", [$jobId]);
                                            echo $avgBid['avg_bid'] ? 'KSh ' . number_format($avgBid['avg_bid'], 2) : 'N/A';
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted small">Days Left</div>
                                        <div class="h5 mb-0 <?= strtotime($job['deadline']) < strtotime('+3 days') ? 'text-danger' : '' ?>">
                                            <?= floor((strtotime($job['deadline']) - time()) / (60 * 60 * 24)) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Similar Jobs -->
                    <?php if (!empty($similarJobs)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Similar Jobs</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($similarJobs as $similar): ?>
                                        <a href="job.php?id=<?= $similar['id'] ?>" 
                                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0 small"><?= htmlspecialchars($similar['title']) ?></h6>
                                                <small class="text-muted">
                                                    KSh <?= number_format($similar['budget'], 2) ?> • 
                                                    <?= $similar['application_count'] ?> applications
                                                </small>
                                            </div>
                                            <i class="fas fa-chevron-right text-muted"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Apply Modal -->
<div class="modal fade" id="applyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Apply for: <?= htmlspecialchars($job['title']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Your proposal should explain why you're the best fit for this job.
                            Include relevant experience, skills, and how you plan to complete the work.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Proposal *</label>
                        <textarea name="proposal" class="form-control" rows="6" 
                                  placeholder="Describe your approach, relevant experience, and why you're the best fit for this job..." 
                                  required minlength="50"></textarea>
                        <small class="text-muted">Minimum 50 characters</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Your Bid (KES) *</label>
                            <div class="input-group">
                                <span class="input-group-text">KSh</span>
                                <input type="number" name="bid_amount" class="form-control" 
                                       value="<?= $job['budget'] ?>" 
                                       min="1" max="1000000" step="0.01" required>
                            </div>
                            <small class="text-muted">
                                Job budget: KSh <?= number_format($job['budget'], 2) ?>
                            </small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Timeline (Days) *</label>
                            <input type="number" name="timeline" class="form-control" 
                                   value="<?= $job['expected_timeline'] ?: 7 ?>" 
                                   min="1" max="365" required>
                            <small class="text-muted">
                                Job deadline: <?= date('M j, Y', strtotime($job['deadline'])) ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle"></i>
                            By submitting this application, you agree to our terms of service.
                            Once accepted, you'll enter into a contract with the employer.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="apply_job" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contact Employer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Employer:</strong> <?= htmlspecialchars($job['first_name'] . ' ' . $job['last_name']) ?>
                </div>
                
                <?php if ($job['email']): ?>
                    <div class="mb-3">
                        <strong>Email:</strong><br>
                        <a href="mailto:<?= htmlspecialchars($job['email']) ?>">
                            <?= htmlspecialchars($job['email']) ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($job['phone']): ?>
                    <div class="mb-3">
                        <strong>Phone:</strong><br>
                        <a href="tel:<?= htmlspecialchars($job['phone']) ?>">
                            <?= htmlspecialchars($job['phone']) ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="alert alert-info">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        Please be professional when contacting the employer. 
                        Mention that you found this job on WEZO CAMPUS HUB.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function shareJob() {
    const shareData = {
        title: '<?= addslashes($job['title']) ?> - WEZO CAMPUS HUB',
        text: 'Check out this freelance job on WEZO CAMPUS HUB',
        url: window.location.href
    };
    
    if (navigator.share) {
        navigator.share(shareData);
    } else {
        navigator.clipboard.writeText(window.location.href);
        Swal.fire({
            title: 'Link Copied!',
            text: 'Job link copied to clipboard',
            icon: 'success',
            timer: 2000
        });
    }
}

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
        } else {
            Swal.fire({
                title: 'Error',
                text: data.error || 'Failed to save job',
                icon: 'error'
            });
        }
    });
}
</script>

<style>
.job-description, .deliverables {
    line-height: 1.6;
    font-size: 1.05em;
}
.job-description p, .deliverables p {
    margin-bottom: 1rem;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>