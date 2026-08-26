<?php
/**
 * WEZO CAMPUS HUB - User Profile
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Upload.php';

use Core\Auth;
use Core\Database;
use Core\Session;
use Core\Upload;

// Initialize
Auth::init();
Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::user();
$userId = $user['id'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !Auth::validateCsrfToken($_POST['csrf_token'])) {
        $response['message'] = 'Security token invalid.';
        echo json_encode($response);
        exit;
    }
    
    switch ($action) {
        case 'update_profile':
            $data = [
                'first_name' => trim($_POST['first_name'] ?? ''),
                'last_name' => trim($_POST['last_name'] ?? ''),
                'phone' => trim($_POST['phone'] ?? ''),
                'bio' => trim($_POST['bio'] ?? '')
            ];
            
            $result = Auth::updateProfile($data);
            if ($result['success']) {
                Session::flash('success', $result['message']);
            } else {
                Session::flash('error', $result['message']);
            }
            header('Location: /profile.php');
            exit;
            
        case 'change_password':
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if ($newPassword !== $confirmPassword) {
                $response['message'] = 'New passwords do not match.';
            } else {
                $result = Auth::changePassword($currentPassword, $newPassword);
                $response = $result;
            }
            
            echo json_encode($response);
            exit;
            
        case 'upload_avatar':
            $upload = new Upload();
            $result = $upload->profilePicture($_FILES['avatar'] ?? null);
            
            if ($result['success']) {
                $db->update('users', 
                    ['profile_pic' => $result['filename']], 
                    'id = ?', 
                    [$userId]
                );
                
                // Update session user data
                $_SESSION['user_profile_pic'] = $result['filename'];
                
                $response['success'] = true;
                $response['message'] = 'Profile picture updated successfully.';
                $response['filename'] = $result['filename'];
            } else {
                $response['message'] = $result['message'];
            }
            
            echo json_encode($response);
            exit;
            
        case 'delete_account':
            // This is a serious action - require password confirmation
            $password = $_POST['password'] ?? '';
            
            if (!password_verify($password, $user['password'])) {
                $response['message'] = 'Incorrect password.';
            } else {
                // Soft delete - mark as inactive
                $db->update('users', 
                    [
                        'status' => 'banned',
                        'email' => 'deleted_' . time() . '_' . $user['email'],
                        'username' => 'deleted_' . time() . '_' . $user['username']
                    ], 
                    'id = ?', 
                    [$userId]
                );
                
                // Log activity
                $db->insert('activity_logs', [
                    'user_id' => $userId,
                    'action' => 'account_deletion',
                    'description' => 'User deleted their account',
                    'ip_address' => $_SERVER['REMOTE_ADDR']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Account deleted successfully.';
                $response['redirect'] = '/logout.php';
            }
            
            echo json_encode($response);
            exit;
    }
}

// Get user statistics
$stats = $db->fetch("
    SELECT 
        (SELECT COUNT(*) FROM notes WHERE user_id = ?) as notes_count,
        (SELECT COUNT(*) FROM marketplace_items WHERE user_id = ?) as items_count,
        (SELECT COUNT(*) FROM hostel_reviews WHERE user_id = ?) as reviews_count,
        (SELECT COUNT(*) FROM resources WHERE user_id = ?) as resources_count,
        (SELECT COUNT(*) FROM favorites WHERE user_id = ?) as favorites_count
", [$userId, $userId, $userId, $userId, $userId]);

// Get recent activity
$recentActivity = $db->fetchAll("
    SELECT * FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
", [$userId]);

// Get user's recent notes
$userNotes = $db->fetchAll("
    SELECT n.*, c.name as category_name 
    FROM notes n 
    LEFT JOIN note_categories c ON n.category_id = c.id 
    WHERE n.user_id = ? 
    ORDER BY n.created_at DESC 
    LIMIT 5
", [$userId]);

// Get user's active marketplace items
$userItems = $db->fetchAll("
    SELECT m.*, c.name as category_name 
    FROM marketplace_items m 
    LEFT JOIN marketplace_categories c ON m.category_id = c.id 
    WHERE m.user_id = ? AND m.status = 'active' 
    ORDER BY m.created_at DESC 
    LIMIT 5
", [$userId]);

// Set page title
$pageTitle = "My Profile - WEZO CAMPUS HUB";

// Include header
include __DIR__ . '/../templates/header.php';
?>

<div class="container py-4">
    <!-- Profile Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <!-- Avatar Section -->
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <div class="position-relative d-inline-block">
                                <div class="avatar-container">
                                    <?php if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default.png'): ?>
                                    <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_pic']); ?>" 
                                         alt="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                         class="avatar-img rounded-circle shadow"
                                         id="profileAvatar"
                                         style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                    <div class="avatar-placeholder rounded-circle bg-primary d-flex align-items-center justify-content-center shadow"
                                         style="width: 150px; height: 150px;">
                                        <span class="display-4 text-white">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Avatar Upload Button -->
                                    <button class="btn btn-primary btn-sm rounded-pill position-absolute bottom-0 end-0"
                                            data-bs-toggle="modal" data-bs-target="#avatarModal">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Verification Badge -->
                            <?php if ($user['is_verified']): ?>
                            <div class="mt-3">
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i> Verified Student
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- User Info -->
                        <div class="col-md-6">
                            <h1 class="h2 mb-1">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </h1>
                            <p class="text-muted mb-2">
                                <i class="fas fa-at me-1"></i> @<?php echo htmlspecialchars($user['username']); ?>
                            </p>
                            <p class="mb-3">
                                <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            
                            <?php if (!empty($user['bio'])): ?>
                            <div class="bio-section mb-3">
                                <h6 class="text-muted mb-2">About Me</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Contact Info -->
                            <?php if (!empty($user['phone'])): ?>
                            <div class="contact-info">
                                <small class="text-muted">
                                    <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($user['phone']); ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Stats -->
                        <div class="col-md-3">
                            <div class="row">
                                <div class="col-6 col-md-12 mb-3">
                                    <div class="stat-card text-center p-3 border rounded">
                                        <div class="stat-number h3 text-primary"><?php echo $stats['notes_count']; ?></div>
                                        <div class="stat-label text-muted small">Notes</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-12 mb-3">
                                    <div class="stat-card text-center p-3 border rounded">
                                        <div class="stat-number h3 text-success"><?php echo $stats['items_count']; ?></div>
                                        <div class="stat-label text-muted small">Items</div>
                                    </div>
                                </div>
                                <div class="col-6 col-md-12">
                                    <div class="stat-card text-center p-3 border rounded">
                                        <div class="stat-number h3 text-info"><?php echo $stats['reviews_count']; ?></div>
                                        <div class="stat-label text-muted small">Reviews</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Profile Tabs -->
        <div class="col-lg-8">
            <!-- Profile Tabs -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom-0">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button">
                                <i class="fas fa-edit me-1"></i> Edit Profile
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                                <i class="fas fa-shield-alt me-1"></i> Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                                <i class="fas fa-history me-1"></i> Recent Activity
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="danger-tab" data-bs-toggle="tab" data-bs-target="#danger" type="button">
                                <i class="fas fa-exclamation-triangle me-1"></i> Danger Zone
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Edit Profile Tab -->
                        <div class="tab-pane fade show active" id="edit" role="tabpanel">
                            <form id="editProfileForm" method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                    <small class="text-muted">Contact support to change email</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                           placeholder="+254 700 000 000">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="4" 
                                              placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                    <small class="text-muted">Brief introduction about yourself (optional)</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Changes
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        <i class="fas fa-undo me-1"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Security Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <form id="changePasswordForm" method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <h6 class="border-bottom pb-2 mb-3">Change Password</h6>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Minimum 8 characters</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div id="passwordMatch" class="form-text"></div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-1"></i> Update Password
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <h6 class="border-bottom pb-2 mb-3">Security Settings</h6>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="twoFactor" disabled>
                                    <label class="form-check-label" for="twoFactor">
                                        Two-Factor Authentication
                                    </label>
                                    <small class="d-block text-muted">Add an extra layer of security to your account</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                    <label class="form-check-label" for="emailNotifications">
                                        Email Notifications
                                    </label>
                                    <small class="d-block text-muted">Receive email updates about your account</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="loginAlerts" checked>
                                    <label class="form-check-label" for="loginAlerts">
                                        Login Alerts
                                    </label>
                                    <small class="d-block text-muted">Get notified of new logins to your account</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel">
                            <h6 class="border-bottom pb-2 mb-3">Recent Activity</h6>
                            
                            <?php if (empty($recentActivity)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No recent activity</p>
                            </div>
                            <?php else: ?>
                            <div class="activity-timeline">
                                <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item border-start border-primary ps-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['action']))); ?></h6>
                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 small"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <?php if (!empty($activity['ip_address'])): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-globe me-1"></i> <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center mt-3">
                                <a href="/activity.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list me-1"></i> View All Activity
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Danger Zone Tab -->
                        <div class="tab-pane fade" id="danger" role="tabpanel">
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">
                                    <i class="fas fa-exclamation-triangle me-2"></i> Danger Zone
                                </h5>
                                <p class="mb-0">These actions are irreversible. Please proceed with caution.</p>
                            </div>
                            
                            <!-- Export Data -->
                            <div class="card border-danger mb-3">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-download me-2"></i> Export Data
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">Download all your data from WEZO CAMPUS HUB.</p>
                                    <button class="btn btn-outline-danger" id="exportDataBtn">
                                        <i class="fas fa-file-export me-1"></i> Request Data Export
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Delete Account -->
                            <div class="card border-danger">
                                <div class="card-header bg-danger text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-trash-alt me-2"></i> Delete Account
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">
                                        Permanently delete your account and all associated data. This action cannot be undone.
                                    </p>
                                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                        <i class="fas fa-trash-alt me-1"></i> Delete My Account
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User's Recent Notes -->
            <?php if (!empty($userNotes)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-book text-primary me-2"></i> My Recent Notes
                    </h5>
                    <a href="/notes/" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Downloads</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userNotes as $note): ?>
                                <tr>
                                    <td>
                                        <a href="/notes/view.php?id=<?php echo $note['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($note['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($note['category_name']); ?></td>
                                    <td><?php echo $note['download_count']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($note['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/notes/edit.php?id=<?php echo $note['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="/notes/view.php?id=<?php echo $note['id']; ?>" class="btn btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right Column: Quick Stats & Marketplace Items -->
        <div class="col-lg-4">
            <!-- Quick Stats -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i> My Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-book text-primary me-2"></i> Notes Uploaded
                            </span>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['notes_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-store text-success me-2"></i> Marketplace Items
                            </span>
                            <span class="badge bg-success rounded-pill"><?php echo $stats['items_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-star text-warning me-2"></i> Reviews Written
                            </span>
                            <span class="badge bg-warning rounded-pill"><?php echo $stats['reviews_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-file-alt text-info me-2"></i> Resources
                            </span>
                            <span class="badge bg-info rounded-pill"><?php echo $stats['resources_count']; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-heart text-danger me-2"></i> Favorites
                            </span>
                            <span class="badge bg-danger rounded-pill"><?php echo $stats['favorites_count']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-center">
                    <small class="text-muted">Member since <?php echo date('F Y', strtotime($user['created_at'])); ?></small>
                </div>
            </div>
            
            <!-- My Marketplace Items -->
            <?php if (!empty($userItems)): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-store text-success me-2"></i> My Active Listings
                    </h5>
                    <a href="/marketplace/" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($userItems as $item): ?>
                        <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                <small class="text-success">KSh <?php echo number_format($item['price'], 2); ?></small>
                            </div>
                            <p class="mb-1 small text-muted">
                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                <span class="badge bg-light text-dark ms-1"><?php echo ucfirst($item['condition']); ?></span>
                            </p>
                            <small class="text-muted">
                                <i class="far fa-eye me-1"></i> <?php echo $item['view_count']; ?> views
                            </small>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Account Verification -->
            <?php if (!$user['is_verified']): ?>
            <div class="card border-warning shadow-sm">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-user-check me-2"></i> Verify Your Account
                    </h5>
                </div>
                <div class="card-body">
                    <p class="card-text small">
                        Get verified to unlock premium features like:
                    </p>
                    <ul class="small mb-3">
                        <li>Priority listings in marketplace</li>
                        <li>Larger file uploads</li>
                        <li>Verified badge on profile</li>
                        <li>Access to exclusive content</li>
                    </ul>
                    <a href="/verify.php" class="btn btn-warning btn-sm">
                        <i class="fas fa-check-circle me-1"></i> Get Verified
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="avatarForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="upload_avatar">
                    
                    <div class="text-center mb-3">
                        <div class="avatar-preview mb-3">
                            <img id="avatarPreview" src="<?php 
                                if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default.png') {
                                    echo '/assets/uploads/profiles/' . htmlspecialchars($user['profile_pic']);
                                } else {
                                    echo 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgZmlsbD0iIzFhNTZkYiIvPjx0ZXh0IHg9Ijc1IiB5PSI4MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjMwIiBmaWxsPSIjZmZmIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+PD90ZW1wbGF0ZQ==';
                                }
                            ?>" 
                                 class="rounded-circle" 
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="avatarInput" class="btn btn-primary btn-sm">
                                <i class="fas fa-upload me-1"></i> Choose Image
                            </label>
                            <input type="file" class="d-none" id="avatarInput" name="avatar" accept="image/*">
                            <button type="button" class="btn btn-outline-danger btn-sm" id="removeAvatar">
                                <i class="fas fa-trash me-1"></i> Remove
                            </button>
                        </div>
                        
                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-1"></i> 
                            Max size: 2MB. Allowed: JPG, PNG, GIF, WebP
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveAvatar">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i> Delete Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="deleteAccountForm">
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">Warning: This action is irreversible!</h6>
                        <p class="mb-0 small">
                            All your data including notes, marketplace items, reviews, and favorites will be permanently deleted.
                        </p>
                    </div>
                    
                    <p>Please confirm your password to continue:</p>
                    
                    <input type="hidden" name="csrf_token" value="<?php echo Auth::csrfToken(); ?>">
                    <input type="hidden" name="action" value="delete_account">
                    
                    <div class="mb-3">
                        <label for="deletePassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="deletePassword" name="password" required>
                        <div class="form-text">Enter your current password to confirm account deletion.</div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="confirmDelete" required>
                        <label class="form-check-label" for="confirmDelete">
                            I understand that this action cannot be undone
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete My Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});

// Password match validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    const matchDiv = document.getElementById('passwordMatch');
    
    if (confirmPassword === '') {
        matchDiv.innerHTML = '';
        matchDiv.className = 'form-text';
    } else if (password === confirmPassword) {
        matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> Passwords match</span>';
        matchDiv.className = 'form-text text-success';
    } else {
        matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i> Passwords do not match</span>';
        matchDiv.className = 'form-text text-danger';
    }
});

// Avatar Upload Preview
document.getElementById('avatarInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('avatarPreview');
    const reader = new FileReader();
    
    reader.onload = function(e) {
        preview.src = e.target.result;
    };
    
    if (file) {
        reader.readAsDataURL(file);
    }
});

// Remove Avatar
document.getElementById('removeAvatar').addEventListener('click', function() {
    document.getElementById('avatarPreview').src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgZmlsbD0iIzFhNTZkYiIvPjx0ZXh0IHg9Ijc1IiB5PSI4MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjMwIiBmaWxsPSIjZmZmIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+PD90ZW1wbGF0ZQ==';
    document.getElementById('avatarInput').value = '';
});

// Avatar Upload Form
document.getElementById('avatarForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const saveBtn = document.getElementById('saveAvatar');
    const originalText = saveBtn.innerHTML;
    
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
    
    fetch('/profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update profile image on page
            const profileAvatar = document.getElementById('profileAvatar');
            if (profileAvatar) {
                profileAvatar.src = '/assets/uploads/profiles/' + data.filename + '?t=' + new Date().getTime();
            }
            
            // Show success message
            alert('Profile picture updated successfully!');
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('avatarModal')).hide();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    });
});

// Change Password Form
document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Updating...';
    
    fetch('/profile.php', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Password updated successfully!');
            this.reset();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Delete Account Form
document.getElementById('deleteAccountForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!confirm('Are you absolutely sure? This action cannot be undone!')) {
        return;
    }
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Deleting...';
    
    fetch('/profile.php', {
        method: 'POST',
        body: formData,
        headers: {
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Account deleted successfully. You will be logged out.');
            if (data.redirect) {
                window.location.href = data.redirect;
            }
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Export Data
document.getElementById('exportDataBtn').addEventListener('click', function() {
    if (confirm('This will generate a file with all your data. Continue?')) {
        alert('Data export requested. You will receive an email with download link when ready.');
        // In production, this would trigger an API call
    }
});

// Form validation for edit profile
document.getElementById('editProfileForm').addEventListener('submit', function(e) {
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    
    if (!firstName || !lastName) {
        e.preventDefault();
        alert('First name and last name are required.');
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
// Include footer
include __DIR__ . '/../templates/footer.php';
?>