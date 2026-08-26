<?php
/**
 * WEZO CAMPUS HUB - Lost & Found Item Matching
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

// Get item IDs from query string
$item1Id = intval($_GET['id1'] ?? 0);
$item2Id = intval($_GET['id2'] ?? 0);
$matchId = intval($_GET['match_id'] ?? 0);

if (!$item1Id || !$item2Id) {
    Session::setFlash('error', 'Invalid item selection');
    header('Location: index.php');
    exit;
}

// Ensure items are of opposite types
$items = $db->fetchAll("
    SELECT lf.*, u.first_name, u.last_name, u.username, u.avatar, 
           u.phone, u.email, c.name as campus_name,
           cat.name as category_name
    FROM lostfound lf
    LEFT JOIN users u ON lf.user_id = u.id
    LEFT JOIN campuses c ON lf.campus_id = c.id
    LEFT JOIN lostfound_categories cat ON lf.category_id = cat.id
    WHERE lf.id IN (?, ?)
", [$item1Id, $item2Id]);

if (count($items) !== 2) {
    Session::setFlash('error', 'One or both items not found');
    header('Location: index.php');
    exit;
}

$item1 = $items[0]['id'] == $item1Id ? $items[0] : $items[1];
$item2 = $items[0]['id'] == $item2Id ? $items[0] : $items[1];

// Check if items are of opposite types
if ($item1['type'] === $item2['type']) {
    Session::setFlash('error', 'Cannot match two items of the same type');
    header('Location: index.php');
    exit;
}

// Ensure user has permission (must be owner of one item or admin)
if ($user['role'] !== 'admin' && $user['id'] !== $item1['user_id'] && $user['id'] !== $item2['user_id']) {
    Session::setFlash('error', 'You do not have permission to view this match');
    header('Location: index.php');
    exit;
}

// Get or create match record
if (!$matchId) {
    $match = $db->fetch("
        SELECT * FROM lostfound_matches 
        WHERE (lostfound_id = ? AND matched_item_id = ?)
           OR (lostfound_id = ? AND matched_item_id = ?)
        LIMIT 1
    ", [$item1Id, $item2Id, $item2Id, $item1Id]);
    
    if ($match) {
        $matchId = $match['id'];
    } else {
        // Calculate match confidence
        $confidence = calculateMatchConfidence($item1, $item2);
        
        $matchId = $db->insert('lostfound_matches', [
            'lostfound_id' => $item1Id,
            'matched_item_id' => $item2Id,
            'confidence_score' => $confidence,
            'status' => 'pending',
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Notify both users
        notifyUsersAboutMatch($matchId, $item1, $item2, $user['id']);
    }
} else {
    $match = $db->fetch("SELECT * FROM lostfound_matches WHERE id = ?", [$matchId]);
}

// Handle match actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (in_array($action, ['accept', 'reject', 'request_verification', 'mark_resolved'])) {
        handleMatchAction($matchId, $action, $user['id'], $item1, $item2);
    }
}

// Get match history
$matchHistory = $db->fetchAll("
    SELECT mh.*, u.first_name, u.last_name, u.username
    FROM lostfound_match_history mh
    LEFT JOIN users u ON mh.user_id = u.id
    WHERE mh.match_id = ?
    ORDER BY mh.created_at DESC
", [$matchId]);

// Get match verification requests
$verificationRequests = $db->fetchAll("
    SELECT vr.*, u.first_name, u.last_name, u.username
    FROM lostfound_verification_requests vr
    LEFT JOIN users u ON vr.requested_by = u.id
    WHERE vr.match_id = ? AND vr.status = 'pending'
    ORDER BY vr.created_at DESC
", [$matchId]);

/**
 * Calculate match confidence score
 */
