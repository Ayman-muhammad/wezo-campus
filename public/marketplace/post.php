<?php
/**
 * WEZO CAMPUS HUB - Post Marketplace Item
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
        Session::flash('error', 'Security token invalid. Please try again.');
        header('Location: /marketplace/post.php');
        exit;
    }
    
    // Collect form data
    $data = [
        'title' => trim($_POST['title'] ?? ''),
        'category_id' => intval($_POST['category_id'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'price' => floatval($_POST['price'] ?? 0),
        'condition' => $_POST['condition'] ?? 'good',
        'location' => trim($_POST['location'] ?? ''),
        'contact_phone' => trim($_POST['contact_phone'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'negotiable' => isset($_POST['negotiable']) ? 1 : 0,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
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
    
    if ($data['price'] <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }
    
    if (empty($data['location'])) {
        $errors[] = 'Location is required for buyer safety.';
    }
    
    // Validate contact information
    if (empty($data['contact_phone']) && empty($data['contact_email'])) {
        $errors[] = 'Please provide at least one contact method (phone or email).';
    }
    
    if (!empty($data['contact_phone']) && !preg_match('/^[0-9+\-\s]{10,20}$/', $data['contact_phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
    
    if (!empty($data['contact_email']) && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    // Handle image uploads
    $uploadedImages = [];
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload = new Upload();
        $uploadResult = $upload->marketplaceImages($_FILES['images']);
        
        if (!$uploadResult['success']) {
            $errors[] = $uploadResult['message'];
        } else {
            $uploadedImages = $uploadResult['files'];
        }
    } else {
        $errors[] = 'At least one image is required.';
    }
    
    // Check if user has reached daily listing limit
    $dailyListings = $db->fetchColumn(
        "SELECT COUNT(*) FROM marketplace_items WHERE user_id = ? AND DATE(created_at) = CURDATE()",
        [$user['id']]
    );
    
    if ($dailyListings >= 5) {
        $errors[] = 'Daily listing limit reached (5 items per day).';
    }
    
    // If no errors, save item
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Generate slug
            $slug = $this->generateSlug($data['title']);
            $counter = 1;
            while ($db->exists('marketplace_items', 'slug = ?', [$slug])) {
                $slug = $this->generateSlug($data['title']) . '-' . $counter;
                $counter++;
            }
            
            // Prepare images array
            $imageFilenames = array_map(function($img) {
                return $img['filename'];
            }, $uploadedImages);
            
            // Insert item
            $itemId = $db->insert('marketplace_items', [
                'user_id' => $user['id'],
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'],
                'price' => $data['price'],
                'condition' => $data['condition'],
                'location' => $data['location'],
                'contact_phone' => $data['contact_phone'],
                'contact_email' => $data['contact_email'],
                'negotiable' => $data['negotiable'],
                'images' => json_encode($imageFilenames),
                'status' => 'active',
                'is_approved' => Auth::isAdmin() || Auth::isModerator() ? 1 : 0,
                'expires_at' => $data['expires_at']
            ]);
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $user['id'],
                'action' => 'marketplace_post',
                'description' => 'Posted item: ' . $data['title'],
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
            
            $db->commit();
            
            Session::flash('success', 
                Auth::isAdmin() || Auth::isModerator() 
                ? 'Item listed successfully!' 
                : 'Item submitted for review. It will be visible after approval.'
            );
            
            header('Location: /marketplace/item.php?id=' . $itemId);
            exit;
            
        } catch (Exception $e) {
            $db->rollback();
            Session::flash('error', 'Failed to list item. Please try again.');
            
            // Delete uploaded images if item creation failed
            foreach ($uploadedImages as $image) {
                if (isset($image['path'])) {
                    unlink($image['path']);
                }
            }
        }
    } else {
        $errorMessage = implode('<br>', $errors);
        Session::flash('error', $errorMessage);
    }
}

// Get categories for dropdown
$categories = $db->fetchAll("SELECT * FROM marketplace_categories ORDER BY name");

// Set page title
$pageTitle = "Sell Item - WEZO CAMPUS HUB";

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
                        <i class="fas fa-tag text-success me-2"></i> Sell an Item
                    </h1>
                    <p class="text-muted mb-0">
                        List your item for sale to campus students
                    </p>
                </div>
                <a href="/marketplace/" class="btn btn-outline-success">
                    <i class="fas fa-arrow-left me-1"></i> Back to Marketplace
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <!-- Safety Notice -->
                    <div class="alert alert-warning mb-4">
                        <h6 class="alert-heading">
                            <i class="fas fa-shield-alt me-2"></i> Safety First!
                        </h6>
                        <ul class="mb-0 small">
                            <li>Meet buyers in safe, public places on campus</li>
                            <li>Never share personal information like home address</li>
                            <li>Use campus security escort service if needed</li>
                            <li>Trust your instincts - if something feels wrong, it probably is</li>
                        </ul>
                    </div>
                    
                    <!-- Listing Form -->
                    <form method="POST" action="" enctype="multipart/form-data" id="listingForm" novalidate>
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
                                   placeholder="e.g., MacBook Pro 2020, Calculus Textbook, Winter Jacket">
                            <div class="invalid-feedback">Please enter a title for your item.</div>
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
                                      placeholder="Describe your item in detail (condition, specifications, reason for selling, etc.)"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="invalid-feedback">Please provide a description.</div>
                            <div class="form-text">
                                <span id="descriptionCounter">0</span>/1000 characters
                            </div>
                        </div>
                        
                        <!-- Price -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label">
                                    <i class="fas fa-money-bill-wave me-1"></i> Price (KES) *
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">KSh</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                                           required
                                           min="0"
                                           step="0.01"
                                           placeholder="0.00">
                                </div>
                                <div class="invalid-feedback">Please enter a valid price.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="condition" class="form-label">
                                    <i class="fas fa-star me-1"></i> Condition *
                                </label>
                                <select class="form-select" id="condition" name="condition" required>
                                    <option value="">Select condition</option>
                                    <option value="new" <?php echo ($_POST['condition'] ?? '') === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="like_new" <?php echo ($_POST['condition'] ?? '') === 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                    <option value="good" <?php echo ($_POST['condition'] ?? '') === 'good' ? 'selected' : ''; ?>>Good</option>
                                    <option value="fair" <?php echo ($_POST['condition'] ?? '') === 'fair' ? 'selected' : ''; ?>>Fair</option>
                                    <option value="poor" <?php echo ($_POST['condition'] ?? '') === 'poor' ? 'selected' : ''; ?>>Poor</option>
                                </select>
                                <div class="invalid-feedback">Please select condition.</div>
                            </div>
                        </div>
                        
                        <!-- Negotiable -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="negotiable" 
                                       name="negotiable" 
                                       value="1"
                                       <?php echo isset($_POST['negotiable']) ? 'checked' : 'checked'; ?>>
                                <label class="form-check-label" for="negotiable">
                                    Price is negotiable
                                </label>
                            </div>
                        </div>
                        
                        <!-- Images -->
                        <div class="mb-4">
                            <label for="images" class="form-label">
                                <i class="fas fa-images me-1"></i> Photos *
                            </label>
                            <div class="image-upload-area border rounded p-4 text-center">
                                <input type="file" 
                                       class="form-control d-none" 
                                       id="images" 
                                       name="images[]"
                                       accept="image/*"
                                       multiple>
                                
                                <div id="imageUploadPlaceholder">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-2">Click to upload or drag & drop images</p>
                                    <p class="small text-muted mb-0">
                                        Upload at least 1 clear photo of your item (max 5 images, 5MB each)
                                    </p>
                                    <button type="button" class="btn btn-outline-success btn-sm mt-3" id="browseImagesBtn">
                                        <i class="fas fa-camera me-1"></i> Select Images
                                    </button>
                                </div>
                                
                                <div id="imagePreview" class="row mt-3 d-none">
                                    <!-- Images will be previewed here -->
                                </div>
                                
                                <div class="alert alert-info small mt-3 mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>
                                    Tips: Use good lighting, show all angles, include any defects.
                                </div>
                            </div>
                            <div class="form-text">Clear photos help items sell faster.</div>
                        </div>
                        
                        <!-- Location -->
                        <div class="mb-3">
                            <label for="location" class="form-label">
                                <i class="fas fa-map-marker-alt me-1"></i> Location *
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="location" 
                                   name="location" 
                                   value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>"
                                   required
                                   placeholder="e.g., Campus Hostels Block A, Student Center, Library Area">
                            <div class="invalid-feedback">Please enter a location for meeting buyers.</div>
                            <div class="form-text">Meeting location for buyers (use general campus areas, not specific addresses).</div>
                        </div>
                        
                        <!-- Contact Information -->
                        <div class="card border mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="fas fa-address-book me-2"></i> Contact Information *
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="small text-muted mb-3">
                                    Provide at least one contact method. This will be shown to interested buyers.
                                </p>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_phone" class="form-label">
                                            <i class="fas fa-phone me-1"></i> Phone Number
                                        </label>
                                        <input type="tel" 
                                               class="form-control" 
                                               id="contact_phone" 
                                               name="contact_phone" 
                                               value="<?php echo htmlspecialchars($_POST['contact_phone'] ?? $user['phone'] ?? ''); ?>"
                                               placeholder="+254 700 000 000">
                                        <div class="form-text">Used for SMS/WhatsApp communication</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="contact_email" class="form-label">
                                            <i class="fas fa-envelope me-1"></i> Email Address
                                        </label>
                                        <input type="email" 
                                               class="form-control" 
                                               id="contact_email" 
                                               name="contact_email" 
                                               value="<?php echo htmlspecialchars($_POST['contact_email'] ?? $user['email'] ?? ''); ?>"
                                               placeholder="your.email@example.com">
                                        <div class="form-text">Used for email notifications</div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning small mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Your contact information will be visible to other students. Use campus email if possible.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the 
                                    <a href="/marketplace/terms.php" target="_blank" class="text-decoration-none">Marketplace Terms</a> 
                                    and understand that I am responsible for safe transactions *
                                </label>
                                <div class="invalid-feedback">You must agree to the marketplace terms.</div>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="action" value="draft" class="btn btn-outline-secondary">
                                <i class="fas fa-save me-1"></i> Save as Draft
                            </button>
                            <button type="submit" name="action" value="publish" class="btn btn-success">
                                <i class="fas fa-paper-plane me-1"></i> 
                                <?php echo Auth::isAdmin() || Auth::isModerator() ? 'List Item Now' : 'Submit for Review'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Pricing Tips -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-lightbulb text-warning me-2"></i> Pricing Tips
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small">
                        <li class="mb-2"><strong>Research:</strong> Check similar items on campus</li>
                        <li class="mb-2"><strong>Be Realistic:</strong> Consider item condition and age</li>
                        <li class="mb-2"><strong>Negotiation Room:</strong> Price slightly higher if negotiable</li>
                        <li class="mb-2"><strong>Original Price:</strong> Mention original price if relevant</li>
                        <li class="mb-2"><strong>Urgency:</strong> Lower price if you need quick sale</li>
                        <li><strong>Campus Discount:</strong> Students appreciate student prices</li>
                    </ul>
                </div>
            </div>
            
            <!-- Photo Guidelines -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-camera text-info me-2"></i> Photo Guidelines
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="text-center">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                <p class="small mb-0">Good lighting</p>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="text-center">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                <p class="small mb-0">Multiple angles</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                <p class="small mb-0">Show defects</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                                <p class="small mb-0">Clear background</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Listings -->
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i> Your Recent Listings
                    </h5>
                    <a href="/marketplace/?user=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php
                    $recentItems = $db->fetchAll("
                        SELECT m.*, c.name as category_name 
                        FROM marketplace_items m 
                        LEFT JOIN marketplace_categories c ON m.category_id = c.id 
                        WHERE m.user_id = ? 
                        ORDER BY m.created_at DESC 
                        LIMIT 5
                    ", [$user['id']]);
                    
                    if (empty($recentItems)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-store fa-2x text-muted mb-3"></i>
                        <p class="text-muted small mb-0">No items listed yet</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentItems as $item): ?>
                        <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 small"><?php echo htmlspecialchars($item['title']); ?></h6>
                                <span class="badge bg-<?php echo $item['is_approved'] ? 'success' : 'warning'; ?> small">
                                    <?php echo $item['is_approved'] ? 'Active' : 'Pending'; ?>
                                </span>
                            </div>
                            <small class="text-muted d-block mb-1">
                                <?php echo htmlspecialchars($item['category_name']); ?>
                            </small>
                            <small class="text-muted">
                                KSh <?php echo number_format($item['price'], 2); ?>
                                • <?php echo date('M d', strtotime($item['created_at'])); ?>
                            </small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
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
if (descriptionTextarea.value) {
    descriptionCounter.textContent = descriptionTextarea.value.length;
}

// Image upload handling
const imagesInput = document.getElementById('images');
const browseBtn = document.getElementById('browseImagesBtn');
const imagePlaceholder = document.getElementById('imageUploadPlaceholder');
const imagePreview = document.getElementById('imagePreview');
const maxImages = 5;

// Browse button click
browseBtn.addEventListener('click', function() {
    imagesInput.click();
});

// Image input change
imagesInput.addEventListener('change', function() {
    const files = Array.from(this.files);
    
    // Validate number of images
    if (files.length > maxImages) {
        alert(`Maximum ${maxImages} images allowed. Please select fewer images.`);
        this.value = '';
        return;
    }
    
    // Validate each file
    let hasErrors = false;
    files.forEach((file, index) => {
        // Check file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert(`"${file.name}" exceeds 5MB size limit.`);
            hasErrors = true;
        }
        
        // Check file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert(`"${file.name}" is not a valid image type. Please use JPG, PNG, GIF, or WebP.`);
            hasErrors = true;
        }
    });
    
    if (hasErrors) {
        this.value = '';
        return;
    }
    
    // Show preview
    updateImagePreview(files);
});

// Update image preview
function updateImagePreview(files) {
    imagePreview.innerHTML = '';
    
    if (files.length === 0) {
        imagePlaceholder.classList.remove('d-none');
        imagePreview.classList.add('d-none');
        return;
    }
    
    files.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4 mb-3';
            
            col.innerHTML = `
                <div class="image-preview-item position-relative">
                    <img src="${e.target.result}" 
                         class="img-thumbnail" 
                         alt="Preview ${index + 1}"
                         style="height: 100px; width: 100%; object-fit: cover;">
                    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 remove-image" data-index="${index}">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="small text-center mt-1">${file.name.length > 15 ? file.name.substring(0, 15) + '...' : file.name}</div>
                </div>
            `;
            
            imagePreview.appendChild(col);
        };
        reader.readAsDataURL(file);
    });
    
    imagePlaceholder.classList.add('d-none');
    imagePreview.classList.remove('d-none');
}

// Remove image
imagePreview.addEventListener('click', function(e) {
    if (e.target.closest('.remove-image')) {
        const index = parseInt(e.target.closest('.remove-image').dataset.index);
        const dt = new DataTransfer();
        const files = Array.from(imagesInput.files);
        
        // Remove file from array
        files.splice(index, 1);
        
        // Update file input
        files.forEach(file => {
            dt.items.add(file);
        });
        imagesInput.files = dt.files;
        
        // Update preview
        updateImagePreview(files);
    }
});

// Drag and drop functionality
const uploadArea = imagePlaceholder.parentElement;

uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('border-success', 'bg-light');
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    this.classList.remove('border-success', 'bg-light');
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-success', 'bg-light');
    
    if (e.dataTransfer.files.length > 0) {
        imagesInput.files = e.dataTransfer.files;
        const event = new Event('change', { bubbles: true });
        imagesInput.dispatchEvent(event);
    }
});

// Form validation
document.getElementById('listingForm').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const category = document.getElementById('category_id').value;
    const description = document.getElementById('description').value.trim();
    const price = document.getElementById('price').value;
    const condition = document.getElementById('condition').value;
    const location = document.getElementById('location').value.trim();
    const contactPhone = document.getElementById('contact_phone').value.trim();
    const contactEmail = document.getElementById('contact_email').value.trim();
    const images = imagesInput.files;
    const terms = document.getElementById('terms').checked;
    
    let isValid = true;
    
    // Check required fields
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
    
    if (!price || parseFloat(price) <= 0) {
        document.getElementById('price').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('price').classList.remove('is-invalid');
    }
    
    if (!condition) {
        document.getElementById('condition').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('condition').classList.remove('is-invalid');
    }
    
    if (!location) {
        document.getElementById('location').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('location').classList.remove('is-invalid');
    }
    
    if (!contactPhone && !contactEmail) {
        document.getElementById('contact_phone').classList.add('is-invalid');
        document.getElementById('contact_email').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('contact_phone').classList.remove('is-invalid');
        document.getElementById('contact_email').classList.remove('is-invalid');
    }
    
    if (images.length === 0) {
        alert('Please upload at least one image of your item.');
        isValid = false;
    }
    
    if (!terms) {
        document.getElementById('terms').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('terms').classList.remove('is-invalid');
    }
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields correctly.');
    }
});

// Auto-fill location based on user profile
document.addEventListener('DOMContentLoaded', function() {
    const locationInput = document.getElementById('location');
    if (!locationInput.value) {
        // Could use geolocation or campus data here
        locationInput.placeholder = 'e.g., Main Campus, Library Area, Student Center';
    }
});
</script>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>