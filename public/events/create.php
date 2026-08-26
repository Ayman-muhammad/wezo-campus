<?php
/**
 * WEZO CAMPUS HUB - Create Event
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Helpers.php';
require_once __DIR__ . '/../../core/Validation.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Helpers;
use Core\Validation;

Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();

// Get categories and campuses
$categories = $db->fetchAll("SELECT * FROM event_categories ORDER BY name");
$campuses = $db->fetchAll("SELECT * FROM campuses ORDER BY name");

$errors = [];
$formData = [
    'title' => '',
    'description' => '',
    'category_id' => '',
    'campus_id' => $user['campus_id'] ?? '',
    'event_type' => 'social',
    'start_date' => '',
    'end_date' => '',
    'start_time' => '',
    'end_time' => '',
    'location' => '',
    'venue' => '',
    'max_attendees' => '',
    'is_free' => 1,
    'fee' => 0,
    'contact_email' => $user['email'] ?? '',
    'contact_phone' => $user['phone'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $formData = array_merge($formData, $_POST);
    
    // Validate data
    $validationErrors = Validation::validateEventData($_POST);
    
    if (!empty($validationErrors)) {
        $errors = array_merge($errors, $validationErrors);
    }
    
    // Validate image upload
    if (!empty($_FILES['cover_image']['name'])) {
        $imageResult = Helpers::validateAndUploadImage($_FILES['cover_image'], 'events');
        if ($imageResult['error']) {
            $errors[] = $imageResult['error'];
        } else {
            $formData['cover_image'] = $imageResult['path'];
        }
    }
    
    // If no errors, create event
    if (empty($errors)) {
        $eventData = [
            'user_id' => $user['id'],
            'title' => Validation::sanitize($formData['title']),
            'description' => Validation::sanitize($formData['description']),
            'category_id' => intval($formData['category_id']),
            'campus_id' => intval($formData['campus_id']),
            'event_type' => $formData['event_type'],
            'start_date' => $formData['start_date'],
            'end_date' => $formData['end_date'] ?: $formData['start_date'],
            'start_time' => $formData['start_time'] ?: NULL,
            'end_time' => $formData['end_time'] ?: NULL,
            'location' => Validation::sanitize($formData['location']),
            'venue' => Validation::sanitize($formData['venue']),
            'cover_image' => $formData['cover_image'] ?? NULL,
            'max_attendees' => $formData['max_attendees'] ? intval($formData['max_attendees']) : NULL,
            'is_free' => isset($_POST['is_free']) ? 1 : 0,
            'fee' => $formData['is_free'] ? 0 : floatval($formData['fee']),
            'contact_email' => Validation::sanitize($formData['contact_email']),
            'contact_phone' => Validation::sanitize($formData['contact_phone']),
            'website_url' => Validation::sanitize($formData['website_url'] ?? ''),
            'status' => $user['role'] === 'admin' ? 'approved' : 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $eventId = $db->insert('events', $eventData);
        
        if ($eventId) {
            Session::setFlash('success', 
                $user['role'] === 'admin' 
                ? 'Event created successfully!' 
                : 'Event submitted for approval. Admin will review it soon.'
            );
            header('Location: view.php?id=' . $eventId);
            exit;
        } else {
            $errors[] = 'Failed to create event. Please try again.';
        }
    }
}

$pageTitle = "Create Event";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Create New Event</h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Events
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="eventForm">
                        <div class="row">
                            <!-- Basic Information -->
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Event Title *</label>
                                    <input type="text" name="title" class="form-control" 
                                           value="<?= htmlspecialchars($formData['title']) ?>" 
                                           required maxlength="200">
                                    <small class="text-muted">Be descriptive and catchy</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Description *</label>
                                    <textarea name="description" class="form-control" rows="6" required><?= htmlspecialchars($formData['description']) ?></textarea>
                                    <small class="text-muted">Include all important details about the event</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category *</label>
                                        <select name="category_id" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>" 
                                                    <?= $formData['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Event Type *</label>
                                        <select name="event_type" class="form-select" required>
                                            <option value="academic" <?= $formData['event_type'] == 'academic' ? 'selected' : '' ?>>Academic</option>
                                            <option value="social" <?= $formData['event_type'] == 'social' ? 'selected' : '' ?>>Social</option>
                                            <option value="club" <?= $formData['event_type'] == 'club' ? 'selected' : '' ?>>Club Meeting</option>
                                            <option value="workshop" <?= $formData['event_type'] == 'workshop' ? 'selected' : '' ?>>Workshop</option>
                                            <option value="sports" <?= $formData['event_type'] == 'sports' ? 'selected' : '' ?>>Sports</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Campus *</label>
                                        <select name="campus_id" class="form-select" required>
                                            <option value="">Select Campus</option>
                                            <?php foreach ($campuses as $campus): ?>
                                                <option value="<?= $campus['id'] ?>" 
                                                    <?= $formData['campus_id'] == $campus['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($campus['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cover Image</label>
                                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                                        <small class="text-muted">Recommended size: 1200x600px, max 2MB</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Date & Time -->
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-header">
                                        <h6 class="mb-0">Date & Time</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Start Date *</label>
                                            <input type="date" name="start_date" class="form-control" 
                                                   value="<?= htmlspecialchars($formData['start_date']) ?>" 
                                                   required min="<?= date('Y-m-d') ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">End Date</label>
                                            <input type="date" name="end_date" class="form-control" 
                                                   value="<?= htmlspecialchars($formData['end_date']) ?>">
                                            <small class="text-muted">Leave blank if single day event</small>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Start Time</label>
                                                <input type="time" name="start_time" class="form-control" 
                                                       value="<?= htmlspecialchars($formData['start_time']) ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">End Time</label>
                                                <input type="time" name="end_time" class="form-control" 
                                                       value="<?= htmlspecialchars($formData['end_time']) ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Location & Venue -->
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location *</label>
                                <input type="text" name="location" class="form-control" 
                                       value="<?= htmlspecialchars($formData['location']) ?>" 
                                       placeholder="e.g., Main Campus, Student Center" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Venue</label>
                                <input type="text" name="venue" class="form-control" 
                                       value="<?= htmlspecialchars($formData['venue']) ?>" 
                                       placeholder="e.g., Room 101, Auditorium A">
                            </div>
                        </div>

                        <!-- Attendance & Fees -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Attendance</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Maximum Attendees</label>
                                            <input type="number" name="max_attendees" class="form-control" 
                                                   value="<?= htmlspecialchars($formData['max_attendees']) ?>" 
                                                   min="1" placeholder="Leave blank for unlimited">
                                            <small class="text-muted">Set a limit for registration</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">Fees & Contact</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="is_free" class="form-check-input" 
                                                       id="is_free" <?= $formData['is_free'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="is_free">This is a free event</label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3" id="fee_field" style="display: <?= $formData['is_free'] ? 'none' : 'block' ?>;">
                                            <label class="form-label">Fee Amount (KSh)</label>
                                            <input type="number" name="fee" class="form-control" 
                                                   value="<?= htmlspecialchars($formData['fee']) ?>" 
                                                   min="0" step="0.01">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Contact Email *</label>
                                            <input type="email" name="contact_email" class="form-control" 
                                                   value="<?= htmlspecialchars($formData['contact_email']) ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Contact Phone</label>
                                            <input type="tel" name="contact_phone" class="form-control" 
                                                   value="<?= htmlspecialchars($formData['contact_phone']) ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Website URL</label>
                                            <input type="url" name="website_url" class="form-control" 
                                                   value="<?= htmlspecialchars($formData['website_url'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-calendar-plus"></i> Create Event
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle fee field
document.getElementById('is_free').addEventListener('change', function() {
    document.getElementById('fee_field').style.display = this.checked ? 'none' : 'block';
});

// Form validation
document.getElementById('eventForm').addEventListener('submit', function(e) {
    const title = this.elements['title'].value.trim();
    const description = this.elements['description'].value.trim();
    const startDate = this.elements['start_date'].value;
    const today = new Date().toISOString().split('T')[0];
    
    if (!title) {
        e.preventDefault();
        alert('Please enter an event title');
        return false;
    }
    
    if (!description) {
        e.preventDefault();
        alert('Please enter an event description');
        return false;
    }
    
    if (startDate < today) {
        e.preventDefault();
        alert('Start date cannot be in the past');
        return false;
    }
    
    return true;
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>