function calculateMatchConfidence($item1, $item2) {
    $score = 0;
    
    // Category match (30 points)
    if ($item1['category_id'] == $item2['category_id']) {
        $score += 30;
    }
    
    // Campus match (20 points)
    if ($item1['campus_id'] == $item2['campus_id']) {
        $score += 20;
    }
    
    // Date proximity (25 points)
    $date1 = strtotime($item1['incident_date'] ?? $item1['created_at']);
    $date2 = strtotime($item2['incident_date'] ?? $item2['created_at']);
    $dateDiff = abs($date1 - $date2) / (60 * 60 * 24); // Days difference
    
    if ($dateDiff <= 1) $score += 25;
    elseif ($dateDiff <= 3) $score += 15;
    elseif ($dateDiff <= 7) $score += 10;
    
    // Description similarity (15 points) - simple keyword matching
    $desc1 = strtolower($item1['description']);
    $desc2 = strtolower($item2['description']);
    
    $keywords1 = explode(' ', $desc1);
    $keywords2 = explode(' ', $desc2);
    $commonKeywords = array_intersect($keywords1, $keywords2);
    
    if (count($commonKeywords) >= 3) $score += 15;
    elseif (count($commonKeywords) >= 2) $score += 10;
    elseif (count($commonKeywords) >= 1) $score += 5;
    
    // Location similarity (10 points)
    if (strpos(strtolower($item1['location']), strtolower($item2['location'])) !== false ||
        strpos(strtolower($item2['location']), strtolower($item1['location'])) !== false) {
        $score += 10;
    }
    
    return min(100, $score);
}

/**
 * Notify users about match
 */
