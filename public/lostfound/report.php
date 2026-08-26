<?php
/**
 * WEZO CAMPUS HUB - Report Lost/Found Item
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Validation.php';
require_once __DIR__ . '/../../core/Helpers.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Validation;
use Core\Helpers;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

// Get categories and campuses
$categories = $db->fetchAll("SELECT * FROM lostfound_categories ORDER BY name");
$campuses = $db->fetchAll("SELECT * FROM campuses ORDER BY name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validation($_POST);
    
    $validator->rules([
        'type' => ['required', 'in:lost,found'],
        'title' => ['required', 'min:5', 'max:200'],
        'category_id' => ['required', 'numeric'],
        'description' => ['required', 'min:20', 'max:2000'],
        'location' => ['required', 'min:5', 'max:255'],
        'campus_id' => ['required', 'numeric'],
        'date_incident' => ['required', 'date'],
        'contact_preference' => ['required', 'in:email,phone,both'],
        'reward' => ['numeric', 'min:0', 'max:100000']
    ]);
    
    // Validate file upload
    $uploadResult = null;
    if (isset($_FILES['images']) && is_uploaded_file($_FILES['images']['tmp_name'][0])) {
        $uploadResult = validateAndUploadImages($_FILES['images']);
        if (!$uploadResult['success']) {
            Session::setFlash('error', $uploadResult['message']);
        }
    }
    
    if ($validator->validate() && (!isset($uploadResult) || $uploadResult['success'])) {
        try {
            $db->beginTransaction();
            
            // Prepare item data
            $itemData = [
                'user_id' => $user['id'],
                'type' => $_POST['type'],
                'title' => $_POST['title'],
                'category_id' => $_POST['category_id'],
                'description' => $_POST['description'],
                'location' => $_POST['location'],
                'campus_id' => $_POST['campus_id'],
                'date_incident' => $_POST['date_incident'],
                'contact_preference' => $_POST['contact_preference'],
                'reward' => $_POST['reward'] ? floatval($_POST['reward']) : null,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Add images if uploaded
            if ($uploadResult && $uploadResult['success']) {
                $itemData['images'] = json_encode($uploadResult['files']);
            }
            
            // Insert item
            $itemId = $db->insert('lostfound', $itemData);
            
            // Log activity
            $db->insert('activity_logs', [
                'user_id' => $user['id'],
                'action' => 'lostfound_report',
                'description' => 'Reported ' . $_POST['type'] . ' item: ' . $_POST['title'],
                'item_type' => 'lostfound',
                'item_id' => $itemId,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Create notification for campus admins
            $campusAdmins = $db->fetchAll("
                SELECT u.id FROM users u 
                WHERE u.campus_id = ? AND u.role IN ('admin', 'moderator') AND u.status = 'active'
            ", [$_POST['campus_id']]);
            
            foreach ($campusAdmins as $admin) {
                $db->insert('notifications', [
                    'user_id' => $admin['id'],
                    'title' => 'New ' . ucfirst($_POST['type']) . ' Item Reported',
                    'message' => $_POST['title'] . ' was reported on your campus.',
                    'type' => 'info',
                    'link' => '/lostfound/view.php?id=' . $itemId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $db->commit();
            
            // Attempt automatic matching
            attemptAutomaticMatching($itemId, $_POST['type'], $_POST['title'], $_POST['category_id'], 
                                   $_POST['campus_id'], $_POST['location']);
            
            Session::setFlash('success', 
                ucfirst($_POST['type']) . ' item reported successfully! ' .
                ($_POST['type'] === 'lost' ? 
                 'We will notify you if any matching found items are reported.' : 
                 'We will notify you if any matching lost items are reported.')
            );
            
            header('Location: view.php?id=' . $itemId);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            Session::setFlash('error', 'Failed to report item: ' . $e->getMessage());
        }
    } else {
        Session::setFlash('error', 'Please correct the errors below.');
    }
}

/**
 * Validate and upload images
 */
