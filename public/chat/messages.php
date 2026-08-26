<?php
/**
 * WEZO CAMPUS HUB - Chat Messages API
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

// Set headers for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

Auth::init();
$db = Database::getInstance();

// Get current user
try {
    $user = Auth::user();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    case 'PUT':
        handlePutRequest();
        break;
    case 'DELETE':
        handleDeleteRequest();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}

function handleGetRequest() {
    global $db, $user;
    
    $conversationId = intval($_GET['conversation_id'] ?? 0);
    $messageId = intval($_GET['message_id'] ?? 0);
    $lastMessageId = intval($_GET['last_message_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 50);
    $offset = intval($_GET['offset'] ?? 0);
    
    if ($conversationId) {
        // Get messages from conversation
        $messages = $db->fetchAll("
            SELECT cm.*, 
                   u.first_name, u.last_name, u.username, u.avatar,
                   cms.status as delivery_status,
                   (SELECT COUNT(*) FROM chat_message_status 
                    WHERE message_id = cm.id AND status = 'read') as read_count
            FROM chat_messages cm
            LEFT JOIN users u ON cm.sender_id = u.id
            LEFT JOIN chat_message_status cms ON cm.id = cms.message_id AND cms.user_id = ?
            WHERE cm.conversation_id = ?
            AND (? = 0 OR cm.id > ?)
            ORDER BY cm.created_at DESC
            LIMIT ? OFFSET ?
        ", [$user['id'], $conversationId, $lastMessageId, $lastMessageId, $limit, $offset]);
        
        // Mark as read
        if ($lastMessageId > 0) {
            $db->query("
                UPDATE chat_message_status 
                SET status = 'read' 
                WHERE message_id IN (
                    SELECT id FROM chat_messages 
                    WHERE conversation_id = ? AND sender_id != ? AND id <= ?
                ) AND user_id = ?
            ", [$conversationId, $user['id'], $lastMessageId, $user['id']]);
        }
        
        echo json_encode([
            'success' => true,
            'messages' => array_reverse($messages),
            'total' => count($messages)
        ]);
        
    } elseif ($messageId) {
        // Get specific message
        $message = $db->fetch("
            SELECT cm.*, 
                   u.first_name, u.last_name, u.username, u.avatar
            FROM chat_messages cm
            LEFT JOIN users u ON cm.sender_id = u.id
            WHERE cm.id = ?
            AND (cm.sender_id = ? OR EXISTS (
                SELECT 1 FROM chat_participants cp
                WHERE cp.conversation_id = cm.conversation_id
                AND cp.user_id = ?
            ))
        ", [$messageId, $user['id'], $user['id']]);
        
        if ($message) {
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Message not found']);
        }
        
    } else {
        // Get unread message count
        $unread = $db->fetch("
            SELECT COUNT(*) as unread_count
            FROM chat_messages cm
            INNER JOIN chat_participants cp ON cm.conversation_id = cp.conversation_id
            WHERE cp.user_id = ? 
            AND cm.sender_id != ?
            AND NOT EXISTS (
                SELECT 1 FROM chat_message_status cms
                WHERE cms.message_id = cm.id
                AND cms.user_id = ?
                AND cms.status = 'read'
            )
        ", [$user['id'], $user['id'], $user['id']]);
        
        echo json_encode(['success' => true, 'unread_count' => $unread['unread_count'] ?? 0]);
    }
}

function handlePostRequest() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $conversationId = intval($data['conversation_id'] ?? 0);
    $message = trim($data['message'] ?? '');
    $messageType = $data['message_type'] ?? 'text';
    $attachmentUrl = $data['attachment_url'] ?? null;
    $attachmentName = $data['attachment_name'] ?? null;
    $attachmentSize = $data['attachment_size'] ?? null;
    
    // Validate conversation access
    $access = $db->fetch("
        SELECT 1 FROM chat_participants 
        WHERE conversation_id = ? AND user_id = ?
    ", [$conversationId, $user['id']]);
    
    if (!$access) {
        http_response_code(403);
        echo json_encode(['error' => 'No access to conversation']);
        exit;
    }
    
    // Validate message
    if ($messageType === 'text' && empty($message)) {
        echo json_encode(['error' => 'Message cannot be empty']);
        exit;
    }
    
    // Insert message
    $messageId = $db->insert('chat_messages', [
        'conversation_id' => $conversationId,
        'sender_id' => $user['id'],
        'message_type' => $messageType,
        'message' => $message,
        'attachment_url' => $attachmentUrl,
        'attachment_name' => $attachmentName,
        'attachment_size' => $attachmentSize,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    if ($messageId) {
        // Update conversation last message time
        $db->query("
            UPDATE chat_conversations 
            SET last_message_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ", [$conversationId]);
        
        // Get participants to mark as delivered
        $participants = $db->fetchAll("
            SELECT user_id FROM chat_participants 
            WHERE conversation_id = ? AND user_id != ?
        ", [$conversationId, $user['id']]);
        
        foreach ($participants as $participant) {
            $db->insert('chat_message_status', [
                'message_id' => $messageId,
                'user_id' => $participant['user_id'],
                'status' => 'delivered'
            ]);
        }
        
        // Mark sender's message as read
        $db->insert('chat_message_status', [
            'message_id' => $messageId,
            'user_id' => $user['id'],
            'status' => 'read'
        ]);
        
        // Get complete message data
        $newMessage = $db->fetch("
            SELECT cm.*, 
                   u.first_name, u.last_name, u.username, u.avatar
            FROM chat_messages cm
            LEFT JOIN users u ON cm.sender_id = u.id
            WHERE cm.id = ?
        ", [$messageId]);
        
        echo json_encode([
            'success' => true,
            'message' => $newMessage,
            'message_id' => $messageId
        ]);
        
        // Send WebSocket notification
        sendWebSocketNotification($conversationId, $messageId, $user['id']);
        
    } else {
        echo json_encode(['error' => 'Failed to send message']);
    }
}

function handlePutRequest() {
    global $db, $user;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = intval($data['message_id'] ?? 0);
    $action = $data['action'] ?? '';
    
    if (!$messageId || !$action) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    switch ($action) {
        case 'mark_read':
            // Check if user has access to message
            $access = $db->fetch("
                SELECT 1 FROM chat_messages cm
                INNER JOIN chat_participants cp ON cm.conversation_id = cp.conversation_id
                WHERE cm.id = ? AND cp.user_id = ?
            ", [$messageId, $user['id']]);
            
            if ($access) {
                $db->query("
                    INSERT INTO chat_message_status (message_id, user_id, status)
                    VALUES (?, ?, 'read')
                    ON DUPLICATE KEY UPDATE status = 'read', updated_at = NOW()
                ", [$messageId, $user['id']]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'No access to message']);
            }
            break;
            
        case 'delete':
            // Only sender can delete
            $message = $db->fetch("
                SELECT sender_id FROM chat_messages WHERE id = ?
            ", [$messageId]);
            
            if ($message && $message['sender_id'] == $user['id']) {
                $db->query("
                    UPDATE chat_messages 
                    SET message = '[Message deleted]', 
                        attachment_url = NULL,
                        attachment_name = NULL
                    WHERE id = ?
                ", [$messageId]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['error' => 'Cannot delete this message']);
            }
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}

function handleDeleteRequest() {
    global $db, $user;
    
    $messageId = intval($_GET['message_id'] ?? 0);
    
    if (!$messageId) {
        echo json_encode(['error' => 'Missing message ID']);
        exit;
    }
    
    // Check if user is admin in conversation
    $access = $db->fetch("
        SELECT 1 FROM chat_messages cm
        INNER JOIN chat_participants cp ON cm.conversation_id = cp.conversation_id
        WHERE cm.id = ? AND cp.user_id = ? AND cp.role = 'admin'
    ", [$messageId, $user['id']]);
    
    if ($access) {
        $db->query("DELETE FROM chat_messages WHERE id = ?", [$messageId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Admin access required']);
    }
}

function sendWebSocketNotification($conversationId, $messageId, $senderId) {
    // This is a placeholder for WebSocket implementation
    // In production, you would use Ratchet, Swoole, or Pusher
    
    global $db;
    
    // Get conversation participants
    $participants = $db->fetchAll("
        SELECT user_id FROM chat_participants 
        WHERE conversation_id = ? AND user_id != ?
    ", [$conversationId, $senderId]);
    
    // Get message preview
    $message = $db->fetch("
        SELECT message, message_type FROM chat_messages WHERE id = ?
    ", [$messageId]);
    
    // Create notification for each participant
    foreach ($participants as $participant) {
        $db->insert('notifications', [
            'user_id' => $participant['user_id'],
            'type' => 'chat_message',
            'title' => 'New Message',
            'message' => substr($message['message'] ?? 'Attachment', 0, 100),
            'data' => json_encode(['conversation_id' => $conversationId, 'message_id' => $messageId]),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Log WebSocket event (for debugging)
    error_log("WebSocket: New message {$messageId} in conversation {$conversationId}");
    
    // Here you would typically:
    // 1. Push to Redis pub/sub
    // 2. Send to WebSocket server
    // 3. Use Pusher or similar service
}