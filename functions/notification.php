<?php
// Notification Functions

/**
 * Create a notification for user
 */
function createNotification($user_id, $message, $type = 'info') {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iss", $user_id, $message, $type);
    $success = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $success;
}

/**
 * Get unread notifications for user
 */
function getUnreadNotifications($user_id, $limit = 10) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = FALSE 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $notifications;
}

/**
 * Mark notification as read
 */
function markNotificationAsRead($notification_id, $user_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $success = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $success;
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead($user_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = TRUE 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->bind_param("i", $user_id);
    $success = $stmt->execute();
    
    $stmt->close();
    $conn->close();
    
    return $success;
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($user_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $row['count'];
}

/**
 * Send deadline reminder notifications
 */
function sendDeadlineReminders() {
    $conn = getDBConnection();
    
    // Get tasks due in 24 hours
    $tomorrow = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $now = date('Y-m-d H:i:s');
    
    $result = $conn->query("
        SELECT t.*, u.id as user_id, s.name as subject_name
        FROM tasks t
        JOIN users u ON t.user_id = u.id
        JOIN subjects s ON t.subject_id = s.id
        WHERE t.status != 'completed'
        AND t.deadline BETWEEN '$now' AND '$tomorrow'
    ");
    
    $sent = 0;
    while ($task = $result->fetch_assoc()) {
        $message = "⏰ Reminder: Task '{$task['title']}' for {$task['subject_name']} is due in 24 hours!";
        if (createNotification($task['user_id'], $message, 'warning')) {
            $sent++;
        }
    }
    
    $conn->close();
    
    return $sent;
}

/**
 * Send achievement notification
 */
function sendAchievementNotification($user_id, $badge_name) {
    $message = "🏆 Congratulations! You've unlocked the '{$badge_name}' achievement! +50 XP";
    return createNotification($user_id, $message, 'success');
}

/**
 * Send level up notification
 */
function sendLevelUpNotification($user_id, $new_level) {
    $title = getLevelTitle($new_level);
    $message = "🌟 Level Up! You are now Level {$new_level} - {$title}!";
    return createNotification($user_id, $message, 'success');
}

/**
 * Send streak milestone notification
 */
function sendStreakMilestoneNotification($user_id, $streak) {
    $message = "🔥 Amazing! {$streak} days streak! Keep it up! +100 XP";
    return createNotification($user_id, $message, 'success');
}

/**
 * Delete old notifications
 */
function deleteOldNotifications($days = 30) {
    $conn = getDBConnection();
    
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    
    $stmt = $conn->prepare("
        DELETE FROM notifications 
        WHERE created_at < ? AND is_read = TRUE
    ");
    $stmt->bind_param("s", $cutoff_date);
    $success = $stmt->execute();
    $deleted = $stmt->affected_rows;
    
    $stmt->close();
    $conn->close();
    
    return $deleted;
}

/**
 * Get recent notifications
 */
function getRecentNotifications($user_id, $limit = 20) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $notifications;
}

/**
 * Format notification time (e.g., "2 hours ago")
 */
function formatNotificationTime($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('d M Y', $time);
    }
}
?>