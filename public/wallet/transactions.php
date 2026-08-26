<?php
/**
 * WEZO CAMPUS HUB - Wallet Transactions
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

// Get filter parameters
$type = $_GET['type'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = ["wt.user_id = ?"];
$params = [$user['id']];

if ($type !== 'all') {
    $where[] = "wt.type = ?";
    $params[] = $type;
}

if ($status !== 'all') {
    $where[] = "wt.status = ?";
    $params[] = $status;
}

if ($start_date) {
    $where[] = "DATE(wt.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $where[] = "DATE(wt.created_at) <= ?";
    $params[] = $end_date;
}

if ($search) {
    $where[] = "(wt.transaction_id LIKE ? OR wt.description LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$total = $db->fetch("
    SELECT COUNT(*) as total
    FROM wallet_transactions wt
    LEFT JOIN users u ON wt.recipient_id = u.id
    LEFT JOIN users u2 ON wt.sender_id = u2.id
    $whereClause
", $params);

$totalPages = ceil($total['total'] / $limit);

// Get transactions
$transactions = $db->fetchAll("
    SELECT wt.*, 
           u.username as recipient_username,
           u2.username as sender_username,
           w.wallet_number
    FROM wallet_transactions wt
    LEFT JOIN users u ON wt.recipient_id = u.id
    LEFT JOIN users u2 ON wt.sender_id = u2.id
    LEFT JOIN user_wallets w ON wt.wallet_id = w.id
    $whereClause
    ORDER BY wt.created_at DESC
    LIMIT ? OFFSET ?
", array_merge($params, [$limit, $offset]));

// Get statistics
$stats = $db->fetch("
    SELECT 
        SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as deposits,
        SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawals,
        SUM(CASE WHEN type = 'transfer' THEN amount ELSE 0 END) as transfers,
        SUM(CASE WHEN type = 'received' THEN amount ELSE 0 END) as received,
        SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) as payments,
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
    FROM wallet_transactions
    WHERE user_id = ?
", [$user['id']]);

$pageTitle = "Transaction History";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Transaction History</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Wallet</a></li>
                            <li class="breadcrumb-item active">Transactions</li>
                        </ol>
                    </nav>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="export.php?format=csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a></li>
                        <li><a class="dropdown-item" href="export.php?format=pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </a></li>
                        <li><a class="dropdown-item" href="export.php?format=excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-6">
                    <div class="card bg-success bg-opacity-10 border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Total Deposits</h6>
                                    <h3 class="card-title">KSh <?= number_format($stats['deposits'] ?? 0, 2) ?></h3>
                                </div>
                                <div class="text-success">
                                    <i class="fas fa-plus-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-danger bg-opacity-10 border-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Total Withdrawals</h6>
                                    <h3 class="card-title">KSh <?= number_format($stats['withdrawals'] ?? 0, 2) ?></h3>
                                </div>
                                <div class="text-danger">
                                    <i class="fas fa-minus-circle fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-primary bg-opacity-10 border-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Money Sent</h6>
                                    <h3 class="card-title">KSh <?= number_format($stats['transfers'] ?? 0, 2) ?></h3>
                                </div>
                                <div class="text-primary">
                                    <i class="fas fa-paper-plane fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card bg-info bg-opacity-10 border-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-subtitle text-muted">Pending</h6>
                                    <h3 class="card-title"><?= $stats['pending'] ?? 0 ?></h3>
                                </div>
                                <div class="text-info">
                                    <i class="fas fa-clock fa-2x"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-select">
                                <option value="all">All Types</option>
                                <option value="deposit" <?= $type == 'deposit' ? 'selected' : '' ?>>Deposit</option>
                                <option value="withdrawal" <?= $type == 'withdrawal' ? 'selected' : '' ?>>Withdrawal</option>
                                <option value="transfer" <?= $type == 'transfer' ? 'selected' : '' ?>>Transfer</option>
                                <option value="received" <?= $type == 'received' ? 'selected' : '' ?>>Received</option>
                                <option value="payment" <?= $type == 'payment' ? 'selected' : '' ?>>Payment</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="all">All Status</option>
                                <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="pending" <?= $status == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="failed" <?= $status == 'failed' ? 'selected' : '' ?>>Failed</option>
                                <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Transaction ID">
                        </div>
                        <div class="col-md-12">
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="transactions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        Transactions (<?= $total['total'] ?>)
                        <small class="text-muted">Showing <?= count($transactions) ?> of <?= $total['total'] ?></small>
                    </h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="printTransactions()">
                                <i class="fas fa-print"></i> Print
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="downloadStatement()">
                                <i class="fas fa-download"></i> Download Statement
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="statements.php">
                                <i class="fas fa-file-invoice"></i> Account Statements
                            </a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                            <h5>No transactions found</h5>
                            <p class="text-muted">Try adjusting your filters</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Transaction Details</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Wallet</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted d-block">
                                                    <?= date('M j, Y', strtotime($transaction['created_at'])) ?>
                                                </small>
                                                <small class="text-muted">
                                                    <?= date('g:i A', strtotime($transaction['created_at'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-start">
                                                    <?php 
                                                    $icon = '';
                                                    $bgColor = '';
                                                    switch ($transaction['type']) {
                                                        case 'deposit': $icon = 'plus-circle'; $bgColor = 'success'; break;
                                                        case 'withdrawal': $icon = 'minus-circle'; $bgColor = 'danger'; break;
                                                        case 'transfer': $icon = 'paper-plane'; $bgColor = 'warning'; break;
                                                        case 'received': $icon = 'download'; $bgColor = 'info'; break;
                                                        case 'payment': $icon = 'credit-card'; $bgColor = 'primary'; break;
                                                        default: $icon = 'exchange-alt'; $bgColor = 'secondary';
                                                    }
                                                    ?>
                                                    <div class="flex-shrink-0">
                                                        <div class="rounded-circle bg-<?= $bgColor ?> bg-opacity-10 p-2 me-2">
                                                            <i class="fas fa-<?= $icon ?> text-<?= $bgColor ?>"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold"><?= htmlspecialchars($transaction['description']) ?></div>
                                                        <small class="text-muted d-block">
                                                            ID: <?= htmlspecialchars($transaction['transaction_id']) ?>
                                                        </small>
                                                        <?php if ($transaction['recipient_username']): ?>
                                                            <small class="text-muted">
                                                                To: @<?= htmlspecialchars($transaction['recipient_username']) ?>
                                                            </small>
                                                        <?php elseif ($transaction['sender_username']): ?>
                                                            <small class="text-muted">
                                                                From: @<?= htmlspecialchars($transaction['sender_username']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $bgColor ?>">
                                                    <?= ucfirst($transaction['type']) ?>
                                                </span>
                                                <?php if ($transaction['payment_method']): ?>
                                                    <small class="d-block text-muted">
                                                        <?= strtoupper($transaction['payment_method']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold <?= in_array($transaction['type'], ['deposit', 'received']) ? 'text-success' : 'text-danger' ?>">
                                                    <?= in_array($transaction['type'], ['deposit', 'received']) ? '+' : '-' ?>
                                                    KSh <?= number_format($transaction['amount'], 2) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusColor = '';
                                                switch ($transaction['status']) {
                                                    case 'completed': $statusColor = 'success'; break;
                                                    case 'pending': $statusColor = 'warning'; break;
                                                    case 'failed': $statusColor = 'danger'; break;
                                                    case 'cancelled': $statusColor = 'secondary'; break;
                                                    default: $statusColor = 'info';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $statusColor ?>">
                                                    <?= ucfirst($transaction['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= substr($transaction['wallet_number'] ?? 'N/A', 0, 8) ?>...
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="transaction.php?id=<?= $transaction['id'] ?>" 
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-secondary" title="Get Receipt">
                                                        <i class="fas fa-receipt"></i>
                                                    </a>
                                                    <?php if ($transaction['type'] === 'transfer' && $transaction['status'] === 'completed'): ?>
                                                        <a href="send.php?repeat=<?= $transaction['id'] ?>" 
                                                           class="btn btn-outline-success" title="Repeat">
                                                            <i class="fas fa-redo"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Transaction pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" 
                                       href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                        <a class="page-link" 
                                           href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" 
                                           href="?<?= http_build_query(array_merge($_GET, ['page' => $totalPages])) ?>">
                                            <?= $totalPages ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" 
                                       href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Summary -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Transaction Summary</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Deposits</small>
                                    <div class="h5 text-success">KSh <?= number_format($stats['deposits'] ?? 0, 2) ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Withdrawals</small>
                                    <div class="h5 text-danger">KSh <?= number_format($stats['withdrawals'] ?? 0, 2) ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Money Sent</small>
                                    <div class="h5 text-warning">KSh <?= number_format($stats['transfers'] ?? 0, 2) ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Money Received</small>
                                    <div class="h5 text-info">KSh <?= number_format($stats['received'] ?? 0, 2) ?></div>
                                </div>
                                <div class="col-12 mt-3">
                                    <hr>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i>
                                        These totals include only completed transactions.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Export Options</h6>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                Download your transaction history for record keeping or accounting purposes.
                            </p>
                            <div class="d-grid gap-2">
                                <a href="export.php?format=pdf&<?= http_build_query($_GET) ?>" 
                                   class="btn btn-outline-danger">
                                    <i class="fas fa-file-pdf"></i> Download PDF Statement
                                </a>
                                <a href="export.php?format=excel&<?= http_build_query($_GET) ?>" 
                                   class="btn btn-outline-success">
                                    <i class="fas fa-file-excel"></i> Download Excel Report
                                </a>
                                <a href="export.php?format=csv&<?= http_build_query($_GET) ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-file-csv"></i> Download CSV Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printTransactions() {
    window.print();
}

function downloadStatement() {
    window.location.href = 'export.php?format=pdf&<?= http_build_query($_GET) ?>';
}

// Auto-submit date filters when date changes
document.querySelectorAll('input[type="date"]').forEach(input => {
    input.addEventListener('change', function() {
        if (this.name === 'start_date' && this.value) {
            const endDate = document.querySelector('input[name="end_date"]');
            if (endDate.value && endDate.value < this.value) {
                alert('End date cannot be before start date');
                this.value = '';
                return;
            }
        }
        if (this.name === 'end_date' && this.value) {
            const startDate = document.querySelector('input[name="start_date"]');
            if (startDate.value && startDate.value > this.value) {
                alert('Start date cannot be after end date');
                this.value = '';
                return;
            }
        }
    });
});
</script>

<style>
@media print {
    .col-md-3, .card-header, .btn, .dropdown, .pagination, .col-md-6:last-child {
        display: none !important;
    }
    .col-md-9 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    table {
        font-size: 11px;
    }
    .badge {
        border: 1px solid #000;
    }
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>