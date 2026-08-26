<?php
/**
 * WEZO CAMPUS HUB - Post a Freelance Job
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Validation.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Helpers;
use Core\Validation;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

// Get categories and campuses
$categories = $db->fetchAll("SELECT * FROM freelance_categories ORDER BY name");
$campuses = $db->fetchAll("SELECT * FROM campuses ORDER BY name");

$errors = [];
$formData = [
    'title' => '',
    'category_id' => '',
    'job_type' => 'one_time',
    'budget' => '',
    'description' => '',
    'skills_required' => '',
    'deliverables' => '',
    'expected_timeline' => '',
    'deadline' => date('Y-m-d', strtotime('+7 days')),
    'campus_id' => $user['campus_id'] ?? '',
    'is_remote' => 0,
    'contact_email' => $user['email'] ?? '',
    'contact_phone' => $user['phone'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = array_merge($formData, $_POST);
    
    // Validate required fields
    if (empty(trim($formData['title']))) {
        $errors[] = 'Job title is required';
    } elseif (strlen(trim($formData['title'])) < 5) {
        $errors[] = 'Title must be at least 5 characters';
    }
    
    if (empty($formData['category_id'])) {
        $errors[] = 'Category is required';
    }
    
    if (empty(trim($formData['description']))) {
        $errors[] = 'Description is required';
    } elseif (strlen(trim($formData['description'])) < 50) {
        $errors[] = 'Description must be at least 50 characters';
    }
    
    if (empty($formData['budget']) || !is_numeric($formData['budget']) || $formData['budget'] < 1) {
        $errors[] = 'Valid budget amount is required (minimum KSh 1)';
    }
    
    if (empty($formData['deadline'])) {
        $errors[] = 'Deadline is required';
    } elseif (strtotime($formData['deadline']) < strtotime('+1 day')) {
        $errors[] = 'Deadline must be at least 1 day from now';
    }
    
    if (empty($formData['expected_timeline']) || !is_numeric($formData['expected_timeline']) || $formData['expected_timeline'] < 1) {
        $errors[] = 'Valid timeline is required (minimum 1 day)';
    }
    
    if (empty($errors)) {
        // Create job
        $jobData = [
            'user_id' => $user['id'],
            'title' => Validation::sanitize($formData['title']),
            'category_id' => intval($formData['category_id']),
            'job_type' => $formData['job_type'],
            'budget' => floatval($formData['budget']),
            'description' => Validation::sanitize($formData['description']),
            'skills_required' => Validation::sanitize($formData['skills_required']),
            'deliverables' => Validation::sanitize($formData['deliverables']),
            'expected_timeline' => intval($formData['expected_timeline']),
            'deadline' => $formData['deadline'],
            'campus_id' => intval($formData['campus_id']),
            'is_remote' => isset($_POST['is_remote']) ? 1 : 0,
            'contact_email' => Validation::sanitize($formData['contact_email']),
            'contact_phone' => Validation::sanitize($formData['contact_phone']),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $jobId = $db->insert('freelance_jobs', $jobData);
        
        if ($jobId) {
            Session::setFlash('success', 'Job posted successfully!');
            header('Location: job.php?id=' . $jobId);
            exit;
        } else {
            $errors[] = 'Failed to post job. Please try again.';
        }
    }
}

$pageTitle = "Post a Job";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Post a Freelance Job</h1>
                    <p class="text-muted mb-0">Find talented students for your project</p>
                </div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Jobs
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" id="jobForm">
                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Job Title *</label>
                                <input type="text" name="title" class="form-control" 
                                       value="<?= htmlspecialchars($formData['title']) ?>" 
                                       placeholder="e.g., Website Development, Graphic Design, Data Entry" 
                                       required minlength="5" maxlength="200">
                                <small class="text-muted">Be clear and specific about the job</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" 
                                            <?= $formData['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Job Type & Budget -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Job Type *</label>
                                <select name="job_type" class="form-select" required>
                                    <option value="one_time" <?= $formData['job_type'] == 'one_time' ? 'selected' : '' ?>>One-time Project</option>
                                    <option value="ongoing" <?= $formData['job_type'] == 'ongoing' ? 'selected' : '' ?>>Ongoing Work</option>
                                    <option value="hourly" <?= $formData['job_type'] == 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                    <option value="fixed" <?= $formData['job_type'] == 'fixed' ? 'selected' : '' ?>>Fixed Price</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Budget (KES) *</label>
                                <div class="input-group">
                                    <span class="input-group-text">KSh</span>
                                    <input type="number" name="budget" class="form-control" 
                                           value="<?= htmlspecialchars($formData['budget']) ?>" 
                                           min="1" max="1000000" step="0.01" required>
                                </div>
                                <small class="text-muted">Expected total cost for the job</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Expected Timeline (Days) *</label>
                                <input type="number" name="expected_timeline" class="form-control" 
                                       value="<?= htmlspecialchars($formData['expected_timeline']) ?>" 
                                       min="1" max="365" required>
                                <small class="text-muted">How long will the job take?</small>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label class="form-label">Job Description *</label>
                            <textarea name="description" id="description" class="form-control" 
                                      rows="6" required minlength="50"><?= htmlspecialchars($formData['description']) ?></textarea>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <span id="charCount">0</span> characters • Minimum 50 characters
                                </small>
                                <small class="text-muted d-block">
                                    Be detailed about what needs to be done, requirements, and expectations.
                                </small>
                            </div>
                        </div>

                        <!-- Skills Required -->
                        <div class="mb-3">
                            <label class="form-label">Skills Required</label>
                            <textarea name="skills_required" class="form-control" rows="3" 
                                      placeholder="e.g., PHP, JavaScript, Graphic Design, Data Analysis, Writing (comma separated)"><?= htmlspecialchars($formData['skills_required']) ?></textarea>
                            <small class="text-muted">Separate skills with commas</small>
                        </div>

                        <!-- Deliverables -->
                        <div class="mb-3">
                            <label class="form-label">Expected Deliverables</label>
                            <textarea name="deliverables" class="form-control" rows="3" 
                                      placeholder="e.g., Fully functional website, 10 blog posts, Data analysis report, Design mockups"><?= htmlspecialchars($formData['deliverables']) ?></textarea>
                            <small class="text-muted">What should be delivered upon completion?</small>
                        </div>

                        <!-- Deadline & Location -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Application Deadline *</label>
                                <input type="date" name="deadline" class="form-control" 
                                       value="<?= htmlspecialchars($formData['deadline']) ?>" 
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                                <small class="text-muted">When should freelancers apply by?</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Campus *</label>
                                <select name="campus_id" class="form-select" required>
                                    <option value="">Select Campus</option>
                                    <?php foreach ($campuses as $camp): ?>
                                        <option value="<?= $camp['id'] ?>" 
                                            <?= $formData['campus_id'] == $camp['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($camp['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="is_remote" class="form-check-input" 
                                           id="is_remote" <?= $formData['is_remote'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_remote">
                                        This job can be done remotely
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Contact Email *</label>
                                <input type="email" name="contact_email" class="form-control" 
                                       value="<?= htmlspecialchars($formData['contact_email']) ?>" required>
                                <small class="text-muted">Where applicants can contact you</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Contact Phone (Optional)</label>
                                <input type="tel" name="contact_phone" class="form-control" 
                                       value="<?= htmlspecialchars($formData['contact_phone']) ?>">
                            </div>
                        </div>

                        <!-- Tips & Guidelines -->
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb"></i> Tips for Posting a Great Job</h6>
                            <ul class="mb-0">
                                <li>Be specific about requirements and expectations</li>
                                <li>Set a realistic budget based on market rates</li>
                                <li>Include clear deliverables and timeline</li>
                                <li>Mention if the job is remote or on-campus</li>
                                <li>Respond promptly to applications and questions</li>
                            </ul>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> Post Job
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="previewJob()">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <a href="index.php" class="btn btn-outline-danger">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Character counter
document.getElementById('description').addEventListener('input', function() {
    document.getElementById('charCount').textContent = this.value.length;
});

// Form validation
document.getElementById('jobForm').addEventListener('submit', function(e) {
    const title = this.elements['title'].value.trim();
    const description = this.elements['description'].value.trim();
    const budget = parseFloat(this.elements['budget'].value);
    const deadline = new Date(this.elements['deadline'].value);
    const today = new Date();
    
    // Title validation
    if (title.length < 5) {
        e.preventDefault();
        Swal.fire({
            title: 'Title Too Short',
            text: 'Job title must be at least 5 characters',
            icon: 'warning'
        });
        return false;
    }
    
    // Description validation
    if (description.length < 50) {
        e.preventDefault();
        Swal.fire({
            title: 'Description Too Short',
            text: 'Job description must be at least 50 characters',
            icon: 'warning'
        });
        return false;
    }
    
    // Budget validation
    if (budget < 1) {
        e.preventDefault();
        Swal.fire({
            title: 'Invalid Budget',
            text: 'Budget must be at least KSh 1',
            icon: 'warning'
        });
        return false;
    }
    
    // Deadline validation
    if (deadline <= today) {
        e.preventDefault();
        Swal.fire({
            title: 'Invalid Deadline',
            text: 'Deadline must be at least 1 day from today',
            icon: 'warning'
        });
        return false;
    }
    
    return true;
});

// Preview job
function previewJob() {
    const form = document.getElementById('jobForm');
    const formData = new FormData(form);
    
    // Store form data in session for preview
    const jobData = {};
    for (let [key, value] of formData.entries()) {
        jobData[key] = value;
    }
    
    // Open preview in new tab
    const previewUrl = 'preview_job.php?' + new URLSearchParams(jobData).toString();
    window.open(previewUrl, '_blank');
}

// Auto-set minimum deadline
document.querySelector('input[name="deadline"]').min = 
    new Date(new Date().getTime() + 24 * 60 * 60 * 1000).toISOString().split('T')[0];
</script>

<style>
textarea {
    resize: vertical;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>