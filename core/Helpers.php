<?php
/**
 * WEZO CAMPUS HUB - Core Helper Functions
 * Phase 3: Community & Communication
 */

namespace Core;

class Helpers {
    
    /**
     * Generate match suggestions for lost & found items
     */
    public static function generateMatchSuggestions($itemId, $limit = 5) {
        $db = Database::getInstance();
        
        $item = $db->fetch("SELECT * FROM lostfound WHERE id = ?", [$itemId]);
        if (!$item) return [];
        
        $oppositeType = $item['type'] === 'lost' ? 'found' : 'lost';
        
        $matches = $db->fetchAll("
            SELECT lf.*, 
                   MATCH(lf.item_name, lf.description, lf.location) AGAINST(?) as relevance,
                   u.first_name, u.last_name, u.username, c.name as campus_name,
                   cat.name as category_name
            FROM lostfound lf
            LEFT JOIN users u ON lf.user_id = u.id
            LEFT JOIN campuses c ON lf.campus_id = c.id
            LEFT JOIN lostfound_categories cat ON lf.category_id = cat.id
            WHERE lf.type = ? 
            AND lf.status = 'active'
            AND lf.id != ?
            AND (lf.campus_id = ? OR ? IS NULL)
            HAVING relevance > 0
            ORDER BY relevance DESC, lf.created_at DESC
            LIMIT ?
        ", [
            $item['item_name'] . ' ' . $item['description'] . ' ' . $item['location'],
            $oppositeType,
            $itemId,
            $item['campus_id'],
            $item['campus_id'],
            $limit
        ]);
        
