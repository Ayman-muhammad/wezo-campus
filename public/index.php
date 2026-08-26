<?php
/**
 * WEZO CAMPUS HUB - Main Dashboard
 * Powered by AYGLOBE INC
 */

// Start session and include autoloader
// At the top of your PHP files:
require_once __DIR__ . '/../core/autoload.php';
require_once __DIR__ . '/../core/helpers/functions.php';

use Core\Auth;
use Core\Database;
use Core\Session;

// Initialize
try {
    Auth::init();
    $db = Database::getInstance();
    $user = Auth::user();
    $isLoggedIn = Auth::isLoggedIn();
} catch (Exception $e) {
    // If database connection fails, show maintenance page
    die('<h2>Database Connection Error</h2><p>Please run the installation script: <a href="/install.php">install.php</a></p>');
}

// Rest of your index.php continues...

// Set page title
$pageTitle = "Dashboard - WEZO CAMPUS HUB";

// Get dashboard statistics with fallback
$stats = [];
try {
    // Check if dashboard_stats table exists first
    $tableExists = $db->fetchColumn("SHOW TABLES LIKE 'dashboard_stats'");
    
    if ($tableExists) {
        $stats = $db->fetch("SELECT * FROM dashboard_stats ORDER BY stats_date DESC LIMIT 1");
    }
    
    // If no stats found, create default stats
    if (!$stats) {
        $stats = [
            'total_users' => 0,
            'total_notes' => 0,
            'total_marketplace_items' => 0,
            'total_hostels' => 0,
            'total_resources' => 0,
            'active_listings' => 0,
            'total_downloads' => 0,
            'total_revenue' => 0.00,
            'total_students' => 0, // Added for compatibility
            'active_items' => 0    // Added for compatibility
        ];
        
        // Count actual data if tables exist
        $tables = $db->fetchAll("SHOW TABLES");
        $tableList = array_column($tables, 0);
        
        if (in_array('users', $tableList)) {
            $stats['total_users'] = $db->fetchColumn("SELECT COUNT(*) FROM users");
            $stats['total_students'] = $db->fetchColumn("SELECT COUNT(*) FROM users WHERE role IN ('student', 'verified_student')");
        }
        
        if (in_array('notes', $tableList)) {
            $stats['total_notes'] = $db->fetchColumn("SELECT COUNT(*) FROM notes WHERE is_approved = 1");
        }
        
        if (in_array('marketplace_items', $tableList)) {
            $stats['total_marketplace_items'] = $db->fetchColumn("SELECT COUNT(*) FROM marketplace_items");
            $stats['active_items'] = $db->fetchColumn("SELECT COUNT(*) FROM marketplace_items WHERE status = 'active' AND is_approved = 1");
        }
        
        if (in_array('hostels', $tableList)) {
            $stats['total_hostels'] = $db->fetchColumn("SELECT COUNT(*) FROM hostels WHERE is_approved = 1");
        }
    }
} catch (Exception $e) {
    // If any query fails, use default stats
    $stats = [
        'total_users' => 0,
        'total_notes' => 0,
        'total_marketplace_items' => 0,
        'total_hostels' => 0,
        'total_resources' => 0,
        'active_listings' => 0,
        'total_downloads' => 0,
        'total_revenue' => 0.00,
        'total_students' => 0,
        'active_items' => 0
    ];
}

