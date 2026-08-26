<?php
/**
 * WEZO CAMPUS HUB - Report & Feedback
 * Powered by AYGLOBE INC
 */

session_start();
require_once __DIR__ . '/../core/bootstrap.php';

use Core\Auth;
use Core\Database;

try {
    Auth::init();
    $db = Database::getInstance();
    $user = Auth::user();
    $isLoggedIn = Auth::isLoggedIn();
} catch (Exception $e) {
    $user = null;
    $isLoggedIn = false;
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportType = $_POST['report_type'] ?? '';
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $email = trim($_POST['email'] ?? ($user ? $user['email'] : ''));
    $name = trim($_POST['name'] ?? ($user ? $user['first_name'] . ' ' . $user['last_name'] : ''));
    
    // Validation
    if (empty($reportType)) {
        $error = 'Please select a report type.';
    } elseif (empty($reason)) {
        $error = 'Please select a reason.';
    } elseif (empty($description) || strlen($description) < 10) {
        $error = 'Please provide a detailed description (minimum 10 characters).';
    } elseif (!$isLoggedIn && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $error = 'Please provide a valid email address.';
    } else {
        try {
            // In a real application, you would save to database
            // For now, we'll just log it
            
            $logData = [
                'report_type' => $reportType,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'reason' => $reason,
                'description' => $description,
                'reporter_name' => $name,
                'reporter_email' => $email,
                'user_id' => $isLoggedIn ? $user['id'] : 'anonymous',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Log to file
            $logDir = __DIR__ . '/../logs/';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logMessage = date('[Y-m-d H:i:s]') . " Report Submitted:\n";
            $logMessage .= json_encode($logData, JSON_PRETTY_PRINT) . "\n";
            $logMessage .= str_repeat('-', 50) . "\n";
            
            file_put_contents($logDir . 'reports.log', $logMessage, FILE_APPEND);
            
            // If database is available, save to reports table
            if ($isLoggedIn && isset($db)) {
                $reportData = [
                    'reporter_id' => $user['id'],
                    'item_type' => !empty($itemType) ? $itemType : null,
                    'item_id' => !empty($itemId) ? $itemId : null,
                    'reason' => $reason,
                    'description' => $description,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Remove null values
                $reportData = array_filter($reportData, function($value) {
                    return $value !== null;
                });
                
                $db->insert('reports', $reportData);
            }
            
            $success = 'Thank you for your report. Our moderation team will review it within 24-48 hours.';
            
            // Clear form if success
            $_POST = [];
            
        } catch (Exception $e) {
            $error = 'Failed to submit report. Please try again later.';
            error_log("Report submission error: " . $e->getMessage());
        }
    }
}

$pageTitle = "Report & Feedback - WEZO CAMPUS HUB";

// Include header
$headerPath = __DIR__ . '/../templates/header.php';
if (file_exists($headerPath)) {
    include $headerPath;
} else {
    include __DIR__ . '/../templates/mini-header.php';
}
?>

<!-- Hero Section -->
<section class="hero-section py-5" style="background: linear-gradient(135deg, #1A56DB, #10B981);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h1 class="display-4 fw-bold text-white mb-3">Report & Feedback</h1>
                <p class="lead text-white mb-0">Help us keep WEZO CAMPUS HUB safe and improve our platform</p>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="container py-5">
    <div class="row">
        <!-- Report Form -->
        <div class="col-lg-8 mx-auto">
            <div class="card border-0 shadow-sm mb-5">
                <div class="card-body p-4 p-md-5">
                    <h2 class="fw-bold mb-4">Submit a Report</h2>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <!-- Report Type -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">What would you like to do? *</label>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-check card p-3 border report-type-card">
                                        <input class="form-check-input" type="radio" name="report_type" id="report_problem" value="problem" required>
                                        <label class="form-check-label fw-bold" for="report_problem">
                                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                            Report a Problem
                                        </label>
                                        <small class="text-muted d-block mt-1">Technical issues, bugs, or errors</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card p-3 border report-type-card">
                                        <input class="form-check-input" type="radio" name="report_type" id="report_content" value="content" required>
                                        <label class="form-check-label fw-bold" for="report_content">
                                            <i class="fas fa-flag text-warning me-2"></i>
                                            Report Content
                                        </label>
                                        <small class="text-muted d-block mt-1">Inappropriate or abusive content</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check card p-3 border report-type-card">
                                        <input class="form-check-input" type="radio" name="report_type" id="report_feedback" value="feedback" required>
                                        <label class="form-check-label fw-bold" for="report_feedback">
                                            <i class="fas fa-comment-dots text-info me-2"></i>
                                            Send Feedback
                                        </label>
                                        <small class="text-muted d-block mt-1">Suggestions or general feedback</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Item Selection (Conditional) -->
                        <div class="mb-4" id="itemSection" style="display: none;">
                            <label class="form-label fw-bold">What are you reporting?</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <select class="form-select" name="item_type" id="itemType">
                                        <option value="">Select type...</option>
                                        <option value="user">User/Profile</option>
                                        <option value="note">Study Note</option>
                                        <option value="marketplace">Marketplace Item</option>
                                        <option value="hostel">Hostel Listing</option>
                                        <option value="resource">Study Resource</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="item_id" id="itemId" placeholder="Item ID or URL (if known)">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Reason -->
                        <div class="mb-4" id="reasonSection" style="display: none;">
                            <label class="form-label fw-bold">Reason for report *</label>
                            <select class="form-select" name="reason" id="reasonSelect" required>
                                <option value="">Select a reason...</option>
                                <option value="spam">Spam or misleading content</option>
                                <option value="inappropriate">Inappropriate content</option>
                                <option value="harassment">Harassment or bullying</option>
                                <option value="fraud">Fraud or scam</option>
                                <option value="copyright">Copyright violation</option>
                                <option value="safety">Safety concern</option>
                                <option value="technical">Technical issue</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-bold">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="5" 
                                      placeholder="Please provide as much detail as possible. Include URLs, usernames, screenshots if applicable..." 
                                      required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">
                                Be specific and include relevant details. This helps us take appropriate action quickly.
                            </div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Your Information</label>
                            <?php if ($isLoggedIn): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                You are logged in as <strong><?php echo htmlspecialchars($user['username']); ?></strong>. 
                                We'll use your account information for follow-up if needed.
                            </div>
                            <?php else: ?>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                           placeholder="Your name">
                                </div>
                                <div class="col-md-6">
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                           placeholder="Your email address" required>
                                </div>
                            </div>
                            <div class="form-text mt-2">
                                We need your email to follow up on your report. We respect your privacy.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Evidence Upload -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Evidence (Optional)</label>
                            <div class="card border-dashed p-4 text-center">
                                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-3"></i>
                                <p class="mb-3">Upload screenshots or supporting documents</p>
                                <input type="file" class="form-control" id="evidence" multiple>
                                <div class="form-text mt-2">
                                    Max file size: 5MB each. Supported formats: JPG, PNG, PDF
                                </div>
                            </div>
                        </div>
                        
                        <!-- Privacy Notice -->
                        <div class="alert alert-warning mb-4">
                            <h6 class="alert-heading">
                                <i class="fas fa-shield-alt me-2"></i>
                                Privacy & Confidentiality
                            </h6>
                            <p class="mb-0">
                                Your report will be handled confidentially. We may share limited information with 
                                relevant parties to investigate the issue. We will not disclose your identity unless 
                                required by law or with your explicit consent.
                            </p>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-paper-plane me-2"></i> Submit Report
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Report Guidelines -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <h3 class="fw-bold mb-4">
                        <i class="fas fa-book text-success me-2"></i> Reporting Guidelines
                    </h3>
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-success me-3"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-2">Do Report</h5>
                                    <ul class="mb-0">
                                        <li>Harassment or threats</li>
                                        <li>Fraudulent listings</li>
                                        <li>Copyright violations</li>
                                        <li>Spam or scams</li>
                                        <li>Inappropriate content</li>
                                        <li>Safety concerns</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex mb-3">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-times-circle text-danger me-3"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-2">Don't Report</h5>
                                    <ul class="mb-0">
                                        <li>Personal disagreements</li>
                                        <li>Price disagreements</li>
                                        <li>Legal disputes</li>
                                        <li>Off-platform issues</li>
                                        <li>False accusations</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-clock text-primary me-2"></i> Response Time
                            </h5>
                            <p class="mb-0">
                                We review all reports within 24-48 hours. Urgent safety issues are prioritized.
                            </p>
                        </div>
                        
                        <div class="col-md-6">
                            <h5 class="fw-bold mb-3">
                                <i class="fas fa-user-shield text-warning me-2"></i> Your Safety
                            </h5>
                            <p class="mb-0">
                                If you feel unsafe, contact local authorities first, then report to us.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="card border-danger mt-4">
                <div class="card-body p-4 text-center">
                    <h4 class="fw-bold text-danger mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Emergency Contact
                    </h4>
                    <p class="mb-3">
                        If you're in immediate danger or need urgent police assistance:
                    </p>
                    <div class="d-flex flex-column flex-md-row justify-content-center gap-3">
                        <a href="tel:999" class="btn btn-danger btn-lg">
                            <i class="fas fa-phone me-2"></i> Emergency: 999
                        </a>
                        <a href="tel:112" class="btn btn-outline-danger btn-lg">
                            <i class="fas fa-ambulance me-2"></i> Ambulance: 112
                        </a>
                    </div>
                    <p class="mt-3 mb-0 text-muted">
                        <small>For non-emergency police assistance, call your local police station.</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
$footerPath = __DIR__ . '/../templates/footer.php';
if (file_exists($footerPath)) {
    include $footerPath;
} else {
    include __DIR__ . '/../templates/mini-footer.php';
}
?>

<style>
.report-type-card {
    transition: all 0.3s ease;
    cursor: pointer;
    border: 2px solid transparent !important;
}

.report-type-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.report-type-card input:checked + label {
    color: #1A56DB;
}

.report-type-card input:checked {
    border-color: #1A56DB;
    background-color: #1A56DB;
}

.report-type-card input:checked ~ small {
    color: #1A56DB;
}

.border-dashed {
    border: 2px dashed #dee2e6 !important;
    background-color: #f8f9fa;
}

.alert h6 {
    margin-bottom: 0.5rem;
}

.btn-lg {
    padding: 12px 30px;
    font-weight: 600;
}

.form-control:focus, .form-select:focus {
    border-color: #1A56DB;
    box-shadow: 0 0 0 0.2rem rgba(26, 86, 219, 0.25);
}
</style>

<script>
// Dynamic form handling
document.addEventListener('DOMContentLoaded', function() {
    const reportTypeRadios = document.querySelectorAll('input[name="report_type"]');
    const itemSection = document.getElementById('itemSection');
    const reasonSection = document.getElementById('reasonSection');
    const reasonSelect = document.getElementById('reasonSelect');
    
    // Show/hide sections based on report type
    reportTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            const reportType = this.value;
            
            if (reportType === 'problem' || reportType === 'content') {
                itemSection.style.display = 'block';
                reasonSection.style.display = 'block';
            } else if (reportType === 'feedback') {
                itemSection.style.display = 'none';
                reasonSection.style.display = 'none';
                reasonSelect.required = false;
            }
        });
    });
    
    // Initialize based on selected radio (if any)
    const selectedRadio = document.querySelector('input[name="report_type"]:checked');
    if (selectedRadio) {
        selectedRadio.dispatchEvent(new Event('change'));
    }
    
    // Add visual feedback for radio selection
    document.querySelectorAll('.report-type-card').forEach(card => {
        card.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
                
                // Remove selected class from all cards
                document.querySelectorAll('.report-type-card').forEach(c => {
                    c.style.borderColor = 'transparent';
                });
                
                // Add selected class to clicked card
                this.style.borderColor = '#1A56DB !important';
            }
        });
    });
    
    // Character counter for description
    const descriptionTextarea = document.getElementById('description');
    const charCount = document.createElement('div');
    charCount.className = 'form-text text-end mt-1';
    charCount.id = 'charCount';
    descriptionTextarea.parentNode.appendChild(charCount);
    
    function updateCharCount() {
        const length = descriptionTextarea.value.length;
        charCount.textContent = `${length} characters (minimum 10 required)`;
        
        if (length < 10) {
            charCount.style.color = '#dc3545';
        } else if (length < 50) {
            charCount.style.color = '#ffc107';
        } else {
            charCount.style.color = '#198754';
        }
    }
    
    descriptionTextarea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
    
    // File upload preview
    const evidenceInput = document.getElementById('evidence');
    evidenceInput.addEventListener('change', function(e) {
        const files = e.target.files;
        if (files.length > 0) {
            const fileList = document.createElement('div');
            fileList.className = 'mt-3';
            fileList.innerHTML = '<strong>Selected files:</strong><br>';
            
            for (let i = 0; i < files.length; i++) {
                fileList.innerHTML += `
                    <div class="d-flex align-items-center justify-content-between mt-2 p-2 border rounded">
                        <div>
                            <i class="fas fa-file me-2"></i>
                            ${files[i].name} (${(files[i].size / 1024 / 1024).toFixed(2)} MB)
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-file" data-index="${i}">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            }
            
            // Remove any existing file list
            const existingList = evidenceInput.parentNode.querySelector('.file-list');
            if (existingList) {
                existingList.remove();
            }
            
            fileList.className += ' file-list';
            evidenceInput.parentNode.appendChild(fileList);
            
            // Add remove functionality
            document.querySelectorAll('.remove-file').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    const newFiles = Array.from(files);
                    newFiles.splice(index, 1);
                    
                    // Create new DataTransfer with remaining files
                    const dataTransfer = new DataTransfer();
                    newFiles.forEach(file => dataTransfer.items.add(file));
                    evidenceInput.files = dataTransfer.files;
                    
                    // Trigger change event to update preview
                    evidenceInput.dispatchEvent(new Event('change'));
                });
            });
        } else {
            const existingList = evidenceInput.parentNode.querySelector('.file-list');
            if (existingList) {
                existingList.remove();
            }
        }
    });
});
</script>