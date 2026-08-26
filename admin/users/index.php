<?php
/**
 * WEZO CAMPUS HUB - Users Management
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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $userId = $_POST['user_id'] ?? 0;
        
        switch ($action) {
            case 'delete_user':
                if ($userId && $userId != $user['id']) {
                    $db->query("DELETE FROM users WHERE id = ?", [$userId]);
                    Session::setFlash('success', 'User deleted successfully');
                }
                break;
                
            case 'update_role':
                $role = $_POST['role'];
                if ($userId && $userId != $user['id']) {
                    $db->query("UPDATE users SET role = ? WHERE id = ?", [$role, $userId]);
                    Session::setFlash('success', 'User role updated');
                }
                break;
                
            case 'toggle_status':
                $currentStatus = $db->fetchColumn("SELECT status FROM users WHERE id = ?", [$userId]);
                $newStatus = $currentStatus === 'active' ? 'suspended' : 'active';
                $db->query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
                Session::setFlash('success', "User {$newStatus}");
                break;
                
            case 'bulk_action':
                $userIds = $_POST['user_ids'] ?? [];
                $bulkAction = $_POST['bulk_action'];
                
                if (!empty($userIds)) {
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    
                    switch ($bulkAction) {
                        case 'activate':
                            $db->query("UPDATE users SET status = 'active' WHERE id IN ($placeholders)", $userIds);
                            Session::setFlash('success', 'Selected users activated');
                            break;
                            
                        case 'suspend':
                            $db->query("UPDATE users SET status = 'suspended' WHERE id IN ($placeholders)", $userIds);
                            Session::setFlash('success', 'Selected users suspended');
                            break;
                            
                        case 'delete':
                            // Don't allow self-deletion
                            $userIds = array_diff($userIds, [$user['id']]);
                            if (!empty($userIds)) {
                                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                                $db->query("DELETE FROM users WHERE id IN ($placeholders)", $userIds);
                                Session::setFlash('success', 'Selected users deleted');
                            }
                            break;
                    }
                }
                break;
        }
        
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Get filter parameters
$filterRole = $_GET['role'] ?? 'all';
$filterStatus = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM notes WHERE user_id = u.id) as note_count,
          (SELECT COUNT(*) FROM marketplace_items WHERE user_id = u.id) as item_count
          FROM users u WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filterRole !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $filterRole;
}

if ($filterStatus !== 'all') {
    $query .= " AND u.status = ?";
    $params[] = $filterStatus;
}

// Get total count for pagination
$countQuery = str_replace('SELECT u.*', 'SELECT COUNT(*) as total', $query);
$totalUsers = $db->fetchColumn($countQuery, $params);
$totalPages = ceil($totalUsers / $limit);

// Get users with pagination
$query .= " ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$users = $db->fetchAll($query, $params);

// Get statistics
$stats = $db->fetch("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM users WHERE role = 'tutor') as total_tutors,
        (SELECT COUNT(*) FROM users WHERE role = 'hostel_owner') as total_hostel_owners,
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM users WHERE status = 'suspended') as suspended_users,
        (SELECT COUNT(*) FROM users WHERE status = 'pending') as pending_users,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as today_registrations
");

// Set page title
$pageTitle = "Users Management - WEZO CAMPUS HUB";

// Include admin header
include '../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-2">
                <i class="fas fa-users text-primary me-2"></i> Users Management
            </h1>
            <p class="text-muted mb-0">Manage all user accounts, roles, and permissions</p>
        </div>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i> Add New User
            </button>
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
                                Total Students
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_students']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-primary"></i>
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
                                Active Users
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['active_users']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-success"></i>
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
                                Today's Registrations
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['today_registrations']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-plus fa-2x text-warning"></i>
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
                                Suspended Users
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['suspended_users']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-slash fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small">Search Users</label>
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, email, or username">
                        <button class="btn btn-outline-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Filter by Role</label>
                    <select class="form-select" name="role">
                        <option value="all" <?php echo $filterRole === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="student" <?php echo $filterRole === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="tutor" <?php echo $filterRole === 'tutor' ? 'selected' : ''; ?>>Tutor</option>
                        <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="hostel_owner" <?php echo $filterRole === 'hostel_owner' ? 'selected' : ''; ?>>Hostel Owner</option>
                        <option value="moderator" <?php echo $filterRole === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Filter by Status</label>
                    <select class="form-select" name="status">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="suspended" <?php echo $filterStatus === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i> Users List
                <span class="badge bg-primary ms-2"><?php echo number_format($totalUsers); ?> total</span>
            </h6>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" 
                        data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-cog me-2"></i> Bulk Actions
                </button>
                <ul class="dropdown-menu">
                    <li><button class="dropdown-item" type="button" onclick="bulkAction('activate')">
                        <i class="fas fa-check-circle text-success me-2"></i> Activate Selected
                    </button></li>
                    <li><button class="dropdown-item" type="button" onclick="bulkAction('suspend')">
                        <i class="fas fa-ban text-warning me-2"></i> Suspend Selected
                    </button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button class="dropdown-item text-danger" type="button" onclick="bulkAction('delete')">
                        <i class="fas fa-trash me-2"></i> Delete Selected
                    </button></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <?php if (Session::hasFlash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo Session::getFlash('success'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form id="bulkForm" method="POST">
                <input type="hidden" name="action" value="bulk_action">
                <input type="hidden" name="bulk_action" id="bulkActionValue">
                
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Activity</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $userItem): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="user_ids[]" value="<?php echo $userItem['id']; ?>"
                                           class="user-checkbox" <?php echo $userItem['id'] == $user['id'] ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="/uploads/avatars/<?php echo $userItem['avatar'] ?? 'default.jpg'; ?>" 
                                             class="rounded-circle me-3" width="40" height="40" alt="Avatar">
                                        <div>
                                            <strong><?php echo htmlspecialchars($userItem['first_name'] . ' ' . $userItem['last_name']); ?></strong>
                                            <div class="small text-muted">
                                                @<?php echo htmlspecialchars($userItem['username']); ?>
                                            </div>
                                            <div class="small"><?php echo htmlspecialchars($userItem['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        $roleColors = [
                                            'admin' => 'danger',
                                            'student' => 'primary',
                                            'tutor' => 'success',
                                            'moderator' => 'warning',
                                            'hostel_owner' => 'info'
                                        ];
                                        echo $roleColors[$userItem['role']] ?? 'secondary';
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $userItem['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $userItem['status'] === 'active' ? 'success' : 
                                             ($userItem['status'] === 'pending' ? 'warning' : 'danger');
                                    ?>">
                                        <?php echo ucfirst($userItem['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <div>
                                            <i class="fas fa-file-alt text-primary me-1"></i>
                                            <?php echo $userItem['note_count']; ?> notes
                                        </div>
                                        <div>
                                            <i class="fas fa-store text-success me-1"></i>
                                            <?php echo $userItem['item_count']; ?> items
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        <?php echo date('M d, Y', strtotime($userItem['created_at'])); ?>
                                        <br>
                                        <span class="text-muted">Last login: 
                                            <?php echo $userItem['last_login'] ? date('M d', strtotime($userItem['last_login'])) : 'Never'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $userItem['id']; ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $userItem['id']; ?>" 
                                           class="btn btn-outline-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($userItem['id'] != $user['id']): ?>
                                        <button type="button" class="btn btn-outline-<?php 
                                            echo $userItem['status'] === 'suspended' ? 'success' : 'warning'; ?>" 
                                            onclick="toggleStatus(<?php echo $userItem['id']; ?>, '<?php echo $userItem['status']; ?>')"
                                            title="<?php echo $userItem['status'] === 'suspended' ? 'Activate' : 'Suspend'; ?>">
                                            <i class="fas fa-<?php echo $userItem['status'] === 'suspended' ? 'check' : 'ban'; ?>"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteUser(<?php echo $userItem['id']; ?>, '<?php echo htmlspecialchars($userItem['first_name'] . ' ' . $userItem['last_name']); ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filterRole !== 'all' ? '&role=' . $filterRole : ''; ?><?php echo $filterStatus !== 'all' ? '&status=' . $filterStatus : ''; ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filterRole !== 'all' ? '&role=' . $filterRole : ''; ?><?php echo $filterStatus !== 'all' ? '&status=' . $filterStatus : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $filterRole !== 'all' ? '&role=' . $filterRole : ''; ?><?php echo $filterStatus !== 'all' ? '&status=' . $filterStatus : ''; ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="add.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="fas fa-user-plus me-2"></i> Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Campus</label>
                            <select class="form-select" name="campus_id">
                                <option value="">Select Campus</option>
                                <?php
                                $campuses = $db->fetchAll("SELECT * FROM campuses ORDER BY name");
                                foreach ($campuses as $campus): ?>
                                <option value="<?php echo $campus['id']; ?>"><?php echo htmlspecialchars($campus['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="student">Student</option>
                                <option value="tutor">Tutor</option>
                                <option value="moderator">Moderator</option>
                                <option value="hostel_owner">Hostel Owner</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="pending">Pending</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Select all checkboxes
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.user-checkbox:not(:disabled)');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Bulk actions
function bulkAction(action) {
    const selectedCount = document.querySelectorAll('.user-checkbox:checked').length;
    
    if (selectedCount === 0) {
        alert('Please select at least one user.');
        return;
    }
    
    let confirmMessage = '';
    switch (action) {
        case 'activate':
            confirmMessage = `Activate ${selectedCount} selected user(s)?`;
            break;
        case 'suspend':
            confirmMessage = `Suspend ${selectedCount} selected user(s)?`;
            break;
        case 'delete':
            confirmMessage = `Delete ${selectedCount} selected user(s)? This action cannot be undone.`;
            break;
    }
    
    if (confirm(confirmMessage)) {
        document.getElementById('bulkActionValue').value = action;
        document.getElementById('bulkForm').submit();
    }
}

// Toggle user status
function toggleStatus(userId, currentStatus) {
    const action = currentStatus === 'suspended' ? 'activate' : 'suspend';
    if (confirm(`Are you sure you want to ${action} this user?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_status';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'user_id';
        idInput.value = userId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Delete user
function deleteUser(userId, userName) {
    if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_user';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'user_id';
        idInput.value = userId;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
// Include admin footer
include '../templates/footer.php';
?>