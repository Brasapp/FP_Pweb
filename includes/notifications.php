<?php
// Get notifications for current user
if (!isset($user) || !$user) {
    return;
}

$user_id = $user['id'];

// Check deadline reminders (tasks due in next 24 hours)
$upcoming_tasks = queryAll("
    SELECT t.id, t.title, t.deadline, s.name as subject_name, s.color
    FROM " . table('tasks') . " t
    JOIN " . table('subjects') . " s ON t.subject_id = s.id
    WHERE t.user_id = ? 
    AND t.status != 'completed'
    AND t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
    ORDER BY t.deadline ASC
    LIMIT 5
", "i", [$user_id]);

// Check overdue tasks
$overdue_tasks = queryAll("
    SELECT COUNT(*) as count
    FROM " . table('tasks') . "
    WHERE user_id = ? 
    AND status != 'completed'
    AND deadline < NOW()
", "i", [$user_id]);

$overdue_count = $overdue_tasks[0]['count'] ?? 0;

// Check if user hasn't completed task today (streak reminder)
$today_completed = queryOne("
    SELECT COUNT(*) as count
    FROM " . table('tasks') . "
    WHERE user_id = ?
    AND status = 'completed'
    AND DATE(completed_at) = CURDATE()
", "i", [$user_id])['count'] ?? 0;

// Check unread achievements (last 7 days)
$recent_achievements = queryAll("
    SELECT badge_name, earned_at
    FROM " . table('achievements') . "
    WHERE user_id = ?
    AND earned_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY earned_at DESC
    LIMIT 3
", "i", [$user_id]);

$total_notifications = count($upcoming_tasks) + ($overdue_count > 0 ? 1 : 0) + ($today_completed == 0 ? 1 : 0) + count($recent_achievements);
?>

<!-- Notification Bell -->
<div class="dropdown">
    <button class="btn btn-link position-relative" data-bs-toggle="dropdown" id="notificationBell">
        <i class="fas fa-bell fa-lg text-dark"></i>
        <?php if ($total_notifications > 0): ?>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
            <?= $total_notifications > 9 ? '9+' : $total_notifications ?>
        </span>
        <?php endif; ?>
    </button>
    
    <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 380px; max-height: 500px; overflow-y: auto;">
        <div class="dropdown-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>
            <?php if ($total_notifications > 0): ?>
            <span class="badge bg-primary"><?= $total_notifications ?></span>
            <?php endif; ?>
        </div>
        
        <?php if ($total_notifications == 0): ?>
        <div class="dropdown-item text-center text-muted py-4">
            <i class="fas fa-check-circle fa-3x mb-2"></i>
            <p class="mb-0">All caught up! 🎉</p>
        </div>
        <?php else: ?>
        
        <!-- Overdue Tasks Alert -->
        <?php if ($overdue_count > 0): ?>
        <div class="dropdown-item notification-item bg-danger bg-opacity-10 border-start border-danger border-3">
            <div class="d-flex align-items-start">
                <div class="notification-icon bg-danger text-white me-3">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="text-danger">Overdue Tasks!</strong>
                    <p class="mb-1 small">You have <?= $overdue_count ?> overdue task<?= $overdue_count > 1 ? 's' : '' ?></p>
                    <a href="/pages/tasks.php?status=overdue" class="small text-decoration-none">View all →</a>
                </div>
            </div>
        </div>
        <div class="dropdown-divider"></div>
        <?php endif; ?>
        
        <!-- Upcoming Deadlines -->
        <?php if (!empty($upcoming_tasks)): ?>
        <div class="dropdown-header small text-muted">
            <i class="fas fa-clock me-1"></i>Upcoming Deadlines (24h)
        </div>
        <?php foreach ($upcoming_tasks as $task): 
            $hours_left = round((strtotime($task['deadline']) - time()) / 3600);
        ?>
        <a href="/pages/tasks.php" class="dropdown-item notification-item border-start border-warning border-3">
            <div class="d-flex align-items-start">
                <div class="notification-icon bg-warning text-white me-3">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="flex-grow-1">
                    <strong><?= e($task['title']) ?></strong>
                    <p class="mb-1 small text-muted">
                        <span class="badge" style="background: var(--<?= $task['color'] ?>);"><?= e($task['subject_name']) ?></span>
                    </p>
                    <small class="text-warning">
                        <i class="fas fa-hourglass-half me-1"></i>
                        <?= $hours_left ?> hours left
                    </small>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        <div class="dropdown-divider"></div>
        <?php endif; ?>
        
        <!-- Streak Reminder -->
        <?php if ($today_completed == 0 && $user['streak'] > 0): ?>
        <div class="dropdown-item notification-item bg-warning bg-opacity-10 border-start border-warning border-3">
            <div class="d-flex align-items-start">
                <div class="notification-icon bg-warning text-white me-3">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="text-warning">Keep Your Streak!</strong>
                    <p class="mb-1 small">Complete a task today to maintain your <?= $user['streak'] ?> day streak 🔥</p>
                </div>
            </div>
        </div>
        <div class="dropdown-divider"></div>
        <?php endif; ?>
        
        <!-- Recent Achievements -->
        <?php if (!empty($recent_achievements)): ?>
        <div class="dropdown-header small text-muted">
            <i class="fas fa-trophy me-1"></i>Recent Achievements
        </div>
        <?php foreach ($recent_achievements as $achievement): ?>
        <a href="/pages/achievements.php" class="dropdown-item notification-item border-start border-success border-3">
            <div class="d-flex align-items-start">
                <div class="notification-icon bg-success text-white me-3">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="flex-grow-1">
                    <strong class="text-success">Achievement Unlocked!</strong>
                    <p class="mb-1 small"><?= e($achievement['badge_name']) ?></p>
                    <small class="text-muted"><?= timeAgo($achievement['earned_at']) ?></small>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php endif; ?>
        
        <div class="dropdown-divider"></div>
        <div class="dropdown-item text-center">
            <a href="/pages/tasks.php" class="btn btn-sm btn-primary">
                <i class="fas fa-tasks me-1"></i>View All Tasks
            </a>
        </div>
    </div>
</div>

<style>
.notification-dropdown {
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
}

.notification-item {
    padding: 1rem;
    transition: all 0.3s;
    cursor: pointer;
}

.notification-item:hover {
    background-color: #f3f4f6;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

#notificationBell {
    position: relative;
}

#notificationBell .badge {
    font-size: 0.65rem;
    padding: 0.25em 0.5em;
}

/* Bell animation on new notification */
@keyframes ring {
    0% { transform: rotate(0deg); }
    10% { transform: rotate(15deg); }
    20% { transform: rotate(-15deg); }
    30% { transform: rotate(15deg); }
    40% { transform: rotate(-15deg); }
    50% { transform: rotate(0deg); }
}

.notification-bell-ring {
    animation: ring 1s ease-in-out;
}
</style>

<script>
// Auto-refresh notifications every 5 minutes
setInterval(function() {
    location.reload();
}, 300000); // 5 minutes

// Ring bell animation on page load if there are notifications
document.addEventListener('DOMContentLoaded', function() {
    const notificationCount = <?= $total_notifications ?>;
    if (notificationCount > 0) {
        const bell = document.getElementById('notificationBell');
        bell.classList.add('notification-bell-ring');
    }
});
</script>