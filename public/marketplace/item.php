<?php
/**
 * WEZO CAMPUS HUB - Marketplace Item Details
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

use Core\Auth;
use Core\Database;
use Core\Session;

// Initialize
Auth::init();
$db = Database::getInstance();
$isLoggedIn = Auth::isLoggedIn();
$user = Auth::user();

// Get item ID
$itemId = intval($_GET['id'] ?? 0);
if (!$itemId) {
    header('Location: /marketplace/');
    exit;
}

// Get item details
$item = $db->fetch("
    SELECT m.*, u.username, u.profile_pic, u.email as seller_email, 
           c.name as category_name, c.slug as category_slug 
    FROM marketplace_items m 
    LEFT JOIN users u ON m.user_id = u.id 
    LEFT JOIN marketplace_categories c ON m.category_id = c.id 
    WHERE m.id = ? 
    AND m.status = 'active' 
    AND (m.is_approved = 1 OR ? = 1 OR m.user_id = ?)
", [$itemId, Auth::isAdmin() || Auth::isModerator(), $isLoggedIn ? $user['id'] : 0]);

if (!$item) {
    Session::flash('error', 'Item not found or not approved yet.');
    header('Location: /marketplace/');
    exit;
}

// Update view count (only once per session)
if (!isset($_SESSION['viewed_item_' . $itemId])) {
    $db->update('marketplace_items', 
        ['view_count' => $item['view_count'] + 1], 
        'id = ?', 
        [$itemId]
    );
    $_SESSION['viewed_item_' . $itemId] = true;
}

// Get seller's other items
$sellerItems = $db->fetchAll("
    SELECT m.*, c.name as category_name 
    FROM marketplace_items m 
    LEFT JOIN marketplace_categories c ON m.category_id = c.id 
    WHERE m.user_id = ? 
    AND m.id != ? 
    AND m.status = 'active' 
    AND m.is_approved = 1 
    ORDER BY m.created_at DESC 
    LIMIT 4
", [$item['user_id'], $itemId]);

// Get similar items
$similarItems = $db->fetchAll("
    SELECT m.*, u.username, c.name as category_name 
    FROM marketplace_items m 
    LEFT JOIN users u ON m.user_id = u.id 
    LEFT JOIN marketplace_categories c ON m.category_id = c.id 
    WHERE m.category_id = ? 
    AND m.id != ? 
    AND m.status = 'active' 
    AND m.is_approved = 1 
    ORDER BY m.view_count DESC 
    LIMIT 4
", [$item['category_id'], $itemId]);

// Handle contact request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send_message') {
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
            Session::flash('error', 'Security token invalid.');
        } else {
            $message = trim($_POST['message'] ?? '');
            $contactMethod = $_POST['contact_method'] ?? '';
            
            if (empty($message)) {
                Session::flash('error', 'Please enter a message.');
            } else {
                // In production, this would send email/notification to seller
                // For now, we'll log it
                $db->insert('activity_logs', [
                    'user_id' => $user['id'],
                    'action' => 'marketplace_contact',
                    'description' => 'Contacted seller about: ' . $item['title'],
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                Session::flash('success', 'Message sent to seller! They will contact you soon.');
                header('Location: /marketplace/item.php?id=' . $itemId);
                exit;
            }
        }
    }
    
    elseif ($action === 'report_item') {
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
            Session::flash('error', 'Security token invalid.');
        } else {
            $reason = $_POST['reason'] ?? '';
            $description = trim($_POST['description'] ?? '');
            
            if (empty($reason)) {
                Session::flash('error', 'Please select a reason for reporting.');
            } else {
                $db->insert('reports', [
                    'reporter_id' => $user['id'],
                    'item_type' => 'marketplace',
                    'item_id' => $itemId,
                    'reason' => $reason,
                    'description' => $description,
                    'status' => 'pending'
                ]);
                
                Session::flash('success', 'Item reported. Our moderators will review it.');
                header('Location: /marketplace/item.php?id=' . $itemId);
                exit;
            }
        }
    }
}

// Parse images
$images = json_decode($item['images'] ?? '[]', true);
if (empty($images)) {
    $images = ['default-item.jpg'];
}

// Set page title
$pageTitle = htmlspecialchars($item['title']) . " - Marketplace - WEZO CAMPUS HUB";

// Include header
include __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <!-- Breadcrumb -->
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/marketplace/">Marketplace</a></li>
                    <li class="breadcrumb-item"><a href="?category=<?php echo urlencode($item['category_slug']); ?>">
                        <?php echo htmlspecialchars($item['category_name']); ?>
                    </a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($item['title']); ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Images and Details -->
        <div class="col-lg-8">
            <!-- Item Images -->
            <div class="card shadow-sm mb-4">
                <div class="card-body p-3">
                    <!-- Main Image -->
                    <div class="text-center mb-3">
                        <img id="mainImage" 
                             src="/assets/uploads/marketplace/<?php echo htmlspecialchars($images[0]); ?>" 
                             class="img-fluid rounded" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                             style="max-height: 400px; object-fit: contain;">
                    </div>
                    
                    <!-- Thumbnails -->
                    <?php if (count($images) > 1): ?>
                    <div class="image-thumbnails">
                        <div class="row g-2">
                            <?php foreach ($images as $index => $image): ?>
                            <div class="col-3 col-md-2">
                                <img src="/assets/uploads/marketplace/<?php echo htmlspecialchars($image); ?>" 
                                     class="img-thumbnail cursor-pointer <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     alt="Thumbnail <?php echo $index + 1; ?>"
                                     onclick="changeMainImage(this.src)"
                                     style="height: 80px; width: 100%; object-fit: cover;">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Item Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Item Header -->
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <div class="mb-2">
                                <span class="badge bg-success"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                <?php if ($item['negotiable']): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-handshake me-1"></i> Negotiable
                                </span>
                                <?php endif; ?>
                            </div>
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($item['title']); ?></h1>
                            
                            <!-- Price -->
                            <div class="d-flex align-items-center mb-3">
                                <h2 class="text-success mb-0">KSh <?php echo number_format($item['price'], 2); ?></h2>
                                <?php if ($item['negotiable']): ?>
                                <small class="text-muted ms-3">
                                    <i class="fas fa-info-circle me-1"></i> Price is negotiable
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Stats -->
                        <div class="text-end">
                            <div class="small text-muted">
                                <i class="fas fa-eye me-1"></i> <?php echo $item['view_count']; ?> views
                            </div>
                            <div class="small text-muted">
                                Listed <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Condition -->
                    <div class="mb-4">
                        <h5 class="mb-2">Condition</h5>
                        <span class="badge bg-<?php 
                            $conditionColors = [
                                'new' => 'success',
                                'like_new' => 'info',
                                'good' => 'primary',
                                'fair' => 'warning',
                                'poor' => 'secondary'
                            ];
                            echo $conditionColors[$item['condition']] ?? 'secondary';
                        ?> p-2">
                            <?php echo ucfirst(str_replace('_', ' ', $item['condition'])); ?>
                        </span>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <h5 class="mb-2">Description</h5>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="mb-4">
                        <h5 class="mb-2">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i> Location
                        </h5>
                        <p class="mb-0">
                            <?php echo htmlspecialchars($item['location']); ?>
                            <small class="text-muted d-block mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Meet in this general area for safety
                            </small>
                        </p>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center border-top pt-4">
                        <div>
                            <?php if ($isLoggedIn && $item['user_id'] != $user['id']): ?>
                            <button class="btn btn-outline-danger me-2 favorite-btn" data-item-id="<?php echo $itemId; ?>">
                                <i class="far fa-heart"></i> Save
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reportModal">
                                <i class="fas fa-flag"></i> Report
                            </button>
                        </div>
                        
                        <div>
                            <?php if ($item['user_id'] == ($user['id'] ?? 0) || Auth::isAdmin() || Auth::isModerator()): ?>
                            <a href="/marketplace/edit.php?id=<?php echo $itemId; ?>" class="btn btn-outline-primary me-2">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($isLoggedIn && $item['user_id'] != $user['id']): ?>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#contactModal">
                                <i class="fas fa-comment me-1"></i> Contact Seller
                            </button>
                            <?php elseif (!$isLoggedIn): ?>
                            <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-1"></i> Login to Contact
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Seller's Other Items -->
            <?php if (!empty($sellerItems)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-store me-2"></i> More from This Seller
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($sellerItems as $sellerItem): 
                            $sellerImages = json_decode($sellerItem['images'] ?? '[]', true);
                            $sellerFirstImage = !empty($sellerImages) ? $sellerImages[0] : 'default-item.jpg';
                        ?>
                        <div class="col-md-6 mb-3">
                            <div class="card border h-100">
                                <div class="row g-0 h-100">
                                    <div class="col-4">
                                        <img src="/assets/uploads/marketplace/<?php echo htmlspecialchars($sellerFirstImage); ?>" 
                                             class="img-fluid rounded-start h-100" 
                                             alt="<?php echo htmlspecialchars($sellerItem['title']); ?>"
                                             style="object-fit: cover;">
                                    </div>
                                    <div class="col-8">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <a href="/marketplace/item.php?id=<?php echo $sellerItem['id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($sellerItem['title']); ?>
                                                </a>
                                            </h6>
                                            <p class="card-text text-success mb-1">
                                                KSh <?php echo number_format($sellerItem['price'], 2); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($sellerItem['category_name']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column: Seller Info & Contact -->
        <div class="col-lg-4">
            <!-- Seller Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-circle me-2"></i> Seller Information
                    </h5>
                </div>
                <div class="card-body text-center">
                    <!-- Seller Avatar -->
                    <?php if ($item['profile_pic'] && $item['profile_pic'] != 'default.png'): ?>
                    <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($item['profile_pic']); ?>" 
                         alt="<?php echo htmlspecialchars($item['username']); ?>" 
                         class="rounded-circle mb-3" width="80" height="80">
                    <?php else: ?>
                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 80px; height: 80px;">
                        <i class="fas fa-user text-white fa-2x"></i>
                    </div>
                    <?php endif; ?>
                    
                    <h5><?php echo htmlspecialchars($item['username']); ?></h5>
                    
                    <?php
                    $sellerStats = $db->fetch("
                        SELECT 
                            COUNT(*) as total_items,
                            AVG((SELECT AVG(rating) FROM note_reviews nr WHERE nr.user_id = ?)) as avg_rating
                        FROM marketplace_items 
                        WHERE user_id = ? AND status = 'active' AND is_approved = 1
                    ", [$item['user_id'], $item['user_id']]);
                    ?>
                    
                    <div class="row text-center mt-3">
                        <div class="col-6 mb-3">
                            <div class="h5 text-success"><?php echo $sellerStats['total_items'] ?? 0; ?></div>
                            <div class="small text-muted">Items Sold</div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h5 text-warning"><?php echo number_format($sellerStats['avg_rating'] ?? 0, 1); ?></div>
                            <div class="small text-muted">Avg Rating</div>
                        </div>
                    </div>
                    
                    <a href="/profile.php?user=<?php echo $item['user_id']; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-user me-1"></i> View Profile
                    </a>
                </div>
            </div>
            
            <!-- Contact Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-address-card me-2"></i> Contact Seller
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($item['contact_phone']): ?>
                    <div class="mb-3">
                        <h6 class="small text-muted mb-1">Phone Number</h6>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-phone text-success me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($item['contact_phone']); ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($item['contact_phone']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($item['contact_email']): ?>
                    <div class="mb-3">
                        <h6 class="small text-muted mb-1">Email</h6>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-envelope text-primary me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($item['contact_email']); ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($item['contact_email']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($isLoggedIn && $item['user_id'] != $user['id']): ?>
                    <div class="d-grid">
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="fas fa-comment me-1"></i> Send Message
                        </button>
                    </div>
                    <?php elseif (!$isLoggedIn): ?>
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Login to contact the seller directly.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Safety Tips -->
            <div class="card border-warning shadow-sm mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i> Safety Tips
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="mb-0 small">
                        <li class="mb-2">Meet in safe, public places on campus</li>
                        <li class="mb-2">Inspect item thoroughly before paying</li>
                        <li class="mb-2">Use campus security escort if needed</li>
                        <li class="mb-2">Avoid sharing personal information</li>
                        <li>Trust your instincts</li>
                    </ul>
                </div>
            </div>
            
            <!-- Similar Items -->
            <?php if (!empty($similarItems)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-random me-2"></i> Similar Items
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($similarItems as $similar): 
                            $similarImages = json_decode($similar['images'] ?? '[]', true);
                            $similarFirstImage = !empty($similarImages) ? $similarImages[0] : 'default-item.jpg';
                        ?>
                        <a href="/marketplace/item.php?id=<?php echo $similar['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="row g-0">
                                <div class="col-4">
                                    <img src="/assets/uploads/marketplace/<?php echo htmlspecialchars($similarFirstImage); ?>" 
                                         class="img-fluid rounded" 
                                         alt="<?php echo htmlspecialchars($similar['title']); ?>"
                                         style="height: 60px; object-fit: cover;">
                                </div>
                                <div class="col-8 ps-3">
                                    <h6 class="mb-1 small"><?php echo htmlspecialchars($similar['title']); ?></h6>
                                    <small class="text-success">KSh <?php echo number_format($similar['price'], 2); ?></small>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Contact Modal -->
<?php if ($isLoggedIn && $item['user_id'] != $user['id']): ?>
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Contact Seller</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="send_message">
                    
                    <div class="mb-3">
                        <label class="form-label">Your Message *</label>
                        <textarea class="form-control" name="message" rows="4" required
                                  placeholder="Hi, I'm interested in your item '<?php echo htmlspecialchars($item['title']); ?>'. Please let me know when we can meet to see it..."></textarea>
                        <div class="form-text">Be clear about your questions and preferred meeting time.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Preferred Contact Method</label>
                        <div>
                            <?php if ($item['contact_phone']): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contact_method" id="contactPhone" value="phone" checked>
                                <label class="form-check-label" for="contactPhone">
                                    Phone: <?php echo htmlspecialchars($item['contact_phone']); ?>
                                </label>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($item['contact_email']): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="contact_method" id="contactEmail" value="email">
                                <label class="form-check-label" for="contactEmail">
                                    Email: <?php echo htmlspecialchars($item['contact_email']); ?>
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="alert alert-info small mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        The seller will receive your message and contact you directly.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Report This Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="report_item">
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Reporting *</label>
                        <select class="form-select" name="reason" required>
                            <option value="">Select a reason</option>
                            <option value="fraud">Suspected fraud or scam</option>
                            <option value="prohibited">Prohibited item</option>
                            <option value="inaccurate">Inaccurate description</option>
                            <option value="spam">Spam or duplicate listing</option>
                            <option value="harassment">Harassment or inappropriate content</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reportDescription" class="form-label">Additional Details</label>
                        <textarea class="form-control" id="reportDescription" name="description" rows="3" 
                                  placeholder="Please provide more details about your report..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning small mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Reports are confidential. False reporting may result in account suspension.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Change main image when thumbnail is clicked
function changeMainImage(src) {
    document.getElementById('mainImage').src = src;
    
    // Update active thumbnail
    document.querySelectorAll('.image-thumbnails .img-thumbnail').forEach(img => {
        img.classList.remove('active', 'border-primary');
        img.classList.add('border-secondary');
    });
    event.target.classList.add('active', 'border-primary');
    event.target.classList.remove('border-secondary');
}

// Favorite button
const favoriteBtn = document.querySelector('.favorite-btn');
if (favoriteBtn) {
    favoriteBtn.addEventListener('click', function() {
        const itemId = this.dataset.itemId;
        const icon = this.querySelector('i');
        
        // Toggle visual state
        if (icon.classList.contains('far')) {
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-danger');
            this.classList.remove('btn-outline-danger');
            this.classList.add('btn-danger');
        } else {
            icon.classList.remove('fas', 'text-danger');
            icon.classList.add('far');
            this.classList.remove('btn-danger');
            this.classList.add('btn-outline-danger');
        }
        
        // Send AJAX request
        fetch('/api/favorites.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN
            },
            body: JSON.stringify({
                action: 'toggle',
                item_type: 'marketplace',
                item_id: itemId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // Revert visual state if failed
                if (icon.classList.contains('fas')) {
                    icon.classList.remove('fas', 'text-danger');
                    icon.classList.add('far');
                    this.classList.remove('btn-danger');
                    this.classList.add('btn-outline-danger');
                } else {
                    icon.classList.remove('far');
                    icon.classList.add('fas', 'text-danger');
                    this.classList.remove('btn-outline-danger');
                    this.classList.add('btn-danger');
                }
            }
        });
    });
}

// Check if item is already favorited
<?php if ($isLoggedIn && $item['user_id'] != $user['id']): ?>
fetch('/api/favorites.php?item_type=marketplace&item_id=<?php echo $itemId; ?>')
    .then(response => response.json())
    .then(data => {
        if (data.is_favorited && favoriteBtn) {
            const icon = favoriteBtn.querySelector('i');
            icon.classList.remove('far');
            icon.classList.add('fas', 'text-danger');
            favoriteBtn.classList.remove('btn-outline-danger');
            favoriteBtn.classList.add('btn-danger');
        }
    });
<?php endif; ?>

// Share functionality
function shareItem() {
    const shareUrl = window.location.href;
    const shareTitle = document.title;
    
    if (navigator.share) {
        navigator.share({
            title: shareTitle,
            text: 'Check out this item on WEZO CAMPUS HUB Marketplace',
            url: shareUrl,
        })
        .then(() => console.log('Shared successfully'))
        .catch(error => console.log('Error sharing:', error));
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(shareUrl).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}

// Add share button
const shareBtn = document.createElement('button');
shareBtn.className = 'btn btn-outline-info btn-sm ms-2';
shareBtn.innerHTML = '<i class="fas fa-share-alt me-1"></i> Share';
shareBtn.onclick = shareItem;

// Add share button to action buttons
const actionButtons = document.querySelector('.d-flex.justify-content-between.align-items-center.border-top.pt-4 > div:first-child');
if (actionButtons) {
    actionButtons.appendChild(shareBtn);
}

// Initialize image gallery
document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.getElementById('mainImage');
    if (mainImage) {
        // Add click to zoom functionality
        mainImage.addEventListener('click', function() {
            this.classList.toggle('img-zoomed');
        });
    }
});

// Form validation for contact modal
const contactForm = document.querySelector('#contactModal form');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
        const message = this.querySelector('textarea[name="message"]').value.trim();
        if (!message) {
            e.preventDefault();
            alert('Please enter a message.');
        }
    });
}
</script>

<style>
.img-thumbnail.cursor-pointer {
    cursor: pointer;
    transition: all 0.3s ease;
}

.img-thumbnail.cursor-pointer:hover {
    transform: scale(1.05);
}

.img-thumbnail.cursor-pointer.active {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.img-zoomed {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    object-fit: contain;
    background: rgba(0, 0, 0, 0.9);
    z-index: 9999;
    cursor: zoom-out;
    padding: 20px;
}
</style>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>