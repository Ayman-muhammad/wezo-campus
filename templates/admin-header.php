<?php
/**
 * WEZO CAMPUS HUB - Admin Template Header
 * Powered by AYGLOBE INC
 */
if (!isset($pageTitle)) {
    $pageTitle = "Admin Panel - WEZO CAMPUS HUB";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Admin Custom CSS -->
    <link href="/admin/assets/css/admin.css" rel="stylesheet">
    
    <!-- Custom theme colors -->
    <style>
        :root {
            --primary-color: #1A56DB;
            --secondary-color: #10B981;
            --accent-color: #F59E0B;
            --sidebar-width: 250px;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Inter', sans-serif;
        }
    </style>
    
    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="<?php echo Core\Auth::csrfToken(); ?>">
</head>
<body id="page-top">
    <!-- Page Wrapper -->
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="sidebar-brand-text mx-3">
                    <div>WEZO CAMPUS</div>
                    <small>Admin Panel</small>
                </div>
            </a>
            
            <!-- Divider -->
            <hr class="sidebar-divider my-0">
            
            <!-- Nav Item - Dashboard -->
            <li class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <!-- Divider -->
            <hr class="sidebar-divider">
            
            <!-- Heading -->
            <div class="sidebar-heading">
                User Management
            </div>
            
            <!-- Users -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/users/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/users/">
                    <i class="fas fa-fw fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            
            <!-- Roles & Permissions -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseRoles">
                    <i class="fas fa-fw fa-user-shield"></i>
                    <span>Roles & Permissions</span>
                </a>
                <div id="collapseRoles" class="collapse">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="/admin/roles/">Manage Roles</a>
                        <a class="collapse-item" href="/admin/permissions/">Permissions</a>
                    </div>
                </div>
            </li>
            
            <!-- Divider -->
            <hr class="sidebar-divider">
            
            <!-- Heading -->
            <div class="sidebar-heading">
                Content Management
            </div>
            
            <!-- Content Review -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/content-review/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/content-review/">
                    <i class="fas fa-fw fa-check-circle"></i>
                    <span>Content Review</span>
                    <?php if (isset($stats) && ($stats['pending_notes'] + $stats['pending_items']) > 0): ?>
                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                        <?php echo $stats['pending_notes'] + $stats['pending_items']; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Notes Management -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/notes/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/notes/">
                    <i class="fas fa-fw fa-file-alt"></i>
                    <span>Notes</span>
                </a>
            </li>
            
            <!-- Marketplace -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/marketplace/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/marketplace/">
                    <i class="fas fa-fw fa-store"></i>
                    <span>Marketplace</span>
                </a>
            </li>
            
            <!-- Hostels -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/hostels/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/hostels/">
                    <i class="fas fa-fw fa-home"></i>
                    <span>Hostels</span>
                </a>
            </li>
            
            <!-- Divider -->
            <hr class="sidebar-divider">
            
            <!-- Heading -->
            <div class="sidebar-heading">
                System
            </div>
            
            <!-- Reports -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/reports/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/reports/">
                    <i class="fas fa-fw fa-flag"></i>
                    <span>Reports</span>
                    <?php if (isset($stats) && $stats['pending_reports'] > 0): ?>
                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                        <?php echo $stats['pending_reports']; ?>
                    </span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Activity Logs -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/logs/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/logs/">
                    <i class="fas fa-fw fa-history"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            
            <!-- Settings -->
            <li class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/settings/') !== false ? 'active' : ''; ?>">
                <a class="nav-link" href="/admin/settings/">
                    <i class="fas fa-fw fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <!-- Tools -->
            <li class="nav-item">
                <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#collapseTools">
                    <i class="fas fa-fw fa-tools"></i>
                    <span>Tools</span>
                </a>
                <div id="collapseTools" class="collapse">
                    <div class="bg-white py-2 collapse-inner rounded">
                        <a class="collapse-item" href="/admin/tools/backup.php">Backup</a>
                        <a class="collapse-item" href="/admin/tools/logs.php">System Logs</a>
                        <a class="collapse-item" href="/admin/tools/maintenance.php">Maintenance</a>
                    </div>
                </div>
            </li>
            
            <!-- Divider -->
            <hr class="sidebar-divider d-none d-md-block">
            
            <!-- Sidebar Toggler -->
            <div class="text-center d-none d-md-inline">
                <button class="rounded-circle border-0" id="sidebarToggle"></button>
            </div>
        </ul>
        <!-- End of Sidebar -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content -->
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    
                    <!-- Topbar Brand -->
                    <div class="d-none d-sm-inline-block">
                        <span class="h5 mb-0 text-gray-800">
                            <img src="/assets/images/logo.png" alt="WEZO CAMPUS HUB" height="30" class="me-2">
                            <span class="text-primary fw-bold">WEZO</span> 
                            <span class="text-secondary">CAMPUS HUB</span>
                            <small class="text-muted ms-2">Admin</small>
                        </span>
                    </div>
                    
                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">
                        <!-- Alerts Dropdown -->
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-bell fa-fw"></i>
                                <!-- Counter - Alerts -->
                                <span class="badge badge-danger badge-counter">3+</span>
                            </a>
                            <!-- Dropdown - Alerts -->
                            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="alertsDropdown">
                                <h6 class="dropdown-header">
                                    Alerts Center
                                </h6>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="mr-3">
                                        <div class="icon-circle bg-primary">
                                            <i class="fas fa-file-alt text-white"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="small text-gray-500">December 12, 2023</div>
                                        <span class="font-weight-bold">5 new notes awaiting approval</span>
                                    </div>
                                </a>
                                <a class="dropdown-item text-center small text-gray-500" href="/admin/content-review/">
                                    Show All Alerts
                                </a>
                            </div>
                        </li>
                        
                        <!-- Messages Dropdown -->
                        <li class="nav-item dropdown no-arrow mx-1">
                            <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-envelope fa-fw"></i>
                                <!-- Counter - Messages -->
                                <span class="badge badge-danger badge-counter">7</span>
                            </a>
                            <!-- Dropdown - Messages -->
                            <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="messagesDropdown">
                                <h6 class="dropdown-header">
                                    Message Center
                                </h6>
                                <a class="dropdown-item d-flex align-items-center" href="#">
                                    <div class="dropdown-list-image mr-3">
                                        <img class="rounded-circle" src="/uploads/avatars/default.jpg"
                                            alt="..." style="width: 40px; height: 40px;">
                                    </div>
                                    <div>
                                        <div class="text-truncate">I have an issue with my account...</div>
                                        <div class="small text-gray-500">John Doe · 58m</div>
                                    </div>
                                </a>
                                <a class="dropdown-item text-center small text-gray-500" href="#">Read More Messages</a>
                            </div>
                        </li>
                        
                        <!-- User Dropdown -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($_SESSION['user']['first_name'] ?? 'Admin'); ?>
                                </span>
                                <img class="img-profile rounded-circle" src="/uploads/avatars/<?php echo $_SESSION['user']['avatar'] ?? 'default.jpg'; ?>">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="/profile.php">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <a class="dropdown-item" href="/admin/settings/">
                                    <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Settings
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid">