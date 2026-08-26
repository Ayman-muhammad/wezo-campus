<?php
/**
 * WEZO CAMPUS HUB - Hostels Comparison
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

use Core\Auth;
use Core\Database;
use Core\Session;

Auth::init();
$db = Database::getInstance();

// Get hostel IDs from query string
$hostelIds = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
$hostelIds = array_filter(array_map('intval', $hostelIds));
$hostelIds = array_unique(array_slice($hostelIds, 0, 4)); // Max 4 hostels

if (empty($hostelIds)) {
    header('Location: index.php');
    exit;
}

// Fetch hostel details
$placeholders = implode(',', array_fill(0, count($hostelIds), '?'));
$hostels = $db->fetchAll("
    SELECT h.*, 
           u.first_name as owner_name,
           u.phone as owner_phone,
           (SELECT AVG(rating) FROM hostel_reviews WHERE hostel_id = h.id) as avg_rating,
           (SELECT COUNT(*) FROM hostel_reviews WHERE hostel_id = h.id) as review_count,
           (SELECT COUNT(*) FROM hostel_bookings WHERE hostel_id = h.id AND status = 'confirmed') as bookings_count
    FROM hostels h
    LEFT JOIN users u ON h.owner_id = u.id
    WHERE h.id IN ($placeholders) AND h.status = 'active' AND h.is_approved = 1
    ORDER BY h.name
", $hostelIds);

if (empty($hostels)) {
    header('Location: index.php');
    exit;
}

// Fetch amenities for comparison
$amenities = $db->fetchAll("
    SELECT a.*, GROUP_CONCAT(ha.hostel_id) as hostel_ids
    FROM hostel_amenities a
    LEFT JOIN hostel_amenity_relations ha ON a.id = ha.amenity_id
    WHERE ha.hostel_id IN ($placeholders)
    GROUP BY a.id
    ORDER BY a.category, a.name
", $hostelIds);

// Prepare comparison data
$comparisonData = [];
foreach ($hostels as $hostel) {
    $comparisonData[$hostel['id']] = [
        'basic' => [
            'name' => $hostel['name'],
            'price' => $hostel['price_per_month'],
            'type' => ucfirst($hostel['type']),
            'gender' => ucfirst($hostel['gender_preference']),
            'distance' => $hostel['distance_to_campus'] . ' km',
            'available' => $hostel['available_rooms'],
            'rating' => round($hostel['avg_rating'], 1),
            'reviews' => $hostel['review_count'],
            'bookings' => $hostel['bookings_count']
        ],
        'amenities' => []
    ];
}

// Map amenities
foreach ($amenities as $amenity) {
    $hostelIdsWithAmenity = explode(',', $amenity['hostel_ids']);
    foreach ($hostels as $hostel) {
        $comparisonData[$hostel['id']]['amenities'][$amenity['id']] = in_array($hostel['id'], $hostelIdsWithAmenity);
    }
}

$pageTitle = "Compare Hostels - WEZO CAMPUS HUB";
include '../../templates/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h2 mb-2">
                <i class="fas fa-balance-scale text-primary me-2"></i> Compare Hostels
            </h1>
            <p class="text-muted">Compare up to 4 hostels side by side</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Listings
            </a>
        </div>
    </div>

    <!-- Comparison Table -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="bg-light">
                        <tr>
                            <th width="20%">Feature</th>
                            <?php foreach ($hostels as $hostel): ?>
                            <th width="<?php echo 80/count($hostels); ?>%">
                                <div class="text-center">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($hostel['name']); ?></h6>
                                    <div class="small text-muted"><?php echo htmlspecialchars($hostel['location']); ?></div>
                                </div>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Basic Information -->
                        <tr class="table-primary">
                            <td colspan="<?php echo count($hostels) + 1; ?>">
                                <strong><i class="fas fa-info-circle me-2"></i> Basic Information</strong>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>Monthly Price</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <strong class="text-primary">KSh <?php echo number_format($hostel['price_per_month']); ?></strong>
                                <div class="small text-muted">per month</div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <tr>
                            <td>Hostel Type</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <span class="badge bg-<?php echo $hostel['type'] == 'single' ? 'primary' : 'info'; ?>">
                                    <?php echo ucfirst($hostel['type']); ?> Room
                                </span>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <tr>
                            <td>Gender Preference</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <i class="fas fa-<?php echo $hostel['gender_preference'] == 'male' ? 'male text-primary' : ($hostel['gender_preference'] == 'female' ? 'female text-pink' : 'users text-secondary'); ?> me-1"></i>
                                <?php echo ucfirst($hostel['gender_preference']); ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <tr>
                            <td>Distance to Campus</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <i class="fas fa-walking text-success me-1"></i>
                                <?php echo $hostel['distance_to_campus']; ?> km
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <tr>
                            <td>Available Rooms</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <?php if ($hostel['available_rooms'] > 0): ?>
                                <span class="badge bg-success"><?php echo $hostel['available_rooms']; ?> available</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Fully booked</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <tr>
                            <td>Rating & Reviews</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <?php if ($hostel['avg_rating']): ?>
                                <div class="d-flex align-items-center justify-content-center">
                                    <div class="text-warning me-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= floor($hostel['avg_rating']) ? '' : ($i == ceil($hostel['avg_rating']) && $hostel['avg_rating'] - floor($hostel['avg_rating']) >= 0.5 ? '-half-alt' : ''); ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo round($hostel['avg_rating'], 1); ?></strong>
                                        <div class="small text-muted">(<?php echo $hostel['review_count']; ?> reviews)</div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">No reviews yet</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <!-- Amenities -->
                        <tr class="table-info">
                            <td colspan="<?php echo count($hostels) + 1; ?>">
                                <strong><i class="fas fa-star me-2"></i> Amenities & Facilities</strong>
                            </td>
                        </tr>
                        
                        <?php 
                        $amenityCategories = [];
                        foreach ($amenities as $amenity) {
                            $category = $amenity['category'];
                            if (!isset($amenityCategories[$category])) {
                                $amenityCategories[$category] = [];
                            }
                            $amenityCategories[$category][] = $amenity;
                        }
                        
                        foreach ($amenityCategories as $category => $catAmenities):
                        ?>
                        <tr>
                            <td colspan="<?php echo count($hostels) + 1; ?>" class="bg-light">
                                <small class="text-uppercase text-muted">
                                    <i class="fas fa-<?php 
                                        $icons = [
                                            'room' => 'bed',
                                            'bathroom' => 'bath',
                                            'kitchen' => 'utensils',
                                            'security' => 'shield-alt',
                                            'internet' => 'wifi',
                                            'laundry' => 'tshirt',
                                            'common' => 'users'
                                        ];
                                        echo $icons[$category] ?? 'check-circle';
                                    ?> me-2"></i>
                                    <?php echo ucfirst($category); ?>
                                </small>
                            </td>
                        </tr>
                        
                        <?php foreach ($catAmenities as $amenity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($amenity['name']); ?></td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <?php if (in_array($hostel['id'], explode(',', $amenity['hostel_ids']))): ?>
                                <i class="fas fa-check-circle text-success" data-bs-toggle="tooltip" title="Available"></i>
                                <?php else: ?>
                                <i class="fas fa-times-circle text-danger" data-bs-toggle="tooltip" title="Not Available"></i>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <!-- Contact & Actions -->
                        <tr class="table-warning">
                            <td colspan="<?php echo count($hostels) + 1; ?>">
                                <strong><i class="fas fa-phone me-2"></i> Contact & Actions</strong>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>Contact Owner</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <div class="mb-2">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($hostel['owner_name']); ?>
                                </div>
                                <a href="tel:<?php echo htmlspecialchars($hostel['owner_phone']); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-phone me-1"></i> Call
                                </a>
                                <a href="details.php?id=<?php echo $hostel['id']; ?>" 
                                   class="btn btn-sm btn-outline-info ms-1">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        
                        <tr>
                            <td>Book Now</td>
                            <?php foreach ($hostels as $hostel): ?>
                            <td class="text-center">
                                <?php if (Auth::isLoggedIn()): ?>
                                    <?php if ($hostel['available_rooms'] > 0): ?>
                                    <a href="booking.php?id=<?php echo $hostel['id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-calendar-check me-1"></i> Book Room
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>
                                        <i class="fas fa-ban me-1"></i> Fully Booked
                                    </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="/login.php?redirect=<?php echo urlencode("/hostels/booking.php?id={$hostel['id']}"); ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-sign-in-alt me-1"></i> Login to Book
                                    </a>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Summary Section -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i> Price Comparison
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="priceChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-star me-2"></i> Rating Comparison
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="ratingChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Share & Export -->
    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h6 class="mb-3">Share this comparison:</h6>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary" onclick="shareComparison()">
                            <i class="fas fa-share-alt me-2"></i> Share Link
                        </button>
                        <button class="btn btn-outline-secondary" onclick="printComparison()">
                            <i class="fas fa-print me-2"></i> Print
                        </button>
                        <button class="btn btn-outline-success" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf me-2"></i> Export PDF
                        </button>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="input-group">
                        <input type="text" id="comparisonUrl" class="form-control" 
                               value="<?php echo APP_URL . '/hostels/compare.php?ids=' . implode(',', $hostelIds); ?>" readonly>
                        <button class="btn btn-primary" onclick="copyUrl()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize tooltips
$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Price Chart
const priceCtx = document.getElementById('priceChart').getContext('2d');
const priceChart = new Chart(priceCtx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($hostels as $h) echo '"' . htmlspecialchars($h['name']) . '",'; ?>],
        datasets: [{
            label: 'Monthly Price (KSh)',
            data: [<?php foreach ($hostels as $h) echo $h['price_per_month'] . ','; ?>],
            backgroundColor: [
                '#4e73df',
                '#1cc88a',
                '#36b9cc',
                '#f6c23e'
            ],
            borderColor: [
                '#2e59d9',
                '#17a673',
                '#2c9faf',
                '#dda20a'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'KSh ' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Rating Chart
const ratingCtx = document.getElementById('ratingChart').getContext('2d');
const ratingChart = new Chart(ratingCtx, {
    type: 'radar',
    data: {
        labels: ['Price', 'Location', 'Amenities', 'Cleanliness', 'Security', 'Overall'],
        datasets: [
            <?php foreach ($hostels as $index => $hostel): 
                $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'];
            ?>
            {
                label: '<?php echo htmlspecialchars($hostel['name']); ?>',
                data: [
                    Math.max(1, 5 - (<?php echo $hostel['price_per_month']; ?> / 20000)),
                    Math.max(1, 5 - (<?php echo $hostel['distance_to_campus']; ?> / 5)),
                    <?php echo count(explode(',', $amenity['hostel_ids'] ?? '')) / 3; ?>,
                    <?php echo $hostel['avg_rating'] ?: 3; ?>,
                    <?php echo $hostel['avg_rating'] ? $hostel['avg_rating'] + 0.5 : 3.5; ?>,
                    <?php echo $hostel['avg_rating'] ?: 3; ?>
                ],
                backgroundColor: 'rgba(<?php 
                    $rgb = hexToRgb(colors[<?php echo $index; ?>]);
                    echo implode(',', $rgb) . ',0.2';
                ?>)',
                borderColor: '<?php echo $colors[$index]; ?>',
                pointBackgroundColor: '<?php echo $colors[$index]; ?>',
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '<?php echo $colors[$index]; ?>'
            },
            <?php endforeach; ?>
        ]
    },
    options: {
        scale: {
            ticks: {
                beginAtZero: true,
                max: 5,
                stepSize: 1
            }
        }
    }
});

// Helper function to convert hex to RGB
function hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? [
        parseInt(result[1], 16),
        parseInt(result[2], 16),
        parseInt(result[3], 16)
    ] : [0, 0, 0];
}

// Share functionality
function shareComparison() {
    const url = document.getElementById('comparisonUrl').value;
    const text = 'Check out this hostel comparison on WEZO CAMPUS HUB';
    
    if (navigator.share) {
        navigator.share({
            title: 'Hostel Comparison',
            text: text,
            url: url
        });
    } else {
        copyUrl();
        alert('Link copied to clipboard!');
    }
}

function copyUrl() {
    const urlInput = document.getElementById('comparisonUrl');
    urlInput.select();
    urlInput.setSelectionRange(0, 99999);
    document.execCommand('copy');
}

function printComparison() {
    window.print();
}

function exportToPDF() {
    // This would require a PDF generation library
    alert('PDF export feature coming soon!');
    // window.open('/api/export/comparison.php?ids=<?php echo implode(',', $hostelIds); ?>', '_blank');
}

// Add to comparison
function addToComparison(hostelId) {
    const currentIds = new URLSearchParams(window.location.search).get('ids') || '';
    const ids = currentIds ? currentIds.split(',').map(Number) : [];
    
    if (!ids.includes(hostelId)) {
        ids.push(hostelId);
        if (ids.length > 4) {
            alert('Maximum 4 hostels can be compared at once');
            return;
        }
    }
    
    window.location.href = 'compare.php?ids=' + ids.join(',');
}

// Remove from comparison
function removeFromComparison(hostelId) {
    const currentIds = new URLSearchParams(window.location.search).get('ids') || '';
    const ids = currentIds.split(',').map(Number).filter(id => id !== hostelId);
    
    if (ids.length === 0) {
        window.location.href = 'index.php';
    } else {
        window.location.href = 'compare.php?ids=' + ids.join(',');
    }
}
</script>

<?php include '../../templates/footer.php'; ?>