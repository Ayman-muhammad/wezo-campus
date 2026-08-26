<?php
/**
 * WEZO CAMPUS HUB - Real-time Chat
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

// Get or create conversation
$conversationId = $_GET['conversation_id'] ?? null;
$userId = $_GET['user_id'] ?? null;

if ($userId) {
    // Create or get private conversation
    $conversation = $db->fetch("
        SELECT c.*
        FROM chat_conversations c
        INNER JOIN chat_participants cp1 ON c.id = cp1.conversation_id
        INNER JOIN chat_participants cp2 ON c.id = cp2.conversation_id
        WHERE c.type = 'private'
        AND cp1.user_id = ?
        AND cp2.user_id = ?
        LIMIT 1
    ", [$user['id'], $userId]);
    
    if (!$conversation) {
        // Create new conversation
        $conversationUid = Helpers::generateConversationId([$user['id'], $userId]);
        $conversationId = $db->insert('chat_conversations', [
            'conversation_uid' => $conversationUid,
            'type' => 'private',
            'created_by' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Add participants
        $db->insert('chat_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $user['id'],
            'role' => 'admin'
        ]);
        
        $db->insert('chat_participants', [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'role' => 'member'
        ]);
        
        $conversation = $db->fetch("SELECT * FROM chat_conversations WHERE id = ?", [$conversationId]);
    } else {
        $conversationId = $conversation['id'];
    }
}

// Get user conversations
$conversations = $db->fetchAll("
    SELECT c.*, 
           (SELECT message FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM chat_messages 
            WHERE conversation_id = c.id 
            ORDER BY created_at DESC LIMIT 1) as last_message_at,
           (SELECT COUNT(*) FROM chat_messages cm
            LEFT JOIN chat_message_status cms ON cm.id = cms.message_id
            WHERE cm.conversation_id = c.id 
            AND cm.sender_id != ?
            AND (cms.user_id = ? AND cms.status = 'delivered' OR cms.id IS NULL)) as unread_count,
           GROUP_CONCAT(DISTINCT u2.username) as participants
    FROM chat_conversations c
    INNER JOIN chat_participants cp ON c.id = cp.conversation_id
    LEFT JOIN chat_participants cp2 ON c.id = cp2.conversation_id
    LEFT JOIN users u2 ON cp2.user_id = u2.id AND u2.id != ?
    WHERE cp.user_id = ?
    GROUP BY c.id
    ORDER BY c.last_message_at DESC, c.updated_at DESC
", [$user['id'], $user['id'], $user['id'], $user['id']]);

// Get online users
$onlineUsers = $db->fetchAll("
    SELECT u.id, u.first_name, u.last_name, u.username, u.avatar, u.campus_id,
           c.name as campus_name,
           (SELECT 1 FROM user_sessions WHERE user_id = u.id AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) LIMIT 1) as is_online
    FROM users u
    LEFT JOIN campuses c ON u.campus_id = c.id
    WHERE u.id != ? AND u.status = 'active'
    ORDER BY u.first_name, u.last_name
    LIMIT 50
", [$user['id']]);

$pageTitle = "Chat";
include __DIR__ . '/../../templates/header.php';
include __DIR__ . '/../../templates/navbar.php';
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3">
            <?php include __DIR__ . '/../../templates/sidebar.php'; ?>
        </div>
        <div class="col-md-9">
            <div class="chat-container">
                <div class="row g-0">
                    <!-- Sidebar -->
                    <div class="col-md-4">
                        <div class="chat-sidebar">
                            <!-- New Conversation -->
                            <div class="p-3 border-bottom">
                                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#newChatModal">
                                    <i class="fas fa-plus"></i> New Chat
                                </button>
                            </div>
                            
                            <!-- Online Users -->
                            <div class="p-3 border-bottom">
                                <h6 class="mb-2">Online Now</h6>
                                <div class="online-users">
                                    <?php foreach ($onlineUsers as $onlineUser): ?>
                                        <?php if ($onlineUser['is_online']): ?>
                                            <a href="?user_id=<?= $onlineUser['id'] ?>" class="d-flex align-items-center mb-2 text-decoration-none text-dark">
                                                <div class="position-relative">
                                                    <img src="<?= htmlspecialchars($onlineUser['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                                         alt="<?= htmlspecialchars($onlineUser['username']) ?>" 
                                                         class="rounded-circle me-2" width="40" height="40">
                                                    <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle">
                                                        <span class="visually-hidden">Online</span>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 small">
                                                        <?= htmlspecialchars($onlineUser['first_name'] . ' ' . $onlineUser['last_name']) ?>
                                                    </h6>
                                                    <small class="text-muted"><?= htmlspecialchars($onlineUser['campus_name']) ?></small>
                                                </div>
                                            </a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Conversations List -->
                            <div class="p-3">
                                <h6 class="mb-2">Recent Chats</h6>
                                <div class="conversations-list">
                                    <?php foreach ($conversations as $conv): ?>
                                        <a href="?conversation_id=<?= $conv['id'] ?>" 
                                           class="d-flex align-items-center p-2 mb-2 rounded text-decoration-none text-dark 
                                                  <?= $conversationId == $conv['id'] ? 'bg-light' : '' ?>">
                                            <div class="flex-shrink-0 position-relative">
                                                <img src="/assets/images/default-group.png" 
                                                     alt="Group" 
                                                     class="rounded-circle me-2" width="40" height="40">
                                                <?php if ($conv['unread_count'] > 0): ?>
                                                    <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger">
                                                        <?= $conv['unread_count'] ?>
                                                        <span class="visually-hidden">unread messages</span>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 small">
                                                    <?= htmlspecialchars($conv['participants'] ?? 'Group Chat') ?>
                                                </h6>
                                                <small class="text-muted text-truncate d-block">
                                                    <?= substr($conv['last_message'] ?? 'No messages yet', 0, 30) ?>...
                                                </small>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <small class="text-muted">
                                                    <?= $conv['last_message_at'] ? 
                                                        date('H:i', strtotime($conv['last_message_at'])) : '' ?>
                                                </small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($conversations)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-comment-slash fa-2x text-muted mb-2"></i>
                                            <p class="text-muted small">No conversations yet</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Chat Area -->
                    <div class="col-md-8">
                        <?php if ($conversationId): ?>
                            <?php 
                            $currentConversation = $db->fetch("
                                SELECT c.*, 
                                       GROUP_CONCAT(DISTINCT u.id) as participant_ids,
                                       GROUP_CONCAT(DISTINCT u.username) as participant_names
                                FROM chat_conversations c
                                INNER JOIN chat_participants cp ON c.id = cp.conversation_id
                                INNER JOIN users u ON cp.user_id = u.id
                                WHERE c.id = ? AND cp.user_id = ?
                                GROUP BY c.id
                            ", [$conversationId, $user['id']]);
                            ?>
                            
                            <?php if ($currentConversation): ?>
                                <!-- Chat Header -->
                                <div class="chat-header p-3 border-bottom d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <h5 class="mb-0">
                                            <?php if ($currentConversation['type'] === 'private'): 
                                                $otherParticipant = $db->fetch("
                                                    SELECT u.*, c.name as campus_name
                                                    FROM users u
                                                    LEFT JOIN campuses c ON u.campus_id = c.id
                                                    INNER JOIN chat_participants cp ON u.id = cp.user_id
                                                    WHERE cp.conversation_id = ? AND u.id != ?
                                                    LIMIT 1
                                                ", [$conversationId, $user['id']]);
                                            ?>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= htmlspecialchars($otherParticipant['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                                         alt="<?= htmlspecialchars($otherParticipant['username']) ?>" 
                                                         class="rounded-circle me-2" width="40" height="40">
                                                    <div>
                                                        <h6 class="mb-0">
                                                            <?= htmlspecialchars($otherParticipant['first_name'] . ' ' . $otherParticipant['last_name']) ?>
                                                            <small class="text-muted">@<?= htmlspecialchars($otherParticipant['username']) ?></small>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($otherParticipant['campus_name']) ?>
                                                            <?php 
                                                            $isOnline = $db->fetch("
                                                                SELECT 1 FROM user_sessions 
                                                                WHERE user_id = ? AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                                                                LIMIT 1
                                                            ", [$otherParticipant['id']]);
                                                            ?>
                                                            • <span class="<?= $isOnline ? 'text-success' : 'text-muted' ?>">
                                                                <i class="fas fa-circle"></i> <?= $isOnline ? 'Online' : 'Offline' ?>
                                                            </span>
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center">
                                                    <img src="/assets/images/default-group.png" 
                                                         alt="Group" 
                                                         class="rounded-circle me-2" width="40" height="40">
                                                    <div>
                                                        <h6 class="mb-0">Group Chat</h6>
                                                        <small class="text-muted">
                                                            <?= count(explode(',', $currentConversation['participant_ids'])) ?> participants
                                                        </small>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </h5>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" 
                                                    type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="#">
                                                        <i class="fas fa-info-circle"></i> View Details
                                                    </a>
                                                </li>
                                                <?php if ($currentConversation['type'] === 'group'): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#">
                                                            <i class="fas fa-user-plus"></i> Add People
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#">
                                                            <i class="fas fa-cog"></i> Group Settings
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#">
                                                        <i class="fas fa-trash"></i> Delete Chat
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Messages Area -->
                                <div class="chat-messages p-3" id="chatMessages" style="height: 400px; overflow-y: auto;">
                                    <?php 
                                    $messages = $db->fetchAll("
                                        SELECT cm.*, 
                                               u.first_name, u.last_name, u.username, u.avatar,
                                               cms.status as delivery_status
                                        FROM chat_messages cm
                                        LEFT JOIN users u ON cm.sender_id = u.id
                                        LEFT JOIN chat_message_status cms ON cm.id = cms.message_id AND cms.user_id = ?
                                        WHERE cm.conversation_id = ?
                                        ORDER BY cm.created_at ASC
                                        LIMIT 50
                                    ", [$user['id'], $conversationId]);
                                    ?>
                                    
                                    <?php if (empty($messages)): ?>
                                        <div class="text-center py-5">
                                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                            <h5>No messages yet</h5>
                                            <p class="text-muted">Say hello to start the conversation!</p>
                                        </div>
                                    <?php else: ?>
                                        <?php 
                                        $lastDate = null;
                                        foreach ($messages as $message): 
                                            $currentDate = date('Y-m-d', strtotime($message['created_at']));
                                            if ($currentDate != $lastDate):
                                                $lastDate = $currentDate;
                                        ?>
                                            <div class="text-center my-3">
                                                <span class="badge bg-light text-dark">
                                                    <?= date('F j, Y', strtotime($message['created_at'])) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="message mb-3 <?= $message['sender_id'] == $user['id'] ? 'text-end' : '' ?>">
                                            <div class="d-inline-block max-w-75">
                                                <?php if ($message['sender_id'] != $user['id']): ?>
                                                    <div class="d-flex align-items-center mb-1">
                                                        <img src="<?= htmlspecialchars($message['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                                             alt="<?= htmlspecialchars($message['username']) ?>" 
                                                             class="rounded-circle me-2" width="24" height="24">
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($message['first_name'] . ' ' . $message['last_name']) ?>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="message-bubble p-3 rounded 
                                                    <?= $message['sender_id'] == $user['id'] ? 'bg-primary text-white' : 'bg-light' ?>">
                                                    <?php if ($message['message_type'] === 'text'): ?>
                                                        <?= Helpers::formatChatMessage($message['message'], $message) ?>
                                                    <?php elseif ($message['message_type'] === 'image'): ?>
                                                        <img src="<?= htmlspecialchars($message['attachment_url']) ?>" 
                                                             alt="Image" class="img-fluid rounded" style="max-height: 200px;">
                                                    <?php elseif ($message['message_type'] === 'file'): ?>
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-file fa-2x me-2"></i>
                                                            <div>
                                                                <strong><?= htmlspecialchars($message['attachment_name']) ?></strong>
                                                                <br>
                                                                <small class="text-muted">
                                                                    <?= Helpers::formatFileSize($message['attachment_size']) ?>
                                                                </small>
                                                            </div>
                                                            <a href="<?= htmlspecialchars($message['attachment_url']) ?>" 
                                                               class="btn btn-sm btn-outline-secondary ms-3" download>
                                                                <i class="fas fa-download"></i>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="message-info mt-1">
                                                        <small class="<?= $message['sender_id'] == $user['id'] ? 'text-white-50' : 'text-muted' ?>">
                                                            <?= date('g:i A', strtotime($message['created_at'])) ?>
                                                            
                                                            <?php if ($message['sender_id'] == $user['id']): ?>
                                                                <?php if ($message['delivery_status'] === 'read'): ?>
                                                                    <i class="fas fa-check-double ms-1"></i>
                                                                <?php elseif ($message['delivery_status'] === 'delivered'): ?>
                                                                    <i class="fas fa-check ms-1"></i>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Message Input -->
                                <div class="chat-input p-3 border-top">
                                    <form id="messageForm" method="POST" action="send_message.php" 
                                          enctype="multipart/form-data" class="d-flex gap-2">
                                        <input type="hidden" name="conversation_id" value="<?= $conversationId ?>">
                                        
                                        <div class="flex-grow-1">
                                            <input type="text" name="message" class="form-control" 
                                                   placeholder="Type a message..." id="messageInput" 
                                                   autocomplete="off" required>
                                        </div>
                                        
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-paperclip"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <label class="dropdown-item">
                                                        <i class="fas fa-image"></i> Image
                                                        <input type="file" name="image" accept="image/*" 
                                                               class="d-none" id="imageUpload">
                                                    </label>
                                                </li>
                                                <li>
                                                    <label class="dropdown-item">
                                                        <i class="fas fa-file"></i> File
                                                        <input type="file" name="file" 
                                                               class="d-none" id="fileUpload">
                                                    </label>
                                                </li>
                                            </ul>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </form>
                                    
                                    <div class="mt-2 small text-muted">
                                        Press Enter to send • Shift+Enter for new line
                                    </div>
                                </div>
                                
                                <script>
                                // Auto-scroll to bottom
                                function scrollToBottom() {
                                    const messages = document.getElementById('chatMessages');
                                    messages.scrollTop = messages.scrollHeight;
                                }
                                
                                // Send message with Enter
                                document.getElementById('messageInput').addEventListener('keypress', function(e) {
                                    if (e.key === 'Enter' && !e.shiftKey) {
                                        e.preventDefault();
                                        document.getElementById('messageForm').submit();
                                    }
                                });
                                
                                // File upload preview
                                document.getElementById('imageUpload').addEventListener('change', function() {
                                    if (this.files.length > 0) {
                                        document.getElementById('messageForm').submit();
                                    }
                                });
                                
                                document.getElementById('fileUpload').addEventListener('change', function() {
                                    if (this.files.length > 0) {
                                        document.getElementById('messageForm').submit();
                                    }
                                });
                                
                                // Initial scroll
                                window.addEventListener('load', scrollToBottom);
                                </script>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                                    <h5>Conversation not found</h5>
                                    <p class="text-muted">You don't have access to this conversation</p>
                                    <a href="index.php" class="btn btn-primary">Back to Chats</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- Empty State -->
                            <div class="text-center py-5">
                                <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                                <h3>Welcome to Campus Chat</h3>
                                <p class="text-muted mb-4">
                                    Connect with fellow students, share notes, or discuss campus events in real-time.
                                </p>
                                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#newChatModal">
                                    <i class="fas fa-plus"></i> Start a New Chat
                                </button>
                                <div class="mt-4">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt"></i> All chats are encrypted and private
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Chat Modal -->
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Chat</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Search Users</label>
                    <input type="text" class="form-control" id="userSearch" 
                           placeholder="Search by name, username, or campus...">
                </div>
                
                <div class="user-list" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($onlineUsers as $onlineUser): ?>
                        <a href="?user_id=<?= $onlineUser['id'] ?>" class="d-flex align-items-center p-2 mb-2 rounded text-decoration-none text-dark hover-bg">
                            <img src="<?= htmlspecialchars($onlineUser['avatar'] ?: '/assets/images/default-avatar.png') ?>" 
                                 alt="<?= htmlspecialchars($onlineUser['username']) ?>" 
                                 class="rounded-circle me-2" width="40" height="40">
                            <div class="flex-grow-1">
                                <h6 class="mb-0">
                                    <?= htmlspecialchars($onlineUser['first_name'] . ' ' . $onlineUser['last_name']) ?>
                                </h6>
                                <small class="text-muted">
                                    @<?= htmlspecialchars($onlineUser['username']) ?>
                                    • <?= htmlspecialchars($onlineUser['campus_name']) ?>
                                </small>
                            </div>
                            <div class="flex-shrink-0">
                                <?php if ($onlineUser['is_online']): ?>
                                    <span class="badge bg-success">Online</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Offline</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newGroupModal">
                    <i class="fas fa-users"></i> Create Group
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New Group Modal -->
<div class="modal fade" id="newGroupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="create_group.php">
                <div class="modal-header">
                    <h5 class="modal-title">Create Group Chat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Group Name</label>
                        <input type="text" name="group_name" class="form-control" 
                               placeholder="e.g., Class of 2024, Study Group" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Add Members</label>
                        <select name="members[]" class="form-select" multiple size="5" required>
                            <?php foreach ($onlineUsers as $onlineUser): ?>
                                <option value="<?= $onlineUser['id'] ?>">
                                    <?= htmlspecialchars($onlineUser['first_name'] . ' ' . $onlineUser['last_name']) ?>
                                    (@<?= htmlspecialchars($onlineUser['username']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple members</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.chat-container {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    height: calc(100vh - 200px);
}
.chat-sidebar {
    height: 100%;
    border-right: 1px solid #dee2e6;
    background: #f8f9fa;
}
.chat-messages {
    background: #f8f9fa;
}
.message-bubble {
    max-width: 100%;
    word-wrap: break-word;
}
.max-w-75 {
    max-width: 75%;
}
.hover-bg:hover {
    background: #f8f9fa;
}
.chat-header, .chat-input {
    background: white;
}
.online-users, .conversations-list {
    max-height: 200px;
    overflow-y: auto;
}
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>