// Get recent notes with fallback
$recentNotes = [];
try {
    $recentNotes = $db->fetchAll("
        SELECT n.*, u.username, u.profile_pic, c.name as category_name 
        FROM notes n 
        LEFT JOIN users u ON n.user_id = u.id 
        LEFT JOIN note_categories c ON n.category_id = c.id 
        WHERE n.is_approved = 1 
        ORDER BY n.created_at DESC 
        LIMIT 6
    ");
} catch (Exception $e) {
    // If query fails, leave empty array
    $recentNotes = [];
}

// Get featured marketplace items with fallback
$featuredItems = [];
try {
    $featuredItems = $db->fetchAll("
        SELECT m.*, u.username, c.name as category_name 
        FROM marketplace_items m 
        LEFT JOIN users u ON m.user_id = u.id 
        LEFT JOIN marketplace_categories c ON m.category_id = c.id 
        WHERE m.status = 'active' AND m.is_approved = 1 
        ORDER BY m.view_count DESC 
        LIMIT 6
    ");
} catch (Exception $e) {
    // If query fails, leave empty array
    $featuredItems = [];
}

// Get featured hostels with fallback
$featuredHostels = [];
try {
    $featuredHostels = $db->fetchAll("
        SELECT * FROM hostels 
        WHERE is_approved = 1 AND (is_featured = 1 OR is_featured IS NULL)
        ORDER BY rating DESC, created_at DESC 
        LIMIT 4
    ");
} catch (Exception $e) {
    // If query fails, leave empty array
    $featuredHostels = [];
}

// Check if header template exists, if not use minimal header
$headerPath = __DIR__ . '/../templates/header.php';
$footerPath = __DIR__ . '/../templates/footer.php';

if (!file_exists($headerPath)) {
    // Use minimal header
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($pageTitle); ?></title>
        <!-- Bootstrap 5 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary-color: #1A56DB;
                --secondary-color: #10B981;
                --accent-color: #F59E0B;
            }
            body {
                background-color: #f8f9fa;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            .stat-card {
                transition: transform 0.3s;
            }
            .stat-card:hover {
                transform: translateY(-5px);
            }
            .navbar-brand {
                font-weight: bold;
                color: var(--primary-color) !important;
            }
            .btn-primary {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
            }
            .btn-success {
                background-color: var(--secondary-color);
                border-color: var(--secondary-color);
            }
            .text-primary {
                color: var(--primary-color) !important;
            }
            .text-success {
                color: var(--secondary-color) !important;
            }
            .text-warning {
                color: var(--accent-color) !important;
            }
            .welcome-banner {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                border-radius: 10px;
            }
            .card {
                border: none;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            }
            .card-header {
                background-color: white;
                border-bottom: 2px solid #f0f0f0;
                border-radius: 10px 10px 0 0 !important;
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid">
                <a class="navbar-brand" href="/">
                    <i class="fas fa-graduation-cap me-2"></i>WEZO CAMPUS HUB
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <?php if ($isLoggedIn): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user me-1"></i> 
                                    <?php echo htmlspecialchars($user['first_name'] ?? $user['username']); ?>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="/profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                                </ul>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="/login.php"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
                            </li>
                            <li class="nav-item">
                                <a class="btn btn-primary ms-2" href="/register.php">Join Free</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    <?php
} else {
    include $headerPath;
}
?>

<div class="container-fluid">
    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card welcome-banner text-white shadow">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-6 mb-2">
                                <?php if ($isLoggedIn): ?>
                                Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? $user['username']); ?>! 👋
                                <?php else: ?>
                                Welcome to WEZO CAMPUS HUB! 🎓
                                <?php endif; ?>
                            </h1>
                            <p class="lead mb-0">
                                The ultimate student ecosystem powered by AYGLOBE INC
                            </p>
                            <p class="small mb-0 opacity-75">Founded by Ayman Muhammad</p>
                            <?php if (!$isLoggedIn): ?>
                            <div class="mt-3">
                                <a href="register.php" class="btn btn-light btn-lg me-2">
                                    <i class="fas fa-user-plus me-1"></i> Join Now
                                </a>
                                <a href="login.php" class="btn btn-outline-light btn-lg">
                                    <i class="fas fa-sign-in-alt me-1"></i> Login
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-center d-none d-md-block">
                            <i class="fas fa-graduation-cap fa-8x opacity-25"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <?php if ($isLoggedIn): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-4">
                        <i class="fas fa-chart-line text-primary me-2"></i> Campus Overview
                    </h5>
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card text-center p-3 border rounded">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                </div>
                                <h3 class="stat-number"><?php echo number_format($stats['total_students'] ?? 0); ?></h3>
                                <p class="stat-label text-muted mb-0">Total Students</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card text-center p-3 border rounded">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-book fa-2x text-success"></i>
                                </div>
                                <h3 class="stat-number"><?php echo number_format($stats['total_notes'] ?? 0); ?></h3>
                                <p class="stat-label text-muted mb-0">Study Notes</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card text-center p-3 border rounded">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-store fa-2x text-warning"></i>
                                </div>
                                <h3 class="stat-number"><?php echo number_format($stats['active_items'] ?? 0); ?></h3>
                                <p class="stat-label text-muted mb-0">Marketplace Items</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="stat-card text-center p-3 border rounded">
                                <div class="stat-icon mb-2">
                                    <i class="fas fa-bed fa-2x text-info"></i>
                                </div>
                                <h3 class="stat-number"><?php echo number_format($stats['total_hostels'] ?? 0); ?></h3>
                                <p class="stat-label text-muted mb-0">Hostels Listed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left Column: Quick Actions & Recent Notes -->
        <div class="col-lg-8">
            <?php if ($isLoggedIn): ?>
            <!-- Quick Actions -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bolt text-warning me-2"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-sm-3 col-6">
                            <a href="/notes/create.php" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-upload fa-2x mb-2"></i>
                                <span>Upload Notes</span>
                            </a>
                        </div>
                        <div class="col-sm-3 col-6">
                            <a href="/marketplace/post.php" class="btn btn-outline-success w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-tag fa-2x mb-2"></i>
                                <span>Sell Item</span>
                            </a>
                        </div>
                        <div class="col-sm-3 col-6">
                            <a href="/hostels/" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-bed fa-2x mb-2"></i>
                                <span>Find Hostel</span>
                            </a>
                        </div>
                        <div class="col-sm-3 col-6">
                            <a href="/notes/" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-search fa-2x mb-2"></i>
                                <span>Find Notes</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Study Notes -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-book text-primary me-2"></i> Recent Study Notes
                    </h5>
                    <a href="/notes/" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentNotes)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-book fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No study notes available yet.</p>
                        <?php if ($isLoggedIn): ?>
                        <a href="/notes/create.php" class="btn btn-primary">Upload First Note</a>
                        <?php else: ?>
                        <a href="/login.php" class="btn btn-primary">Login to Upload</a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($recentNotes as $note): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($note['category_name'] ?? 'Uncategorized'); ?></span>
                                            <h6 class="card-title mb-1">
                                                <a href="/notes/view.php?id=<?php echo $note['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($note['title']); ?>
                                                </a>
                                            </h6>
                                        </div>
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-download me-1"></i> <?php echo $note['download_count'] ?? 0; ?>
                                        </span>
                                    </div>
                                    <p class="card-text small text-muted mb-2">
                                        <?php 
                                        $description = $note['description'] ?? '';
                                        echo strlen($description) > 100 ? substr($description, 0, 100) . '...' : $description; 
                                        ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($note['username'] ?? 'Anonymous'); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="far fa-clock me-1"></i> 
                                            <?php echo isset($note['created_at']) ? date('M d, Y', strtotime($note['created_at'])) : 'Recently'; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Featured Marketplace Items -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-store text-success me-2"></i> Featured Marketplace Items
                    </h5>
                    <a href="/marketplace/" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($featuredItems)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-store fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No marketplace items available yet.</p>
                        <?php if ($isLoggedIn): ?>
                        <a href="/marketplace/post.php" class="btn btn-success">Sell First Item</a>
                        <?php else: ?>
                        <a href="/login.php" class="btn btn-success">Login to Sell</a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($featuredItems as $item): 
                            $images = json_decode($item['images'] ?? '[]', true) ?: [];
                            $firstImage = !empty($images) ? $images[0] : 'default-item.jpg';
                        ?>
                        <div class="col-md-4 col-sm-6 mb-3">
                            <div class="card h-100 border">
                                <div class="position-relative">
                                    <div class="marketplace-image" style="height: 150px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                        <?php if ($firstImage !== 'default-item.jpg'): ?>
                                        <img src="/assets/uploads/marketplace/<?php echo htmlspecialchars($firstImage); ?>" 
                                             class="img-fluid" alt="<?php echo htmlspecialchars($item['title']); ?>"
                                             style="max-height: 150px; object-fit: cover;">
                                        <?php else: ?>
                                        <i class="fas fa-store fa-3x text-muted"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-<?php echo ($item['status'] ?? 'inactive') == 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($item['status'] ?? 'inactive'); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="/marketplace/item.php?id=<?php echo $item['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($item['category_name'] ?? 'General'); ?>
                                        </span>
                                        <h5 class="text-success mb-0">
                                            KSh <?php echo number_format($item['price'] ?? 0, 2); ?>
                                        </h5>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($item['location'] ?? 'Campus'); ?>
                                    </small>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($item['username'] ?? 'User'); ?>
                                        </small>
                                        <small class="text-muted">
                                            <i class="far fa-eye me-1"></i> <?php echo $item['view_count'] ?? 0; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Featured Hostels & Quick Links -->
        <div class="col-lg-4">
            <!-- Featured Hostels -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bed text-info me-2"></i> Featured Hostels
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($featuredHostels)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No hostels available yet.</p>
                    </div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($featuredHostels as $hostel): ?>
                        <a href="/hostels/details.php?id=<?php echo $hostel['id']; ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($hostel['name']); ?></h6>
                                <small class="text-success">KSh <?php echo number_format($hostel['price_per_month'] ?? 0); ?>/month</small>
                            </div>
                            <p class="mb-1 small text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($hostel['location'] ?? 'Unknown'); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?php 
                                    $rating = $hostel['rating'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++): 
                                    ?>
                                    <i class="fas fa-star <?php echo $i <= floor($rating) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-1">(<?php echo $hostel['review_count'] ?? 0; ?>)</small>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-door-open me-1"></i> <?php echo $hostel['available_rooms'] ?? 0; ?> rooms left
                                </small>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center">
                    <a href="hostels/index.php" class="btn btn-sm btn-outline-info">Browse All Hostels</a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-link text-secondary me-2"></i> Quick Links
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="resources/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-alt text-primary me-2"></i> Study Resources
                        </a>
                        <a href="notes/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-book text-success me-2"></i> Study Notes
                        </a>
                        <a href="marketplace/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-store text-warning me-2"></i> Marketplace
                        </a>
                        <a href="hostels/index.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-bed text-info me-2"></i> Hostels
                        </a>
                        <?php if ($isLoggedIn): ?>
                        <a href="profile.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-circle text-dark me-2"></i> My Profile
                        </a>
                        <?php endif; ?>
                        <?php if ($isLoggedIn && isset($user['role']) && in_array($user['role'], ['admin', 'moderator'])): ?>
                        <a href="/admin/index.php" class="list-group-item list-group-item-action bg-light">
                            <i class="fas fa-shield-alt text-dark me-2"></i> Admin Panel
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Campus Announcements -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-bullhorn text-warning me-2"></i> Campus Announcements
                    </h5>
                </div>
                <div class="card-body">
                    <div class="announcement-item mb-3 pb-3 border-bottom">
                        <h6 class="mb-1">Welcome to WEZO CAMPUS HUB</h6>
                        <p class="small text-muted mb-1">Your ultimate student ecosystem is now live!</p>
                        <small class="text-muted"><i class="far fa-clock me-1"></i> Just now</small>
                    </div>
                    <div class="announcement-item mb-3 pb-3 border-bottom">
                        <h6 class="mb-1">Share Your Notes</h6>
                        <p class="small text-muted mb-1">Earn while helping fellow students succeed.</p>
                        <small class="text-muted"><i class="far fa-clock me-1"></i> 1 day ago</small>
                    </div>
                    <div class="announcement-item">
                        <h6 class="mb-1">Safe Transactions</h6>
                        <p class="small text-muted mb-1">Always meet in safe, public places when buying/selling.</p>
                        <small class="text-muted"><i class="far fa-clock me-1"></i> 2 days ago</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Founder's Message -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-tie fa-3x text-primary me-3"></i>
                        </div>
                        <div>
                            <h5 class="card-title">Message from the Founder</h5>
                            <p class="card-text">
                                "Welcome to WEZO CAMPUS HUB! As a student myself, I understand the challenges we face. 
                                That's why I created this platform - to empower students with tools, resources, 
                                and opportunities to succeed both academically and financially. 
                                Let's build a supportive community together!"
                            </p>
                            <p class="card-text mb-0">
                                <strong>Ayman Muhammad</strong><br>
                                <small class="text-muted">Founder & CEO, AYGLOBE INC</small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
if (file_exists($footerPath)) {
    include $footerPath;
} else {
    // Minimal footer
    ?>
    <footer class="bg-white border-top mt-5 py-4">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <h5>WEZO CAMPUS HUB</h5>
                    <p class="text-muted">The Ultimate Student Ecosystem</p>
                    <p>Powered by <strong>AYGLOBE INC</strong><br>
                    Founded by Ayman Muhammad</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="text-muted">
                        &copy; <?php echo date('Y'); ?> WEZO CAMPUS HUB. All rights reserved.<br>
                        <small>For support: contact@ayglobe.com</small>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Simple JavaScript for interactivity
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effects to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.1)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.boxShadow = 'none';
            });
        });
        
        // Show alert if not logged in
        <?php if (!$isLoggedIn): ?>
        const quickActions = document.querySelector('.quick-actions');
        if (quickActions) {
            quickActions.addEventListener('click', function(e) {
                if (e.target.closest('a')) {
                    alert('Please login to access this feature!');
                    e.preventDefault();
                }
            });
        }
        <?php endif; ?>
    });
    </script>
    </body>
    </html>
    <?php
}