<?php
/**
 * WEZO CAMPUS HUB - Edit Notes
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

$db = Database::getInstance();
$user = Auth::user();

// Get note ID
$noteId = intval($_GET['id'] ?? 0);
if (!$noteId) {
    Session::flash('error', 'Note ID not specified.');
    header('Location: /notes/');
    exit;
}

// Get note details
$note = $db->fetch("
    SELECT n.*, u.username, c.name as category_name 
    FROM notes n 
    LEFT JOIN users u ON n.user_id = u.id 
    LEFT JOIN note_categories c ON n.category_id = c.id 
    WHERE n.id = ?
", [$noteId]);

// Check if note exists and user has permission
if (!$note) {
    Session::flash('error', 'Note not found.');
    header('Location: /notes/');
    exit;
}

if ($note['user_id'] != $user['id'] && !Auth::isAdmin() && !Auth::isModerator()) {
    Session::flash('error', 'You do not have permission to edit this note.');
    header('Location: /notes/view.php?id=' . $noteId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
        Session::flash('error', 'Security token invalid. Please try again.');
        header('Location: /notes/edit.php?id=' . $noteId);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_note') {
        // Collect form data
        $data = [
            'title' => trim($_POST['title'] ?? ''),
            'category_id' => intval($_POST['category_id'] ?? 0),
            'description' => trim($_POST['description'] ?? ''),
            'content' => trim($_POST['content'] ?? ''),
            'tags' => trim($_POST['tags'] ?? ''),
            'is_free' => isset($_POST['is_free']) ? 1 : 0,
            'price' => floatval($_POST['price'] ?? 0),
            'is_approved' => $note['is_approved'] // Keep existing approval status
        ];
        
        // Admin/Moderator can change approval status
        if (Auth::isAdmin() || Auth::isModerator()) {
            $data['is_approved'] = isset($_POST['is_approved']) ? 1 : 0;
            $data['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
        }
        
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
        
        // Handle file upload if new file provided
        $fileUpload = null;
        if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = new Upload();
            $fileUpload = $upload->noteFile($_FILES['note_file']);
            
            if (!$fileUpload['success']) {
                $errors[] = $fileUpload['message'];
            } else {
                $data['file_path'] = $fileUpload['filename'];
                $data['file_size'] = $fileUpload['size'];
                $data['file_type'] = $fileUpload['type'];
                
                // Delete old file if exists
                if ($note['file_path']) {
                    $oldFilePath = __DIR__ . '/../../public/assets/uploads/notes/' . $note['file_path'];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
            }
        }
        
        // If no errors, update note
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Update slug if title changed
                if ($data['title'] !== $note['title']) {
                    $slug = $this->generateSlug($data['title']);
                    $counter = 1;
                    while ($db->exists('notes', 'slug = ? AND id != ?', [$slug, $noteId])) {
                        $slug = $this->generateSlug($data['title']) . '-' . $counter;
                        $counter++;
                    }
                    $data['slug'] = $slug;
                }
                
                // Update note
                $db->update('notes', $data, 'id = ?', [$noteId]);
                
                // Log activity
                $db->insert('activity_logs', [
                    'user_id' => $user['id'],
                    'action' => 'note_update',
                    'description' => 'Updated note: ' . $data['title'],
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $db->commit();
                
                Session::flash('success', 'Note updated successfully!');
                header('Location: /notes/view.php?id=' . $noteId);
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                Session::flash('error', 'Failed to update note. Please try again.');
                
                // Delete uploaded file if update failed
                if ($fileUpload && isset($fileUpload['path'])) {
                    unlink($fileUpload['path']);
                }
            }
        } else {
            $errorMessage = implode('<br>', $errors);
            Session::flash('error', $errorMessage);
        }
    }
    
    elseif ($action === 'delete_note') {
        if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
            try {
                $db->beginTransaction();
                
                // Delete file if exists
                if ($note['file_path']) {
                    $filePath = __DIR__ . '/../../public/assets/uploads/notes/' . $note['file_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                
                // Delete reviews
                $db->delete('note_reviews', 'note_id = ?', [$noteId]);
                
                // Delete favorites
                $db->delete('favorites', 'item_type = ? AND item_id = ?', ['note', $noteId]);
                
                // Delete note
                $db->delete('notes', 'id = ?', [$noteId]);
                
                // Log activity
                $db->insert('activity_logs', [
                    'user_id' => $user['id'],
                    'action' => 'note_delete',
                    'description' => 'Deleted note: ' . $note['title'],
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $db->commit();
                
                Session::flash('success', 'Note deleted successfully.');
                header('Location: /notes/');
                exit;
                
            } catch (Exception $e) {
                $db->rollback();
                Session::flash('error', 'Failed to delete note. Please try again.');
            }
        }
    }
}

// Get categories for dropdown
$categories = $db->fetchAll("SELECT * FROM note_categories ORDER BY name");

// Set page title
$pageTitle = "Edit: " . htmlspecialchars($note['title']) . " - WEZO CAMPUS HUB";

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
                        <i class="fas fa-edit text-primary me-2"></i> Edit Note
                    </h1>
                    <p class="text-muted mb-0">
                        Update your study notes
                    </p>
                </div>
                <a href="/notes/view.php?id=<?php echo $noteId; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-eye me-1"></i> View Note
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <!-- Edit Form -->
                    <form method="POST" action="" enctype="multipart/form-data" id="editForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                        <input type="hidden" name="action" value="update_note">
                        
                        <!-- Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label">
                                <i class="fas fa-heading me-1"></i> Title *
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="title" 
                                   name="title" 
                                   value="<?php echo htmlspecialchars($note['title']); ?>"
                                   required
                                   maxlength="200">
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
                                        <?php echo $note['category_id'] == $category['id'] ? 'selected' : ''; ?>>
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
                                      maxlength="1000"><?php echo htmlspecialchars($note['description']); ?></textarea>
                            <div class="invalid-feedback">Please provide a description.</div>
                            <div class="form-text">
                                <span id="descriptionCounter"><?php echo strlen($note['description']); ?></span>/1000 characters
                            </div>
                        </div>
                        
                        <!-- Content -->
                        <div class="mb-3">
                            <label for="content" class="form-label">
                                <i class="fas fa-file-alt me-1"></i> Content
                            </label>
                            <textarea class="form-control" 
                                      id="content" 
                                      name="content" 
                                      rows="6"><?php echo htmlspecialchars($note['content']); ?></textarea>
                        </div>
                        
                        <!-- File Upload -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-file me-1"></i> Current File
                            </label>
                            
                            <?php if ($note['file_path']): 
                                $fileSize = $note['file_size'] ? round($note['file_size'] / 1024, 2) . ' KB' : 'Unknown size';
                                $fileType = $note['file_type'] ?? 'Document';
                            ?>
                            <div class="card border mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-pdf fa-2x text-danger me-3"></i>
                                            <div>
                                                <strong><?php echo htmlspecialchars($note['file_path']); ?></strong>
                                                <div class="small text-muted">
                                                    <?php echo "{$fileType} • {$fileSize}"; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <a href="/notes/view.php?id=<?php echo $noteId; ?>&download=1" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download me-1"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No file uploaded for this note.
                            </div>
                            <?php endif; ?>
                            
                            <!-- Upload New File -->
                            <label for="note_file" class="form-label mt-3">
                                <i class="fas fa-file-upload me-1"></i> Upload New File (Optional)
                            </label>
                            <div class="file-upload-area border rounded p-3">
                                <input type="file" 
                                       class="form-control" 
                                       id="note_file" 
                                       name="note_file"
                                       accept=".pdf,.doc,.docx,.txt,.ppt,.pptx">
                                <div class="form-text small mt-2">
                                    Leave empty to keep current file. Max: 50MB. Allowed: PDF, DOC, DOCX, TXT, PPT, PPTX
                                </div>
                            </div>
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
                                   value="<?php echo htmlspecialchars($note['tags']); ?>"
                                   placeholder="Enter tags separated by commas">
                        </div>
                        
                        <!-- Pricing -->
                        <div class="mb-4">
                            <div class="form-check mb-2">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_free" 
                                       name="is_free" 
                                       value="1"
                                       <?php echo $note['is_free'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_free">
                                    These notes are free to download
                                </label>
                            </div>
                            
                            <div id="priceField" class="<?php echo $note['is_free'] ? 'd-none' : ''; ?>">
                                <label for="price" class="form-label">Price (KES)</label>
                                <div class="input-group">
                                    <span class="input-group-text">KSh</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           value="<?php echo $note['price']; ?>"
                                           min="0" 
                                           step="0.01">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Admin Options -->
                        <?php if (Auth::isAdmin() || Auth::isModerator()): ?>
                        <div class="border rounded p-3 mb-4 bg-light">
                            <h6 class="mb-3">
                                <i class="fas fa-shield-alt text-warning me-2"></i> Admin Options
                            </h6>
                            
                            <div class="form-check mb-2">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_approved" 
                                       name="is_approved" 
                                       value="1"
                                       <?php echo $note['is_approved'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_approved">
                                    Approved (Visible to users)
                                </label>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_featured" 
                                       name="is_featured" 
                                       value="1"
                                       <?php echo $note['is_featured'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_featured">
                                    Featured (Show in featured section)
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="fas fa-trash-alt me-1"></i> Delete Note
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Note Stats -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i> Note Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Downloads</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $note['download_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Views</span>
                            <span class="badge bg-info rounded-pill"><?php echo $note['view_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Likes</span>
                            <span class="badge bg-success rounded-pill"><?php echo $note['like_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Created</span>
                            <span class="small text-muted"><?php echo date('M d, Y', strtotime($note['created_at'])); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Last Updated</span>
                            <span class="small text-muted"><?php echo date('M d, Y', strtotime($note['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="/notes/view.php?id=<?php echo $noteId; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i> View Note
                        </a>
                        <a href="/notes/view.php?id=<?php echo $noteId; ?>&download=1" class="btn btn-outline-success">
                            <i class="fas fa-download me-2"></i> Download
                        </a>
                        <button class="btn btn-outline-info" onclick="previewNote()">
                            <i class="fas fa-search me-2"></i> Preview Changes
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Status Info -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i> Status Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted mb-1">Current Status</label>
                        <div>
                            <?php if ($note['is_approved']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle me-1"></i> Approved
                            </span>
                            <?php else: ?>
                            <span class="badge bg-warning">
                                <i class="fas fa-clock me-1"></i> Pending Approval
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($note['is_featured']): ?>
                            <span class="badge bg-primary ms-1">
                                <i class="fas fa-star me-1"></i> Featured
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-lightbulb me-2"></i>
                        Changes to approved notes will be reviewed by moderators.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i> Delete Note
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="delete_note">
                    
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">Warning: This action is irreversible!</h6>
                        <p class="mb-0 small">
                            The following will be permanently deleted:
                        </p>
                        <ul class="mb-0 small">
                            <li>Note content and description</li>
                            <li>Uploaded file (if any)</li>
                            <li>All reviews and ratings</li>
                            <li>Download history</li>
                        </ul>
                    </div>
                    
                    <p>Are you sure you want to delete "<strong><?php echo htmlspecialchars($note['title']); ?></strong>"?</p>
                    
                    <div class="mb-3">
                        <label for="confirmDelete" class="form-label">
                            Type "DELETE" to confirm:
                        </label>
                        <input type="text" class="form-control" id="confirmDelete" name="confirm_delete" required>
                        <div class="form-text">This action cannot be undone.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="deleteConfirmBtn" disabled>
                        <i class="fas fa-trash-alt me-1"></i> Delete Permanently
                    </button>
                </div>
            </form>
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

// Free/Paid toggle
const freeCheckbox = document.getElementById('is_free');
const priceField = document.getElementById('priceField');

freeCheckbox.addEventListener('change', function() {
    if (this.checked) {
        priceField.classList.add('d-none');
        document.getElementById('price').value = '0';
    } else {
        priceField.classList.remove('d-none');
    }
});

// Delete confirmation
const confirmDeleteInput = document.getElementById('confirmDelete');
const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');

confirmDeleteInput.addEventListener('input', function() {
    deleteConfirmBtn.disabled = this.value.toUpperCase() !== 'DELETE';
});

// Form validation
document.getElementById('editForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const category = document.getElementById('category_id').value;
    const description = document.getElementById('description').value.trim();
    
    let isValid = true;
    
    if (!title) {
        document.getElementById('title').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('title').classList.remove('is-invalid');
    }
    
    if (!category) {
        document.getElementById('category_id').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('category_id').classList.remove('is-invalid');
    }
    
    if (!description) {
        document.getElementById('description').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('description').classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields.');
    }
});

// Preview function
function previewNote() {
    // Collect form data
    const formData = {
        title: document.getElementById('title').value,
        description: document.getElementById('description').value,
        content: document.getElementById('content').value,
        tags: document.getElementById('tags').value
    };
    
    // Store in sessionStorage for preview page
    sessionStorage.setItem('notePreview', JSON.stringify(formData));
    
    // Open preview in new tab
    window.open('/notes/preview.php', '_blank');
}

// File size validation
document.getElementById('note_file').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const fileSizeMB = file.size / (1024 * 1024);
        if (fileSizeMB > 50) {
            alert('File size exceeds 50MB limit. Please choose a smaller file.');
            this.value = '';
        }
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>