function validateAndUploadImages($files) {
    $maxFiles = 5;
    $maxSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    $uploadedFiles = [];
    $errors = [];
    
    for ($i = 0; $i < min(count($files['name']), $maxFiles); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            // Check file size
            if ($files['size'][$i] > $maxSize) {
                $errors[] = "File '{$files['name'][$i]}' exceeds maximum size of 5MB";
                continue;
            }
            
            // Check file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowedTypes)) {
                $errors[] = "File '{$files['name'][$i]}' is not a valid image type";
                continue;
            }
            
            // Generate unique filename
            $extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $uploadPath = upload_path('lostfound/' . $filename);
            
            // Create directory if it doesn't exist
            $dir = dirname($uploadPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Move uploaded file
            if (move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
                // Resize image if too large
                if ($files['size'][$i] > 1024 * 1024) { // > 1MB
                    Helpers::resizeImage($uploadPath, $uploadPath, 1200, 1200, 80);
                }
                
                $uploadedFiles[] = [
                    'filename' => $filename,
                    'original_name' => $files['name'][$i],
                    'size' => $files['size'][$i],
                    'type' => $mime
                ];
            }
        }
    }
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => implode('<br>', $errors),
            'files' => $uploadedFiles
        ];
    }
    
    return [
        'success' => true,
        'files' => $uploadedFiles
    ];
}

/**
 * Attempt automatic matching
 */
