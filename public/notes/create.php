<?php
/**
 * WEZO CAMPUS HUB - Create Notes
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Upload.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Upload;

// Initialize
Auth::init();
Auth::requireLogin();
Auth::requireVerified();

$db = Database::getInstance();
$user = Auth::user();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
        Session::flash('error', 'Security token invalid. Please try again.');
        header('Location: /notes/create.php');
        exit;
    }
    
    // Collect form data
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'category_id' => intval($_POST['category_id'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'content' => trim($_POST['content'] ?? ''),
        'tags' => trim($_POST['tags'] ?? ''),
        'is_free' => isset($_POST['is_free']) ? 1 : 0,
        'price' => floatval($_POST['price'] ?? 0)
    ];
    
    // Validate required fields
    $errors = [];
    if (empty($data['title'])) {
        $errors[] = 'Title is required.';
    }
    
    if (empty($data['category_id'])) {
        $errors[] = 'Category is required.';
    }
    
    if (empty($data['description'])) {
        $errors[] = 'Description is required.';
    }
    
    // Validate file upload
    $fileUpload = null;
    if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = new Upload();
        $fileUpload = $upload->noteFile($_FILES['note_file']);
        
        if (!$fileUpload['success']) {
            $errors[] = $fileUpload['message'];
        }
    }
    
    // Check if user has reached upload limit
    $dailyUploads = $db->fetchColumn(
        "SELECT COUNT(*) FROM notes WHERE user_id = ? AND DATE(created_at) = CURDATE()",
        [$user['id']]
    );
    
    if ($dailyUploads >= 10) {
        $errors[] = 'Daily upload limit reached (10 notes per day).';
    }
    
    // If no errors, save note
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate slug
            $slug = $this->generateSlug($data['title']);
            $counter = 1;
            while ($db->exists('notes', 'slug = ?', [$slug])) {
                $slug = $this->generateSlug($data['title']) . '-' . $counter;
                $counter++;
            }
            
            // Insert note
            $noteId = $db->insert('notes', [
                'user_id' => $user['id'],
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'],
                'content' => $data['content'],
                'file_path' => $fileUpload ? $fileUpload['filename'] : null,
                'file_size' => $fileUpload ? $fileUpload['size'] : null,
                'file_type' => $fileUpload ? $fileUpload['type'] : null,
                'tags' => $data['tags'],
                'is_free' => $data['is_free'],
                'price' => $data['price'],
                'is_approved' => Auth::isAdmin() || Auth::isModerator() ? 1 : 0
            ]);
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $user['id'],
                'action' => 'note_upload',
                'description' => 'Uploaded note: ' . $data['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $db->commit();
            
            Session::flash('success', 
                Auth::isAdmin() || Auth::isModerator() 
                ? 'Note published successfully!' 
                : 'Note submitted for review. It will be visible after approval.'
            );
            
            header('Location: /notes/view.php?id=' . $noteId);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            Session::flash('error', 'Failed to save note. Please try again.');
            
            // Delete uploaded file if note creation failed
            if ($fileUpload && isset($fileUpload['path'])) {
                unlink($fileUpload['path']);
            }
        }
    } else {
        $errorMessage = implode('<br>', $errors);
        Session::flash('error', $errorMessage);
    }
}

// Get categories for dropdown
$categories = $db->fetchAll("SELECT * FROM note_categories ORDER BY name");

// Set page title
$pageTitle = "Upload Notes - WEZO CAMPUS HUB";

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
                        <i class="fas fa-upload text-primary me-2"></i> Upload Study Notes
                    </h1>
                    <p class="text-muted mb-0">
                        Share your knowledge with fellow students
                    </p>
                </div>
                <a href="/notes/" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Notes
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <!-- Upload Guidelines -->
                    <div class="alert alert-info mb-4">
                        <h6 class="alert-heading">
                            <i class="fas fa-info-circle me-2"></i> Upload Guidelines
                        </h6>
                        <ul class="mb-0 small">
                            <li>Upload only study-related materials (notes, summaries, past papers)</li>
                            <li>Maximum file size: 50MB</li>
                            <li>Allowed formats: PDF, DOC, DOCX, TXT, PPT, PPTX</li>
                            <li>Ensure content is accurate and properly formatted</li>
                            <li>Use appropriate tags for better searchability</li>
                            <li>Notes require admin approval before being published</li>
                        </ul>
                    </div>
                    
                    <!-- Upload Form -->
                    <form method="POST" action="" enctype="multipart/form-data" id="uploadForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                        
                        <!-- Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label">
                                <i class="fas fa-heading me-1"></i> Title *
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="title" 
                                   name="title" 
                                   value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                   required
                                   maxlength="200"
                                   placeholder="Enter a descriptive title for your notes">
                            <div class="invalid-feedback">Please enter a title for your notes.</div>
                        </div>
                        
                        <!-- Category -->
                        <div class="mb-3">
                            <label for="category_id" class="form-label">
                                <i class="fas fa-folder me-1"></i> Category *
                            </label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"
                                        <?php echo ($_POST['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a category.</div>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left me-1"></i> Description *
                            </label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="4"
                                      required
                                      maxlength="1000"
                                      placeholder="Describe your notes (what topics are covered, key points, etc.)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">Please provide a description.</div>
                            <div class="form-text">
                                <span id="descriptionCounter">0</span>/1000 characters
                            </div>
                        </div>
                        
                        <!-- Content (Optional) -->
                        <div class="mb-3">
                            <label for="content" class="form-label">
                                <i class="fas fa-file-alt me-1"></i> Content (Optional)
                            </label>
                            <textarea class="form-control" 
                                      id="content" 
                                      name="content" 
                                      rows="6"
                                      placeholder="You can type your notes directly here or upload a file below"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                            <div class="form-text">Use this for short notes or summaries. For longer notes, upload a file.</div>
                        </div>
                        
                        <!-- File Upload -->
                        <div class="mb-4">
                            <label for="note_file" class="form-label">
                                <i class="fas fa-file-upload me-1"></i> Upload File (Optional)
                            </label>
                            <div class="file-upload-area border rounded p-4 text-center">
                                <input type="file" 
                                       class="form-control d-none" 
                                       id="note_file" 
                                       name="note_file"
                                       accept=".pdf,.doc,.docx,.txt,.ppt,.pptx">
                                
                                <div id="fileUploadPlaceholder">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-2">Drag & drop your file here or click to browse</p>
                                    <p class="small text-muted mb-0">
                                        Max file size: 50MB. Allowed: PDF, DOC, DOCX, TXT, PPT, PPTX
                                    </p>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-3" id="browseFileBtn">
                                        <i class="fas fa-folder-open me-1"></i> Browse Files
                                    </button>
                                </div>
                                
                                <div id="filePreview" class="d-none">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fas fa-file-pdf fa-2x text-danger me-3"></i>
                                            <div class="d-inline-block">
                                                <strong id="fileName">filename.pdf</strong>
                                                <div class="small text-muted" id="fileSize">0 KB</div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="removeFileBtn">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">File upload is optional if you typed content above.</div>
                        </div>
                        
                        <!-- Tags -->
                        <div class="mb-3">
                            <label for="tags" class="form-label">
                                <i class="fas fa-tags me-1"></i> Tags
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="tags" 
                                   name="tags" 
                                   value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>"
                                   placeholder="Enter tags separated by commas (e.g., mathematics, algebra, calculus)">
                            <div class="form-text">Tags help others find your notes through search.</div>
                        </div>
                        
                        <!-- Pricing -->
                        <div class="mb-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_free" 
                                       name="is_free" 
                                       value="1"
                                       <?php echo isset($_POST['is_free']) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="is_free">
                                    These notes are free to download
                                </label>
                            </div>
                            
                            <div id="priceField" class="d-none">
                                <label for="price" class="form-label">Price (KES)</label>
                                <div class="input-group">
                                    <span class="input-group-text">KSh</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? '0'); ?>"
                                           min="0" 
                                           step="0.01"
                                           placeholder="0.00">
                                </div>
                                <div class="form-text">Set a fair price for your premium notes.</div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                                <i class="fas fa-save me-1"></i> Save as Draft
                            </button>
                            <button type="submit" name="action" value="publish" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i> 
                                <?php echo Auth::isAdmin() || Auth::isModerator() ? 'Publish Now' : 'Submit for Review'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Guidelines Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb me-2"></i> Tips for Great Notes
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small">
                        <li class="mb-2"><strong>Clear Titles:</strong> Use descriptive titles</li>
                        <li class="mb-2"><strong>Proper Formatting:</strong> Use headings and bullet points</li>
                        <li class="mb-2"><strong>Complete Coverage:</strong> Cover entire topics or chapters</li>
                        <li class="mb-2"><strong>Accurate Information:</strong> Double-check facts and figures</li>
                        <li class="mb-2"><strong>Good Scans:</strong> Ensure PDFs are clear and readable</li>
                        <li class="mb-2"><strong>Relevant Tags:</strong> Use keywords for better search</li>
                    </ul>
                </div>
            </div>
            
            <!-- Recent Uploads -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i> Your Recent Uploads
                    </h5>
                    <a href="/profile.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php
                    $recentNotes = $db->fetchAll("
                        SELECT id, title, created_at, is_approved 
                        FROM notes 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ", [$user['id']]);
                    
                    if (empty($recentNotes)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book fa-2x text-muted mb-3"></i>
                        <p class="text-muted small mb-0">No notes uploaded yet</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentNotes as $note): ?>
                        <a href="/notes/view.php?id=<?php echo $note['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 small"><?php echo htmlspecialchars($note['title']); ?></h6>
                                <span class="badge bg-<?php echo $note['is_approved'] ? 'success' : 'warning'; ?> small">
                                    <?php echo $note['is_approved'] ? 'Approved' : 'Pending'; ?>
                                </span>
                            </div>
                            <small class="text-muted">
                                <?php echo date('M d', strtotime($note['created_at'])); ?>
                            </small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i> Your Stats
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    $stats = $db->fetch("
                        SELECT 
                            COUNT(*) as total_notes,
                            SUM(download_count) as total_downloads,
                            SUM(view_count) as total_views,
                            (SELECT COUNT(*) FROM note_reviews WHERE note_id IN (
                                SELECT id FROM notes WHERE user_id = ?
                            )) as total_reviews
                        FROM notes 
                        WHERE user_id = ?
                    ", [$user['id'], $user['id']]);
                    ?>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="stat-number h4 text-primary"><?php echo $stats['total_notes'] ?? 0; ?></div>
                            <div class="stat-label small text-muted">Total Notes</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-number h4 text-success"><?php echo $stats['total_downloads'] ?? 0; ?></div>
                            <div class="stat-label small text-muted">Downloads</div>
                        </div>
                        <div class="col-6">
                            <div class="stat-number h4 text-info"><?php echo $stats['total_views'] ?? 0; ?></div>
                            <div class="stat-label small text-muted">Views</div>
                        </div>
                        <div class="col-6">
                            <div class="stat-number h4 text-warning"><?php echo $stats['total_reviews'] ?? 0; ?></div>
                            <div class="stat-label small text-muted">Reviews</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Character counter for description
const descriptionTextarea = document.getElementById('description');
const descriptionCounter = document.getElementById('descriptionCounter');

descriptionTextarea.addEventListener('input', function() {
    const length = this.value.length;
    descriptionCounter.textContent = length;
    
    if (length > 1000) {
        descriptionCounter.classList.add('text-danger');
    } else {
        descriptionCounter.classList.remove('text-danger');
    }
});

// Initialize with current length
descriptionCounter.textContent = descriptionTextarea.value.length;

// File upload handling
const fileInput = document.getElementById('note_file');
const browseBtn = document.getElementById('browseFileBtn');
const filePlaceholder = document.getElementById('fileUploadPlaceholder');
const filePreview = document.getElementById('filePreview');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const removeBtn = document.getElementById('removeFileBtn');

// Browse button click
browseBtn.addEventListener('click', function() {
    fileInput.click();
});

// File input change
fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        const file = this.files[0];
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
        
        // Check file size
        if (file.size > 50 * 1024 * 1024) { // 50MB
            alert('File size exceeds 50MB limit. Please choose a smaller file.');
            this.value = '';
            return;
        }
        
        // Check file extension
        const allowedExtensions = ['.pdf', '.doc', '.docx', '.txt', '.ppt', '.pptx'];
        const fileName = file.name.toLowerCase();
        const isValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));
        
        if (!isValidExtension) {
            alert('Invalid file type. Please upload PDF, DOC, DOCX, TXT, PPT, or PPTX files.');
            this.value = '';
            return;
        }
        
        // Show preview
        updateFilePreview(file.name, fileSizeMB + ' MB');
    }
});

// Drag and drop functionality
const uploadArea = filePlaceholder.parentElement;

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('border-primary', 'bg-light');
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('border-primary', 'bg-light');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-primary', 'bg-light');
    
    if (e.dataTransfer.files.length > 0) {
        fileInput.files = e.dataTransfer.files;
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }
});

// Remove file
removeBtn.addEventListener('click', function() {
    fileInput.value = '';
    filePlaceholder.classList.remove('d-none');
    filePreview.classList.add('d-none');
});

// Update file preview
function updateFilePreview(name, size) {
    fileName.textContent = name;
    fileSize.textContent = size;
    
    // Determine icon based on file extension
    const extension = name.split('.').pop().toLowerCase();
    let iconClass = 'fas fa-file';
    let iconColor = 'text-primary';
    
    switch (extension) {
        case 'pdf':
            iconClass = 'fas fa-file-pdf';
            iconColor = 'text-danger';
            break;
        case 'doc':
        case 'docx':
            iconClass = 'fas fa-file-word';
            iconColor = 'text-primary';
            break;
        case 'txt':
            iconClass = 'fas fa-file-alt';
            iconColor = 'text-secondary';
            break;
        case 'ppt':
        case 'pptx':
            iconClass = 'fas fa-file-powerpoint';
            iconColor = 'text-warning';
            break;
    }
    
    fileName.previousElementSibling.className = `${iconClass} fa-2x ${iconColor} me-3`;
    
    filePlaceholder.classList.add('d-none');
    filePreview.classList.remove('d-none');
}

// Free/Paid toggle
const freeCheckbox = document.getElementById('is_free');
const priceField = document.getElementById('priceField');

freeCheckbox.addEventListener('change', function() {
    if (this.checked) {
        priceField.classList.add('d-none');
    } else {
        priceField.classList.remove('d-none');
    }
});

// Form validation
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const category = document.getElementById('category_id').value;
    const description = document.getElementById('description').value.trim();
    const content = document.getElementById('content').value.trim();
    const fileInput = document.getElementById('note_file');
    
    let isValid = true;
    
    // Check title
    if (!title) {
        document.getElementById('title').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('title').classList.remove('is-invalid');
    }
    
    // Check category
    if (!category) {
        document.getElementById('category_id').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('category_id').classList.remove('is-invalid');
    }
    
    // Check description
    if (!description) {
        document.getElementById('description').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('description').classList.remove('is-invalid');
    }
    
    // Check if either content or file is provided
    if (!content.trim() && (!fileInput.files || fileInput.files.length === 0)) {
        alert('Please either type content or upload a file.');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields correctly.');
    }
});

// Auto-generate tags from title
document.getElementById('title').addEventListener('blur', function() {
    const title = this.value.trim();
    const tagsInput = document.getElementById('tags');
    
    if (title && !tagsInput.value) {
        // Simple tag generation - could be improved
        const words = title.toLowerCase().split(' ');
        const stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        const generatedTags = words
            .filter(word => word.length > 3 && !stopWords.includes(word))
            .slice(0, 5)
            .join(', ');
        
        if (generatedTags) {
            tagsInput.value = generatedTags;
        }
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>