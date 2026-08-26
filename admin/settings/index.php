<?php
/**
 * WEZO CAMPUS HUB - Admin Settings
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../../core/Config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';

use Core\Auth;
use Core\Database;
use Core\Session;

// Initialize and check admin access
Auth::init();
Auth::requireAdmin();

$db = Database::getInstance();
$user = Auth::user();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_general':
            $siteTitle = $_POST['site_title'];
            $siteDescription = $_POST['site_description'];
            $contactEmail = $_POST['contact_email'];
            $contactPhone = $_POST['contact_phone'];
            
            $db->query("UPDATE settings SET 
                site_title = ?, 
                site_description = ?, 
                contact_email = ?, 
                contact_phone = ? 
                WHERE id = 1", 
                [$siteTitle, $siteDescription, $contactEmail, $contactPhone]
            );
            Session::setFlash('success', 'General settings updated');
            break;
            
        case 'update_registration':
            $allowRegistration = isset($_POST['allow_registration']) ? 1 : 0;
            $requireEmailVerification = isset($_POST['require_email_verification']) ? 1 : 0;
            $defaultUserRole = $_POST['default_user_role'];
            
            $db->query("UPDATE settings SET 
                allow_registration = ?, 
                require_email_verification = ?, 
                default_user_role = ? 
                WHERE id = 1", 
                [$allowRegistration, $requireEmailVerification, $defaultUserRole]
            );
            Session::setFlash('success', 'Registration settings updated');
            break;
            
        case 'update_content':
            $autoApproveNotes = isset($_POST['auto_approve_notes']) ? 1 : 0;
            $autoApproveMarketplace = isset($_POST['auto_approve_marketplace']) ? 1 : 0;
            $maxFileSize = $_POST['max_file_size'];
            $allowedFileTypes = $_POST['allowed_file_types'];
            
            $db->query("UPDATE settings SET 
                auto_approve_notes = ?, 
                auto_approve_marketplace = ?, 
                max_file_size = ?, 
                allowed_file_types = ? 
                WHERE id = 1", 
                [$autoApproveNotes, $autoApproveMarketplace, $maxFileSize, $allowedFileTypes]
            );
            Session::setFlash('success', 'Content settings updated');
            break;
            
        case 'update_email':
            $smtpHost = $_POST['smtp_host'];
            $smtpPort = $_POST['smtp_port'];
            $smtpUsername = $_POST['smtp_username'];
            $smtpPassword = $_POST['smtp_password'];
            $smtpEncryption = $_POST['smtp_encryption'];
            $fromEmail = $_POST['from_email'];
            $fromName = $_POST['from_name'];
            
            $db->query("UPDATE settings SET 
                smtp_host = ?, 
                smtp_port = ?, 
                smtp_username = ?, 
                smtp_password = ?, 
                smtp_encryption = ?, 
                from_email = ?, 
                from_name = ? 
                WHERE id = 1", 
                [$smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $smtpEncryption, $fromEmail, $fromName]
            );
            Session::setFlash('success', 'Email settings updated');
            break;
            
        case 'test_email':
            $testEmail = $_POST['test_email'];
            // Send test email
            $subject = "Test Email from WEZO CAMPUS HUB";
            $message = "This is a test email sent from the WEZO CAMPUS HUB admin panel.";
            
            if (mail($testEmail, $subject, $message)) {
                Session::setFlash('success', 'Test email sent successfully');
            } else {
                Session::setFlash('error', 'Failed to send test email');
            }
            break;
            
        case 'clear_cache':
            // Clear cache directories
            $cacheDirs = ['/tmp', '/cache', '/uploads/cache'];
            foreach ($cacheDirs as $dir) {
                if (is_dir(__DIR__ . $dir)) {
                    array_map('unlink', glob(__DIR__ . $dir . "/*"));
                }
            }
            Session::setFlash('success', 'Cache cleared successfully');
            break;
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Get current settings
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

// Set page title
$pageTitle = "Settings - WEZO CAMPUS HUB";

// Include admin header
include '../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-2">
                <i class="fas fa-cog text-primary me-2"></i> Settings
            </h1>
            <p class="text-muted mb-0">Configure system settings and preferences</p>
        </div>
    </div>

    <?php if (Session::hasFlash('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?php echo Session::getFlash('success'); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Navigation -->
        <div class="col-lg-3">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Settings Menu</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#general" class="list-group-item list-group-item-action active" data-bs-toggle="tab">
                        <i class="fas fa-globe me-2"></i> General
                    </a>
                    <a href="#registration" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-user-plus me-2"></i> Registration
                    </a>
                    <a href="#content" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-file-alt me-2"></i> Content
                    </a>
                    <a href="#email" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-envelope me-2"></i> Email
                    </a>
                    <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-shield-alt me-2"></i> Security
                    </a>
                    <a href="#maintenance" class="list-group-item list-group-item-action" data-bs-toggle="tab">
                        <i class="fas fa-tools me-2"></i> Maintenance
                    </a>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">System Info</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <strong>PHP Version:</strong>
                            <span class="float-end"><?php echo phpversion(); ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>MySQL Version:</strong>
                            <span class="float-end"><?php echo $db->fetchColumn("SELECT VERSION()"); ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>Server Software:</strong>
                            <span class="float-end"><?php echo $_SERVER['SERVER_SOFTWARE']; ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>Memory Limit:</strong>
                            <span class="float-end"><?php echo ini_get('memory_limit'); ?></span>
                        </li>
                        <li class="mb-2">
                            <strong>Max Upload Size:</strong>
                            <span class="float-end"><?php echo ini_get('upload_max_filesize'); ?></span>
                        </li>
                        <li>
                            <strong>Session Save Path:</strong>
                            <span class="float-end"><?php echo session_save_path(); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="col-lg-9">
            <div class="tab-content">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">General Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_general">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Site Title</label>
                                        <input type="text" class="form-control" name="site_title" 
                                               value="<?php echo htmlspecialchars($settings['site_title'] ?? 'WEZO CAMPUS HUB'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Email</label>
                                        <input type="email" class="form-control" name="contact_email" 
                                               value="<?php echo htmlspecialchars($settings['contact_email'] ?? 'info@wezocampushub.com'); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Phone</label>
                                        <input type="tel" class="form-control" name="contact_phone" 
                                               value="<?php echo htmlspecialchars($settings['contact_phone'] ?? '+254 700 000000'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Default Currency</label>
                                        <select class="form-select" name="default_currency">
                                            <option value="KES" <?php echo ($settings['default_currency'] ?? 'KES') === 'KES' ? 'selected' : ''; ?>>Kenyan Shilling (KES)</option>
                                            <option value="USD" <?php echo ($settings['default_currency'] ?? 'KES') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Site Description</label>
                                    <textarea class="form-control" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? 'The ultimate campus platform for students'); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Footer Text</label>
                                    <input type="text" class="form-control" name="footer_text" 
                                           value="<?php echo htmlspecialchars($settings['footer_text'] ?? '&copy; ' . date('Y') . ' WEZO CAMPUS HUB. All rights reserved.'); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Registration Settings -->
                <div class="tab-pane fade" id="registration">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Registration Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_registration">
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="allow_registration" 
                                               id="allow_registration" value="1" 
                                               <?php echo ($settings['allow_registration'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_registration">
                                            Allow new user registration
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="require_email_verification" 
                                               id="require_email_verification" value="1" 
                                               <?php echo ($settings['require_email_verification'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="require_email_verification">
                                            Require email verification
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Default User Role</label>
                                        <select class="form-select" name="default_user_role">
                                            <option value="student" <?php echo ($settings['default_user_role'] ?? 'student') === 'student' ? 'selected' : ''; ?>>Student</option>
                                            <option value="tutor" <?php echo ($settings['default_user_role'] ?? 'student') === 'tutor' ? 'selected' : ''; ?>>Tutor</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Registration Approval</label>
                                        <select class="form-select" name="registration_approval">
                                            <option value="auto" <?php echo ($settings['registration_approval'] ?? 'auto') === 'auto' ? 'selected' : ''; ?>>Auto Approve</option>
                                            <option value="manual" <?php echo ($settings['registration_approval'] ?? 'auto') === 'manual' ? 'selected' : ''; ?>>Manual Approval</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Welcome Email Template</label>
                                    <textarea class="form-control" name="welcome_email" rows="5"><?php 
                                        echo htmlspecialchars($settings['welcome_email'] ?? "Welcome to WEZO CAMPUS HUB!\n\nThank you for joining our community. Your account has been successfully created.\n\nYou can now access all features including notes sharing, marketplace, and more.\n\nBest regards,\nWEZO CAMPUS HUB Team");
                                    ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Content Settings -->
                <div class="tab-pane fade" id="content">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Content Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_content">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="auto_approve_notes" 
                                                   id="auto_approve_notes" value="1" 
                                                   <?php echo ($settings['auto_approve_notes'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="auto_approve_notes">
                                                Auto-approve uploaded notes
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="auto_approve_marketplace" 
                                                   id="auto_approve_marketplace" value="1" 
                                                   <?php echo ($settings['auto_approve_marketplace'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="auto_approve_marketplace">
                                                Auto-approve marketplace items
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Maximum File Size (MB)</label>
                                        <input type="number" class="form-control" name="max_file_size" 
                                               value="<?php echo $settings['max_file_size'] ?? 10; ?>" min="1" max="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Allowed File Types</label>
                                        <input type="text" class="form-control" name="allowed_file_types" 
                                               value="<?php echo htmlspecialchars($settings['allowed_file_types'] ?? 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif'); ?>">
                                        <small class="text-muted">Separate with commas</small>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Notes per Page</label>
                                        <input type="number" class="form-control" name="notes_per_page" 
                                               value="<?php echo $settings['notes_per_page'] ?? 20; ?>" min="5" max="100">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Marketplace Items per Page</label>
                                        <input type="number" class="form-control" name="items_per_page" 
                                               value="<?php echo $settings['items_per_page'] ?? 20; ?>" min="5" max="100">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Content Moderation Guidelines</label>
                                    <textarea class="form-control" name="moderation_guidelines" rows="5"><?php 
                                        echo htmlspecialchars($settings['moderation_guidelines'] ?? "1. No offensive or inappropriate content\n2. No copyrighted material without permission\n3. No spam or advertisements\n4. Keep discussions respectful\n5. Report any violations to administrators");
                                    ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div class="tab-pane fade" id="email">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Email Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_email">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="smtp_host" 
                                               value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" name="smtp_port" 
                                               value="<?php echo $settings['smtp_port'] ?? 587; ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" name="smtp_username" 
                                               value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" name="smtp_password" 
                                               value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">SMTP Encryption</label>
                                        <select class="form-select" name="smtp_encryption">
                                            <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                            <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                            <option value="" <?php echo empty($settings['smtp_encryption']) ? 'selected' : ''; ?>>None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">From Email</label>
                                        <input type="email" class="form-control" name="from_email" 
                                               value="<?php echo htmlspecialchars($settings['from_email'] ?? 'noreply@wezocampushub.com'); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">From Name</label>
                                    <input type="text" class="form-control" name="from_name" 
                                           value="<?php echo htmlspecialchars($settings['from_name'] ?? 'WEZO CAMPUS HUB'); ?>">
                                </div>
                                
                                <hr>
                                
                                <h6 class="mb-3">Test Email Configuration</h6>
                                <div class="row">
                                    <div class="col-md-8">
                                        <input type="email" class="form-control" name="test_email" 
                                               placeholder="Enter email address to send test">
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" name="action" value="test_email" class="btn btn-outline-primary w-100">
                                            Send Test Email
                                        </button>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <button type="submit" class="btn btn-primary">Save Email Settings</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="tab-pane fade" id="security">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Security Settings</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_security">
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_https" 
                                               id="enable_https" value="1" 
                                               <?php echo ($settings['enable_https'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_https">
                                            Force HTTPS connections
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="enable_captcha" 
                                               id="enable_captcha" value="1" 
                                               <?php echo ($settings['enable_captcha'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_captcha">
                                            Enable CAPTCHA on registration/login
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Login Attempts Before Lockout</label>
                                        <input type="number" class="form-control" name="max_login_attempts" 
                                               value="<?php echo $settings['max_login_attempts'] ?? 5; ?>" min="1" max="20">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Lockout Duration (minutes)</label>
                                        <input type="number" class="form-control" name="lockout_duration" 
                                               value="<?php echo $settings['lockout_duration'] ?? 15; ?>" min="1" max="1440">
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Session Timeout (minutes)</label>
                                        <input type="number" class="form-control" name="session_timeout" 
                                               value="<?php echo $settings['session_timeout'] ?? 30; ?>" min="5" max="1440">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Password Expiry (days)</label>
                                        <input type="number" class="form-control" name="password_expiry" 
                                               value="<?php echo $settings['password_expiry'] ?? 90; ?>" min="0" max="365">
                                        <small class="text-muted">0 = never expires</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Security Headers</label>
                                    <textarea class="form-control" name="security_headers" rows="3"><?php 
                                        echo htmlspecialchars($settings['security_headers'] ?? "Content-Security-Policy: default-src 'self'\nX-Frame-Options: DENY\nX-Content-Type-Options: nosniff");
                                    ?></textarea>
                                    <small class="text-muted">One header per line</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Security Settings</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Settings -->
                <div class="tab-pane fade" id="maintenance">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Maintenance Tools</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-broom fa-2x text-primary mb-3"></i>
                                            <h5>Clear Cache</h5>
                                            <p class="text-muted small">Clear all cached files and data</p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="clear_cache">
                                                <button type="submit" class="btn btn-outline-primary" 
                                                        onclick="return confirm('Clear all cache?')">
                                                    Clear Cache
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-database fa-2x text-success mb-3"></i>
                                            <h5>Backup Database</h5>
                                            <p class="text-muted small">Create a database backup</p>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="backupDatabase()">
                                                Backup Now
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-robot fa-2x text-warning mb-3"></i>
                                            <h5>System Check</h5>
                                            <p class="text-muted small">Run system diagnostics</p>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="runSystemCheck()">
                                                Run Check
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-file-alt fa-2x text-info mb-3"></i>
                                            <h5>System Logs</h5>
                                            <p class="text-muted small">View system logs</p>
                                            <a href="/admin/tools/logs.php" class="btn btn-outline-info">
                                                View Logs
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-history fa-2x text-secondary mb-3"></i>
                                            <h5>Activity Logs</h5>
                                            <p class="text-muted small">View user activity logs</p>
                                            <a href="/admin/logs/" class="btn btn-outline-secondary">
                                                View Activity
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-4">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                                            <h5>Emergency Mode</h5>
                                            <p class="text-muted small">Enable maintenance mode</p>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="toggle_maintenance">
                                                <input type="hidden" name="maintenance_mode" 
                                                       value="<?php echo ($settings['maintenance_mode'] ?? 0) ? '0' : '1'; ?>">
                                                <button type="submit" class="btn btn-outline-danger" 
                                                        onclick="return confirm('Toggle maintenance mode?')">
                                                    <?php echo ($settings['maintenance_mode'] ?? 0) ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3">System Information</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th>Disk Space</th>
                                            <td>
                                                <?php
                                                $total = disk_total_space('/');
                                                $free = disk_free_space('/');
                                                $used = $total - $free;
                                                $percent = round(($used / $total) * 100, 2);
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo $percent > 90 ? 'danger' : ($percent > 70 ? 'warning' : 'success'); ?>" 
                                                         style="width: <?php echo $percent; ?>%">
                                                        <?php echo $percent; ?>%
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    Used: <?php echo round($used / (1024*1024*1024), 2); ?>GB / 
                                                    Total: <?php echo round($total / (1024*1024*1024), 2); ?>GB
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Memory Usage</th>
                                            <td>
                                                <?php
                                                $memoryUsage = memory_get_usage(true);
                                                $memoryLimit = ini_get('memory_limit');
                                                $percent = round(($memoryUsage / ($memoryLimit == '-1' ? $memoryUsage * 2 : convertToBytes($memoryLimit))) * 100, 2);
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-<?php echo $percent > 90 ? 'danger' : ($percent > 70 ? 'warning' : 'success'); ?>" 
                                                         style="width: <?php echo $percent; ?>%">
                                                        <?php echo $percent; ?>%
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    Used: <?php echo round($memoryUsage / (1024*1024), 2); ?>MB / 
                                                    Limit: <?php echo $memoryLimit; ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Last Backup</th>
                                            <td>
                                                <?php
                                                $backupDir = __DIR__ . '/../../backups/';
                                                $latestBackup = '';
                                                if (is_dir($backupDir)) {
                                                    $files = glob($backupDir . '*.sql');
                                                    if (!empty($files)) {
                                                        $latestBackup = basename(max($files));
                                                    }
                                                }
                                                echo $latestBackup ?: 'No backups found';
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Last System Check</th>
                                            <td>
                                                <?php echo $settings['last_system_check'] ?? 'Never'; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function backupDatabase() {
    if (confirm('Create a database backup? This may take a moment.')) {
        fetch('/admin/tools/backup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Backup created successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function runSystemCheck() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Checking...';
    
    fetch('/admin/tools/system-check.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let message = 'System Check Results:\n\n';
                data.results.forEach(result => {
                    const status = result.status === 'OK' ? '✅' : result.status === 'WARNING' ? '⚠️' : '❌';
                    message += `${status} ${result.test}: ${result.message}\n`;
                });
                alert(message);
            } else {
                alert('Error running system check: ' + data.message);
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
}

// Helper function to convert memory limit string to bytes
function convertToBytes(memoryLimit) {
    const unit = memoryLimit.slice(-1).toUpperCase();
    const value = parseInt(memoryLimit.slice(0, -1));
    
    switch (unit) {
        case 'G': return value * 1024 * 1024 * 1024;
        case 'M': return value * 1024 * 1024;
        case 'K': return value * 1024;
        default: return parseInt(memoryLimit);
    }
}

// Tab switching
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.list-group-item').forEach(item => {
            item.classList.remove('active');
        });
        this.classList.add('active');
    });
});
</script>

<?php
// Include admin footer
include '../templates/footer.php';
?>