        return $matches;
    }
    
    /**
     * Calculate event attendance statistics
     */
    public static function getEventStats($eventId) {
        $db = Database::getInstance();
        
        $stats = $db->fetch("
            SELECT 
                COUNT(*) as total_registered,
                SUM(CASE WHEN status = 'attended' THEN 1 ELSE 0 END) as total_attended,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as total_cancelled
            FROM event_attendees
            WHERE event_id = ?
        ", [$eventId]);
        
        $event = $db->fetch("SELECT max_attendees FROM events WHERE id = ?", [$eventId]);
        if ($event) {
            $stats['capacity'] = $event['max_attendees'];
            $stats['available_spots'] = $event['max_attendees'] ? 
                max(0, $event['max_attendees'] - $stats['total_registered']) : null;
            $stats['attendance_rate'] = $stats['total_registered'] > 0 ? 
                round(($stats['total_attended'] / $stats['total_registered']) * 100, 1) : 0;
        }
        
        return $stats;
    }
    
    /**
     * Generate forum activity summary
     */
    public static function getForumActivitySummary($userId = null, $days = 30) {
        $db = Database::getInstance();
        
        $whereClause = $userId ? "WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)" : 
                                 "WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params = $userId ? [$userId, $days] : [$days];
        
        $activity = $db->fetch("
            SELECT 
                COUNT(*) as total_posts,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_posts,
                COUNT(DISTINCT DATE(created_at)) as active_days
            FROM (
                SELECT user_id, created_at FROM forum_threads $whereClause
                UNION ALL
                SELECT user_id, created_at FROM forum_replies $whereClause
            ) as combined
        ", $params);
        
        return $activity;
    }
    
    /**
     * Send real-time notification via WebSocket
     */
    public static function sendWebSocketNotification($userId, $type, $data) {
        // Implementation depends on your WebSocket setup
        // This is a placeholder for the actual implementation
        try {
            $notificationData = [
                'user_id' => $userId,
                'type' => $type,
                'data' => $data,
                'timestamp' => time()
            ];
            
            // Example using Ratchet WebSocket
            // $server->sendToUser($userId, json_encode($notificationData));
            
            return true;
        } catch (\Exception $e) {
            error_log("WebSocket notification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format chat message for display
     */
    public static function formatChatMessage($message, $sender) {
        // Sanitize message
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        // Convert URLs to links
        $message = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $message
        );
        
        // Convert @mentions to links
        $message = preg_replace(
            '/@(\w+)/',
            '<a href="/profile/$1" class="mention">@$1</a>',
            $message
        );
        
        // Convert newlines to <br>
        $message = nl2br($message);
        
        return $message;
    }
    
    /**
     * Check if user is online
     */
    public static function isUserOnline($userId) {
        $db = Database::getInstance();
        
        $lastSeen = $db->fetch("SELECT last_seen FROM user_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$userId]);
        
        if ($lastSeen) {
            $lastSeenTime = strtotime($lastSeen['last_seen']);
            $currentTime = time();
            $diff = $currentTime - $lastSeenTime;
            
            // Consider online if active within last 5 minutes
            return $diff <= 300;
        }
        
        return false;
    }
    
    /**
     * Get unread message count
     */
    public static function getUnreadMessageCount($userId) {
        $db = Database::getInstance();
        
        $count = $db->fetch("
            SELECT COUNT(*) as unread_count
            FROM chat_messages cm
            INNER JOIN chat_participants cp ON cm.conversation_id = cp.conversation_id
            WHERE cp.user_id = ? 
            AND cm.sender_id != ?
            AND cm.is_read = FALSE
        ", [$userId, $userId]);
        
        return $count['unread_count'] ?? 0;
    }
    
    /**
     * Get notification badge count
     */
    public static function getNotificationCount($userId) {
        $db = Database::getInstance();
        
        $count = $db->fetch("
            SELECT COUNT(*) as notification_count
            FROM notifications
            WHERE user_id = ? AND is_read = FALSE
        ", [$userId]);
        
        return $count['notification_count'] ?? 0;
    }
    
    /**
     * Generate unique conversation ID
     */
    public static function generateConversationId($userIds) {
        sort($userIds);
        $hash = md5(implode('-', $userIds) . time());
        return substr($hash, 0, 16);
    }
    
    /**
     * Validate and sanitize forum content
     */
    public static function sanitizeForumContent($content) {
        // Remove dangerous tags but allow safe ones
        $allowedTags = '<p><br><strong><em><u><s><code><pre><blockquote><ul><ol><li><a><img>';
        
        $content = strip_tags($content, $allowedTags);
        
        // Limit content length
        $content = substr($content, 0, 10000);
        
        // Remove excessive line breaks
        $content = preg_replace('/(\r\n|\r|\n){3,}/', "\n\n", $content);
        
        return trim($content);
    }
    
    /**
     * Calculate user reputation score
     */
    public static function calculateReputationScore($userId) {
        $db = Database::getInstance();
        
        $scores = $db->fetch("
            SELECT 
                COUNT(DISTINCT ft.id) * 5 as thread_score,
                COUNT(DISTINCT fr.id) * 2 as reply_score,
                COALESCE(SUM(fr.likes), 0) as like_score,
                COUNT(DISTINCT CASE WHEN fr.is_solution = TRUE THEN fr.id END) * 10 as solution_score,
                COUNT(DISTINCT CASE WHEN ea.status = 'attended' THEN ea.id END) * 3 as event_score
            FROM users u
            LEFT JOIN forum_threads ft ON u.id = ft.user_id AND ft.status = 'active'
            LEFT JOIN forum_replies fr ON u.id = fr.user_id AND fr.status = 'active'
            LEFT JOIN event_attendees ea ON u.id = ea.user_id
            WHERE u.id = ?
        ", [$userId]);
        
        $totalScore = array_sum($scores);
        
        // Add badge bonuses
        $badges = $db->fetch("
            SELECT SUM(b.points) as badge_points
            FROM user_badges ub
            JOIN badges b ON ub.badge_id = b.id
            WHERE ub.user_id = ?
        ", [$userId]);
        
        $totalScore += $badges['badge_points'] ?? 0;
        
        return $totalScore;
    }
}