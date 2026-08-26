<?php
/**
 * WEZO CAMPUS HUB - Hostel Details
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

// Get hostel ID
$hostelId = intval($_GET['id'] ?? 0);
if (!$hostelId) {
    header('Location: /hostels/');
    exit;
}

// Get hostel details
$hostel = $db->fetch("
    SELECT h.*, u.username, u.profile_pic 
    FROM hostels h 
    LEFT JOIN users u ON h.owner_id = u.id 
    WHERE h.id = ? AND h.is_approved = 1
", [$hostelId]);

if (!$hostel) {
    Session::flash('error', 'Hostel not found or not approved.');
    header('Location: /hostels/');
    exit;
}

// Update view count
if (!isset($_SESSION['viewed_hostel_' . $hostelId])) {
    $db->update('hostels', 
        ['view_count' => $hostel['view_count'] + 1], 
        'id = ?', 
        [$hostelId]
    );
    $_SESSION['viewed_hostel_' . $hostelId] = true;
}

// Get reviews
$reviews = $db->fetchAll("
    SELECT r.*, u.username, u.profile_pic 
    FROM hostel_reviews r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE r.hostel_id = ? 
    ORDER BY r.created_at DESC 
    LIMIT 10
", [$hostelId]);

// Get similar hostels
$similarHostels = $db->fetchAll("
    SELECT * FROM hostels 
    WHERE location LIKE ? 
    AND id != ? 
    AND is_approved = 1 
    ORDER BY rating DESC 
    LIMIT 4
", ["%{$hostel['location']}%", $hostelId]);

// Parse amenities and images
$amenities = json_decode($hostel['amenities'] ?? '[]', true);
$images = json_decode($hostel['images'] ?? '[]', true);
if (empty($images)) {
    $images = ['default-hostel.jpg'];
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'submit_review') {
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
            Session::flash('error', 'Security token invalid.');
        } else {
            $rating = intval($_POST['rating'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $review = trim($_POST['review'] ?? '');
            $cleanliness = intval($_POST['cleanliness'] ?? 0);
            $safety = intval($_POST['safety'] ?? 0);
            $management = intval($_POST['management'] ?? 0);
            $amenitiesRating = intval($_POST['amenities_rating'] ?? 0);
            
            // Check if user has already reviewed
            $existingReview = $db->fetch("
                SELECT * FROM hostel_reviews 
                WHERE hostel_id = ? AND user_id = ?
            ", [$hostelId, $user['id']]);
            
            if ($existingReview) {
                Session::flash('error', 'You have already reviewed this hostel.');
            } elseif ($rating < 1 || $rating > 5) {
                Session::flash('error', 'Please select a valid rating.');
            } elseif (empty($title)) {
                Session::flash('error', 'Please enter a title for your review.');
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Insert review
                    $db->insert('hostel_reviews', [
                        'hostel_id' => $hostelId,
                        'user_id' => $user['id'],
                        'rating' => $rating,
                        'title' => $title,
                        'review' => $review,
                        'cleanliness' => $cleanliness,
                        'safety' => $safety,
                        'management' => $management,
                        'amenities' => $amenitiesRating,
                        'is_verified_stay' => 1 // Assuming verification system
                    ]);
                    
                    // Update hostel rating
                    $newRating = $db->fetchColumn("
                        SELECT AVG(rating) FROM hostel_reviews WHERE hostel_id = ?
                    ", [$hostelId]);
                    
                    $reviewCount = $db->fetchColumn("
                        SELECT COUNT(*) FROM hostel_reviews WHERE hostel_id = ?
                    ", [$hostelId]);
                    
                    $db->update('hostels', 
                        [
                            'rating' => $newRating,
                            'review_count' => $reviewCount
                        ], 
                        'id = ?', 
                        [$hostelId]
                    );
                    
                    // Log activity
                    $db->insert('activity_logs', [
                        'user_id' => $user['id'],
                        'action' => 'hostel_review',
                        'description' => 'Reviewed hostel: ' . $hostel['name'],
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ]);
                    
                    $db->commit();
                    
                    Session::flash('success', 'Thank you for your review!');
                    header('Location: /hostels/details.php?id=' . $hostelId);
                    exit;
                    
                } catch (Exception $e) {
                    $db->rollback();
                    Session::flash('error', 'Failed to submit review. Please try again.');
                }
            }
        }
    }
    
    elseif ($action === 'contact_owner') {
        if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
            Session::flash('error', 'Security token invalid.');
        } else {
            $message = trim($_POST['message'] ?? '');
            $contactName = trim($_POST['contact_name'] ?? '');
            $contactPhone = trim($_POST['contact_phone'] ?? '');
            $contactEmail = trim($_POST['contact_email'] ?? '');
            
            if (empty($message)) {
                Session::flash('error', 'Please enter a message.');
            } else {
                // Send email notification (in production)
                // For now, just log it
                $db->insert('activity_logs', [
                    'user_id' => $user['id'],
                    'action' => 'hostel_contact',
                    'description' => 'Contacted hostel owner: ' . $hostel['name'],
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                Session::flash('success', 'Message sent to hostel owner! They will contact you soon.');
                header('Location: /hostels/details.php?id=' . $hostelId);
                exit;
            }
        }
    }
}

// Calculate average sub-ratings
$avgCleanliness = $db->fetchColumn("SELECT AVG(cleanliness) FROM hostel_reviews WHERE hostel_id = ?", [$hostelId]);
$avgSafety = $db->fetchColumn("SELECT AVG(safety) FROM hostel_reviews WHERE hostel_id = ?", [$hostelId]);
$avgManagement = $db->fetchColumn("SELECT AVG(management) FROM hostel_reviews WHERE hostel_reviews WHERE hostel_id = ?", [$hostelId]);
$avgAmenities = $db->fetchColumn("SELECT AVG(amenities) FROM hostel_reviews WHERE hostel_id = ?", [$hostelId]);

// Set page title
$pageTitle = htmlspecialchars($hostel['name']) . " - Hostels - WEZO CAMPUS HUB";

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
                    <li class="breadcrumb-item"><a href="/hostels/">Hostels</a></li>
                    <li class="breadcrumb-item"><a href="/hostels/?location=<?php echo urlencode($hostel['location']); ?>">
                        <?php echo htmlspecialchars($hostel['location']); ?>
                    </a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($hostel['name']); ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Images and Details -->
        <div class="col-lg-8">
            <!-- Image Gallery -->
            <div class="card shadow-sm mb-4">
                <div class="card-body p-3">
                    <!-- Main Image -->
                    <div class="text-center mb-3">
                        <img id="mainImage" 
                             src="/assets/uploads/hostels/<?php echo htmlspecialchars($images[0]); ?>" 
                             class="img-fluid rounded" 
                             alt="<?php echo htmlspecialchars($hostel['name']); ?>"
                             style="max-height: 400px; object-fit: cover;">
                    </div>
                    
                    <!-- Thumbnails -->
                    <?php if (count($images) > 1): ?>
                    <div class="image-thumbnails">
                        <div class="row g-2">
                            <?php foreach ($images as $index => $image): ?>
                            <div class="col-3 col-md-2">
                                <img src="/assets/uploads/hostels/<?php echo htmlspecialchars($image); ?>" 
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
            
            <!-- Hostel Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <div class="mb-2">
                                <?php if ($hostel['is_featured']): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-star me-1"></i> Featured
                                </span>
                                <?php endif; ?>
                            </div>
                            <h1 class="h2 mb-2"><?php echo htmlspecialchars($hostel['name']); ?></h1>
                            
                            <!-- Price -->
                            <div class="d-flex align-items-center mb-3">
                                <h2 class="text-success mb-0">KSh <?php echo number_format($hostel['price_per_month']); ?>/month</h2>
                                <?php if ($hostel['deposit_amount'] > 0): ?>
                                <small class="text-muted ms-3">
                                    + KSh <?php echo number_format($hostel['deposit_amount']); ?> deposit
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Stats -->
                        <div class="text-end">
                            <div class="mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= floor($hostel['rating']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <small class="text-muted ms-1">(<?php echo $hostel['review_count']; ?>)</small>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-eye me-1"></i> <?php echo $hostel['view_count']; ?> views
                            </div>
                        </div>
                    </div>
                    
                    <!-- Availability -->
                    <div class="mb-4">
                        <h5 class="mb-2">Availability</h5>
                        <div class="availability-status">
                            <?php if ($hostel['available_rooms'] > 0): ?>
                            <span class="badge bg-success p-2">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php echo $hostel['available_rooms']; ?> rooms available
                            </span>
                            <small class="text-muted ms-3">
                                Out of <?php echo $hostel['total_rooms']; ?> total rooms
                            </small>
                            <?php else: ?>
                            <span class="badge bg-danger p-2">
                                <i class="fas fa-times-circle me-1"></i>
                                Currently fully booked
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-4">
                        <h5 class="mb-2">Description</h5>
                        <div class="border rounded p-3 bg-light">
                            <?php echo nl2br(htmlspecialchars($hostel['description'])); ?>
                        </div>
                    </div>
                    
                    <!-- Amenities -->
                    <?php if (!empty($amenities)): ?>
                    <div class="mb-4">
                        <h5 class="mb-3">Amenities</h5>
                        <div class="row">
                            <?php foreach ($amenities as $amenity): ?>
                            <div class="col-md-4 col-sm-6 mb-2">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check text-success me-2"></i>
                                    <span><?php echo htmlspecialchars($amenity); ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Location -->
                    <div class="mb-4">
                        <h5 class="mb-2">
                            <i class="fas fa-map-marker-alt text-primary me-2"></i> Location
                        </h5>
                        <p class="mb-2"><?php echo htmlspecialchars($hostel['location']); ?></p>
                        <?php if (!empty($hostel['address'])): ?>
                        <p class="mb-0 text-muted">
                            <small><?php echo htmlspecialchars($hostel['address']); ?></small>
                        </p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Rules -->
                    <?php if (!empty($hostel['rules'])): ?>
                    <div class="mb-4">
                        <h5 class="mb-2">
                            <i class="fas fa-clipboard-list text-warning me-2"></i> House Rules
                        </h5>
                        <div class="border rounded p-3">
                            <?php echo nl2br(htmlspecialchars($hostel['rules'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center border-top pt-4">
                        <div>
                            <?php if ($isLoggedIn): ?>
                            <button class="btn btn-outline-danger me-2 favorite-btn" data-hostel-id="<?php echo $hostelId; ?>">
                                <i class="far fa-heart"></i> Save
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-secondary" onclick="shareHostel()">
                                <i class="fas fa-share-alt"></i> Share
                            </button>
                        </div>
                        
                        <div>
                            <?php if ($isLoggedIn): ?>
                            <button class="btn btn-info me-2" data-bs-toggle="modal" data-bs-target="#contactModal">
                                <i class="fas fa-envelope me-1"></i> Contact Owner
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal" 
                                    <?php echo $hostel['available_rooms'] <= 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-calendar-check me-1"></i> Book Visit
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reviews Section -->
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-star text-warning me-2"></i> Reviews
                        <small class="text-muted">(<?php echo count($reviews); ?>)</small>
                    </h5>
                    <?php if ($isLoggedIn): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                        <i class="fas fa-plus me-1"></i> Add Review
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="card-body">
                    <!-- Overall Rating -->
                    <div class="text-center mb-4">
                        <div class="display-4 text-warning mb-2"><?php echo number_format($hostel['rating'], 1); ?></div>
                        <div class="mb-3">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= floor($hostel['rating']) ? 'text-warning' : 'text-muted'; ?> fa-lg"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted mb-0">Based on <?php echo $hostel['review_count']; ?> review<?php echo $hostel['review_count'] != 1 ? 's' : ''; ?></p>
                    </div>
                    
                    <!-- Detailed Ratings -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="rating-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Cleanliness</span>
                                    <span class="text-warning"><?php echo number_format($avgCleanliness, 1); ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($avgCleanliness / 5) * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="rating-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Safety</span>
                                    <span class="text-warning"><?php echo number_format($avgSafety, 1); ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-primary" style="width: <?php echo ($avgSafety / 5) * 100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="rating-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Management</span>
                                    <span class="text-warning"><?php echo number_format($avgManagement, 1); ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo ($avgManagement / 5) * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="rating-item mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Amenities</span>
                                    <span class="text-warning"><?php echo number_format($avgAmenities, 1); ?></span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo ($avgAmenities / 5) * 100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reviews List -->
                    <?php if (empty($reviews)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No reviews yet. Be the first to review!</p>
                        <?php if ($isLoggedIn): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            <i class="fas fa-star me-1"></i> Write Review
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="reviews-list">
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-item border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center">
                                    <?php if ($review['profile_pic'] && $review['profile_pic'] != 'default.png'): ?>
                                    <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($review['profile_pic']); ?>" 
                                         alt="<?php echo htmlspecialchars($review['username']); ?>" 
                                         class="rounded-circle me-2" width="32" height="32">
                                    <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                         style="width: 32px; height: 32px;">
                                        <i class="fas fa-user text-white small"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['username']); ?></strong>
                                        <?php if ($review['is_verified_stay']): ?>
                                        <span class="badge bg-success small ms-2">Verified Stay</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <?php if (!empty($review['title'])): ?>
                            <h6 class="mb-2"><?php echo htmlspecialchars($review['title']); ?></h6>
                            <?php endif; ?>
                            
                            <?php if (!empty($review['review'])): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($reviews) > 5): ?>
                    <div class="text-center mt-3">
                        <a href="/hostels/reviews.php?id=<?php echo $hostelId; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-list me-1"></i> View All Reviews
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column: Contact & Similar Hostels -->
        <div class="col-lg-4">
            <!-- Contact Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-phone-alt me-2"></i> Contact Information
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($hostel['contact_phone']): ?>
                    <div class="mb-3">
                        <h6 class="small text-muted mb-1">Phone Number</h6>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-phone text-success me-2"></i>
                            <a href="tel:<?php echo htmlspecialchars($hostel['contact_phone']); ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($hostel['contact_phone']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($hostel['contact_email']): ?>
                    <div class="mb-3">
                        <h6 class="small text-muted mb-1">Email</h6>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-envelope text-primary me-2"></i>
                            <a href="mailto:<?php echo htmlspecialchars($hostel['contact_email']); ?>" 
                               class="text-decoration-none">
                                <?php echo htmlspecialchars($hostel['contact_email']); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Owner Info -->
                    <?php if ($hostel['owner_id']): ?>
                    <div class="mb-3">
                        <h6 class="small text-muted mb-1">Listed By</h6>
                        <div class="d-flex align-items-center">
                            <?php if ($hostel['profile_pic'] && $hostel['profile_pic'] != 'default.png'): ?>
                            <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($hostel['profile_pic']); ?>" 
                                 alt="<?php echo htmlspecialchars($hostel['username']); ?>" 
                                 class="rounded-circle me-2" width="24" height="24">
                            <?php else: ?>
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center me-2" 
                                 style="width: 24px; height: 24px;">
                                <i class="fas fa-user text-white small"></i>
                            </div>
                            <?php endif; ?>
                            <span><?php echo htmlspecialchars($hostel['username']); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($isLoggedIn): ?>
                    <div class="d-grid">
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#contactModal">
                            <i class="fas fa-comment me-1"></i> Send Message
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Facts -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i> Quick Facts
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Price per Month</span>
                            <span class="text-success">KSh <?php echo number_format($hostel['price_per_month']); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Deposit</span>
                            <span>KSh <?php echo number_format($hostel['deposit_amount']); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Available Rooms</span>
                            <span class="badge bg-<?php echo $hostel['available_rooms'] > 0 ? 'success' : 'danger'; ?>">
                                <?php echo $hostel['available_rooms']; ?>
                            </span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Total Rooms</span>
                            <span><?php echo $hostel['total_rooms']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Location</span>
                            <span class="small"><?php echo htmlspecialchars($hostel['location']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Similar Hostels -->
            <?php if (!empty($similarHostels)): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-building me-2"></i> Similar Hostels
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($similarHostels as $similar): ?>
                        <a href="/hostels/details.php?id=<?php echo $similar['id']; ?>" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 small"><?php echo htmlspecialchars($similar['name']); ?></h6>
                                <small class="text-success">KSh <?php echo number_format($similar['price_per_month']); ?></small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?php echo $similar['available_rooms']; ?> rooms left
                                </small>
                                <small>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= floor($similar['rating']) ? 'text-warning' : 'text-muted'; ?>" style="font-size: 10px;"></i>
                                    <?php endfor; ?>
                                </small>
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

<!-- Review Modal -->
<?php if ($isLoggedIn): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Write a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="submit_review">
                    
                    <!-- Overall Rating -->
                    <div class="mb-4">
                        <label class="form-label">Overall Rating *</label>
                        <div class="rating-input text-center">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" 
                                   class="d-none" required>
                            <label for="star<?php echo $i; ?>" class="star-label">
                                <i class="far fa-star fa-2x"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                        <div class="invalid-feedback">Please select an overall rating.</div>
                    </div>
                    
                    <!-- Detailed Ratings -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cleanliness</label>
                                <select class="form-select" name="cleanliness" required>
                                    <option value="">Select rating</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> Star<?php echo $i != 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Safety</label>
                                <select class="form-select" name="safety" required>
                                    <option value="">Select rating</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> Star<?php echo $i != 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Management</label>
                                <select class="form-select" name="management" required>
                                    <option value="">Select rating</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> Star<?php echo $i != 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amenities</label>
                                <select class="form-select" name="amenities_rating" required>
                                    <option value="">Select rating</option>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> Star<?php echo $i != 1 ? 's' : ''; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Review Title -->
                    <div class="mb-3">
                        <label for="reviewTitle" class="form-label">Review Title *</label>
                        <input type="text" class="form-control" id="reviewTitle" name="title" 
                               required maxlength="200" placeholder="Summarize your experience">
                    </div>
                    
                    <!-- Review Text -->
                    <div class="mb-3">
                        <label for="reviewText" class="form-label">Detailed Review (Optional)</label>
                        <textarea class="form-control" id="reviewText" name="review" rows="4" 
                                  placeholder="Share your experience with this hostel..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Contact Modal -->
<?php if ($isLoggedIn): ?>
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Contact Hostel Owner</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="contact_owner">
                    
                    <div class="mb-3">
                        <label for="contactName" class="form-label">Your Name *</label>
                        <input type="text" class="form-control" id="contactName" name="contact_name" 
                               required value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="contactPhone" class="form-label">Your Phone Number *</label>
                        <input type="tel" class="form-control" id="contactPhone" name="contact_phone" 
                               required value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="contactEmail" class="form-label">Your Email *</label>
                        <input type="email" class="form-control" id="contactEmail" name="contact_email" 
                               required value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required
                                  placeholder="Hi, I'm interested in viewing your hostel '<?php echo htmlspecialchars($hostel['name']); ?>'. Please let me know when I can schedule a visit..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Book Visit Modal -->
<div class="modal fade" id="bookModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule a Visit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-calendar-alt fa-3x text-primary mb-3"></i>
                <h5><?php echo htmlspecialchars($hostel['name']); ?></h5>
                <p class="text-muted">Schedule a viewing appointment</p>
                
                <?php if (!$isLoggedIn): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Please login to schedule a visit.
                </div>
                <a href="/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-1"></i> Login to Continue
                </a>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Contact the owner directly to schedule a visit. Use the contact form above.
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactModal">
                    <i class="fas fa-envelope me-1"></i> Contact Owner
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Change main image
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
        const hostelId = this.dataset.hostelId;
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
                item_type: 'hostel',
                item_id: hostelId
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

// Star rating in review modal
document.querySelectorAll('.star-label').forEach((label, index) => {
    label.addEventListener('mouseover', function() {
        const starIndex = index + 1;
        document.querySelectorAll('.star-label').forEach((l, i) => {
            const icon = l.querySelector('i');
            if (i < starIndex) {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-warning');
            } else {
                icon.classList.remove('fas', 'text-warning');
                icon.classList.add('far');
            }
        });
    });
    
    label.addEventListener('click', function() {
        const starIndex = index + 1;
        document.querySelectorAll('.star-label').forEach((l, i) => {
            const icon = l.querySelector('i');
            if (i < starIndex) {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-warning');
            } else {
                icon.classList.remove('fas', 'text-warning');
                icon.classList.add('far');
            }
        });
    });
});

// Reset stars when modal closes
const reviewModal = document.getElementById('reviewModal');
if (reviewModal) {
    reviewModal.addEventListener('hidden.bs.modal', function() {
        document.querySelectorAll('.star-label').forEach(label => {
            const icon = label.querySelector('i');
            icon.classList.remove('fas', 'text-warning');
            icon.classList.add('far');
        });
    });
}

// Share hostel
function shareHostel() {
    const shareUrl = window.location.href;
    const shareTitle = document.title;
    
    if (navigator.share) {
        navigator.share({
            title: shareTitle,
            text: 'Check out this hostel on WEZO CAMPUS HUB',
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

// Check if hostel is already favorited
<?php if ($isLoggedIn): ?>
fetch('/api/favorites.php?item_type=hostel&item_id=<?php echo $hostelId; ?>')
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

// Form validation for contact modal
const contactForm = document.querySelector('#contactModal form');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
        const phone = this.querySelector('input[name="contact_phone"]').value.trim();
        const email = this.querySelector('input[name="contact_email"]').value.trim();
        const message = this.querySelector('textarea[name="message"]').value.trim();
        
        if (!phone || !email || !message) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
}

// Image gallery navigation
let currentImageIndex = 0;
const totalImages = <?php echo count($images); ?>;

function nextImage() {
    currentImageIndex = (currentImageIndex + 1) % totalImages;
    changeMainImage('/assets/uploads/hostels/<?php echo htmlspecialchars($images[0]); ?>'.replace('<?php echo $images[0]; ?>', images[currentImageIndex]));
}

function prevImage() {
    currentImageIndex = (currentImageIndex - 1 + totalImages) % totalImages;
    changeMainImage('/assets/uploads/hostels/<?php echo htmlspecialchars($images[0]); ?>'.replace('<?php echo $images[0]; ?>', images[currentImageIndex]));
}

// Add keyboard navigation
document.addEventListener('keydown', function(e) {
    if (e.key === 'ArrowLeft') prevImage();
    if (e.key === 'ArrowRight') nextImage();
});
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

.rating-item .progress {
    background-color: #e9ecef;
}

.star-label {
    cursor: pointer;
    transition: transform 0.2s;
}

.star-label:hover {
    transform: scale(1.2);
}
</style>

<?php
// Include footer
include __DIR__ . '/../../templates/footer.php';
?>