function notifyUsersAboutMatch($matchId, $item1, $item2, $createdBy) {
    global $db;
    
    // Get both users
    $users = [$item1['user_id'], $item2['user_id']];
    
    foreach ($users as $userId) {
        if ($userId == $createdBy) continue; // Skip creator
        
        // Create notification
        $db->insert('notifications', [
            'user_id' => $userId,
            'type' => 'lostfound_match',
            'title' => 'New Lost & Found Match',
            'message' => 'A potential match has been found for your ' . ($item1['user_id'] == $userId ? 'lost item' : 'found item'),
            'data' => json_encode(['match_id' => $matchId]),
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Send email notification
        sendMatchNotificationEmail($userId, $matchId);
    }
}

/**
 * Handle match actions
 */
function handleMatchAction($matchId, $action, $userId, $item1, $item2) {
    global $db;
    
    $match = $db->fetch("SELECT * FROM lostfound_matches WHERE id = ?", [$matchId]);
    
    if (!$match) {
        Session::setFlash('error', 'Match not found');
        return;
    }
    
    // Check permissions
    $isOwner = $userId == $item1['user_id'] || $userId == $item2['user_id'];
    $isAdmin = $db->fetch("SELECT role FROM users WHERE id = ?", [$userId])['role'] === 'admin';
    
    if (!$isOwner && !$isAdmin && $action !== 'request_verification') {
        Session::setFlash('error', 'You do not have permission for this action');
        return;
    }
    
    switch ($action) {
        case 'accept':
            // Update match status
            $db->update('lostfound_matches', ['status' => 'accepted'], ['id' => $matchId]);
            
            // Update both items as resolved
            $db->update('lostfound', ['status' => 'resolved'], ['id' => $item1['id']]);
            $db->update('lostfound', ['status' => 'resolved'], ['id' => $item2['id']]);
            
            // Log action
            $db->insert('lostfound_match_history', [
                'match_id' => $matchId,
                'user_id' => $userId,
                'action' => 'accepted_match',
                'details' => 'Match accepted by user',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Session::setFlash('success', 'Match accepted! Both items marked as resolved.');
            break;
            
        case 'reject':
            $db->update('lostfound_matches', ['status' => 'rejected'], ['id' => $matchId]);
            
            $db->insert('lostfound_match_history', [
                'match_id' => $matchId,
                'user_id' => $userId,
                'action' => 'rejected_match',
                'details' => 'Match rejected by user',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Session::setFlash('success', 'Match rejected.');
            break;
            
        case 'request_verification':
            // Only owners can request verification
            if (!$isOwner) {
                Session::setFlash('error', 'Only item owners can request verification');
                return;
            }
            
            $db->insert('lostfound_verification_requests', [
                'match_id' => $matchId,
                'requested_by' => $userId,
                'request_type' => $_POST['verification_type'] ?? 'general',
                'notes' => $_POST['verification_notes'] ?? '',
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Session::setFlash('success', 'Verification request submitted. An admin will review it soon.');
            break;
            
        case 'mark_resolved':
            if (!$isAdmin) {
                Session::setFlash('error', 'Only admins can mark as resolved');
                return;
            }
            
            $db->update('lostfound_matches', ['status' => 'resolved'], ['id' => $matchId]);
            $db->update('lostfound', ['status' => 'resolved'], ['id' => $item1['id']]);
            $db->update('lostfound', ['status' => 'resolved'], ['id' => $item2['id']]);
            
            $db->insert('lostfound_match_history', [
                'match_id' => $matchId,
                'user_id' => $userId,
                'action' => 'admin_resolved',
                'details' => 'Match resolved by admin',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            Session::setFlash('success', 'Match marked as resolved.');
            break;
    }
    
    // Refresh page
    header('Location: match.php?id1=' . $item1['id'] . '&id2=' . $item2['id'] . '&match_id=' . $matchId);
    exit;
}

/**
 * Send email notification
 */
function sendMatchNotificationEmail($userId, $matchId) {
    global $db;
    
    $user = $db->fetch("SELECT email, first_name FROM users WHERE id = ?", [$userId]);
    
    if (!$user || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }
    
    $to = $user['email'];
    $subject = "🔍 New Lost & Found Match - WEZO CAMPUS HUB";
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; }
            .button { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
            .footer { background: #333; color: white; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>WEZO CAMPUS HUB</h2>
                <p>Lost & Found Notification</p>
            </div>
            <div class='content'>
                <p>Hello {$user['first_name']},</p>
                <p>A potential match has been found for your lost/found item on WEZO CAMPUS HUB.</p>
                <p>Please review the match and confirm if it's correct.</p>
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='" . SITE_URL . "/lostfound/match.php?match_id={$matchId}' class='button'>View Match Details</a>
                </p>
                <p>If you have any questions, please contact our support team.</p>
                <p>Best regards,<br>WEZO CAMPUS HUB Team</p>
            </div>
            <div class='footer'>
                <p>Powered by AYGLOBE INC | Campus Solutions</p>
                <p>This is an automated message, please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: no-reply@wezocampushub.com" . "\r\n";
    
    @mail($to, $subject, $message, $headers);
}

// Get current match details
$match = $db->fetch("
    SELECT m.*, 
           u1.first_name as creator_first_name, u1.last_name as creator_last_name,
           u1.username as creator_username
    FROM lostfound_matches m
    LEFT JOIN users u1 ON m.created_by = u1.id
    WHERE m.id = ?
", [$matchId]);

$pageTitle = "Match Details - Lost & Found";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Lost & Found Match</h1>
                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Listings
                    </a>
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

            <!-- Match Overview -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Match Overview</h5>
                    <div class="badge bg-<?= $match['status'] === 'accepted' ? 'success' : ($match['status'] === 'rejected' ? 'danger' : 'warning') ?>">
                        <?= ucfirst($match['status']) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Confidence Score:</strong></p>
                            <div class="progress mb-3">
                                <div class="progress-bar bg-<?= $match['confidence_score'] > 70 ? 'success' : ($match['confidence_score'] > 40 ? 'warning' : 'danger') ?>" 
                                     role="progressbar" 
                                     style="width: <?= $match['confidence_score'] ?>%"
                                     aria-valuenow="<?= $match['confidence_score'] ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                    <?= $match['confidence_score'] ?>%
                                </div>
                            </div>
                            <p><strong>Created by:</strong> <?= $match['creator_first_name'] . ' ' . $match['creator_last_name'] ?></p>
                            <p><strong>Created on:</strong> <?= date('F j, Y g:i A', strtotime($match['created_at'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($match['status'] === 'pending'): ?>
                                <form method="POST" class="mb-3">
                                    <div class="d-flex gap-2">
                                        <?php if ($user['id'] == $item1['user_id'] || $user['id'] == $item2['user_id'] || $user['role'] === 'admin'): ?>
                                            <button type="submit" name="action" value="accept" class="btn btn-success">
                                                <i class="fas fa-check"></i> Accept Match
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger">
                                                <i class="fas fa-times"></i> Reject Match
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <button type="submit" name="action" value="mark_resolved" class="btn btn-primary">
                                                <i class="fas fa-flag-checkered"></i> Mark as Resolved
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                                
                                <?php if ($user['id'] == $item1['user_id'] || $user['id'] == $item2['user_id']): ?>
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#verificationModal">
                                        <i class="fas fa-shield-alt"></i> Request Verification
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item Comparison -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?= ucfirst($item1['type']) ?> Item
                                <?php if ($item1['user_id'] == $user['id']): ?>
                                    <span class="badge bg-info float-end">Your Item</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title"><?= htmlspecialchars($item1['item_name']) ?></h6>
                            <p class="text-muted">Category: <?= htmlspecialchars($item1['category_name']) ?></p>
                            <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($item1['description'])) ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($item1['location']) ?></p>
                            <p><strong>Campus:</strong> <?= htmlspecialchars($item1['campus_name']) ?></p>
                            <p><strong>Date:</strong> <?= date('F j, Y', strtotime($item1['incident_date'] ?? $item1['created_at'])) ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-<?= $item1['status'] === 'found' ? 'success' : 'primary' ?>">
                                <?= ucfirst($item1['status']) ?>
                            </span></p>
                            
                            <?php if ($item1['image']): ?>
                                <div class="mt-3">
                                    <img src="<?= htmlspecialchars($item1['image']) ?>" 
                                         alt="<?= htmlspecialchars($item1['item_name']) ?>" 
                                         class="img-fluid rounded" style="max-height: 200px;">
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            <p><strong>Reported by:</strong> <?= htmlspecialchars($item1['first_name'] . ' ' . $item1['last_name']) ?></p>
                            <p><strong>Contact:</strong> 
                                <?php if ($user['id'] == $item1['user_id'] || $user['id'] == $item2['user_id'] || $user['role'] === 'admin'): ?>
                                    <?= htmlspecialchars($item1['phone']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Contact info hidden</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?= ucfirst($item2['type']) ?> Item
                                <?php if ($item2['user_id'] == $user['id']): ?>
                                    <span class="badge bg-info float-end">Your Item</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title"><?= htmlspecialchars($item2['item_name']) ?></h6>
                            <p class="text-muted">Category: <?= htmlspecialchars($item2['category_name']) ?></p>
                            <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($item2['description'])) ?></p>
                            <p><strong>Location:</strong> <?= htmlspecialchars($item2['location']) ?></p>
                            <p><strong>Campus:</strong> <?= htmlspecialchars($item2['campus_name']) ?></p>
                            <p><strong>Date:</strong> <?= date('F j, Y', strtotime($item2['incident_date'] ?? $item2['created_at'])) ?></p>
                            <p><strong>Status:</strong> <span class="badge bg-<?= $item2['status'] === 'found' ? 'success' : 'primary' ?>">
                                <?= ucfirst($item2['status']) ?>
                            </span></p>
                            
                            <?php if ($item2['image']): ?>
                                <div class="mt-3">
                                    <img src="<?= htmlspecialchars($item2['image']) ?>" 
                                         alt="<?= htmlspecialchars($item2['item_name']) ?>" 
                                         class="img-fluid rounded" style="max-height: 200px;">
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            <p><strong>Reported by:</strong> <?= htmlspecialchars($item2['first_name'] . ' ' . $item2['last_name']) ?></p>
                            <p><strong>Contact:</strong> 
                                <?php if ($user['id'] == $item1['user_id'] || $user['id'] == $item2['user_id'] || $user['role'] === 'admin'): ?>
                                    <?= htmlspecialchars($item2['phone']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Contact info hidden</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Match History -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Match History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($matchHistory)): ?>
                        <p class="text-muted">No history yet.</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($matchHistory as $history): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($history['first_name'] . ' ' . $history['last_name']) ?></h6>
                                            <small class="text-muted"><?= date('M j, g:i A', strtotime($history['created_at'])) ?></small>
                                        </div>
                                        <p class="mb-1"><strong><?= ucfirst(str_replace('_', ' ', $history['action'])) ?>:</strong> <?= htmlspecialchars($history['details']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Verification Requests -->
            <?php if (!empty($verificationRequests)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Pending Verification Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($verificationRequests as $request): ?>
                            <div class="alert alert-warning">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="alert-heading">Request from <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?></h6>
                                        <p><strong>Type:</strong> <?= ucfirst($request['request_type']) ?></p>
                                        <?php if ($request['notes']): ?>
                                            <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($request['notes'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?= date('M j, g:i A', strtotime($request['created_at'])) ?></small>
                                </div>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <div class="mt-2">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="verification_id" value="<?= $request['id'] ?>">
                                            <button type="submit" name="action" value="verify_approve" class="btn btn-sm btn-success">Approve</button>
                                            <button type="submit" name="action" value="verify_reject" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verificationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Request Verification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Verification Type</label>
                        <select name="verification_type" class="form-select" required>
                            <option value="identity">Identity Verification</option>
                            <option value="ownership">Ownership Proof</option>
                            <option value="meeting">Arrange Meeting</option>
                            <option value="general">General Assistance</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea name="verification_notes" class="form-control" rows="3" 
                                  placeholder="Please provide any additional details that might help..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="request_verification" class="btn btn-primary">
                        Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline {
    border-left: 2px solid #dee2e6;
    margin-left: 10px;
    padding-left: 20px;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -26px;
    top: 5px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #667eea;
}
.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>