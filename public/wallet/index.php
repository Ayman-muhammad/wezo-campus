<?php
/**
 * WEZO CAMPUS HUB - Student Wallet
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

// Get wallet balance
$wallet = $db->fetch("
    SELECT * FROM user_wallets 
    WHERE user_id = ?
", [$user['id']]);

// Create wallet if doesn't exist
if (!$wallet) {
    $walletId = $db->insert('user_wallets', [
        'user_id' => $user['id'],
        'balance' => 0.00,
        'currency' => 'KES',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $wallet = $db->fetch("SELECT * FROM user_wallets WHERE id = ?", [$walletId]);
}

// Handle wallet actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fund_wallet'])) {
        $amount = floatval($_POST['amount']);
        $method = $_POST['payment_method'];
        
        if ($amount < 10) {
            Session::setFlash('error', 'Minimum deposit amount is KSh 10');
        } else {
            // Create payment request
            $transactionId = 'WALLET-' . time() . '-' . rand(1000, 9999);
            
            $paymentId = $db->insert('wallet_transactions', [
                'user_id' => $user['id'],
                'wallet_id' => $wallet['id'],
                'transaction_id' => $transactionId,
                'type' => 'deposit',
                'amount' => $amount,
                'payment_method' => $method,
                'status' => 'pending',
                'description' => 'Wallet deposit via ' . $method,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Redirect to payment gateway
            if ($method === 'mpesa') {
                header('Location: mpesa_payment.php?transaction_id=' . $transactionId);
                exit;
            } elseif ($method === 'card') {
                header('Location: card_payment.php?transaction_id=' . $transactionId);
                exit;
            }
        }
    } elseif (isset($_POST['withdraw'])) {
        $amount = floatval($_POST['withdraw_amount']);
        $method = $_POST['withdraw_method'];
        $account = $_POST['account_details'];
        
        if ($amount < 100) {
            Session::setFlash('error', 'Minimum withdrawal amount is KSh 100');
        } elseif ($amount > $wallet['balance']) {
            Session::setFlash('error', 'Insufficient balance');
        } else {
            // Create withdrawal request
            $transactionId = 'WITHDRAW-' . time() . '-' . rand(1000, 9999);
            
            $db->insert('wallet_transactions', [
                'user_id' => $user['id'],
                'wallet_id' => $wallet['id'],
                'transaction_id' => $transactionId,
                'type' => 'withdrawal',
                'amount' => $amount,
                'payment_method' => $method,
                'account_details' => $account,
                'status' => 'pending',
                'description' => 'Withdrawal to ' . $method,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Lock the amount temporarily
            $db->query("
                UPDATE user_wallets 
                SET locked_balance = locked_balance + ?, 
                    available_balance = available_balance - ?
                WHERE id = ?
            ", [$amount, $amount, $wallet['id']]);
            
            Session::setFlash('success', 'Withdrawal request submitted. Processing time: 1-3 business days.');
        }
    } elseif (isset($_POST['send_money'])) {
        $recipientUsername = trim($_POST['recipient']);
        $amount = floatval($_POST['send_amount']);
        $description = trim($_POST['send_description']);
        
        if ($amount < 1) {
            Session::setFlash('error', 'Minimum send amount is KSh 1');
        } elseif ($amount > $wallet['available_balance']) {
            Session::setFlash('error', 'Insufficient available balance');
        } else {
            // Find recipient
            $recipient = $db->fetch("
                SELECT u.*, w.id as wallet_id 
                FROM users u
                LEFT JOIN user_wallets w ON u.id = w.user_id
                WHERE u.username = ? AND u.status = 'active'
            ", [$recipientUsername]);
            
            if (!$recipient) {
                Session::setFlash('error', 'Recipient not found');
            } elseif ($recipient['id'] == $user['id']) {
                Session::setFlash('error', 'Cannot send money to yourself');
            } elseif (!$recipient['wallet_id']) {
                Session::setFlash('error', 'Recipient wallet not activated');
            } else {
                // Start transaction
                $db->beginTransaction();
                
                try {
                    $transactionId = 'TRANSFER-' . time() . '-' . rand(1000, 9999);
                    
                    // Create transaction record
                    $transferId = $db->insert('wallet_transactions', [
                        'user_id' => $user['id'],
                        'wallet_id' => $wallet['id'],
                        'transaction_id' => $transactionId,
                        'type' => 'transfer',
                        'amount' => $amount,
                        'recipient_id' => $recipient['id'],
                        'status' => 'completed',
                        'description' => $description ?: 'Money transfer to @' . $recipientUsername,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Deduct from sender
                    $db->query("
                        UPDATE user_wallets 
                        SET balance = balance - ?, 
                            available_balance = available_balance - ?
                        WHERE id = ?
                    ", [$amount, $amount, $wallet['id']]);
                    
                    // Add to recipient
                    $db->query("
                        UPDATE user_wallets 
                        SET balance = balance + ?, 
                            available_balance = available_balance + ?
                        WHERE id = ?
                    ", [$amount, $amount, $recipient['wallet_id']]);
                    
                    // Create transaction for recipient
                    $db->insert('wallet_transactions', [
                        'user_id' => $recipient['id'],
                        'wallet_id' => $recipient['wallet_id'],
                        'transaction_id' => $transactionId . '-RECEIVE',
                        'type' => 'received',
                        'amount' => $amount,
                        'sender_id' => $user['id'],
                        'status' => 'completed',
                        'description' => 'Received from @' . $user['username'] . ($description ? ': ' . $description : ''),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    $db->commit();
                    
                    Session::setFlash('success', 'KSh ' . number_format($amount, 2) . ' sent to @' . $recipientUsername);
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    Session::setFlash('error', 'Transfer failed: ' . $e->getMessage());
                }
            }
        }
    }
}

// Get recent transactions
$transactions = $db->fetchAll("
    SELECT wt.*, 
           u.username as recipient_username,
           u2.username as sender_username
    FROM wallet_transactions wt
    LEFT JOIN users u ON wt.recipient_id = u.id
    LEFT JOIN users u2 ON wt.sender_id = u2.id
    WHERE wt.user_id = ?
    ORDER BY wt.created_at DESC
    LIMIT 10
", [$user['id']]);

// Get transaction summary
$summary = $db->fetch("
    SELECT 
        SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as total_deposits,
        SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as total_withdrawals,
        SUM(CASE WHEN type = 'received' THEN amount ELSE 0 END) as total_received,
        SUM(CASE WHEN type = 'transfer' THEN amount ELSE 0 END) as total_sent,
        COUNT(*) as total_transactions
    FROM wallet_transactions
    WHERE user_id = ?
", [$user['id']]);

$pageTitle = "My Wallet";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <!-- Wallet Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">My Wallet</h1>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i> Wallet Settings
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="security.php">
                            <i class="fas fa-shield-alt"></i> Security Settings
                        </a></li>
                        <li><a class="dropdown-item" href="limits.php">
                            <i class="fas fa-chart-line"></i> Spending Limits
                        </a></li>
                        <li><a class="dropdown-item" href="linked_accounts.php">
                            <i class="fas fa-university"></i> Linked Accounts
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="statements.php">
                            <i class="fas fa-file-invoice"></i> Account Statements
                        </a></li>
                    </ul>
                </div>
            </div>

            <?php if (Session::hasFlash('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= Session::getFlash('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (Session::hasFlash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= Session::getFlash('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Balance Card -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-subtitle mb-2">Available Balance</h6>
                                    <h1 class="card-title display-6 mb-0">
                                        KSh <?= number_format($wallet['available_balance'], 2) ?>
                                    </h1>
                                    <p class="card-text">
                                        Total Balance: KSh <?= number_format($wallet['balance'], 2) ?>
                                        <?php if ($wallet['locked_balance'] > 0): ?>
                                            • Locked: KSh <?= number_format($wallet['locked_balance'], 2) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <div class="mb-3">
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-wallet"></i> Wallet ID: <?= substr($wallet['wallet_number'], 0, 8) ?>...
                                        </span>
                                    </div>
                                    <div class="btn-group">
                                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#depositModal">
                                            <i class="fas fa-plus-circle"></i> Add Money
                                        </button>
                                        <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                            <i class="fas fa-minus-circle"></i> Withdraw
                                        </button>
                                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#sendModal">
                                            <i class="fas fa-paper-plane"></i> Send
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3">Transaction Summary</h6>
                            <div class="mb-2">
                                <small class="text-muted">Total Deposits</small>
                                <div class="fw-bold text-success">KSh <?= number_format($summary['total_deposits'] ?? 0, 2) ?></div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Total Withdrawals</small>
                                <div class="fw-bold text-danger">KSh <?= number_format($summary['total_withdrawals'] ?? 0, 2) ?></div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Money Sent</small>
                                <div class="fw-bold">KSh <?= number_format($summary['total_sent'] ?? 0, 2) ?></div>
                            </div>
                            <div class="mb-2">
                                <small class="text-muted">Money Received</small>
                                <div class="fw-bold text-info">KSh <?= number_format($summary['total_received'] ?? 0, 2) ?></div>
                            </div>
                            <hr>
                            <small class="text-muted">
                                <i class="fas fa-exchange-alt"></i> 
                                Total Transactions: <?= $summary['total_transactions'] ?? 0 ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-3">Quick Actions</h6>
                            <div class="row g-2">
                                <div class="col-md-2 col-6">
                                    <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#depositModal">
                                        <i class="fas fa-plus-circle fa-2x mb-2"></i><br>
                                        Add Money
                                    </button>
                                </div>
                                <div class="col-md-2 col-6">
                                    <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#sendModal">
                                        <i class="fas fa-paper-plane fa-2x mb-2"></i><br>
                                        Send Money
                                    </button>
                                </div>
                                <div class="col-md-2 col-6">
                                    <button class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#withdrawModal">
                                        <i class="fas fa-minus-circle fa-2x mb-2"></i><br>
                                        Withdraw
                                    </button>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="transactions.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-history fa-2x mb-2"></i><br>
                                        History
                                    </a>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="payments.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-credit-card fa-2x mb-2"></i><br>
                                        Pay Bills
                                    </a>
                                </div>
                                <div class="col-md-2 col-6">
                                    <a href="qrcode.php" class="btn btn-outline-dark w-100">
                                        <i class="fas fa-qrcode fa-2x mb-2"></i><br>
                                        QR Code
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Transactions</h5>
                    <a href="transactions.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($transactions)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-exchange-alt fa-3x text-muted mb-3"></i>
                            <h5>No transactions yet</h5>
                            <p class="text-muted">Your transaction history will appear here</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#depositModal">
                                <i class="fas fa-plus-circle"></i> Make Your First Transaction
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('M j', strtotime($transaction['created_at'])) ?><br>
                                                    <?= date('g:i A', strtotime($transaction['created_at'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
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
                                                        <div><?= htmlspecialchars($transaction['description']) ?></div>
                                                        <small class="text-muted">
                                                            ID: <?= htmlspecialchars($transaction['transaction_id']) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $bgColor ?>">
                                                    <?= ucfirst($transaction['type']) ?>
                                                </span>
                                                <?php if ($transaction['recipient_username']): ?>
                                                    <small class="d-block text-muted">To: @<?= htmlspecialchars($transaction['recipient_username']) ?></small>
                                                <?php elseif ($transaction['sender_username']): ?>
                                                    <small class="d-block text-muted">From: @<?= htmlspecialchars($transaction['sender_username']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong class="<?= in_array($transaction['type'], ['deposit', 'received']) ? 'text-success' : 'text-danger' ?>">
                                                    <?= in_array($transaction['type'], ['deposit', 'received']) ? '+' : '-' ?>
                                                    KSh <?= number_format($transaction['amount'], 2) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusColor = '';
                                                switch ($transaction['status']) {
                                                    case 'completed': $statusColor = 'success'; break;
                                                    case 'pending': $statusColor = 'warning'; break;
                                                    case 'failed': $statusColor = 'danger'; break;
                                                    default: $statusColor = 'secondary';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $statusColor ?>">
                                                    <?= ucfirst($transaction['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-h"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="transaction.php?id=<?= $transaction['id'] ?>">
                                                                <i class="fas fa-eye"></i> View Details
                                                            </a>
                                                        </li>
                                                        <?php if ($transaction['status'] === 'pending'): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="#">
                                                                    <i class="fas fa-clock"></i> Track Payment
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <a class="dropdown-item" href="#">
                                                                <i class="fas fa-receipt"></i> Get Receipt
                                                            </a>
                                                        </li>
                                                        <?php if ($transaction['type'] === 'transfer' && $transaction['status'] === 'completed'): ?>
                                                            <li>
                                                                <a class="dropdown-item" href="#">
                                                                    <i class="fas fa-redo"></i> Repeat Transfer
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
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

            <!-- Security Notice -->
            <div class="alert alert-info mt-4">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shield-alt fa-2x"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="alert-heading">Wallet Security</h6>
                        <p class="mb-0">
                            • Never share your wallet PIN or password<br>
                            • Enable 2FA for enhanced security<br>
                            • Monitor your transactions regularly<br>
                            • Report suspicious activity immediately
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Deposit Modal -->
<div class="modal fade" id="depositModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add Money to Wallet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Amount (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KSh</span>
                            <input type="number" name="amount" class="form-control" 
                                   min="10" max="100000" step="1" 
                                   placeholder="Enter amount" required>
                            <span class="input-group-text">.00</span>
                        </div>
                        <small class="text-muted">Minimum: KSh 10 • Maximum: KSh 100,000</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card payment-method-card active" data-method="mpesa">
                                    <div class="card-body text-center">
                                        <i class="fas fa-mobile-alt fa-3x text-success mb-3"></i>
                                        <h6>M-Pesa</h6>
                                        <small class="text-muted">Instant • No fees</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card payment-method-card" data-method="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-credit-card fa-3x text-primary mb-3"></i>
                                        <h6>Card Payment</h6>
                                        <small class="text-muted">Visa/MasterCard</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="payment_method" id="paymentMethod" value="mpesa" required>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            You will be redirected to the payment gateway to complete the transaction.
                            Funds will appear in your wallet immediately after successful payment.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="fund_wallet" class="btn btn-primary">
                        <i class="fas fa-lock"></i> Proceed to Payment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Withdraw Money</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Available Balance</label>
                        <div class="alert alert-info">
                            <h4 class="mb-0">KSh <?= number_format($wallet['available_balance'], 2) ?></h4>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Withdraw Amount (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KSh</span>
                            <input type="number" name="withdraw_amount" class="form-control" 
                                   min="100" max="<?= $wallet['available_balance'] ?>" 
                                   step="1" required>
                        </div>
                        <small class="text-muted">Minimum: KSh 100 • Processing time: 1-3 business days</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Withdraw Method</label>
                        <select name="withdraw_method" class="form-select" required>
                            <option value="">Select method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Account Details</label>
                        <input type="text" name="account_details" class="form-control" 
                               placeholder="M-Pesa number or bank account details" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="withdraw" class="btn btn-primary">
                        <i class="fas fa-check"></i> Submit Withdrawal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Money Modal -->
<div class="modal fade" id="sendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Send Money</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient Username</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" name="recipient" class="form-control" 
                                   placeholder="Enter username" required>
                        </div>
                        <small class="text-muted">Enter the recipient's WEZO CAMPUS HUB username</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Amount (KES)</label>
                        <div class="input-group">
                            <span class="input-group-text">KSh</span>
                            <input type="number" name="send_amount" class="form-control" 
                                   min="1" max="<?= $wallet['available_balance'] ?>" 
                                   step="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <input type="text" name="send_description" class="form-control" 
                               placeholder="e.g., For lunch, Book payment, etc.">
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            • Instant transfer • No fees • Maximum: KSh <?= number_format($wallet['available_balance'], 2) ?>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_money" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Money
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Payment method selection
document.querySelectorAll('.payment-method-card').forEach(card => {
    card.addEventListener('click', function() {
        // Remove active class from all cards
        document.querySelectorAll('.payment-method-card').forEach(c => {
            c.classList.remove('active');
            c.classList.remove('border-primary');
        });
        
        // Add active class to clicked card
        this.classList.add('active');
        this.classList.add('border-primary');
        
        // Update hidden input
        document.getElementById('paymentMethod').value = this.dataset.method;
    });
});

// Auto-select M-Pesa as default
document.querySelector('.payment-method-card[data-method="mpesa"]').classList.add('border-primary');

// Format currency inputs
document.querySelectorAll('input[type="number"]').forEach(input => {
    input.addEventListener('input', function() {
        if (this.value < this.min) {
            this.value = this.min;
        }
        if (this.max && this.value > this.max) {
            this.value = this.max;
        }
    });
});

// Withdraw amount validation
document.querySelector('input[name="withdraw_amount"]').addEventListener('input', function() {
    const max = parseFloat(this.max);
    const value = parseFloat(this.value);
    const available = parseFloat(<?= $wallet['available_balance'] ?>);
    
    if (value > available) {
        this.value = available;
        alert('Cannot withdraw more than available balance');
    }
});

// Send amount validation
document.querySelector('input[name="send_amount"]').addEventListener('input', function() {
    const max = parseFloat(this.max);
    const value = parseFloat(this.value);
    const available = parseFloat(<?= $wallet['available_balance'] ?>);
    
    if (value > available) {
        this.value = available;
        alert('Cannot send more than available balance');
    }
});
</script>

<style>
.payment-method-card {
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}
.payment-method-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.payment-method-card.active {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>