function attemptAutomaticMatching($itemId, $type, $title, $categoryId, $campusId, $location) {
    $db = Database::getInstance();
    
    // Find matching items of opposite type
    $oppositeType = $type === 'lost' ? 'found' : 'lost';
    
    $query = "
        SELECT lf.id, lf.title, lf.description, lf.location, 
               SIMILARITY(?, lf.title) as title_similarity,
               SIMILARITY(?, lf.location) as location_similarity
        FROM lostfound lf
        WHERE lf.type = ? 
          AND lf.category_id = ? 
          AND lf.campus_id = ? 
          AND lf.status = 'active'
          AND lf.id != ?
        HAVING title_similarity > 0.3 OR location_similarity > 0.5
        ORDER BY (title_similarity * 0.7 + location_similarity * 0.3) DESC
        LIMIT 5
    ";
    
    $matches = $db->fetchAll($query, [$title, $location, $oppositeType, $categoryId, $campusId, $itemId]);
    
    foreach ($matches as $match) {
        // Create match record
        $db->insert('lostfound_matches', [
            'lostfound_id' => $itemId,
            'matched_item_id' => $match['id'],
            'confidence_score' => ($match['title_similarity'] * 0.7 + $match['location_similarity'] * 0.3) * 100,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create notification for item owner
        $itemOwner = $db->fetch("SELECT user_id FROM lostfound WHERE id = ?", [$match['id']]);
        if ($itemOwner) {
            $db->insert('notifications', [
                'user_id' => $itemOwner['user_id'],
                'title' => 'Potential Match Found!',
                'message' => 'A ' . $oppositeType . ' item similar to yours has been reported.',
                'type' => 'info',
                'link' => '/lostfound/match.php?id1=' . $match['id'] . '&id2=' . $itemId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Create notification for current user
        $db->insert('notifications', [
            'user_id' => $GLOBALS['user']['id'],
            'title' => 'Potential Match Found!',
            'message' => 'We found a ' . $oppositeType . ' item that might match your report.',
            'type' => 'info',
            'link' => '/lostfound/match.php?id1=' . $itemId . '&id2=' . $match['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}

$pageTitle = "Report Lost/Found Item - WEZO CAMPUS HUB";
include '../../templates/header.php';
?>

<div class="container-fluid px-4 py-5">
    <!-- Page Header -->
    <div class="row mb-5">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Lost & Found</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Report Item</li>
                </ol>
            </nav>
            <h1 class="display-5 fw-bold mb-3">
                <i class="fas fa-plus-circle text-primary me-3"></i>Report Lost or Found Item
            </h1>
            <p class="lead text-muted mb-0">
                Help reunite lost items with their owners by providing detailed information.
            </p>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (Session::hasFlash('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-5" role="alert">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-circle me-3 fa-lg"></i>
            <div>
                <h5 class="alert-heading mb-2">Please correct the following errors:</h5>
                <div class="mb-0"><?php echo Session::getFlash('error'); ?></div>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8 mb-5">
            <div class="card border-0 shadow-lg">
                <div class="card-body p-5">
                    <form method="POST" enctype="multipart/form-data" novalidate>
                        <!-- Report Type -->
                        <div class="row mb-5">
                            <div class="col-12">
                                <h5 class="fw-bold mb-4">
                                    <i class="fas fa-question-circle text-primary me-2"></i>What are you reporting?
                                </h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="type" id="type-lost" 
                                               value="lost" <?php echo ($_POST['type'] ?? '') === 'lost' ? 'checked' : ''; ?> required>
                                        <label class="btn btn-outline-danger w-100 py-4" for="type-lost">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <i class="fas fa-search fa-3x me-3"></i>
                                                <div class="text-start">
                                                    <div class="h5 mb-2">Lost Item</div>
                                                    <small class="text-muted">Something you lost</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="type" id="type-found" 
                                               value="found" <?php echo ($_POST['type'] ?? '') === 'found' ? 'checked' : ''; ?> required>
                                        <label class="btn btn-outline-success w-100 py-4" for="type-found">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <i class="fas fa-hand-holding-heart fa-3x me-3"></i>
                                                <div class="text-start">
                                                    <div class="h5 mb-2">Found Item</div>
                                                    <small class="text-muted">Something you found</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div class="row mb-5">
                            <div class="col-12">
                                <h5 class="fw-bold mb-4">
                                    <i class="fas fa-info-circle text-primary me-2"></i>Basic Information
                                </h5>
                                
                                <div class="row g-4">
                                    <div class="col-md-12 mb-4">
                                        <label class="form-label fw-semibold">Item Title *</label>
                                        <input type="text" class="form-control form-control-lg <?php echo isset($validator) && $validator->hasError('title') ? 'is-invalid' : ''; ?>" 
                                               name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                               placeholder="e.g., Black Samsung Galaxy S21 with blue case" required>
                                        <?php if (isset($validator) && $validator->hasError('title')): ?>
                                        <div class="invalid-feedback"><?php echo $validator->getError('title'); ?></div>
                                        <?php else: ?>
                                        <div class="form-text">Be specific to help with matching</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">Category *</label>
                                        <select class="form-select form-select-lg <?php echo isset($validator) && $validator->hasError('category_id') ? 'is-invalid' : ''; ?>" 
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
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">Campus/Location *</label>
                                        <select class="form-select form-select-lg <?php echo isset($validator) && $validator->hasError('campus_id') ? 'is-invalid' : ''; ?>" 
                                                name="campus_id" required>
                                            <option value="">Select Campus</option>
                                            <?php foreach ($campuses as $campus): ?>
                                            <option value="<?php echo $campus['id']; ?>" 
                                                    <?php echo ($_POST['campus_id'] ?? '') == $campus['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($campus['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($validator) && $validator->hasError('campus_id')): ?>
                                        <div class="invalid-feedback"><?php echo $validator->getError('campus_id'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">Date of Incident *</label>
                                        <input type="date" class="form-control form-control-lg <?php echo isset($validator) && $validator->hasError('date_incident') ? 'is-invalid' : ''; ?>" 
                                               name="date_incident" value="<?php echo $_POST['date_incident'] ?? date('Y-m-d'); ?>" 
                                               max="<?php echo date('Y-m-d'); ?>" required>
                                        <?php if (isset($validator) && $validator->hasError('date_incident')): ?>
                                        <div class="invalid-feedback"><?php echo $validator->getError('date_incident'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">Specific Location *</label>
                                        <input type="text" class="form-control form-control-lg <?php echo isset($validator) && $validator->hasError('location') ? 'is-invalid' : ''; ?>" 
                                               name="location" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                               placeholder="e.g., Library, Room 205, Cafeteria near entrance" required>
                                        <?php if (isset($validator) && $validator->hasError('location')): ?>
                                        <div class="invalid-feedback"><?php echo $validator->getError('location'); ?></div>
                                        <?php else: ?>
                                        <div class="form-text">Be as specific as possible</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Item Description -->
                        <div class="row mb-5">
                            <div class="col-12">
                                <h5 class="fw-bold mb-4">
                                    <i class="fas fa-align-left text-primary me-2"></i>Detailed Description
                                </h5>
                                
                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Description *</label>
                                    <textarea class="form-control <?php echo isset($validator) && $validator->hasError('description') ? 'is-invalid' : ''; ?>" 
                                              name="description" rows="6" required
                                              placeholder="Describe the item in detail. Include brand, model, color, size, distinguishing features, contents, etc."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <?php if (isset($validator) && $validator->hasError('description')): ?>
                                    <div class="invalid-feedback"><?php echo $validator->getError('description'); ?></div>
                                    <?php else: ?>
                                    <div class="form-text">The more details you provide, the better chance of matching</div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Description Tips -->
                                <div class="alert alert-info">
                                    <div class="d-flex">
                                        <i class="fas fa-lightbulb me-3 fa-lg mt-1"></i>
                                        <div>
                                            <h6 class="alert-heading mb-2">Description Tips:</h6>
                                            <ul class="mb-0 small">
                                                <li>Include brand, model, color, and size</li>
                                                <li>Mention any unique features, scratches, or marks</li>
                                                <li>Note any contents inside (if applicable)</li>
                                                <li>For electronics: mention accessories, cases, or stickers</li>
                                                <li>For documents: mention names, dates, or identifying info</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Images Upload -->
                        <div class="row mb-5">
                            <div class="col-12">
                                <h5 class="fw-bold mb-4">
                                    <i class="fas fa-images text-primary me-2"></i>Upload Images (Optional but Recommended)
                                </h5>
                                
                                <div class="file-upload-area border-2 border-dashed rounded-3 p-5 text-center mb-4">
                                    <input type="file" class="file-input" name="images[]" 
                                           id="imageUpload" multiple accept="image/*">
                                    <div class="file-upload-preview" id="filePreview">
                                        <i class="fas fa-cloud-upload-alt fa-4x text-muted mb-3"></i>
                                        <h5>Drag & drop images here</h5>
                                        <p class="text-muted">or click to browse</p>
                                        <p class="small text-muted">
                                            Max 5 images. Each up to 5MB. Supported: JPG, PNG, GIF, WebP
                                        </p>
                                    </div>
                                    <div class="file-info d-none" id="fileInfo">
                                        <div class="alert alert-light">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-images fa-2x text-primary me-3"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1" id="fileCount"></h6>
                                                    <small class="text-muted" id="fileSize"></small>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="clearFiles()">
                                                    <i class="fas fa-times"></i> Clear All
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="imagePreviews" class="row g-3"></div>
                            </div>
                        </div>

                        <!-- Contact & Reward -->
                        <div class="row mb-5">
                            <div class="col-12">
                                <h5 class="fw-bold mb-4">
                                    <i class="fas fa-user-circle text-primary me-2"></i>Contact & Additional Information
                                </h5>
                                
                                <div class="row g-4">
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">Preferred Contact Method *</label>
                                        <select class="form-select <?php echo isset($validator) && $validator->hasError('contact_preference') ? 'is-invalid' : ''; ?>" 
                                                name="contact_preference" required>
                                            <option value="">Select Method</option>
                                            <option value="email" <?php echo ($_POST['contact_preference'] ?? '') === 'email' ? 'selected' : ''; ?>>Email Only</option>
                                            <option value="phone" <?php echo ($_POST['contact_preference'] ?? '') === 'phone' ? 'selected' : ''; ?>>Phone Only</option>
                                            <option value="both" <?php echo ($_POST['contact_preference'] ?? '') === 'both' ? 'selected' : ''; ?>>Both Email & Phone</option>
                                        </select>
                                        <?php if (isset($validator) && $validator->hasError('contact_preference')): ?>
                                        <div class="invalid-feedback"><?php echo $validator->getError('contact_preference'); ?></div>
                                        <?php else: ?>
                                        <div class="form-text">How should potential matches contact you?</div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">Reward Amount (KES)</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">KSh</span>
                                            <input type="number" class="form-control <?php echo isset($validator) && $validator->hasError('reward') ? 'is-invalid' : ''; ?>" 
                                                   name="reward" value="<?php echo $_POST['reward'] ?? ''; ?>" 
                                                   min="0" max="100000" step="100" placeholder="Optional">
                                        </div>
                                        <?php if (isset($validator) && $validator->hasError('reward')): ?>
                                        <div class="invalid-feedback"><?php echo $validator->getError('reward'); ?></div>
                                        <?php else: ?>
                                        <div class="form-text">Optional reward for return (for lost items)</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Privacy Notice -->
                                <div class="alert alert-warning">
                                    <div class="d-flex">
                                        <i class="fas fa-shield-alt me-3 fa-lg mt-1"></i>
                                        <div>
                                            <h6 class="alert-heading mb-2">Privacy Notice:</h6>
                                            <p class="mb-0 small">Your contact information will only be shared with verified matches. 
                                            Never share sensitive personal information in public descriptions.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submission -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-outline-secondary btn-lg px-4">
                                        <i class="fas fa-arrow-left me-2"></i>Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Tips Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-lightbulb text-warning me-2"></i>Tips for Success
                    </h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Be Specific:</strong> Detailed descriptions increase match chances
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Upload Photos:</strong> Visuals significantly improve matching
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Check Regularly:</strong> Review potential matches frequently
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Update Status:</strong> Mark items as resolved when found/returned
                        </li>
                    </ul>
                </div>
            </div>

            <!-- What Happens Next -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-clock text-info me-2"></i>What Happens Next
                    </h5>
                    <div class="timeline">
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker bg-primary"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">1. Automatic Matching</h6>
                                <p class="small text-muted mb-0">System scans for similar items</p>
                            </div>
                        </div>
                        <div class="timeline-item mb-3">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">2. Notification</h6>
                                <p class="small text-muted mb-0">You'll be notified of potential matches</p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">3. Verification</h6>
                                <p class="small text-muted mb-0">Verify item details with match</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Success Stories -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-handshake text-success me-2"></i>Recent Success Stories
                    </h5>
                    <?php
                    $successStories = $db->fetchAll("
                        SELECT lf.title, u.first_name, u.last_name, lf.updated_at
                        FROM lostfound lf
                        JOIN users u ON lf.user_id = u.id
                        WHERE lf.status = 'resolved'
                        ORDER BY lf.updated_at DESC
                        LIMIT 3
                    ");
                    ?>
                    <?php if (!empty($successStories)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($successStories as $story): ?>
                        <div class="list-group-item border-0 px-0 py-3">
                            <div class="d-flex">
                                <i class="fas fa-check-circle text-success mt-1 me-3"></i>
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($story['title']); ?></h6>
                                    <small class="text-muted">
                                        Reunited by <?php echo htmlspecialchars($story['first_name']); ?> • 
                                        <?php echo Helpers::timeAgo($story['updated_at']); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted small mb-0">No success stories yet. Be the first!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// File upload functionality
const imageUpload = document.getElementById('imageUpload');
const filePreview = document.getElementById('filePreview');
const fileInfo = document.getElementById('fileInfo');
const imagePreviews = document.getElementById('imagePreviews');
const fileCount = document.getElementById('fileCount');
const fileSize = document.getElementById('fileSize');

let uploadedFiles = [];

imageUpload.addEventListener('change', function(e) {
    uploadedFiles = Array.from(this.files);
    
    if (uploadedFiles.length > 0) {
        // Show file info
        const totalSize = uploadedFiles.reduce((sum, file) => sum + file.size, 0);
        fileCount.textContent = `${uploadedFiles.length} image(s) selected`;
        fileSize.textContent = `Total size: ${formatFileSize(totalSize)}`;
        
        filePreview.classList.add('d-none');
        fileInfo.classList.remove('d-none');
        
        // Show image previews
        imagePreviews.innerHTML = '';
        uploadedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const col = document.createElement('div');
                col.className = 'col-6 col-md-4';
                col.innerHTML = `
                    <div class="card position-relative">
                        <img src="${e.target.result}" class="card-img-top" alt="Preview" style="height: 150px; object-fit: cover;">
                        <div class="card-body p-2">
                            <small class="d-block text-truncate">${file.name}</small>
                            <small class="text-muted">${formatFileSize(file.size)}</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1" 
                                onclick="removeFile(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                imagePreviews.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    } else {
        clearFiles();
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
        imageUpload.files = e.dataTransfer.files;
        imageUpload.dispatchEvent(new Event('change'));
    }
});

// Clear all files
function clearFiles() {
    imageUpload.value = '';
    uploadedFiles = [];
    filePreview.classList.remove('d-none');
    fileInfo.classList.add('d-none');
    imagePreviews.innerHTML = '';
}

// Remove single file
function removeFile(index) {
    uploadedFiles.splice(index, 1);
    
    // Create new DataTransfer to update file input
    const dataTransfer = new DataTransfer();
    uploadedFiles.forEach(file => dataTransfer.items.add(file));
    imageUpload.files = dataTransfer.files;
    
    // Trigger change event to update UI
    imageUpload.dispatchEvent(new Event('change'));
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
    // Validate file count
    if (uploadedFiles.length > 5) {
        e.preventDefault();
        showToast('Maximum 5 images allowed', 'warning');
        return;
    }
    
    // Validate individual file sizes
    for (const file of uploadedFiles) {
        if (file.size > 5 * 1024 * 1024) { // 5MB
            e.preventDefault();
            showToast(`File "${file.name}" exceeds 5MB limit`, 'warning');
            return;
        }
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    
    // Auto-enable after 10 seconds in case of error
    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }, 10000);
});

// Initialize form validation
(function() {
    'use strict';
    
    // Fetch all forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
        });
})();

// Show toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(container);
    }
    
    container.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast, { delay: 5000 });
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}
</script>

<style>
.file-upload-area {
    border: 2px dashed #dee2e6;
    transition: all 0.3s ease;
}

.file-upload-area:hover {
    border-color: #4e73df;
    background-color: rgba(78, 115, 223, 0.05);
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    padding-left: 1rem;
}

.timeline-marker {
    position: absolute;
    left: -1.5rem;
    top: 0.5rem;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid white;
}

.btn-check:checked + label {
    border-width: 2px;
}

.btn-check:not(:checked) + label:hover {
    background-color: #f8f9fa;
}
</style>

<?php include '../../templates/footer.php'; ?>