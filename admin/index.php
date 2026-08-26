<?php
/**
 * WEZO CAMPUS HUB - Admin Dashboard
 * Powered by AYGLOBE INC
 */
require_once __DIR__ . '/../core/Config.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';

use Core\Auth;
use Core\Database;
use Core\Session;

// Initialize and check admin access
Auth::init();
Auth::requireAdmin();

$db = Database::getInstance();
$user = Auth::user();

// Get dashboard statistics
$stats = $db->fetch("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM notes WHERE is_approved = 1) as approved_notes,
        (SELECT COUNT(*) FROM notes WHERE is_approved = 0) as pending_notes,
        (SELECT COUNT(*) FROM marketplace_items WHERE status = 'active' AND is_approved = 1) as active_items,
        (SELECT COUNT(*) FROM marketplace_items WHERE is_approved = 0) as pending_items,
        (SELECT COUNT(*) FROM hostels WHERE is_approved = 1) as approved_hostels,
        (SELECT COUNT(*) FROM hostels WHERE is_approved = 0) as pending_hostels,
        (SELECT COUNT(*) FROM reports WHERE status = 'pending') as pending_reports,
        (SELECT SUM(price) FROM marketplace_items WHERE status = 'sold' AND DATE(updated_at) = CURDATE()) as today_revenue,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as today_registrations
");

// Get recent activities
$recentActivities = $db->fetchAll("
    SELECT a.*, u.username 
    FROM activity_logs a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
");

// Get pending approvals
$pendingNotes = $db->fetchAll("
    SELECT n.*, u.username 
    FROM notes n 
    LEFT JOIN users u ON n.user_id = u.id 
    WHERE n.is_approved = 0 
    ORDER BY n.created_at DESC 
    LIMIT 5
");

$pendingItems = $db->fetchAll("
    SELECT m.*, u.username 
    FROM marketplace_items m 
    LEFT JOIN users u ON m.user_id = u.id 
    WHERE m.is_approved = 0 
    ORDER BY m.created_at DESC 
    LIMIT 5
");

// Get system status
$systemStatus = [
    'Database' => 'Connected',
    'Storage' => disk_free_space('/') > 1073741824 ? 'OK' : 'Low', // 1GB threshold
    'PHP Version' => phpversion(),
    'Server' => $_SERVER['SERVER_SOFTWARE'],
    'Users Online' => $db->fetchColumn("SELECT COUNT(DISTINCT user_id) FROM sessions WHERE last_activity > UNIX_TIMESTAMP(NOW() - INTERVAL 5 MINUTE)")
];

// Set page title
$pageTitle = "Admin Dashboard - WEZO CAMPUS HUB";

// Include admin header
include 'templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-2">
                <i class="fas fa-tachometer-alt text-primary me-2"></i> Admin Dashboard
            </h1>
            <p class="text-muted mb-0">
                Welcome back, <?php echo htmlspecialchars($user['first_name']); ?>! 
                <span class="badge bg-primary">Administrator</span>
            </p>
        </div>
        <div class="text-end">
            <small class="text-muted">Last login: <?php echo date('M d, H:i', strtotime($user['last_login'])); ?></small>
            <br>
            <small class="text-muted">IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></small>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_students']); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-success mr-2">
                                    <i class="fas fa-user-plus"></i> <?php echo $stats['today_registrations']; ?> today
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Content Status
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['approved_notes'] + $stats['active_items']; ?> Active
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-warning mr-2">
                                    <i class="fas fa-clock"></i> <?php echo $stats['pending_notes'] + $stats['pending_items']; ?> Pending
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Actions
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $stats['pending_reports']; ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-danger mr-2">
                                    <i class="fas fa-flag"></i> Reports
                                </span>
                                <span class="text-info">
                                    <i class="fas fa-home"></i> <?php echo $stats['pending_hostels']; ?> Hostels
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Today's Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                KSh <?php echo number_format($stats['today_revenue'] ?? 0, 2); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-success mr-2">
                                    <i class="fas fa-chart-line"></i> Marketplace
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Quick Actions & Recent Activity -->
        <div class="col-lg-8">
            <!-- Quick Actions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-bolt me-2"></i> Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <a href="users/" class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <span>Manage Users</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <a href="content-review/" class="btn btn-outline-warning w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <span>Approve Content</span>
                                <?php if ($stats['pending_notes'] + $stats['pending_items'] > 0): ?>
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                    <?php echo $stats['pending_notes'] + $stats['pending_items']; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="reports/" class="btn btn-outline-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-flag fa-2x mb-2"></i>
                                <span>View Reports</span>
                                <?php if ($stats['pending_reports'] > 0): ?>
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                    <?php echo $stats['pending_reports']; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="analytics/" class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <span>Analytics</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history me-2"></i> Recent Activity
                    </h6>
                    <a href="logs/" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Time</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentActivities as $activity): ?>
                                <tr>
                                    <td>
                                        <?php if ($activity['username']): ?>
                                        <span class="badge bg-light text-dark">@<?php echo htmlspecialchars($activity['username']); ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            $actionColors = [
                                                'login' => 'success',
                                                'logout' => 'secondary',
                                                'registration' => 'primary',
                                                'upload' => 'info',
                                                'delete' => 'danger',
                                                'update' => 'warning'
                                            ];
                                            echo $actionColors[$activity['action']] ?? 'dark';
                                        ?>">
                                            <?php echo ucwords(str_replace('_', ' ', $activity['action'])); ?>
                                        </span>
                                    </td>
                                    <td class="small"><?php echo htmlspecialchars($activity['description']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('H:i', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($activity['ip_address']); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals -->
            <div class="row">
                <!-- Pending Notes -->
                <?php if (!empty($pendingNotes)): ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-warning">
                                <i class="fas fa-file-alt me-2"></i> Pending Notes
                                <span class="badge bg-warning ms-2"><?php echo count($pendingNotes); ?></span>
                            </h6>
                            <a href="content-review/notes.php" class="btn btn-sm btn-outline-warning">Review All</a>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($pendingNotes as $note): ?>
                                <a href="content-review/notes.php?action=review&id=<?php echo $note['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 small"><?php echo htmlspecialchars($note['title']); ?></h6>
                                        <small><?php echo date('M d', strtotime($note['created_at'])); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($note['username']); ?>
                                    </small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pending Marketplace Items -->
                <?php if (!empty($pendingItems)): ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-warning">
                                <i class="fas fa-store me-2"></i> Pending Items
                                <span class="badge bg-warning ms-2"><?php echo count($pendingItems); ?></span>
                            </h6>
                            <a href="content-review/marketplace.php" class="btn btn-sm btn-outline-warning">Review All</a>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php foreach ($pendingItems as $item): ?>
                                <a href="content-review/marketplace.php?action=review&id=<?php echo $item['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1 small"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <small>KSh <?php echo number_format($item['price'], 2); ?></small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($item['username']); ?>
                                    </small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: System Status & Quick Stats -->
        <div class="col-lg-4">
            <!-- System Status -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-server me-2"></i> System Status
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($systemStatus as $key => $value): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="small"><?php echo $key; ?></span>
                            <span class="badge bg-<?php 
                                $statusColors = [
                                    'Connected' => 'success',
                                    'OK' => 'success',
                                    'Low' => 'warning',
                                    'Error' => 'danger'
                                ];
                                echo $statusColors[$value] ?? 'primary';
                            ?>"><?php echo $value; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <div class="progress mb-2" style="height: 10px;">
                            <?php
                            $totalSpace = disk_total_space('/');
                            $freeSpace = disk_free_space('/');
                            $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
                            $color = $usedPercent > 90 ? 'danger' : ($usedPercent > 70 ? 'warning' : 'success');
                            ?>
                            <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $usedPercent; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between small">
                            <span>Storage: <?php echo round($usedPercent, 1); ?>% used</span>
                            <span><?php echo round(($totalSpace - $freeSpace) / (1024*1024*1024), 1); ?>GB / <?php echo round($totalSpace / (1024*1024*1024), 1); ?>GB</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie me-2"></i> Quick Stats
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="contentPieChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> Approved
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-warning"></i> Pending
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-danger"></i> Rejected
                        </span>
                    </div>
                </div>
            </div>

            <!-- Admin Tools -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-tools me-2"></i> Admin Tools
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary" onclick="clearCache()">
                            <i class="fas fa-broom me-2"></i> Clear Cache
                        </button>
                        <button class="btn btn-outline-secondary" onclick="backupDatabase()">
                            <i class="fas fa-database me-2"></i> Backup Database
                        </button>
                        <button class="btn btn-outline-secondary" onclick="runMaintenance()">
                            <i class="fas fa-cogs me-2"></i> Run Maintenance
                        </button>
                        <button class="btn btn-outline-danger" onclick="showSystemLogs()">
                            <i class="fas fa-file-alt me-2"></i> View System Logs
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reports -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-flag me-2"></i> Recent Reports
                    </h6>
                    <a href="reports/" class="btn btn-sm btn-outline-danger">Manage All</a>
                </div>
                <div class="card-body">
                    <?php
                    $recentReports = $db->fetchAll("
                        SELECT r.*, u.username as reporter, 
                               (SELECT username FROM users WHERE id = r.resolved_by) as resolver
                        FROM reports r 
                        LEFT JOIN users u ON r.reporter_id = u.id 
                        WHERE r.status = 'pending'
                        ORDER BY r.created_at DESC 
                        LIMIT 5
                    ");
                    
                    if (empty($recentReports)): ?>
                    <div class="text-center py-3">
                        <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                        <p class="text-muted mb-0">No pending reports</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Reported By</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReports as $report): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo ucfirst($report['item_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($report['reporter']); ?></small>
                                    </td>
                                    <td class="small">
                                        <?php echo htmlspecialchars($report['reason']); ?>
                                        <?php if (!empty($report['description'])): ?>
                                        <br>
                                        <small class="text-muted"><?php echo substr(htmlspecialchars($report['description']), 0, 50); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d', strtotime($report['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">Pending</span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="reports/review.php?id=<?php echo $report['id']; ?>" 
                                               class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-outline-success" 
                                                    onclick="resolveReport(<?php echo $report['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="dismissReport(<?php echo $report['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Tools Script -->
<script>
// Chart.js for pie chart
const ctx = document.getElementById('contentPieChart').getContext('2d');
const contentPieChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Approved', 'Pending', 'Rejected'],
        datasets: [{
            data: [
                <?php echo $stats['approved_notes'] + $stats['active_items'] + $stats['approved_hostels']; ?>,
                <?php echo $stats['pending_notes'] + $stats['pending_items'] + $stats['pending_hostels']; ?>,
                0 // Rejected count would come from database
            ],
            backgroundColor: ['#4e73df', '#f6c23e', '#e74a3b'],
            hoverBackgroundColor: ['#2e59d9', '#dda20a', '#be2617'],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
    },
    options: {
        maintainAspectRatio: false,
        tooltips: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            caretPadding: 10,
        },
        legend: {
            display: false
        },
        cutoutPercentage: 80,
    },
});

// Admin tools functions
function clearCache() {
    if (confirm('Clear all cache? This may temporarily slow down the site.')) {
        fetch('tools/clear_cache.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cache cleared successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            });
    }
}

function backupDatabase() {
    if (confirm('Create a database backup? This may take a moment.')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating backup...';
        
        fetch('tools/backup.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Backup created successfully! Download link: ' + data.download_url);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    }
}

function runMaintenance() {
    if (confirm('Run maintenance tasks? This will optimize database and clean temporary files.')) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Running...';
        
        fetch('tools/maintenance.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Maintenance completed successfully!');
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
    }
}

function showSystemLogs() {
    window.open('tools/logs.php', '_blank');
}

function resolveReport(reportId) {
    if (confirm('Mark this report as resolved?')) {
        fetch('reports/resolve.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?php echo Auth::csrfToken(); ?>'
            },
            body: JSON.stringify({ id: reportId, action: 'resolve' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Report resolved!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function dismissReport(reportId) {
    if (confirm('Dismiss this report?')) {
        fetch('reports/dismiss.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?php echo Auth::csrfToken(); ?>'
            },
            body: JSON.stringify({ id: reportId, action: 'dismiss' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Report dismissed!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Auto-refresh dashboard every 60 seconds
setTimeout(() => {
    window.location.reload();
}, 60000);

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + Shift + A: Go to approvals
    if (e.ctrlKey && e.shiftKey && e.key === 'A') {
        e.preventDefault();
        window.location.href = 'content-review/';
    }
    
    // Ctrl + Shift + U: Go to users
    if (e.ctrlKey && e.shiftKey && e.key === 'U') {
        e.preventDefault();
        window.location.href = 'users/';
    }
    
    // Ctrl + Shift + R: Go to reports
    if (e.ctrlKey && e.shiftKey && e.key === 'R') {
        e.preventDefault();
        window.location.href = 'reports/';
    }
    
    // Ctrl + Shift + L: Go to logs
    if (e.ctrlKey && e.shiftKey && e.key === 'L') {
        e.preventDefault();
        window.location.href = 'logs/';
    }
});

// Initialize tooltips
$(function () {
    $('[data-toggle="tooltip"]').tooltip()
});
</script>

<?php
// Include admin footer
include 'templates/footer.php';
?>