<?php
require_once '../config/database.php';
header('Content-Type: application/json');

requireLogin();

$user_id = $_SESSION['st_user_id'];
$response = ['success' => false, 'message' => ''];

// ==========================================
// CREATE TASK
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = clean($_POST['title']);
    $subject_id = intval($_POST['subject_id']);
    $description = clean($_POST['description'] ?? '');
    $deadline = $_POST['deadline'];
    $priority = $_POST['priority'] ?? 'medium';
    $estimated_time = intval($_POST['estimated_time'] ?? 30);
    
    // Calculate XP reward based on priority
    $xp_rewards = ['low' => 10, 'medium' => 30, 'high' => 50];
    $xp_reward = $xp_rewards[$priority];
    
    $task_id = queryExecute("
        INSERT INTO " . table('tasks') . " 
        (user_id, subject_id, title, description, deadline, priority, estimated_time, xp_reward, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ", "iisssiii", [$user_id, $subject_id, $title, $description, $deadline, $priority, $estimated_time, $xp_reward]);
    
    if ($task_id) {
        // Update subject total_tasks
        queryExecute("
            UPDATE " . table('subjects') . " 
            SET total_tasks = total_tasks + 1,
                progress_percentage = (completed_tasks * 100.0) / (total_tasks + 1)
            WHERE id = ? AND user_id = ?
        ", "ii", [$subject_id, $user_id]);
        
        $response['success'] = true;
        $response['message'] = 'Task created successfully!';
        $response['task_id'] = $task_id;
    } else {
        $response['message'] = 'Failed to create task!';
    }
}

// ==========================================
// READ TASKS (GET)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'list') {
        $status = $_GET['status'] ?? 'all';
        $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;
        
        $where_clauses = ["t.user_id = ?"];
        $params = [$user_id];
        $types = "i";
        
        if ($status !== 'all') {
            $where_clauses[] = "t.status = ?";
            $params[] = $status;
            $types .= "s";
        }
        
        if ($subject_id) {
            $where_clauses[] = "t.subject_id = ?";
            $params[] = $subject_id;
            $types .= "i";
        }
        
        $where_sql = implode(" AND ", $where_clauses);
        
        $tasks = queryAll("
            SELECT t.*, s.name as subject_name, s.color as subject_color, s.icon as subject_icon
            FROM " . table('tasks') . " t
            LEFT JOIN " . table('subjects') . " s ON t.subject_id = s.id
            WHERE $where_sql
            ORDER BY t.deadline ASC
        ", $types, $params);
        
        $response['success'] = true;
        $response['tasks'] = $tasks;
    } 
    elseif ($_GET['action'] === 'get' && isset($_GET['task_id'])) {
        // Get single task for editing
        $task_id = intval($_GET['task_id']);
        
        $task = queryOne("
            SELECT t.*, s.name as subject_name 
            FROM " . table('tasks') . " t
            LEFT JOIN " . table('subjects') . " s ON t.subject_id = s.id
            WHERE t.id = ? AND t.user_id = ?
        ", "ii", [$task_id, $user_id]);
        
        if ($task) {
            $response['success'] = true;
            $response['task'] = $task;
        } else {
            $response['message'] = 'Task not found';
        }
    }
}

// ==========================================
// UPDATE TASK
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $task_id = intval($_POST['task_id']);
    $title = clean($_POST['title']);
    $description = clean($_POST['description'] ?? '');
    $deadline = $_POST['deadline'];
    $priority = $_POST['priority'];
    $status = $_POST['status'] ?? 'todo';
    
    $success = queryExecute("
        UPDATE " . table('tasks') . " 
        SET title = ?, description = ?, deadline = ?, priority = ?, status = ?
        WHERE id = ? AND user_id = ?
    ", "sssssii", [$title, $description, $deadline, $priority, $status, $task_id, $user_id]);
    
    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Task updated successfully!';
    } else {
        $response['message'] = 'Failed to update task!';
    }
}

// ==========================================
// COMPLETE TASK (WITH XP, STREAK, BADGES!)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    $task_id = intval($_POST['task_id']);
    
    // Get task details
    $task = queryOne("
        SELECT subject_id, xp_reward, deadline 
        FROM " . table('tasks') . " 
        WHERE id = ? AND user_id = ?
    ", "ii", [$task_id, $user_id]);
    
    if ($task) {
        $xp_earned = $task['xp_reward'];
        $bonus_xp = 0;
        $is_late = false;
        
        // Calculate bonus XP (Early completion)
        $now = new DateTime();
        $deadline = new DateTime($task['deadline']);
        
        if ($now < $deadline) {
            // Completed before deadline - BONUS!
            $diff = $now->diff($deadline);
            $hours_early = ($diff->days * 24) + $diff->h;
            
            if ($hours_early >= 24) {
                $bonus_xp = 20; // Bonus untuk selesai >1 hari sebelum deadline
            } else {
                $bonus_xp = 10; // Bonus untuk selesai tepat waktu
            }
        } else {
            // Completed after deadline - STILL GET BASE XP (no bonus)
            $is_late = true;
            $bonus_xp = 0;
        }
        
        $total_xp = $xp_earned + $bonus_xp;
        
        // 1. Update task status
        queryExecute("
            UPDATE " . table('tasks') . " 
            SET status = 'completed', completed_at = NOW()
            WHERE id = ? AND user_id = ?
        ", "ii", [$task_id, $user_id]);
        
        // 2. Update user XP
        queryExecute("
            UPDATE " . table('users') . " 
            SET xp = xp + ? 
            WHERE id = ?
        ", "ii", [$total_xp, $user_id]);
        
        // 3. Update streak
        $user = getCurrentUser();
        $today = date('Y-m-d');
        $last_activity = $user['last_activity'] ? date('Y-m-d', strtotime($user['last_activity'])) : null;
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $new_streak = $user['streak'];
        
        if ($last_activity == $today) {
            // Sudah aktif hari ini, streak tidak berubah
        } elseif ($last_activity == $yesterday) {
            // Hari berturut-turut, streak naik
            $new_streak++;
        } else {
            // Streak putus, reset ke 1
            $new_streak = 1;
        }
        
        queryExecute("
            UPDATE " . table('users') . " 
            SET streak = ?, last_activity = NOW() 
            WHERE id = ?
        ", "ii", [$new_streak, $user_id]);
        
        // 4. Update subject progress
        queryExecute("
            UPDATE " . table('subjects') . " 
            SET completed_tasks = completed_tasks + 1,
                progress_percentage = ((completed_tasks + 1) * 100.0) / GREATEST(total_tasks, 1)
            WHERE id = ? AND user_id = ?
        ", "ii", [$task['subject_id'], $user_id]);
        
        // 5. Check for level up
        $current_xp = $user['xp'] + $total_xp;
        $current_level = $user['level'];
        $xp_for_next_level = $current_level * 1000;
        
        $level_up = false;
        $new_level = $current_level;
        
        while ($current_xp >= $xp_for_next_level) {
            $new_level++;
            $xp_for_next_level = $new_level * 1000;
            $level_up = true;
        }
        
        if ($level_up) {
            queryExecute("
                UPDATE " . table('users') . " 
                SET level = ? 
                WHERE id = ?
            ", "ii", [$new_level, $user_id]);
        }
        
        // 6. Check and award achievements
        $new_achievements = checkAndAwardAchievements($user_id, $new_streak);
        
        $response['success'] = true;
        $response['message'] = $is_late ? 'Task completed (late, but you still got XP)! 💪' : 'Task completed! 🎉';
        $response['xp_earned'] = $xp_earned;
        $response['bonus_xp'] = $bonus_xp;
        $response['total_xp'] = $total_xp;
        $response['level_up'] = $level_up;
        $response['new_level'] = $new_level;
        $response['new_streak'] = $new_streak;
        $response['new_achievements'] = $new_achievements;
        $response['is_late'] = $is_late;
    } else {
        $response['message'] = 'Task not found!';
    }
}

// ==========================================
// DELETE TASK
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $task_id = intval($_POST['task_id']);
    
    $task = queryOne("
        SELECT subject_id, status 
        FROM " . table('tasks') . " 
        WHERE id = ? AND user_id = ?
    ", "ii", [$task_id, $user_id]);
    
    if ($task) {
        $success = queryExecute("
            DELETE FROM " . table('tasks') . " 
            WHERE id = ? AND user_id = ?
        ", "ii", [$task_id, $user_id]);
        
        if ($success) {
            // Update subject counts
            if ($task['status'] == 'completed') {
                queryExecute("
                    UPDATE " . table('subjects') . " 
                    SET total_tasks = total_tasks - 1, 
                        completed_tasks = completed_tasks - 1,
                        progress_percentage = (GREATEST(completed_tasks - 1, 0) * 100.0) / GREATEST(total_tasks - 1, 1)
                    WHERE id = ? AND user_id = ?
                ", "ii", [$task['subject_id'], $user_id]);
            } else {
                queryExecute("
                    UPDATE " . table('subjects') . " 
                    SET total_tasks = total_tasks - 1,
                        progress_percentage = (completed_tasks * 100.0) / GREATEST(total_tasks - 1, 1)
                    WHERE id = ? AND user_id = ?
                ", "ii", [$task['subject_id'], $user_id]);
            }
            
            $response['success'] = true;
            $response['message'] = 'Task deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete task!';
        }
    } else {
        $response['message'] = 'Task not found!';
    }
}

// ==========================================
// FUNCTION: CHECK AND AWARD ACHIEVEMENTS
// ==========================================
function checkAndAwardAchievements($user_id, $current_streak) {
    $new_achievements = [];
    
    // Get stats
    $early_count = queryOne("
        SELECT COUNT(*) as count 
        FROM " . table('tasks') . " 
        WHERE user_id = ? AND status = 'completed' AND completed_at < deadline
    ", "i", [$user_id])['count'];
    
    $completed_count = queryOne("
        SELECT COUNT(*) as count 
        FROM " . table('tasks') . " 
        WHERE user_id = ? AND status = 'completed'
    ", "i", [$user_id])['count'];
    
    // Early Bird - 10 tasks before deadline
    if ($early_count >= 10) {
        if (awardAchievement($user_id, 'Early Bird', 'fa-sun')) {
            $new_achievements[] = 'Early Bird';
        }
    }
    
    // Week Warrior - 7 day streak
    if ($current_streak >= 7) {
        if (awardAchievement($user_id, 'Week Warrior', 'fa-fire')) {
            $new_achievements[] = 'Week Warrior';
        }
    }
    
    // Consistent - 30 day streak
    if ($current_streak >= 30) {
        if (awardAchievement($user_id, 'Consistent', 'fa-calendar')) {
            $new_achievements[] = 'Consistent';
        }
    }
    
    // Semester Hero - 100 completed tasks
    if ($completed_count >= 100) {
        if (awardAchievement($user_id, 'Semester Hero', 'fa-trophy')) {
            $new_achievements[] = 'Semester Hero';
        }
    }
    
    // Perfectionist - Complete all tasks in one subject
    $perfect_subject = queryOne("
        SELECT id FROM " . table('subjects') . " 
        WHERE user_id = ? AND total_tasks > 0 AND completed_tasks >= total_tasks
        LIMIT 1
    ", "i", [$user_id]);
    
    if ($perfect_subject) {
        if (awardAchievement($user_id, 'Perfectionist', 'fa-star')) {
            $new_achievements[] = 'Perfectionist';
        }
    }
    
    return $new_achievements;
}

function awardAchievement($user_id, $badge_name, $badge_icon) {
    // Check if already awarded
    $existing = queryOne("
        SELECT id FROM " . table('achievements') . " 
        WHERE user_id = ? AND badge_name = ?
    ", "is", [$user_id, $badge_name]);
    
    if ($existing) {
        return false; // Already has this achievement
    }
    
    // Award new achievement
    queryExecute("
        INSERT INTO " . table('achievements') . " 
        (user_id, badge_name, badge_icon, earned_at) 
        VALUES (?, ?, ?, NOW())
    ", "iss", [$user_id, $badge_name, $badge_icon]);
    
    return true;
}

echo json_encode($response);
?>