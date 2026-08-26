<?php
/**
 * WEZO CAMPUS HUB - Upload Resources
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validation.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Validation;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

// Get categories and courses
$categories = $db->fetchAll("SELECT * FROM resource_categories ORDER BY name");
$courses = $db->fetchAll("SELECT * FROM courses ORDER BY name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validation($_POST);
    
    $validator->rules([
        'title' => ['required', 'min:3', 'max:200'],
        'category_id' => ['required', 'numeric'],
        'type' => ['required', 'in:past_paper,textbook,research_paper,slides,notes,other'],
        'description' => ['required', 'min:10', 'max:2000'],
        'author' => ['max:100'],
        'course_id' => ['numeric'],
        'semester' => ['numeric', 'between:1,10'],
        'academic_year' => ['numeric', 'between:2000,' . date('Y')],
        'tags' => ['max:200']
    ]);
    
    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Session::setFlash('error', 'Please select a file to upload');
    } else {
        $file = $_FILES['file'];
        $maxSize = 50 * 1024 * 1024; // 50MB
        $allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
        
        if ($file['size'] > $maxSize) {
            Session::setFlash('error', 'File size must be less than 50MB');
        } else {
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExt, $allowedTypes)) {
                Session::setFlash('error', 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
            }
        }
    }
    
    if ($validator->validate() && !Session::hasFlash('error')) {
        try {
            $db->beginTransaction();
            
            // Generate unique filename
            $uniqueId = uniqid();
            $fileName = $uniqueId . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', basename($file['name']));
            $uploadPath = __DIR__ . '/../../uploads/resources/' . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            // Insert resource
            $resourceId = $db->insert('resources', [
                'user_id' => $user['id'],
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'category_id' => $_POST['category_id'],
                'type' => $_POST['type'],
                'file_path' => 'uploads/resources/' . $fileName,
                'file_size' => $file['size'],
                'file_type' => $fileExt,
                'author' => $_POST['author'] ?? null,
                'course_id' => $_POST['course_id'] ?? null,
                'semester' => $_POST['semester'] ?? null,
                'academic_year' => $_POST['academic_year'] ?? null,
                'tags' => $_POST['tags'] ?? null,
                'is_approved' => $user['role'] === 'admin' ? 1 : 0,
                'status' => 'published',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $user['id'],
                'action' => 'resource_upload',
                'description' => 'Uploaded resource: ' . $_POST['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            Session::setFlash('success', 'Resource uploaded successfully! ' . 
                ($user['role'] !== 'admin' ? 'It will be available after admin approval.' : ''));
            header('Location: view.php?id=' . $resourceId);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            Session::setFlash('error', 'Upload failed: ' . $e->getMessage());
        }
    }
}

$pageTitle = "Upload Resource - WEZO CAMPUS HUB";
include '../../templates/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="mb-4">
                <h1 class="h2 mb-2">
                    <i class="fas fa-upload text-primary me-2"></i> Upload Resource
                </h1>
                <p class="text-muted">
                    Share study materials with the community. All uploads are subject to review.
                </p>
            </div>

            <?php if (Session::hasFlash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo Session::getFlash('error'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Upload Form -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" novalidate>
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Title *</label>
                                <input type="text" class="form-control <?php echo isset($validator) && $validator->hasError('title') ? 'is-invalid' : ''; ?>" 
                                       name="title" value="<?php echo $_POST['title'] ?? ''; ?>" 
                                       placeholder="e.g., Introduction to Programming Past Paper 2023" required>
                                <?php if (isset($validator) && $validator->hasError('title')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('title'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select <?php echo isset($validator) && $validator->hasError('category_id') ? 'is-invalid' : ''; ?>" 
                                        name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                            <?php echo ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($validator) && $validator->hasError('category_id')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('category_id'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Resource Type *</label>
                                <select class="form-select <?php echo isset($validator) && $validator->hasError('type') ? 'is-invalid' : ''; ?>" 
                                        name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="past_paper" <?php echo ($_POST['type'] ?? '') == 'past_paper' ? 'selected' : ''; ?>>Past Paper</option>
                                    <option value="textbook" <?php echo ($_POST['type'] ?? '') == 'textbook' ? 'selected' : ''; ?>>Textbook</option>
                                    <option value="research_paper" <?php echo ($_POST['type'] ?? '') == 'research_paper' ? 'selected' : ''; ?>>Research Paper</option>
                                    <option value="slides" <?php echo ($_POST['type'] ?? '') == 'slides' ? 'selected' : ''; ?>>Slides/Presentation</option>
                                    <option value="notes" <?php echo ($_POST['type'] ?? '') == 'notes' ? 'selected' : ''; ?>>Study Notes</option>
                                    <option value="other" <?php echo ($_POST['type'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if (isset($validator) && $validator->hasError('type')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('type'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Course (Optional)</label>
                                <select class="form-select <?php echo isset($validator) && $validator->hasError('course_id') ? 'is-invalid' : ''; ?>" 
                                        name="course_id">
                                    <option value="">Select Course</option>
                                    <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            <?php echo ($_POST['course_id'] ?? '') == $course['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($validator) && $validator->hasError('course_id')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('course_id'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Author/Publisher (Optional)</label>
                                <input type="text" class="form-control <?php echo isset($validator) && $validator->hasError('author') ? 'is-invalid' : ''; ?>" 
                                       name="author" value="<?php echo $_POST['author'] ?? ''; ?>" 
                                       placeholder="e.g., John Doe, University Press">
                                <?php if (isset($validator) && $validator->hasError('author')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('author'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Semester (Optional)</label>
                                <select class="form-select <?php echo isset($validator) && $validator->hasError('semester') ? 'is-invalid' : ''; ?>" 
                                        name="semester">
                                    <option value="">Select</option>
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" 
                                            <?php echo ($_POST['semester'] ?? '') == $i ? 'selected' : ''; ?>>
                                        Semester <?php echo $i; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <?php if (isset($validator) && $validator->hasError('semester')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('semester'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Academic Year (Optional)</label>
                                <select class="form-select <?php echo isset($validator) && $validator->hasError('academic_year') ? 'is-invalid' : ''; ?>" 
                                        name="academic_year">
                                    <option value="">Select Year</option>
                                    <?php for ($y = date('Y'); $y >= 2000; $y--): ?>
                                    <option value="<?php echo $y; ?>" 
                                            <?php echo ($_POST['academic_year'] ?? '') == $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?> / <?php echo $y + 1; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <?php if (isset($validator) && $validator->hasError('academic_year')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('academic_year'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Description *</label>
                                <textarea class="form-control <?php echo isset($validator) && $validator->hasError('description') ? 'is-invalid' : ''; ?>" 
                                          name="description" rows="4" required 
                                          placeholder="Describe the resource content, topics covered, etc."><?php echo $_POST['description'] ?? ''; ?></textarea>
                                <?php if (isset($validator) && $validator->hasError('description')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('description'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Tags (Optional)</label>
                                <input type="text" class="form-control <?php echo isset($validator) && $validator->hasError('tags') ? 'is-invalid' : ''; ?>" 
                                       name="tags" value="<?php echo $_POST['tags'] ?? ''; ?>" 
                                       placeholder="e.g., programming, algorithms, data-structures (comma separated)">
                                <small class="text-muted">Add relevant keywords to help others find your resource</small>
                                <?php if (isset($validator) && $validator->hasError('tags')): ?>
                                <div class="invalid-feedback"><?php echo $validator->getError('tags'); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- File Upload -->
                            <div class="col-12 mb-4">
                                <label class="form-label">File *</label>
                                <div class="file-upload-area border rounded p-4 text-center">
                                    <input type="file" class="file-input" name="file" id="fileInput" 
                                           accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" required>
                                    <div class="file-upload-preview mt-3" id="filePreview">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                        <h5>Drag & drop your file here</h5>
                                        <p class="text-muted">or click to browse</p>
                                        <p class="small text-muted">
                                            Max file size: 50MB. Allowed types: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, JPG, PNG, ZIP
                                        </p>
                                    </div>
                                    <div class="file-info d-none" id="fileInfo">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file fa-2x text-primary me-3" id="fileIcon"></i>
                                            <div class="text-start">
                                                <h6 id="fileName" class="mb-1"></h6>
                                                <small class="text-muted" id="fileSize"></small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" 
                                                    onclick="clearFile()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="col-12 mb-4">
                                <div class="form-check">
                                    <input class="form-check-input <?php echo isset($validator) && $validator->hasError('terms') ? 'is-invalid' : ''; ?>" 
                                           type="checkbox" name="terms" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I confirm that I have the right to share this resource and it does not violate any copyright laws.
                                    </label>
                                    <?php if (isset($validator) && $validator->hasError('terms')): ?>
                                    <div class="invalid-feedback"><?php echo $validator->getError('terms'); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="allow_commercial" id="allowCommercial">
                                    <label class="form-check-label" for="allowCommercial">
                                        Allow others to use this resource for commercial purposes (with attribution)
                                    </label>
                                </div>
                                
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="allow_modifications" id="allowModifications">
                                    <label class="form-check-label" for="allowModifications">
                                        Allow others to modify and adapt this resource
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit Buttons -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-upload me-2"></i> Upload Resource
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Upload Guidelines -->
            <div class="card shadow-sm mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i> Upload Guidelines
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Ensure you have the right to share the resource (no copyright violations)</li>
                        <li>Provide clear and accurate descriptions</li>
                        <li>Use appropriate file formats (PDF preferred for documents)</li>
                        <li>Add relevant tags to improve searchability</li>
                        <li>Resources will be reviewed by administrators before being published</li>
                        <li>Violations may result in account suspension</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// File upload preview
const fileInput = document.getElementById('fileInput');
const filePreview = document.getElementById('filePreview');
const fileInfo = document.getElementById('fileInfo');
const fileName = document.getElementById('fileName');
const fileSize = document.getElementById('fileSize');
const fileIcon = document.getElementById('fileIcon');

fileInput.addEventListener('change', function(e) {
    if (this.files.length > 0) {
        const file = this.files[0];
        const fileExt = file.name.split('.').pop().toLowerCase();
        
        // Update file info
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        // Set file icon
        const iconMap = {
            'pdf': 'file-pdf',
            'doc': 'file-word',
            'docx': 'file-word',
            'ppt': 'file-powerpoint',
            'pptx': 'file-powerpoint',
            'xls': 'file-excel',
            'xlsx': 'file-excel',
            'jpg': 'file-image',
            'jpeg': 'file-image',
            'png': 'file-image',
            'zip': 'file-archive'
        };
        
        fileIcon.className = 'fas fa-' + (iconMap[fileExt] || 'file') + ' fa-2x text-primary me-3';
        
        // Show file info, hide preview
        filePreview.classList.add('d-none');
        fileInfo.classList.remove('d-none');
    }
});

// Drag and drop
const uploadArea = document.querySelector('.file-upload-area');

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
        fileInput.dispatchEvent(new Event('change'));
    }
});

// Clear file
function clearFile() {
    fileInput.value = '';
    filePreview.classList.remove('d-none');
    fileInfo.classList.add('d-none');
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Form validation
const form = document.querySelector('form');
form.addEventListener('submit', function(e) {
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Please select a file to upload');
        return;
    }
    
    const maxSize = 50 * 1024 * 1024; // 50MB
    if (fileInput.files[0].size > maxSize) {
        e.preventDefault();
        alert('File size must be less than 50MB');
        return;
    }
    
    const allowedTypes = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    const fileExt = fileInput.files[0].name.split('.').pop().toLowerCase();
    if (!allowedTypes.includes(fileExt)) {
        e.preventDefault();
        alert('File type not allowed. Allowed types: ' + allowedTypes.join(', '));
        return;
    }
});
</script>

<?php include '../../templates/footer.php'; ?>