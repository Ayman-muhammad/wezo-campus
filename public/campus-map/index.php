<?php
/**
 * WEZO CAMPUS HUB - Campus Map
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

// Get user's campus
$campus = $db->fetch("
    SELECT c.* FROM campuses c
    WHERE c.id = ?
", [$user['campus_id']]);

if (!$campus) {
    Session::setFlash('error', 'Campus not found');
    header('Location: /dashboard');
    exit;
}

// Get campus locations
$locations = $db->fetchAll("
    SELECT cl.*, ct.name as type_name, ct.icon as type_icon, ct.color as type_color
    FROM campus_locations cl
    LEFT JOIN campus_location_types ct ON cl.type_id = ct.id
    WHERE cl.campus_id = ? AND cl.status = 'active'
    ORDER BY ct.sort_order, cl.name
", [$user['campus_id']]);

// Group locations by type
$groupedLocations = [];
foreach ($locations as $location) {
    $typeId = $location['type_id'];
    if (!isset($groupedLocations[$typeId])) {
        $groupedLocations[$typeId] = [
            'type_name' => $location['type_name'],
            'type_icon' => $location['type_icon'],
            'type_color' => $location['type_color'],
            'locations' => []
        ];
    }
    $groupedLocations[$typeId]['locations'][] = $location;
}

$pageTitle = "Campus Map";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Campus Map Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Campus Map</h1>
                    <p class="text-muted mb-0"><?= htmlspecialchars($campus['name']) ?></p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="printMap()">
                            <i class="fas fa-print"></i> Print Map
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportPDF()">
                            <i class="fas fa-file-pdf"></i> Save as PDF
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="shareMap()">
                            <i class="fas fa-share"></i> Share
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- Map Controls -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="searchLocation" class="form-control" 
                                       placeholder="Search locations...">
                                <button class="btn btn-primary" onclick="searchOnMap()">
                                    Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-end gap-2">
                                <button class="btn btn-outline-secondary" onclick="resetMap()">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                                <button class="btn btn-outline-primary" onclick="getDirections()">
                                    <i class="fas fa-directions"></i> Directions
                                </button>
                                <button class="btn btn-primary" onclick="showMyLocation()">
                                    <i class="fas fa-location-arrow"></i> My Location
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Map Sidebar -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Location Categories</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($groupedLocations as $typeId => $group): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">
                                                <i class="fas fa-<?= $group['type_icon'] ?> me-2" 
                                                   style="color: <?= $group['type_color'] ?>"></i>
                                                <?= htmlspecialchars($group['type_name']) ?>
                                            </h6>
                                            <span class="badge" style="background: <?= $group['type_color'] ?>; color: white;">
                                                <?= count($group['locations']) ?>
                                            </span>
                                        </div>
                                        <div class="locations-list">
                                            <?php foreach ($group['locations'] as $location): ?>
                                                <div class="location-item d-flex align-items-center mb-2 p-2 rounded hover-bg" 
                                                     onclick="focusLocation(<?= $location['id'] ?>)"
                                                     style="cursor: pointer;">
                                                    <div class="flex-shrink-0">
                                                        <div class="rounded-circle p-2 me-2" 
                                                             style="background: <?= $group['type_color'] ?>; color: white; width: 40px; height: 40px;">
                                                            <i class="fas fa-<?= $group['type_icon'] ?>"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-0 small"><?= htmlspecialchars($location['name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($location['building'] ?? '') ?></small>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <i class="fas fa-chevron-right text-muted"></i>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-6">
                                    <button class="btn btn-outline-primary w-100" onclick="showCategory('classroom')">
                                        <i class="fas fa-chalkboard-teacher"></i> Classrooms
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-success w-100" onclick="showCategory('library')">
                                        <i class="fas fa-book"></i> Libraries
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-info w-100" onclick="showCategory('lab')">
                                        <i class="fas fa-flask"></i> Labs
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-warning w-100" onclick="showCategory('cafeteria')">
                                        <i class="fas fa-utensils"></i> Cafeterias
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-danger w-100" onclick="showCategory('health')">
                                        <i class="fas fa-clinic-medical"></i> Health Center
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button class="btn btn-outline-secondary w-100" onclick="showCategory('parking')">
                                        <i class="fas fa-parking"></i> Parking
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Container -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body p-0">
                            <div id="map" style="height: 600px; width: 100%;"></div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Click on markers for details
                                </small>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-outline-secondary" onclick="zoomIn()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="zoomOut()">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Location Details (Hidden by default) -->
                    <div class="card mt-4" id="locationDetails" style="display: none;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 id="locationName" class="mb-0"></h5>
                                <button class="btn btn-sm btn-outline-secondary" onclick="hideDetails()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="locationContent"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Google Maps Script -->
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(Core\Config::GOOGLE_MAPS_API_KEY) ?>&callback=initMap&libraries=places" async defer></script>

<script>
// Map variables
let map;
let markers = [];
let infoWindows = [];
let userLocationMarker = null;

// Initialize map
function initMap() {
    const campusLat = parseFloat("<?= $campus['latitude'] ?? -1.2921 ?>");
    const campusLng = parseFloat("<?= $campus['longitude'] ?? 36.8219 ?>");
    
    const mapOptions = {
        center: { lat: campusLat, lng: campusLng },
        zoom: 17,
        mapTypeId: 'roadmap',
        styles: [
            {
                featureType: "poi.business",
                stylers: [{ visibility: "off" }]
            },
            {
                featureType: "transit",
                elementType: "labels.icon",
                stylers: [{ visibility: "off" }]
            }
        ]
    };
    
    map = new google.maps.Map(document.getElementById('map'), mapOptions);
    
    // Add campus boundary if available
    <?php if (!empty($campus['boundary_coordinates'])): ?>
        const boundaryCoords = JSON.parse('<?= $campus['boundary_coordinates'] ?>');
        const campusBoundary = new google.maps.Polygon({
            paths: boundaryCoords,
            strokeColor: "#FF0000",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#FF0000",
            fillOpacity: 0.1
        });
        campusBoundary.setMap(map);
    <?php endif; ?>
    
    // Add locations
    <?php foreach ($locations as $location): ?>
        addLocationMarker(
            <?= $location['id'] ?>,
            "<?= addslashes($location['name']) ?>",
            parseFloat("<?= $location['latitude'] ?>"),
            parseFloat("<?= $location['longitude'] ?>"),
            "<?= $location['type_color'] ?>",
            "<?= addslashes($location['type_icon']) ?>",
            "<?= addslashes($location['description'] ?? '') ?>",
            "<?= addslashes($location['building'] ?? '') ?>",
            "<?= addslashes($location['floor'] ?? '') ?>",
            "<?= addslashes($location['room_number'] ?? '') ?>",
            "<?= addslashes($location['opening_hours'] ?? '') ?>",
            "<?= addslashes($location['contact_phone'] ?? '') ?>"
        );
    <?php endforeach; ?>
}

// Add location marker to map
function addLocationMarker(id, name, lat, lng, color, icon, description, building, floor, room, hours, phone) {
    const marker = new google.maps.Marker({
        position: { lat: lat, lng: lng },
        map: map,
        title: name,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            scale: 10,
            fillColor: color,
            fillOpacity: 1,
            strokeColor: '#FFFFFF',
            strokeWeight: 2
        },
        animation: google.maps.Animation.DROP
    });
    
    const contentString = `
        <div class="map-info-window">
            <h6>${name}</h6>
            ${building ? `<p><strong>Building:</strong> ${building}</p>` : ''}
            ${floor ? `<p><strong>Floor:</strong> ${floor}</p>` : ''}
            ${room ? `<p><strong>Room:</strong> ${room}</p>` : ''}
            ${description ? `<p>${description}</p>` : ''}
            ${hours ? `<p><strong>Hours:</strong> ${hours}</p>` : ''}
            ${phone ? `<p><strong>Contact:</strong> ${phone}</p>` : ''}
            <div class="mt-3">
                <button class="btn btn-sm btn-primary" onclick="getDirectionsTo(${lat}, ${lng})">
                    <i class="fas fa-directions"></i> Directions
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="saveLocation(${id})">
                    <i class="fas fa-bookmark"></i> Save
                </button>
            </div>
        </div>
    `;
    
    const infowindow = new google.maps.InfoWindow({
        content: contentString
    });
    
    marker.addListener('click', () => {
        // Close all other info windows
        infoWindows.forEach(iw => iw.close());
        infowindow.open(map, marker);
        showLocationDetails(id, name, description, building, floor, room, hours, phone);
    });
    
    markers.push(marker);
    infoWindows.push(infowindow);
}

// Show location details in sidebar
function showLocationDetails(id, name, description, building, floor, room, hours, phone) {
    document.getElementById('locationDetails').style.display = 'block';
    document.getElementById('locationName').textContent = name;
    
    let content = '';
    if (building) content += `<p><strong>Building:</strong> ${building}</p>`;
    if (floor) content += `<p><strong>Floor:</strong> ${floor}</p>`;
    if (room) content += `<p><strong>Room:</strong> ${room}</p>`;
    if (description) content += `<p>${description}</p>`;
    if (hours) content += `<p><strong>Hours:</strong> ${hours}</p>`;
    if (phone) content += `<p><strong>Contact:</strong> ${phone}</p>`;
    
    content += `
        <div class="mt-3">
            <button class="btn btn-primary" onclick="getDirectionsToLocation(${id})">
                <i class="fas fa-directions"></i> Get Directions
            </button>
            <button class="btn btn-outline-secondary" onclick="saveLocation(${id})">
                <i class="fas fa-bookmark"></i> Save Location
            </button>
            <button class="btn btn-outline-secondary" onclick="shareLocation(${id})">
                <i class="fas fa-share"></i> Share
            </button>
        </div>
    `;
    
    document.getElementById('locationContent').innerHTML = content;
}

// Hide location details
function hideDetails() {
    document.getElementById('locationDetails').style.display = 'none';
}

// Focus on specific location
function focusLocation(locationId) {
    const location = locationsData.find(l => l.id === locationId);
    if (location) {
        map.setCenter({ lat: location.lat, lng: location.lng });
        map.setZoom(19);
        
        // Trigger click on marker
        const marker = markers.find(m => m.title === location.name);
        if (marker) {
            google.maps.event.trigger(marker, 'click');
        }
    }
}

// Show user's current location
function showMyLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                
                // Remove previous user marker
                if (userLocationMarker) {
                    userLocationMarker.setMap(null);
                }
                
                // Add new user marker
                userLocationMarker = new google.maps.Marker({
                    position: userLocation,
                    map: map,
                    title: "Your Location",
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 12,
                        fillColor: '#4285F4',
                        fillOpacity: 1,
                        strokeColor: '#FFFFFF',
                        strokeWeight: 3
                    },
                    animation: google.maps.Animation.BOUNCE
                });
                
                // Center map on user location
                map.setCenter(userLocation);
                map.setZoom(18);
                
                // Show info window
                const infowindow = new google.maps.InfoWindow({
                    content: '<div class="p-2"><strong>You are here</strong></div>'
                });
                infowindow.open(map, userLocationMarker);
                
                // Auto-close after 5 seconds
                setTimeout(() => infowindow.close(), 5000);
            },
            (error) => {
                alert('Unable to get your location: ' + error.message);
            },
            {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// Get directions to location
function getDirectionsTo(lat, lng) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const userLat = position.coords.latitude;
                const userLng = position.coords.longitude;
                
                const url = `https://www.google.com/maps/dir/${userLat},${userLng}/${lat},${lng}`;
                window.open(url, '_blank');
            },
            () => {
                // If can't get location, use campus center
                const campusLat = parseFloat("<?= $campus['latitude'] ?? -1.2921 ?>");
                const campusLng = parseFloat("<?= $campus['longitude'] ?? 36.8219 ?>");
                const url = `https://www.google.com/maps/dir/${campusLat},${campusLng}/${lat},${lng}`;
                window.open(url, '_blank');
            }
        );
    } else {
        const campusLat = parseFloat("<?= $campus['latitude'] ?? -1.2921 ?>");
        const campusLng = parseFloat("<?= $campus['longitude'] ?? 36.8219 ?>");
        const url = `https://www.google.com/maps/dir/${campusLat},${campusLng}/${lat},${lng}`;
        window.open(url, '_blank');
    }
}

// Search locations
function searchOnMap() {
    const searchTerm = document.getElementById('searchLocation').value.toLowerCase();
    if (!searchTerm) return;
    
    // Clear all markers
    markers.forEach(marker => marker.setMap(null));
    infoWindows.forEach(iw => iw.close());
    markers = [];
    infoWindows = [];
    
    // Filter and add matching locations
    <?php foreach ($locations as $location): ?>
        const name = "<?= addslashes(strtolower($location['name'])) ?>";
        const building = "<?= addslashes(strtolower($location['building'] ?? '')) ?>";
        const description = "<?= addslashes(strtolower($location['description'] ?? '')) ?>";
        
        if (name.includes(searchTerm) || building.includes(searchTerm) || description.includes(searchTerm)) {
            addLocationMarker(
                <?= $location['id'] ?>,
                "<?= addslashes($location['name']) ?>",
                parseFloat("<?= $location['latitude'] ?>"),
                parseFloat("<?= $location['longitude'] ?>"),
                "<?= $location['type_color'] ?>",
                "<?= addslashes($location['type_icon']) ?>",
                "<?= addslashes($location['description'] ?? '') ?>",
                "<?= addslashes($location['building'] ?? '') ?>",
                "<?= addslashes($location['floor'] ?? '') ?>",
                "<?= addslashes($location['room_number'] ?? '') ?>",
                "<?= addslashes($location['opening_hours'] ?? '') ?>",
                "<?= addslashes($location['contact_phone'] ?? '') ?>"
            );
        }
    <?php endforeach; ?>
    
    if (markers.length === 0) {
        alert('No locations found matching "' + searchTerm + '"');
        // Reset map with all markers
        <?php foreach ($locations as $location): ?>
            addLocationMarker(
                <?= $location['id'] ?>,
                "<?= addslashes($location['name']) ?>",
                parseFloat("<?= $location['latitude'] ?>"),
                parseFloat("<?= $location['longitude'] ?>"),
                "<?= $location['type_color'] ?>",
                "<?= addslashes($location['type_icon']) ?>",
                "<?= addslashes($location['description'] ?? '') ?>",
                "<?= addslashes($location['building'] ?? '') ?>",
                "<?= addslashes($location['floor'] ?? '') ?>",
                "<?= addslashes($location['room_number'] ?? '') ?>",
                "<?= addslashes($location['opening_hours'] ?? '') ?>",
                "<?= addslashes($location['contact_phone'] ?? '') ?>"
            );
        <?php endforeach; ?>
    }
}

// Reset map
function resetMap() {
    map.setCenter({ 
        lat: parseFloat("<?= $campus['latitude'] ?? -1.2921 ?>"), 
        lng: parseFloat("<?= $campus['longitude'] ?? 36.8219 ?>") 
    });
    map.setZoom(17);
    document.getElementById('searchLocation').value = '';
    hideDetails();
}

// Zoom controls
function zoomIn() {
    map.setZoom(map.getZoom() + 1);
}

function zoomOut() {
    map.setZoom(map.getZoom() - 1);
}

// Show specific category
function showCategory(category) {
    // This would filter markers by category
    alert('Showing ' + category + ' locations');
}

// Save location
function saveLocation(locationId) {
    fetch('/api/locations/save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ location_id: locationId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Location saved to your favorites!');
        } else {
            alert('Failed to save location: ' + data.error);
        }
    });
}

// Export functions
function printMap() {
    window.print();
}

function exportPDF() {
    alert('PDF export feature coming soon!');
}

function shareMap() {
    if (navigator.share) {
        navigator.share({
            title: 'Campus Map - <?= htmlspecialchars($campus['name']) ?>',
            text: 'Check out the campus map on WEZO CAMPUS HUB',
            url: window.location.href
        });
    } else {
        // Fallback: copy to clipboard
        navigator.clipboard.writeText(window.location.href);
        alert('Map link copied to clipboard!');
    }
}

// Store locations data for JavaScript
const locationsData = [
    <?php foreach ($locations as $location): ?>
    {
        id: <?= $location['id'] ?>,
        name: "<?= addslashes($location['name']) ?>",
        lat: parseFloat("<?= $location['latitude'] ?>"),
        lng: parseFloat("<?= $location['longitude'] ?>"),
        building: "<?= addslashes($location['building'] ?? '') ?>",
        floor: "<?= addslashes($location['floor'] ?? '') ?>",
        room: "<?= addslashes($location['room_number'] ?? '') ?>"
    },
    <?php endforeach; ?>
];
</script>

<style>
#map {
    border-radius: 8px;
}
.hover-bg:hover {
    background-color: #f8f9fa;
}
.map-info-window {
    max-width: 300px;
}
.map-info-window h6 {
    color: #333;
    margin-bottom: 10px;
}
.map-info-window p {
    margin-bottom: 5px;
    font-size: 0.9em;
}
@media print {
    .col-md-3, .card-footer, .btn {
        display: none !important;
    }
    .col-md-9 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    #map {
        height: 800px !important;
    }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>