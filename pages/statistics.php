<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

// Get overall stats
$total_tasks = queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ?", "i", [$user_id])['count'];
$completed_tasks = queryOne("SELECT COUNT(*) as count FROM " . table('tasks') . " WHERE user_id = ? AND status = 'completed'", "i", [$user_id])['count'];
$pending_tasks = $total_tasks - $completed_tasks;
$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

// Tasks by subject
$tasks_by_subject = queryAll("
    SELECT s.name, s.color, COUNT(t.id) as total, 
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM " . table('subjects') . " s
    LEFT JOIN " . table('tasks') . " t ON s.id = t.subject_id
    WHERE s.user_id = ?
    GROUP BY s.id, s.name, s.color
    HAVING total > 0
    ORDER BY total DESC
", "i", [$user_id]);

// Tasks by priority
$tasks_by_priority = queryAll("
    SELECT priority, COUNT(*) as count
    FROM " . table('tasks') . "
    WHERE user_id = ?
    GROUP BY priority
", "i", [$user_id]);

// Weekly productivity (last 7 days)
$weekly_data = queryAll("
    SELECT DATE(completed_at) as date, COUNT(*) as count
    FROM " . table('tasks') . "
    WHERE user_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(completed_at)
    ORDER BY date
", "i", [$user_id]);

// Monthly productivity (last 30 days)
$monthly_data = queryAll("
    SELECT DATE(completed_at) as date, COUNT(*) as count
    FROM " . table('tasks') . "
    WHERE user_id = ? AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(completed_at)
    ORDER BY date
", "i", [$user_id]);

// Focus sessions stats
$total_focus_time = queryOne("
    SELECT SUM(duration_minutes) as total 
    FROM " . table('focus_sessions') . " 
    WHERE user_id = ?
", "i", [$user_id])['total'] ?? 0;

$focus_sessions_count = queryOne("
    SELECT COUNT(*) as count 
    FROM " . table('focus_sessions') . " 
    WHERE user_id = ?
", "i", [$user_id])['count'];

// Top performing subject
$top_subject = queryOne("
    SELECT s.name, s.color, s.icon, s.progress_percentage, s.completed_tasks
    FROM " . table('subjects') . " s
    WHERE s.user_id = ?
    ORDER BY s.progress_percentage DESC, s.completed_tasks DESC
    LIMIT 1
", "i", [$user_id]);

// Achievements count
$achievements_count = queryOne("
    SELECT COUNT(*) as count 
    FROM " . table('achievements') . " 
    WHERE user_id = ?
", "i", [$user_id])['count'];

// Last update time
$last_update = date('H:i:s');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Study Tracker</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }
        
        .stats-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .stat-box {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .stat-box h3 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }
        
        .refresh-indicator {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .refresh-indicator.show {
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="fas fa-sync-alt fa-spin me-2"></i>Updating stats...
    </div>

    <div class="container mt-4">
        <div class="stats-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-chart-bar text-primary"></i> Statistics & Analytics
                    </h2>
                    <small class="text-muted">Last updated: <span id="lastUpdate"><?= $last_update ?></span></small>
                </div>
                <button class="btn btn-primary" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            
            <!-- Overview Stats -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #3b82f6, #2563eb);">
                        <h3 id="totalTasks"><?= $total_tasks ?></h3>
                        <p class="mb-0">Total Tasks</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <h3 id="completedTasks"><?= $completed_tasks ?></h3>
                        <p class="mb-0">Completed</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <h3 id="pendingTasks"><?= $pending_tasks ?></h3>
                        <p class="mb-0">Pending</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                        <h3 id="completionRate"><?= $completion_rate ?>%</h3>
                        <p class="mb-0">Completion Rate</p>
                    </div>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="row mt-3">
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #ec4899, #db2777);">
                        <h3><?= $focus_sessions_count ?></h3>
                        <p class="mb-0">Focus Sessions</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                        <h3><?= round($total_focus_time / 60, 1) ?></h3>
                        <p class="mb-0">Focus Hours</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <h3><?= $user['streak'] ?></h3>
                        <p class="mb-0">🔥 Day Streak</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #fbbf24, #f59e0b);">
                        <h3><?= $achievements_count ?></h3>
                        <p class="mb-0">🏆 Achievements</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-book me-2"></i>Tasks by Subject</h5>
                    <canvas id="subjectChart"></canvas>
                </div>
            </div>

            <div class="col-md-6">
                <div class="chart-container">
                    <h5 class="mb-3"><i class="fas fa-exclamation-circle me-2"></i>Tasks by Priority</h5>
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Weekly Productivity -->
        <div class="chart-container">
            <h5 class="mb-3"><i class="fas fa-calendar-week me-2"></i>Weekly Productivity (Last 7 Days)</h5>
            <canvas id="weeklyChart"></canvas>
        </div>

        <!-- Monthly Productivity -->
        <div class="chart-container">
            <h5 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>Monthly Productivity (Last 30 Days)</h5>
            <canvas id="monthlyChart"></canvas>
        </div>

        <!-- Top Performer -->
        <?php if ($top_subject): ?>
        <div class="stats-container">
            <h5 class="mb-3"><i class="fas fa-trophy text-warning me-2"></i>Top Performing Subject</h5>
            <div class="d-flex align-items-center gap-3">
                <div style="width: 80px; height: 80px; background: var(--<?= $top_subject['color'] ?>); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                    <i class="fas fa-<?= $top_subject['icon'] ?>"></i>
                </div>
                <div>
                    <h4 class="mb-1"><?= e($top_subject['name']) ?></h4>
                    <p class="text-muted mb-0">
                        <?= round($top_subject['progress_percentage']) ?>% completed · <?= $top_subject['completed_tasks'] ?> tasks done
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    
    <script>
        // Auto-refresh every 30 seconds
        setInterval(() => {
            const indicator = document.getElementById('refreshIndicator');
            indicator.classList.add('show');
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        }, 30000);

        // Tasks by Subject Chart
        const subjectData = <?= json_encode($tasks_by_subject) ?>;
        const subjectNames = subjectData.map(s => s.name);
        const subjectCompleted = subjectData.map(s => parseInt(s.completed));
        const subjectPending = subjectData.map(s => parseInt(s.total) - parseInt(s.completed));
        
        new Chart(document.getElementById('subjectChart'), {
            type: 'bar',
            data: {
                labels: subjectNames,
                datasets: [
                    {
                        label: 'Completed',
                        data: subjectCompleted,
                        backgroundColor: '#10b981'
                    },
                    {
                        label: 'Pending',
                        data: subjectPending,
                        backgroundColor: '#f59e0b'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true }
                }
            }
        });

        // Tasks by Priority Chart
        const priorityData = <?= json_encode($tasks_by_priority) ?>;
        const priorityLabels = priorityData.map(p => p.priority.charAt(0).toUpperCase() + p.priority.slice(1));
        const priorityCounts = priorityData.map(p => parseInt(p.count));
        
        new Chart(document.getElementById('priorityChart'), {
            type: 'doughnut',
            data: {
                labels: priorityLabels,
                datasets: [{
                    data: priorityCounts,
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
                }]
            },
            options: {
                responsive: true
            }
        });

        // Weekly Chart
        const weeklyData = <?= json_encode($weekly_data) ?>;
        const weeklyLabels = weeklyData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric' });
        });
        const weeklyCounts = weeklyData.map(d => parseInt(d.count));
        
        new Chart(document.getElementById('weeklyChart'), {
            type: 'line',
            data: {
                labels: weeklyLabels,
                datasets: [{
                    label: 'Tasks Completed',
                    data: weeklyCounts,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Monthly Chart
        const monthlyData = <?= json_encode($monthly_data) ?>;
        const monthlyLabels = monthlyData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        });
        const monthlyCounts = monthlyData.map(d => parseInt(d.count));
        
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Tasks Completed',
                    data: monthlyCounts,
                    backgroundColor: '#667eea',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</body>
</html>