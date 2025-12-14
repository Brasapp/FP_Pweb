<?php
require_once 'config/database.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    logout();
}

$user_id = $user['id'];

// Get user stats
$total_tasks = queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ?", "i", [$user_id])['count'] ?? 0;

$completed_tasks = queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ? AND status = 'completed'", "i", [$user_id])['count'] ?? 0;

$today_tasks = queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ? AND DATE(deadline) = CURDATE() AND status != 'completed'", "i", [$user_id])['count'] ?? 0;

$pending_tasks = $total_tasks - $completed_tasks;

// Get subjects with progress
$subjects = queryAll("SELECT * FROM " . table('subjects') . " WHERE user_id = ? ORDER BY name", "i", [$user_id]);

// Get today's task list
$tasks_today = queryAll("
    SELECT t.*, s.name as subject_name, s.color, s.icon 
    FROM " . table('tasks') . " t 
    JOIN " . table('subjects') . " s ON t.subject_id = s.id 
    WHERE t.user_id = ? AND DATE(t.deadline) = CURDATE() AND t.status != 'completed'
    ORDER BY t.priority DESC, t.deadline ASC
", "i", [$user_id]);

// Get upcoming tasks
$tasks_upcoming = queryAll("
    SELECT t.*, s.name as subject_name, s.color, s.icon 
    FROM " . table('tasks') . " t 
    JOIN " . table('subjects') . " s ON t.subject_id = s.id 
    WHERE t.user_id = ? AND t.status != 'completed' AND DATE(t.deadline) > CURDATE()
    ORDER BY t.deadline ASC 
    LIMIT 5
", "i", [$user_id]);

// Get achievements count
$achievements_count = queryOne("SELECT COUNT(*) as count FROM " . table('achievements') . " WHERE user_id = ?", "i", [$user_id])['count'] ?? 0;

// Calculate level progress
$level_percentage = getLevelProgress($user['xp'], $user['level']);
$current_level_xp = ($user['level'] - 1) * 1000;
$next_level_xp = $user['level'] * 1000;
$xp_progress = $user['xp'] - $current_level_xp;
$xp_needed = $next_level_xp - $current_level_xp;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Study Tracker</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #10b981;
            --secondary: #047857;
            --blue: #3b82f6;
            --green: #10b981;
            --purple: #8b5cf6;
            --red: #ef4444;
            --orange: #f97316;
            --yellow: #eab308;
            --cyan: #06b6d4;
        }
        
        body {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            padding: 2rem 0;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
        }
        
        .level-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: bold;
        }
        
        .streak-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: bold;
        }
        
        .xp-bar-container {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            height: 30px;
            overflow: hidden;
            margin-top: 1rem;
        }
        
        .xp-bar {
            background: linear-gradient(90deg, #fbbf24, #f59e0b, #ef4444);
            height: 100%;
            transition: width 1s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.9rem;
            width: 0%;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .subject-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .subject-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }
        
        .subject-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }
        
        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            position: relative;
        }
        
        .progress-circle::before {
            content: '';
            position: absolute;
            inset: 5px;
            background: white;
            border-radius: 50%;
            z-index: 0;
        }
        
        .progress-circle span {
            position: relative;
            z-index: 1;
        }
        
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .task-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }
        
        .task-card.priority-high { border-left-color: var(--red); }
        .task-card.priority-medium { border-left-color: var(--orange); }
        .task-card.priority-low { border-left-color: var(--green); }
        
        .task-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--green);
        }
        
        .btn-add-task {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 25px rgba(16, 185, 129, 0.5);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .btn-add-task:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.6);
        }
        
        .section-title {
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .badge-pill {
            padding: 0.35rem 0.8rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid main-container">
        <!-- Welcome Card -->
        <div class="welcome-card" data-aos="fade-up">
            <h2>Hi, <?= e($user['full_name'] ?? $user['username']) ?>! 👋</h2>
            <div class="d-flex gap-3 mt-3 flex-wrap">
                <div class="level-badge">
                    <i class="fas fa-star"></i>
                    <span>Level <?= $user['level'] ?></span>
                </div>
                <?php if ($user['streak'] > 0): ?>
                <div class="streak-badge">
                    <i class="fas fa-fire"></i>
                    <span><?= $user['streak'] ?> Day Streak</span>
                </div>
                <?php endif; ?>
                <div class="badge bg-warning text-dark p-2">
                    <i class="fas fa-trophy me-1"></i> <?= $achievements_count ?> Badges
                </div>
            </div>
            <div class="xp-bar-container">
                <div class="xp-bar" data-width="<?= $level_percentage ?>">
                    <?= $xp_progress ?> / <?= $xp_needed ?> XP
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6" data-aos="fade-up" data-aos-delay="100">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Total Tasks</p>
                            <h3 class="mb-0 fw-bold"><?= $total_tasks ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #10b981, #047857);">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" data-aos="fade-up" data-aos-delay="200">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Completed</p>
                            <h3 class="mb-0 fw-bold text-success"><?= $completed_tasks ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" data-aos="fade-up" data-aos-delay="300">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Pending</p>
                            <h3 class="mb-0 fw-bold text-warning"><?= $pending_tasks ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                            <i class="fas fa-spinner"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6" data-aos="fade-up" data-aos-delay="400">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-1">Due Today</p>
                            <h3 class="mb-0 fw-bold text-primary"><?= $today_tasks ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mata Kuliah Section -->
        <div class="section-title" data-aos="fade-up">
            <i class="fas fa-book"></i> Mata Kuliah (<?= count($subjects) ?>)
        </div>
        
        <div class="row">
            <?php 
            $delay = 100;
            $color_map = [
                'blue' => 'var(--blue)',
                'green' => 'var(--green)',
                'purple' => 'var(--purple)',
                'red' => 'var(--red)',
                'orange' => 'var(--orange)',
                'yellow' => 'var(--yellow)',
                'cyan' => 'var(--cyan)'
            ];
            
            foreach ($subjects as $subject): 
                $color = $color_map[$subject['color']] ?? 'var(--primary)';
                $progress = round($subject['progress_percentage']);
            ?>
            <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                <div class="subject-card" onclick="window.location.href='/pages/tasks.php?subject_id=<?= $subject['id'] ?>'">
                    <div style="background: <?= $color ?>; position: absolute; top: 0; left: 0; right: 0; height: 5px;"></div>
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="subject-icon" style="background: linear-gradient(135deg, <?= $color ?>, <?= $color ?>dd);">
                                <i class="fas fa-<?= $subject['icon'] ?>"></i>
                            </div>
                            <h6 class="fw-bold mb-2"><?= e($subject['name']) ?></h6>
                            <div class="mb-2">
                                <span class="badge bg-warning text-dark"><?= $subject['total_tasks'] - $subject['completed_tasks'] ?> Active</span>
                                <span class="badge bg-success"><?= $subject['completed_tasks'] ?> Done</span>
                            </div>
                        </div>
                        <div class="progress-circle" style="background: conic-gradient(<?= $color ?> <?= $progress ?>%, #e5e7eb 0);">
                            <span><?= $progress ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php 
            $delay += 50;
            endforeach; 
            ?>
        </div>

        <!-- Today's Quest -->
        <?php if (!empty($tasks_today)): ?>
        <div class="section-title mt-5" data-aos="fade-up">
            <i class="fas fa-bullseye"></i> Today's Quest (<?= count($tasks_today) ?>)
        </div>

        <div class="row">
            <div class="col-lg-8">
                <?php foreach ($tasks_today as $i => $task): ?>
                <div class="task-card priority-<?= $task['priority'] ?>" data-aos="fade-up" data-aos-delay="<?= ($i * 50) ?>">
                    <div class="d-flex align-items-center gap-3">
                        <input type="checkbox" class="task-checkbox" data-task-id="<?= $task['id'] ?>">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?= e($task['title']) ?></h6>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <span class="badge-pill" style="background: var(--<?= $task['color'] ?>); color: white;">
                                    <i class="fas fa-<?= $task['icon'] ?>"></i> <?= e($task['subject_name']) ?>
                                </span>
                                <?= getPriorityBadge($task['priority']) ?>
                                <span class="badge bg-primary">+<?= $task['xp_reward'] ?> XP</span>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i><?= formatDateTime($task['deadline'], 'H:i') ?>
                                </small>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-play"></i> Focus
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info mt-4" data-aos="fade-up">
            <i class="fas fa-info-circle me-2"></i>No tasks due today. Great job! 🎉
        </div>
        <?php endif; ?>

        <!-- Upcoming Tasks -->
        <?php if (!empty($tasks_upcoming)): ?>
        <div class="section-title mt-5" data-aos="fade-up">
            <i class="fas fa-calendar-week"></i> Upcoming Tasks (<?= count($tasks_upcoming) ?>)
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <?php foreach ($tasks_upcoming as $i => $task): ?>
                <div class="task-card priority-<?= $task['priority'] ?>" data-aos="fade-up" data-aos-delay="<?= ($i * 50) ?>">
                    <div class="d-flex align-items-center gap-3">
                        <input type="checkbox" class="task-checkbox" data-task-id="<?= $task['id'] ?>">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><?= e($task['title']) ?></h6>
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <span class="badge-pill" style="background: var(--<?= $task['color'] ?>); color: white;">
                                    <i class="fas fa-<?= $task['icon'] ?>"></i> <?= e($task['subject_name']) ?>
                                </span>
                                <?= getPriorityBadge($task['priority']) ?>
                                <span class="badge bg-primary">+<?= $task['xp_reward'] ?> XP</span>
                                <small class="text-<?= getDeadlineColor($task['deadline']) ?>">
                                    <i class="fas fa-calendar me-1"></i><?= formatDate($task['deadline']) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <button class="btn-add-task" onclick="openAddTaskModal()" title="Add New Task">
        <i class="fas fa-plus"></i>
    </button>

    <?php include 'includes/task-modal.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <script src="/assets/js/task-modal.js"></script>
    
    <script>
        AOS.init({ duration: 800, once: true, offset: 50 });
        
        window.addEventListener('load', function() {
            const xpBar = document.querySelector('.xp-bar');
            if (xpBar) {
                setTimeout(() => {
                    xpBar.style.width = xpBar.dataset.width + '%';
                }, 300);
            }
        });

        // ==========================================
        // TASK COMPLETION WITH API
        // ==========================================
        document.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked) {
                    const taskId = this.dataset.taskId;
                    completeTask(taskId);
                }
            });
        });

        function completeTask(taskId) {
            const formData = new FormData();
            formData.append('action', 'complete');
            formData.append('task_id', taskId);

            fetch('/api/task-crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show confetti
                    confetti({
                        particleCount: 100,
                        spread: 70,
                        origin: { y: 0.6 }
                    });

                    // Build notification message
                    let message = `🎉 ${data.message}\n\n`;
                    message += `⭐ XP Earned: ${data.xp_earned}`;
                    
                    if (data.bonus_xp > 0) {
                        message += ` + ${data.bonus_xp} bonus`;
                    }
                    
                    message += ` = ${data.total_xp} total XP\n`;

                    if (data.level_up) {
                        message += `\n🆙 LEVEL UP! You are now Level ${data.new_level}!\n`;
                    }

                    if (data.new_streak > 1) {
                        message += `\n🔥 ${data.new_streak} Day Streak!\n`;
                    }

                    if (data.new_achievements && data.new_achievements.length > 0) {
                        message += `\n🏆 New Achievement(s) Unlocked:\n`;
                        data.new_achievements.forEach(achievement => {
                            message += `   • ${achievement}\n`;
                        });
                    }

                    alert(message);

                    // Remove task card with animation
                    const checkbox = document.querySelector(`[data-task-id="${taskId}"]`);
                    const taskCard = checkbox ? checkbox.closest('.task-card') : null;
                    
                    if (taskCard) {
                        taskCard.style.transition = 'all 0.3s ease';
                        taskCard.style.opacity = '0';
                        taskCard.style.transform = 'translateX(50px)';
                        
                        setTimeout(() => {
                            taskCard.remove();
                            
                            // Reload if no more tasks
                            if (document.querySelectorAll('.task-card').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        location.reload();
                    }
                } else {
                    alert('Error: ' + data.message);
                    // Uncheck checkbox on error
                    const checkbox = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (checkbox) checkbox.checked = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to complete task. Please try again.');
                // Uncheck checkbox on error
                const checkbox = document.querySelector(`[data-task-id="${taskId}"]`);
                if (checkbox) checkbox.checked = false;
            });
        }
    </script>
</body>
</html>