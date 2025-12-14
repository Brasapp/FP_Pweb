<?php
// Task Management Functions

/**
 * Get all tasks for a user
 */
function getUserTasks($user_id, $status = 'all') {
    $conn = getDBConnection();
    
    $query = "
        SELECT t.*, s.name as subject_name, s.color as subject_color, s.icon as subject_icon
        FROM tasks t
        LEFT JOIN subjects s ON t.subject_id = s.id
        WHERE t.user_id = ?
    ";
    
    if ($status !== 'all') {
        $query .= " AND t.status = '$status'";
    }
    
    $query .= " ORDER BY t.deadline ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $tasks;
}

/**
 * Get task by ID
 */
function getTaskById($task_id, $user_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT t.*, s.name as subject_name, s.color as subject_color
        FROM tasks t
        LEFT JOIN subjects s ON t.subject_id = s.id
        WHERE t.id = ? AND t.user_id = ?
    ");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $task;
}

/**
 * Calculate deadline urgency
 */
function getDeadlineUrgency($deadline) {
    $now = new DateTime();
    $deadline_dt = new DateTime($deadline);
    
    if ($now > $deadline_dt) {
        return 'overdue';
    }
    
    $diff = $now->diff($deadline_dt);
    $hours = ($diff->days * 24) + $diff->h;
    
    if ($hours <= 24) {
        return 'critical'; // < 1 day
    } elseif ($hours <= 72) {
        return 'warning'; // < 3 days
    } else {
        return 'safe'; // > 3 days
    }
}

/**
 * Get urgency badge HTML
 */
function getUrgencyBadge($deadline, $status) {
    if ($status === 'completed') {
        return '<span class="badge bg-success"><i class="fas fa-check"></i> Completed</span>';
    }
    
    $urgency = getDeadlineUrgency($deadline);
    
    switch ($urgency) {
        case 'overdue':
            return '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Overdue!</span>';
        case 'critical':
            return '<span class="badge bg-danger">Due in 24 hours!</span>';
        case 'warning':
            return '<span class="badge bg-warning text-dark">Due soon</span>';
        default:
            return '';
    }
}

/**
 * Get priority badge HTML
 */
function getPriorityBadge($priority) {
    $badges = [
        'high' => '<span class="badge bg-danger">High</span>',
        'medium' => '<span class="badge bg-warning text-dark">Medium</span>',
        'low' => '<span class="badge bg-success">Low</span>'
    ];
    
    return $badges[$priority] ?? $badges['medium'];
}

/**
 * Get tasks grouped by date
 */
function getTasksGroupedByDate($user_id) {
    $tasks = getUserTasks($user_id, 'todo');
    $grouped = [
        'overdue' => [],
        'today' => [],
        'tomorrow' => [],
        'this_week' => [],
        'later' => []
    ];
    
    $now = new DateTime();
    $today = $now->format('Y-m-d');
    $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
    $week_end = (clone $now)->modify('+7 days')->format('Y-m-d');
    
    foreach ($tasks as $task) {
        $deadline = date('Y-m-d', strtotime($task['deadline']));
        
        if ($deadline < $today) {
            $grouped['overdue'][] = $task;
        } elseif ($deadline === $today) {
            $grouped['today'][] = $task;
        } elseif ($deadline === $tomorrow) {
            $grouped['tomorrow'][] = $task;
        } elseif ($deadline <= $week_end) {
            $grouped['this_week'][] = $task;
        } else {
            $grouped['later'][] = $task;
        }
    }
    
    return $grouped;
}

/**
 * Get task statistics
 */
function getTaskStatistics($user_id) {
    $conn = getDBConnection();
    
    $stats = [];
    
    // Total tasks
    $result = $conn->query("SELECT COUNT(*) as total FROM tasks WHERE user_id = $user_id");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // Completed tasks
    $result = $conn->query("SELECT COUNT(*) as completed FROM tasks WHERE user_id = $user_id AND status = 'completed'");
    $stats['completed'] = $result->fetch_assoc()['completed'];
    
    // Pending tasks
    $stats['pending'] = $stats['total'] - $stats['completed'];
    
    // Completion rate
    $stats['completion_rate'] = $stats['total'] > 0 
        ? round(($stats['completed'] / $stats['total']) * 100, 1) 
        : 0;
    
    // Tasks by priority
    $result = $conn->query("
        SELECT priority, COUNT(*) as count 
        FROM tasks 
        WHERE user_id = $user_id AND status != 'completed'
        GROUP BY priority
    ");
    $stats['by_priority'] = [];
    while ($row = $result->fetch_assoc()) {
        $stats['by_priority'][$row['priority']] = $row['count'];
    }
    
    // Overdue tasks
    $today = date('Y-m-d H:i:s');
    $result = $conn->query("
        SELECT COUNT(*) as overdue 
        FROM tasks 
        WHERE user_id = $user_id 
        AND status != 'completed' 
        AND deadline < '$today'
    ");
    $stats['overdue'] = $result->fetch_assoc()['overdue'];
    
    // Today's tasks
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');
    $result = $conn->query("
        SELECT COUNT(*) as today 
        FROM tasks 
        WHERE user_id = $user_id 
        AND deadline BETWEEN '$today_start' AND '$today_end'
    ");
    $stats['today'] = $result->fetch_assoc()['today'];
    
    $conn->close();
    
    return $stats;
}

/**
 * Format deadline for display
 */
function formatDeadline($deadline) {
    $dt = new DateTime($deadline);
    $now = new DateTime();
    
    $diff = $now->diff($dt);
    
    if ($diff->days == 0) {
        return 'Today ' . $dt->format('H:i');
    } elseif ($diff->days == 1) {
        return 'Tomorrow ' . $dt->format('H:i');
    } else {
        return $dt->format('d M Y, H:i');
    }
}

/**
 * Get estimated completion time in human readable format
 */
function formatEstimatedTime($minutes) {
    if ($minutes < 60) {
        return $minutes . ' minutes';
    } else {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        if ($mins == 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        } else {
            return $hours . 'h ' . $mins . 'm';
        }
    }
}

/**
 * Delete task and update subject stats
 */
function deleteTask($task_id, $user_id) {
    $conn = getDBConnection();
    
    // Get task info
    $stmt = $conn->prepare("SELECT subject_id, status FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    $stmt->close();
    
    if (!$task) {
        $conn->close();
        return false;
    }
    
    // Delete task
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $task_id, $user_id);
    $success = $stmt->execute();
    $stmt->close();
    
    if ($success) {
        // Update subject stats
        $decrement_completed = $task['status'] === 'completed' ? 1 : 0;
        
        $stmt = $conn->prepare("
            UPDATE subjects 
            SET total_tasks = total_tasks - 1,
                completed_tasks = completed_tasks - ?,
                progress_percentage = CASE 
                    WHEN (total_tasks - 1) > 0 
                    THEN ((completed_tasks - ?) * 100.0 / (total_tasks - 1))
                    ELSE 0 
                END
            WHERE id = ? AND user_id = ?
        ");
        $stmt->bind_param("iiii", $decrement_completed, $decrement_completed, $task['subject_id'], $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    
    return $success;
}
?>