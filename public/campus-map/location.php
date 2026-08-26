<?php
/**
 * WEZO CAMPUS HUB - Location Details
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Helpers.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Helpers;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

$locationId = intval($_GET['id'] ?? 0);
if (!$locationId) {
    Session::setFlash('error', 'Location not found');
    header('Location: index.php');
    exit;
}

// Get location details
$location = $db->fetch("
    SELECT cl.*, 
           ct.name as type_name, ct.icon as type_icon, ct.color as type_color,
           c.name as campus_name, c.address as campus_address
    FROM campus_locations cl
    LEFT JOIN campus_location_types ct ON cl.type_id = ct.id
    LEFT JOIN campuses c ON cl.campus_id = c.id
    WHERE cl.id = ? AND cl.status = 'active'
", [$locationId]);

if (!$location) {
    Session::setFlash('error', 'Location not found');
    header('Location: index.php');
    exit;
}

// Get nearby locations
$nearby = $db->fetchAll("
    SELECT cl.*, ct.name as type_name, ct.icon as type_icon
    FROM campus_locations cl
    LEFT JOIN campus_location_types ct ON cl.type_id = ct.id
    WHERE cl.campus_id = ? 
    AND cl.id != ? 
    AND cl.status = 'active'
    ORDER BY (
        POWER(cl.latitude - ?, 2) + POWER(cl.longitude - ?, 2)
    )
    LIMIT 5
", [$location['campus_id'], $locationId, $location['latitude'], $location['longitude']]);

// Check if user has saved this location
$isSaved = $db->fetch("
    SELECT id FROM user_saved_locations 
    WHERE user_id = ? AND location_id = ?
", [$user['id'], $locationId]);

// Increment view count
$db->query("UPDATE campus_locations SET views = views + 1 WHERE id = ?", [$locationId]);

$pageTitle = $location['name'];
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Location Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Campus Map</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($location['name']) ?></li>
                        </ol>
                    </nav>
                    <h1 class="h3 mb-0"><?= htmlspecialchars($location['name']) ?></h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($location['campus_name']) ?></p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-ellipsis-h"></i> Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="#" onclick="getDirections()">
                                <i class="fas fa-directions"></i> Get Directions
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="shareLocation()">
                                <i class="fas fa-share"></i> Share
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="saveLocation()">
                                <i class="fas fa-<?= $isSaved ? 'bookmark' : 'bookmark' ?>"></i>
                                <?= $isSaved ? 'Remove from Saved' : 'Save Location' ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="report.php?location_id=<?= $locationId ?>">
                                <i class="fas fa-flag"></i> Report Issue
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="row">
                <!-- Main Content -->
                <div class="col-md-8">
                    <!-- Location Info Card -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-4">
                                <div class="flex-shrink-0">
                                    <div class="rounded-circle p-3 me-3" 
                                         style="background: <?= $location['type_color'] ?>; color: white;">
                                        <i class="fas fa-<?= $location['type_icon'] ?> fa-2x"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title"><?= htmlspecialchars($location['name']) ?></h5>
                                    <p class="card-text text-muted">
                                        <span class="badge" style="background: <?= $location['type_color'] ?>; color: white;">
                                            <?= htmlspecialchars($location['type_name']) ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    <button class="btn btn-primary" onclick="showOnMap()">
                                        <i class="fas fa-map-marker-alt"></i> View on Map
                                    </button>
                                </div>
                            </div>

                            <!-- Location Details -->
                            <div class="row">
                                <?php if ($location['building']): ?>
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-building text-primary me-2"></i> Building</h6>
                                        <p class="mb-0"><?= htmlspecialchars($location['building']) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['floor']): ?>
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-layer-group text-primary me-2"></i> Floor</h6>
                                        <p class="mb-0"><?= htmlspecialchars($location['floor']) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['room_number']): ?>
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-door-closed text-primary me-2"></i> Room Number</h6>
                                        <p class="mb-0"><?= htmlspecialchars($location['room_number']) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['contact_phone']): ?>
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-phone text-primary me-2"></i> Contact Phone</h6>
                                        <p class="mb-0">
                                            <a href="tel:<?= htmlspecialchars($location['contact_phone']) ?>">
                                                <?= htmlspecialchars($location['contact_phone']) ?>
                                            </a>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['contact_email']): ?>
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-envelope text-primary me-2"></i> Contact Email</h6>
                                        <p class="mb-0">
                                            <a href="mailto:<?= htmlspecialchars($location['contact_email']) ?>">
                                                <?= htmlspecialchars($location['contact_email']) ?>
                                            </a>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['website_url']): ?>
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-globe text-primary me-2"></i> Website</h6>
                                        <p class="mb-0">
                                            <a href="<?= htmlspecialchars($location['website_url']) ?>" target="_blank">
                                                <?= htmlspecialchars($location['website_url']) ?>
                                            </a>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['opening_hours']): ?>
                                    <div class="col-12 mb-3">
                                        <h6><i class="fas fa-clock text-primary me-2"></i> Opening Hours</h6>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($location['opening_hours'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['description']): ?>
                                    <div class="col-12 mb-3">
                                        <h6><i class="fas fa-info-circle text-primary me-2"></i> Description</h6>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($location['description'])) ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($location['facilities']): ?>
                                    <div class="col-12 mb-3">
                                        <h6><i class="fas fa-tools text-primary me-2"></i> Facilities & Amenities</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php 
                                            $facilities = explode(',', $location['facilities']);
                                            foreach ($facilities as $facility): 
                                                if (trim($facility)):
                                            ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars(trim($facility)) ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Map Preview -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Location on Map</h5>
                        </div>
                        <div class="card-body p-0">
                            <div id="mapPreview" style="height: 300px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Quick Actions -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="getDirections()">
                                    <i class="fas fa-directions"></i> Get Directions
                                </button>
                                <button class="btn btn-outline-primary" onclick="shareLocation()">
                                    <i class="fas fa-share"></i> Share Location
                                </button>
                                <button class="btn btn-outline-secondary" id="saveBtn" onclick="saveLocation()">
                                    <i class="fas fa-bookmark"></i>
                                    <span id="saveText"><?= $isSaved ? 'Remove from Saved' : 'Save Location' ?></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Location Stats -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Location Info</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted small">Views</div>
                                        <div class="h4 mb-0"><?= $location['views'] ?></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted small">Saves</div>
                                        <div class="h4 mb-0">
                                            <?= $db->fetch("SELECT COUNT(*) as count FROM user_saved_locations WHERE location_id = ?", 
                                                [$locationId])['count'] ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> 
                                        Last updated: <?= date('M j, Y', strtotime($location['updated_at'])) ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Nearby Locations -->
                    <?php if (!empty($nearby)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Nearby Locations</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($nearby as $near): ?>
                                        <a href="location.php?id=<?= $near['id'] ?>" 
                                           class="list-group-item list-group-item-action d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <div class="rounded-circle p-2 me-2" 
                                                     style="background: #<?= $near['type_color'] ?>; color: white;">
                                                    <i class="fas fa-<?= $near['type_icon'] ?>"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 small"><?= htmlspecialchars($near['name']) ?></h6>
                                                <small class="text-muted"><?= htmlspecialchars($near['type_name']) ?></small>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-chevron-right text-muted"></i>
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
    </div>
</div>

<!-- Google Maps Script -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(Core\Config::GOOGLE_MAPS_API_KEY) ?>&callback=initMap" async defer></script>

<script>
let map;
let marker;

function initMap() {
    const locationLat = parseFloat("<?= $location['latitude'] ?>");
    const locationLng = parseFloat("<?= $location['longitude'] ?>");
    
    map = new google.maps.Map(document.getElementById('mapPreview'), {
        center: { lat: locationLat, lng: locationLng },
        zoom: 18,
        mapTypeId: 'roadmap'
    });
    
    marker = new google.maps.Marker({
        position: { lat: locationLat, lng: locationLng },
        map: map,
        title: "<?= addslashes($location['name']) ?>",
        animation: google.maps.Animation.DROP
    });
    
    const infowindow = new google.maps.InfoWindow({
        content: `<div class="p-2"><strong><?= addslashes($location['name']) ?></strong></div>`
    });
    
    marker.addListener('click', () => {
        infowindow.open(map, marker);
    });
}

function showOnMap() {
    window.location.href = 'index.php?focus=<?= $locationId ?>';
}

function getDirections() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                const locationLat = parseFloat("<?= $location['latitude'] ?>");
                const locationLng = parseFloat("<?= $location['longitude'] ?>");
                
                const url = `https://www.google.com/maps/dir/${userLat},${userLng}/${locationLat},${locationLng}`;
                window.open(url, '_blank');
            },
            () => {
                const campusLat = parseFloat("<?= $campus['latitude'] ?? -1.2921 ?>");
                const campusLng = parseFloat("<?= $campus['longitude'] ?? 36.8219 ?>");
                const locationLat = parseFloat("<?= $location['latitude'] ?>");
                const locationLng = parseFloat("<?= $location['longitude'] ?>");
                
                const url = `https://www.google.com/maps/dir/${campusLat},${campusLng}/${locationLat},${locationLng}`;
                window.open(url, '_blank');
            }
        );
    } else {
        const campusLat = parseFloat("<?= $campus['latitude'] ?? -1.2921 ?>");
        const campusLng = parseFloat("<?= $campus['longitude'] ?? 36.8219 ?>");
        const locationLat = parseFloat("<?= $location['latitude'] ?>");
        const locationLng = parseFloat("<?= $location['longitude'] ?>");
        
        const url = `https://www.google.com/maps/dir/${campusLat},${campusLng}/${locationLat},${locationLng}`;
        window.open(url, '_blank');
    }
}

function saveLocation() {
    const locationId = <?= $locationId ?>;
    const isCurrentlySaved = <?= $isSaved ? 'true' : 'false' ?>;
    
    fetch('/api/locations/save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            location_id: locationId,
            action: isCurrentlySaved ? 'unsave' : 'save'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (isCurrentlySaved) {
                document.getElementById('saveText').textContent = 'Save Location';
                document.getElementById('saveBtn').classList.remove('btn-secondary');
                document.getElementById('saveBtn').classList.add('btn-outline-secondary');
            } else {
                document.getElementById('saveText').textContent = 'Remove from Saved';
                document.getElementById('saveBtn').classList.remove('btn-outline-secondary');
                document.getElementById('saveBtn').classList.add('btn-secondary');
            }
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to save location');
    });
}

function shareLocation() {
    const shareData = {
        title: '<?= addslashes($location['name']) ?> - <?= addslashes($campus['name']) ?>',
        text: 'Check out this location on WEZO CAMPUS HUB',
        url: window.location.href
    };
    
    if (navigator.share) {
        navigator.share(shareData)
            .then(() => console.log('Shared successfully'))
            .catch(error => console.log('Error sharing:', error));
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href)
            .then(() => alert('Link copied to clipboard!'))
            .catch(err => {
                // Fallback to old method
                const tempInput = document.createElement('input');
                tempInput.value = window.location.href;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                alert('Link copied to clipboard!');
            });
    }
}
</script>

<style>
#mapPreview {
    border-radius: 0 0 8px 8px;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>