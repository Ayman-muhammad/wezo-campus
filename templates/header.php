<?php
/**
 * WEZO CAMPUS HUB Header Template
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Session.php';

use Core\Auth;
use Core\Session;

// Initialize auth
Auth::init();

// Check maintenance mode
if (Core\Config::isFeatureEnabled('maintenance') && !Auth::isAdmin()) {
    header('HTTP/1.1 503 Service Unavailable');
    include __DIR__ . '/maintenance.php';
    exit;
}

$currentUser = Auth::user();
$isLoggedIn = Auth::isLoggedIn();
$isAdmin = Auth::isAdmin();
$isModerator = Auth::isModerator();
$isVerified = Auth::isVerifiedStudent();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="WEZO CAMPUS HUB - The Ultimate Student Ecosystem">
    <meta name="author" content="AYGLOBE INC">
    <meta name="keywords" content="student, campus, notes, marketplace, hostel, university">
    
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>WEZO CAMPUS HUB</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    
    <!-- DataTables CSS (for admin pages) -->
    <?php if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false): ?>
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <?php endif; ?>
    
    <!-- Custom theme colors -->
    <style>
        :root {
            --primary-color: <?php echo Core\Config::PRIMARY_COLOR; ?>;
            --secondary-color: <?php echo Core\Config::SECONDARY_COLOR; ?>;
            --accent-color: <?php echo Core\Config::ACCENT_COLOR; ?>;
        }
    </style>
    
    <!-- CSRF Token for AJAX requests -->
    <meta name="csrf-token" content="<?php echo Auth::csrfToken(); ?>">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
        <div class="container">
            <!-- Logo and Brand -->
            <a class="navbar-brand d-flex align-items-center" href="/">
                <div class="brand-logo me-2">
                    <i class="fas fa-graduation-cap fa-2x"></i>
                </div>
                <div>
                    <h1 class="h5 mb-0 fw-bold">WEZO CAMPUS HUB</h1>
                    <small class="text-light opacity-75">Powered by AYGLOBE INC</small>
                </div>
            </a>
            
            <!-- Mobile Toggle Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                            <i class="fas fa-home me-1"></i> Home
                        </a>
                    </li>
                    
                    <?php if ($isLoggedIn): ?>
                    <!-- Notes -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-book me-1"></i> Study
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/notes/index.php"><i class="fas fa-sticky-note me-2"></i> Notes</a></li>
                            <li><a class="dropdown-item" href="/resources/index.php"><i class="fas fa-file-alt me-2"></i> Resources</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/notes/create.php"><i class="fas fa-plus-circle me-2"></i> Upload Notes</a></li>
                        </ul>
                    </li>
                    
                    <!-- Marketplace -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-store me-1"></i> Marketplace
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/marketplace/index.php"><i class="fas fa-shopping-basket me-2"></i> Browse Items</a></li>
                            <li><a class="dropdown-item" href="/marketplace/categories.php"><i class="fas fa-tags me-2"></i> Categories</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/marketplace/post.php"><i class="fas fa-plus-circle me-2"></i> Sell Item</a></li>
                        </ul>
                    </li>
                    
                    <!-- Hostels -->
                    <li class="nav-item">
                        <a class="nav-link" href="/hostels/index.php">
                            <i class="fas fa-bed me-1"></i> Hostels
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- About -->
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">
                            <i class="fas fa-info-circle me-1"></i> About
                        </a>
                    </li>
                </ul>
                
                <!-- User Actions -->
                <div class="d-flex align-items-center">
                    <!-- Search Form -->
                    <form class="d-none d-lg-flex me-3" action="/search.php" method="GET">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" placeholder="Search..." name="q" aria-label="Search">
                            <button class="btn btn-outline-light" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <?php if ($isLoggedIn): ?>
                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown">
                            <?php if (!empty($currentUser['profile_pic']) && $currentUser['profile_pic'] != 'default.png'): ?>
                            <img src="/assets/uploads/profiles/<?php echo htmlspecialchars($currentUser['profile_pic']); ?>" 
                                 alt="Profile" class="rounded-circle me-2" width="30" height="30">
                            <?php else: ?>
                            <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center me-2" 
                                 style="width: 30px; height: 30px;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <?php endif; ?>
                            <span class="d-none d-md-inline"><?php echo htmlspecialchars($currentUser['first_name']); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user-circle me-2"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="dashboard.php">
                                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            
                            <?php if ($isAdmin || $isModerator): ?>
                            <li>
                                <a class="dropdown-item text-warning" href="/admin/">
                                    <i class="fas fa-shield-alt me-2"></i> Admin Panel
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <?php endif; ?>
                            
                            <li>
                                <a class="dropdown-item" href="settings.php">
                                    <i class="fas fa-cog me-2"></i> Settings
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item text-danger" href="/logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Notifications Bell -->
                    <div class="dropdown ms-2">
                        <button class="btn btn-outline-light position-relative" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                3
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-0" style="min-width: 300px;">
                            <div class="dropdown-header bg-light">
                                <strong>Notifications</strong>
                                <a href="#" class="float-end text-decoration-none small">Mark all as read</a>
                            </div>
                            <div class="notification-list" style="max-height: 300px; overflow-y: auto;">
                                <!-- Notifications will be loaded here -->
                            </div>
                            <div class="dropdown-footer text-center p-2 border-top">
                                <a href="/notifications.php" class="text-decoration-none small">View all notifications</a>
                            </div>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Login/Register Buttons -->
                    <a href="login.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-light">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php if (Session::hasFlash('success')): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo Session::getFlash('success'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (Session::hasFlash('error')): ?>
    <div class="container mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo Session::getFlash('error'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (Session::hasFlash('warning')): ?>
    <div class="container mt-3">
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo Session::getFlash('warning'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (Session::hasFlash('info')): ?>
    <div class="container mt-3">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i> <?php echo Session::getFlash('info'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="py-4">