<?php
/**
 * WEZO CAMPUS HUB - Analytics Dashboard
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
Auth::requireAdmin();

$db = Database::getInstance();
$user = Auth::user();

// Date range parameters
$period = $_GET['period'] ?? '7days'; // 7days, 30days, 90days, year, all
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Calculate date range based on period
switch ($period) {
    case '7days':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $startDate = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-1 year'));
        break;
    case 'custom':
        // Use provided dates
        if (empty($startDate)) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        break;
    default:
        $startDate = date('Y-m-d', strtotime('-7 days'));
}

// Get overall statistics
$stats = $db->fetch("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) >= ?) as new_users,
        (SELECT COUNT(*) FROM notes WHERE is_approved = 1) as total_notes,
        (SELECT COUNT(*) FROM marketplace_items WHERE is_approved = 1) as total_items,
        (SELECT COUNT(*) FROM hostels WHERE is_approved = 1) as total_hostels,
        (SELECT COUNT(*) FROM resources WHERE is_approved = 1) as total_resources,
        (SELECT COUNT(DISTINCT user_id) FROM sessions WHERE last_activity > UNIX_TIMESTAMP(NOW() - INTERVAL 5 MINUTE)) as online_users,
        (SELECT SUM(price) FROM marketplace_items WHERE status = 'sold' AND DATE(updated_at) >= ?) as total_revenue,
        (SELECT COUNT(*) FROM marketplace_items WHERE status = 'sold' AND DATE(updated_at) >= ?) as total_sales
", [$startDate, $startDate, $startDate]);

// Get user growth data
$userGrowth = $db->fetchAll("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count,
        SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative
    FROM users 
    WHERE created_at >= ?
    GROUP BY DATE(created_at)
    ORDER BY date
", [$startDate]);

// Get content uploads by type
$contentUploads = $db->fetchAll("
    SELECT 
        type,
        COUNT(*) as count,
        DATE(created_at) as date
    FROM (
        SELECT 'notes' as type, created_at FROM notes WHERE created_at >= ?
        UNION ALL
        SELECT 'marketplace' as type, created_at FROM marketplace_items WHERE created_at >= ?
        UNION ALL
        SELECT 'resources' as type, created_at FROM resources WHERE created_at >= ?
    ) as content
    GROUP BY type, DATE(created_at)
    ORDER BY date
", [$startDate, $startDate, $startDate]);

// Get top content
$topNotes = $db->fetchAll("
    SELECT n.*, u.username, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM notes_downloads WHERE note_id = n.id) as downloads
    FROM notes n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.is_approved = 1
    ORDER BY downloads DESC
    LIMIT 5
");

$topResources = $db->fetchAll("
    SELECT r.*, u.username, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM resource_downloads WHERE resource_id = r.id) as downloads
    FROM resources r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.is_approved = 1
    ORDER BY downloads DESC
    LIMIT 5
");

// Get user activity
$userActivity = $db->fetchAll("
    SELECT 
        u.username,
        u.first_name,
        u.last_name,
        (SELECT COUNT(*) FROM notes WHERE user_id = u.id) as note_count,
        (SELECT COUNT(*) FROM marketplace_items WHERE user_id = u.id) as item_count,
        (SELECT COUNT(*) FROM resources WHERE user_id = u.id) as resource_count,
        u.last_login
    FROM users u
    WHERE u.role = 'student'
    ORDER BY (note_count + item_count + resource_count) DESC
    LIMIT 10
");

// Get platform metrics
$platformMetrics = $db->fetchAll("
    SELECT 
        metric,
        value,
        change,
        status
    FROM platform_metrics
    ORDER BY importance
");

$pageTitle = "Analytics Dashboard - Admin Panel";
include '../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-2">
                <i class="fas fa-chart-bar text-primary me-2"></i> Analytics Dashboard
            </h1>
            <p class="text-muted mb-0">Platform insights and performance metrics</p>
        </div>
        <div>
            <div class="dropdown">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" 
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-calendar me-2"></i> 
                    <?php 
                    echo ucfirst($period);
                    if ($period === 'custom') {
                        echo ': ' . date('M d, Y', strtotime($startDate)) . ' - ' . date('M d, Y', strtotime($endDate));
                    }
                    ?>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?period=7days">Last 7 Days</a></li>
                    <li><a class="dropdown-item" href="?period=30days">Last 30 Days</a></li>
                    <li><a class="dropdown-item" href="?period=90days">Last 90 Days</a></li>
                    <li><a class="dropdown-item" href="?period=year">Last Year</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#dateRangeModal">
                            Custom Range
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Users
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_users']); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-success me-2">
                                    <i class="fas fa-user-plus"></i> <?php echo $stats['new_users']; ?> new
                                </span>
                                <span class="text-info">
                                    <i class="fas fa-wifi"></i> <?php echo $stats['online_users']; ?> online
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
                                Total Content
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_notes'] + $stats['total_items'] + $stats['total_resources'] + $stats['total_hostels']); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-primary me-2">
                                    <i class="fas fa-file-alt"></i> <?php echo $stats['total_notes']; ?> notes
                                </span>
                                <span class="text-info">
                                    <i class="fas fa-book"></i> <?php echo $stats['total_resources']; ?> resources
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-layer-group fa-2x text-success"></i>
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
                                Revenue
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                KSh <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-success me-2">
                                    <i class="fas fa-chart-line"></i> <?php echo $stats['total_sales']; ?> sales
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

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Platform Health
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php
                                $uptime = $db->fetchColumn("SELECT value FROM platform_metrics WHERE metric = 'uptime'");
                                echo $uptime ?: '99.9%';
                                ?>
                            </div>
                            <div class="mt-2 mb-0 text-muted text-xs">
                                <span class="text-success me-2">
                                    <i class="fas fa-server"></i> <?php echo date('H:i:s'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-heartbeat fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <!-- User Growth Chart -->
        <div class="col-xl-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line me-2"></i> User Growth
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="userGrowthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Distribution -->
        <div class="col-xl-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie me-2"></i> Content Distribution
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4">
                        <canvas id="contentDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> Notes
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> Marketplace
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> Resources
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-warning"></i> Hostels
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics -->
    <div class="row">
        <!-- Top Content -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fire me-2"></i> Top Performing Content
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="topContentTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="notes-tab" data-bs-toggle="tab" 
                                    data-bs-target="#notes-content" type="button">
                                <i class="fas fa-file-alt me-2"></i> Notes
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="resources-tab" data-bs-toggle="tab" 
                                    data-bs-target="#resources-content" type="button">
                                <i class="fas fa-book me-2"></i> Resources
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="topContentTabContent">
                        <!-- Top Notes -->
                        <div class="tab-pane fade show active" id="notes-content">
                            <div class="list-group list-group-flush">
                                <?php foreach ($topNotes as $note): ?>
                                <a href="/notes/view.php?id=<?php echo $note['id']; ?>" target="_blank" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($note['title']); ?></h6>
                                        <small class="text-primary"><?php echo $note['downloads']; ?> downloads</small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> 
                                        <?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?>
                                    </small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Top Resources -->
                        <div class="tab-pane fade" id="resources-content">
                            <div class="list-group list-group-flush">
                                <?php foreach ($topResources as $resource): ?>
                                <a href="/resources/view.php?id=<?php echo $resource['id']; ?>" target="_blank" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                        <small class="text-primary"><?php echo $resource['downloads']; ?> downloads</small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> 
                                        <?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?>
                                    </small>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Users -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-trophy me-2"></i> Top Contributors
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Notes</th>
                                    <th>Items</th>
                                    <th>Resources</th>
                                    <th>Total</th>
                                    <th>Last Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userActivity as $activity): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="/uploads/avatars/<?php echo $activity['avatar'] ?? 'default.jpg'; ?>" 
                                                 class="rounded-circle me-2" width="32" height="32">
                                            <div>
                                                <div class="small fw-medium"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></div>
                                                <small class="text-muted">@<?php echo htmlspecialchars($activity['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-primary"><?php echo $activity['note_count']; ?></span></td>
                                    <td><span class="badge bg-success"><?php echo $activity['item_count']; ?></span></td>
                                    <td><span class="badge bg-info"><?php echo $activity['resource_count']; ?></span></td>
                                    <td><strong><?php echo $activity['note_count'] + $activity['item_count'] + $activity['resource_count']; ?></strong></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo $activity['last_login'] ? timeAgo($activity['last_login']) : 'Never'; ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Metrics -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-chart-line me-2"></i> Platform Metrics
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($platformMetrics as $metric): ?>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-<?php 
                        $statusColors = ['good' => 'success', 'warning' => 'warning', 'critical' => 'danger'];
                        echo $statusColors[$metric['status']] ?? 'secondary';
                    ?> shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-uppercase mb-1">
                                        <?php echo htmlspecialchars($metric['metric']); ?>
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo htmlspecialchars($metric['value']); ?>
                                    </div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <?php if ($metric['change']): ?>
                                        <span class="<?php echo strpos($metric['change'], '+') === 0 ? 'text-success' : 'text-danger'; ?>">
                                            <i class="fas fa-arrow-<?php echo strpos($metric['change'], '+') === 0 ? 'up' : 'down'; ?> me-1"></i>
                                            <?php echo $metric['change']; ?>
                                        </span> from last period
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-<?php 
                                        $icons = ['uptime' => 'line', 'response_time' => 'bar', 'error_rate' => 'exclamation-triangle', 'load_time' => 'tachometer-alt'];
                                        echo $icons[strtolower($metric['metric'])] ?? 'chart-bar';
                                    ?> fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Content Uploads Chart -->
    <div class="card shadow">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-upload me-2"></i> Daily Content Uploads
            </h6>
        </div>
        <div class="card-body">
            <div class="chart-bar">
                <canvas id="contentUploadsChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Date Range Modal -->
<div class="modal fade" id="dateRangeModal" tabindex="-1" aria-labelledby="dateRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="GET">
                <input type="hidden" name="period" value="custom">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="dateRangeModalLabel">
                        <i class="fas fa-calendar me-2"></i> Select Date Range
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $startDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $endDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// User Growth Chart
const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
const userGrowthChart = new Chart(userGrowthCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            $dates = [];
            $daily = [];
            $cumulative = [];
            foreach ($userGrowth as $growth) {
                $dates[] = '"' . date('M d', strtotime($growth['date'])) . '"';
                $daily[] = $growth['count'];
                $cumulative[] = $growth['cumulative'];
            }
            echo implode(',', $dates);
        ?>],
        datasets: [
            {
                label: 'Daily New Users',
                data: [<?php echo implode(',', $daily); ?>],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true,
                tension: 0.4
            },
            {
                label: 'Total Users',
                data: [<?php echo implode(',', $cumulative); ?>],
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                fill: true,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Content Distribution Chart
const contentDistCtx = document.getElementById('contentDistributionChart').getContext('2d');
const contentDistChart = new Chart(contentDistCtx, {
    type: 'doughnut',
    data: {
        labels: ['Notes', 'Marketplace Items', 'Resources', 'Hostels'],
        datasets: [{
            data: [
                <?php echo $stats['total_notes']; ?>,
                <?php echo $stats['total_items']; ?>,
                <?php echo $stats['total_resources']; ?>,
                <?php echo $stats['total_hostels']; ?>
            ],
            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e'],
            hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a'],
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
        cutoutPercentage: 70,
    },
});

// Content Uploads Chart
const contentUploadsCtx = document.getElementById('contentUploadsChart').getContext('2d');

// Prepare data for content uploads chart
const uploadsByDate = {};
<?php foreach ($contentUploads as $upload): ?>
if (!uploadsByDate['<?php echo $upload['date']; ?>']) {
    uploadsByDate['<?php echo $upload['date']; ?>'] = {notes: 0, marketplace: 0, resources: 0};
}
uploadsByDate['<?php echo $upload['date']; ?>']['<?php echo $upload['type']; ?>'] = <?php echo $upload['count']; ?>;
<?php endforeach; ?>

const uploadDates = Object.keys(uploadsByDate).sort();
const notesData = uploadDates.map(date => uploadsByDate[date]?.notes || 0);
const marketplaceData = uploadDates.map(date => uploadsByDate[date]?.marketplace || 0);
const resourcesData = uploadDates.map(date => uploadsByDate[date]?.resources || 0);

const contentUploadsChart = new Chart(contentUploadsCtx, {
    type: 'bar',
    data: {
        labels: uploadDates.map(date => new Date(date).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
        datasets: [
            {
                label: 'Notes',
                data: notesData,
                backgroundColor: '#4e73df',
                borderColor: '#2e59d9',
                borderWidth: 1
            },
            {
                label: 'Marketplace',
                data: marketplaceData,
                backgroundColor: '#1cc88a',
                borderColor: '#17a673',
                borderWidth: 1
            },
            {
                label: 'Resources',
                data: resourcesData,
                backgroundColor: '#36b9cc',
                borderColor: '#2c9faf',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            x: {
                stacked: true,
                grid: {
                    display: false
                }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return Number.isInteger(value) ? value : '';
                    }
                }
            }
        }
    }
});

// Export analytics data
function exportAnalytics(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('export', format);
    
    window.open('export.php?' + params.toString(), '_blank');
}

// Auto-refresh every 5 minutes
setTimeout(() => {
    window.location.reload();
}, 300000);

// Initialize tooltips
$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>

<?php include '../templates/footer.php'; ?>