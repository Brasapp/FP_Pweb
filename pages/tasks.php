<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

// Get filter from URL
$filter_status = $_GET['status'] ?? 'all';
$filter_subject = $_GET['subject'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filters
$where_clauses = ["t.user_id = ?"];
$params = [$user_id];
$types = "i";

if ($filter_status != 'all') {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if ($filter_subject != 'all') {
    $where_clauses[] = "t.subject_id = ?";
    $params[] = (int)$filter_subject;
    $types .= "i";
}

if (!empty($search)) {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

// Get tasks
$tasks = queryAll("
    SELECT t.*, s.name as subject_name, s.color, s.icon 
    FROM " . table('tasks') . " t 
    JOIN " . table('subjects') . " s ON t.subject_id = s.id 
    WHERE $where_sql
    ORDER BY 
        CASE t.status 
            WHEN 'todo' THEN 1
            WHEN 'in_progress' THEN 2
            WHEN 'completed' THEN 3
        END,
        t.priority DESC,
        t.deadline ASC
", $types, $params);

// Get subjects for filter
$subjects = queryAll("SELECT * FROM " . table('subjects') . " WHERE user_id = ? ORDER BY name", "i", [$user_id]);

// Get stats
$stats = [
    'total' => queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ?", "i", [$user_id])['count'],
    'todo' => queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ? AND status = 'todo'", "i", [$user_id])['count'],
    'in_progress' => queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ? AND status = 'in_progress'", "i", [$user_id])['count'],
    'completed' => queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ? AND status = 'completed'", "i", [$user_id])['count']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - Study Tracker</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #10b981;
            --secondary: #047857;
        }
        
        body {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            min-height: 100vh;
        }
        
        .main-container {
            padding: 2rem 0;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: bold;
        }
        
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .task-card:hover {
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transform: translateX(5px);
        }
        
        .task-card.priority-high { border-left-color: #ef4444; }
        .task-card.priority-medium { border-left-color: #f97316; }
        .task-card.priority-low { border-left-color: #10b981; }
        
        .task-card.status-completed {
            opacity: 0.7;
            background: #f9fafb;
        }
        
        .btn-add-task {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #047857);
            border: none;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 5px 25px rgba(16, 185, 129, 0.5);
            z-index: 1000;
        }
        
        .btn-add-task:hover {
            transform: scale(1.1) rotate(90deg);
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container main-container">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-white">
                <i class="fas fa-tasks me-2"></i>All Tasks
            </h2>
            <div class="d-flex gap-2">
                <span class="stats-badge bg-primary text-white">
                    <i class="fas fa-list"></i> <?= $stats['total'] ?>
                </span>
                <span class="stats-badge bg-warning text-dark">
                    <i class="fas fa-circle"></i> <?= $stats['todo'] ?>
                </span>
                <span class="stats-badge bg-info text-white">
                    <i class="fas fa-spinner"></i> <?= $stats['in_progress'] ?>
                </span>
                <span class="stats-badge bg-success text-white">
                    <i class="fas fa-check-circle"></i> <?= $stats['completed'] ?>
                </span>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?= e($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="todo" <?= $filter_status == 'todo' ? 'selected' : '' ?>>To Do</option>
                        <option value="in_progress" <?= $filter_status == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Subject</label>
                    <select name="subject" class="form-select">
                        <option value="all" <?= $filter_subject == 'all' ? 'selected' : '' ?>>All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?= $subject['id'] ?>" <?= $filter_subject == $subject['id'] ? 'selected' : '' ?>>
                            <?= e($subject['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Tasks List -->
        <?php if (empty($tasks)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>No tasks found. Create your first task!
        </div>
        <?php else: ?>
        <?php foreach ($tasks as $task): ?>
        <div class="task-card priority-<?= $task['priority'] ?> status-<?= $task['status'] ?>">
            <div class="row align-items-center">
                <div class="col-md-1">
                    <input type="checkbox" class="form-check-input task-checkbox" 
                           data-task-id="<?= $task['id'] ?>"
                           <?= $task['status'] == 'completed' ? 'checked' : '' ?>>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-1 <?= $task['status'] == 'completed' ? 'text-decoration-line-through text-muted' : '' ?>">
                        <?= e($task['title']) ?>
                    </h5>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <span class="badge" style="background: var(--<?= $task['color'] ?>);">
                            <i class="fas fa-<?= $task['icon'] ?>"></i> <?= e($task['subject_name']) ?>
                        </span>
                        <?= getPriorityBadge($task['priority']) ?>
                        <?= getStatusBadge($task['status']) ?>
                        <span class="badge bg-primary">+<?= $task['xp_reward'] ?> XP</span>
                        <small class="text-<?= getDeadlineColor($task['deadline']) ?>">
                            <i class="fas fa-calendar me-1"></i><?= formatDate($task['deadline']) ?>
                        </small>
                    </div>
                </div>
                <div class="col-md-3">
                    <?php if (!empty($task['description'])): ?>
                    <small class="text-muted"><?= e(substr($task['description'], 0, 100)) ?>...</small>
                    <?php endif; ?>
                </div>
                <div class="col-md-2 text-end">
                    <button class="btn btn-sm btn-outline-primary" onclick="editTask(<?= $task['id'] ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTask(<?= $task['id'] ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Add Task Button -->
    <button class="btn-add-task" onclick="openAddTaskModal()">
        <i class="fas fa-plus"></i>
    </button>

    <?php include '../includes/task-modal.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/canvas-confetti/1.6.0/confetti.browser.min.js"></script>
    <script src="/assets/js/task-modal.js"></script>
    
    <script>
        // ==========================================
        // COMPLETE TASK WITH API
        // ==========================================
        document.querySelectorAll('.task-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (this.checked && this.dataset.taskId) {
                    completeTask(this.dataset.taskId);
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
                    if (typeof confetti !== 'undefined') {
                        confetti({
                            particleCount: 150,
                            spread: 80,
                            origin: { y: 0.6 }
                        });
                    }

                    // Build message
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
                        message += `\n🏆 New Achievement(s):\n`;
                        data.new_achievements.forEach(achievement => {
                            message += `   • ${achievement}\n`;
                        });
                    }

                    alert(message);

                    // Reload page to show updated stats
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    const checkbox = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (checkbox) checkbox.checked = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to complete task. Please try again.');
                const checkbox = document.querySelector(`[data-task-id="${taskId}"]`);
                if (checkbox) checkbox.checked = false;
            });
        }

        // ==========================================
        // DELETE TASK WITH API
        // ==========================================
        function deleteTask(taskId) {
            if (!confirm('Are you sure you want to delete this task?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('task_id', taskId);

            fetch('/api/task-crud.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete task. Please try again.');
            });
        }

        // ==========================================
        // EDIT TASK - Load data and open modal
        // ==========================================
        function editTask(taskId) {
            // Fetch task data via API
            fetch(`/api/task-crud.php?action=get&task_id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.task) {
                    openEditTaskModal(data.task);
                } else {
                    alert('Failed to load task data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load task');
            });
        }
    </script>
</body>
</html>