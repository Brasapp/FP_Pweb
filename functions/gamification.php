<?php
// Gamification Functions

/**
 * Calculate XP needed for next level
 */
function getXPForNextLevel($current_level) {
    return $current_level * 100;
}

/**
 * Get level title based on level number
 */
function getLevelTitle($level) {
    $titles = [
        1 => 'Freshman',
        5 => 'Sophomore',
        10 => 'Junior',
        15 => 'Senior',
        20 => 'Graduate',
        25 => 'Master',
        30 => 'PhD Candidate',
        40 => 'Professor',
        50 => 'Dean',
        75 => 'Chancellor',
        100 => 'Legend'
    ];
    
    $title = 'Freshman';
    foreach ($titles as $lvl => $t) {
        if ($level >= $lvl) {
            $title = $t;
        }
    }
    
    return $title;
}

/**
 * Add XP to user and check for level up
 */
function addXP($user_id, $xp_amount) {
    $conn = getDBConnection();
    
    // Get current user stats
    $stmt = $conn->prepare("SELECT level, xp FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    $current_level = $user['level'];
    $current_xp = $user['xp'];
    $new_xp = $current_xp + $xp_amount;
    
    $xp_needed = getXPForNextLevel($current_level);
    $level_up = false;
    $new_level = $current_level;
    
    // Check for level up
    while ($new_xp >= $xp_needed) {
        $new_level++;
        $new_xp -= $xp_needed;
        $xp_needed = getXPForNextLevel($new_level);
        $level_up = true;
    }
    
    // Update user
    $stmt = $conn->prepare("UPDATE users SET xp = xp + ?, level = ? WHERE id = ?");
    $stmt->bind_param("iii", $xp_amount, $new_level, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->close();
    
    return [
        'level_up' => $level_up,
        'new_level' => $new_level,
        'xp_gained' => $xp_amount,
        'total_xp' => $new_xp
    ];
}

/**
 * Calculate XP reward based on task priority
 */
function calculateXPReward($priority, $estimated_time = 30) {
    $base_xp = [
        'low' => 10,
        'medium' => 20,
        'high' => 40
    ];
    
    $xp = $base_xp[$priority] ?? 20;
    
    // Bonus for longer tasks
    if ($estimated_time >= 120) {
        $xp += 10;
    } elseif ($estimated_time >= 60) {
        $xp += 5;
    }
    
    return $xp;
}

/**
 * Calculate bonus XP for early completion
 */
function calculateBonusXP($deadline, $completion_time = null) {
    $completion_time = $completion_time ?? new DateTime();
    $deadline_dt = new DateTime($deadline);
    
    if ($completion_time >= $deadline_dt) {
        return 0; // No bonus if completed after deadline
    }
    
    $diff = $completion_time->diff($deadline_dt);
    $hours_early = ($diff->days * 24) + $diff->h;
    
    if ($hours_early >= 24) {
        return 20; // Early Bird bonus
    } elseif ($hours_early > 0) {
        return 10; // On time bonus
    }
    
    return 0;
}

/**
 * Update streak
 */
function updateStreak($user_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT streak, last_activity FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    $today = date('Y-m-d');
    $last_activity = $user['last_activity'];
    $current_streak = $user['streak'];
    
    $bonus_xp = 0;
    $new_streak = $current_streak;
    
    if ($last_activity) {
        $last_date = new DateTime($last_activity);
        $today_date = new DateTime($today);
        $diff = $last_date->diff($today_date)->days;
        
        if ($diff == 0) {
            // Same day, no change
            $new_streak = $current_streak;
        } elseif ($diff == 1) {
            // Consecutive day
            $new_streak = $current_streak + 1;
            
            // Streak milestone bonus
            if ($new_streak % 7 == 0) {
                $bonus_xp = 100; // Weekly streak bonus
            } elseif ($new_streak % 30 == 0) {
                $bonus_xp = 500; // Monthly streak bonus
            }
        } else {
            // Streak broken
            $new_streak = 1;
        }
    } else {
        $new_streak = 1;
    }
    
    // Update database
    $stmt = $conn->prepare("UPDATE users SET streak = ?, last_activity = ?, xp = xp + ? WHERE id = ?");
    $stmt->bind_param("isii", $new_streak, $today, $bonus_xp, $user_id);
    $stmt->execute();
    $stmt->close();
    
    $conn->close();
    
    return [
        'streak' => $new_streak,
        'bonus_xp' => $bonus_xp,
        'streak_broken' => $current_streak > $new_streak
    ];
}

/**
 * Check and grant achievements
 */
function checkAndGrantAchievements($user_id) {
    $conn = getDBConnection();
    $granted = [];
    
    // Early Bird - 10 tasks before deadline
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE user_id = ? 
        AND status = 'completed' 
        AND completed_at < deadline
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] >= 10) {
        if (grantAchievement($user_id, 'Early Bird', 'fa-sun')) {
            $granted[] = 'Early Bird';
        }
    }
    $stmt->close();
    
    // Week Warrior - 7 days streak
    $stmt = $conn->prepare("SELECT streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['streak'] >= 7) {
        if (grantAchievement($user_id, 'Week Warrior', 'fa-fire')) {
            $granted[] = 'Week Warrior';
        }
    }
    $stmt->close();
    
    // Perfectionist - Complete all tasks in one subject
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM subjects 
        WHERE user_id = ? 
        AND total_tasks > 0 
        AND completed_tasks = total_tasks
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] >= 1) {
        if (grantAchievement($user_id, 'Perfectionist', 'fa-star')) {
            $granted[] = 'Perfectionist';
        }
    }
    $stmt->close();
    
    // Speed Runner - 5 tasks in 1 day
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE user_id = ? 
        AND DATE(completed_at) = ? 
        AND status = 'completed'
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] >= 5) {
        if (grantAchievement($user_id, 'Speed Runner', 'fa-bolt')) {
            $granted[] = 'Speed Runner';
        }
    }
    $stmt->close();
    
    // Semester Hero - 100 tasks
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE user_id = ? 
        AND status = 'completed'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] >= 100) {
        if (grantAchievement($user_id, 'Semester Hero', 'fa-trophy')) {
            $granted[] = 'Semester Hero';
        }
    }
    $stmt->close();
    
    // Night Owl - Complete task between 12 AM - 5 AM
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tasks 
        WHERE user_id = ? 
        AND status = 'completed'
        AND HOUR(completed_at) >= 0 
        AND HOUR(completed_at) < 5
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] >= 1) {
        if (grantAchievement($user_id, 'Night Owl', 'fa-moon')) {
            $granted[] = 'Night Owl';
        }
    }
    $stmt->close();
    
    // Focus Master - 50 focus sessions
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM focus_sessions 
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] >= 50) {
        if (grantAchievement($user_id, 'Focus Master', 'fa-crosshairs')) {
            $granted[] = 'Focus Master';
        }
    }
    $stmt->close();
    
    $conn->close();
    
    return $granted;
}

/**
 * Grant achievement to user
 */
function grantAchievement($user_id, $badge_name, $badge_icon) {
    $conn = getDBConnection();
    
    // Check if already granted
    $stmt = $conn->prepare("SELECT id FROM achievements WHERE user_id = ? AND badge_name = ?");
    $stmt->bind_param("is", $user_id, $badge_name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->close();
        return false; // Already granted
    }
    $stmt->close();
    
    // Grant achievement
    $stmt = $conn->prepare("INSERT INTO achievements (user_id, badge_name, badge_icon) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $badge_name, $badge_icon);
    $success = $stmt->execute();
    $stmt->close();
    
    // Grant bonus XP for achievement
    if ($success) {
        $stmt = $conn->prepare("UPDATE users SET xp = xp + 50 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->close();
    
    return $success;
}

/**
 * Get user achievements
 */
function getUserAchievements($user_id) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM achievements 
        WHERE user_id = ? 
        ORDER BY earned_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $achievements = [];
    while ($row = $result->fetch_assoc()) {
        $achievements[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $achievements;
}
?>