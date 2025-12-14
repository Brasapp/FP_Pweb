<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

// Get earned achievements
$earned_achievements = queryAll("
    SELECT * FROM " . table('achievements') . " 
    WHERE user_id = ? 
    ORDER BY earned_at DESC
", "i", [$user_id]);

// Define all possible achievements
$all_achievements = [
    [
        'name' => 'Early Bird',
        'icon' => '🌅',
        'description' => 'Complete 10 tasks before deadline',
        'requirement' => 10,
        'category' => 'productivity'
    ],
    [
        'name' => 'Week Warrior',
        'icon' => '⚔️',
        'description' => 'Maintain 7 days streak',
        'requirement' => 7,
        'category' => 'streak'
    ],
    [
        'name' => 'Perfectionist',
        'icon' => '⭐',
        'description' => 'Complete all tasks in 1 subject',
        'requirement' => 1,
        'category' => 'completion'
    ],
    [
        'name' => 'Speed Runner',
        'icon' => '⚡',
        'description' => 'Complete 5 tasks in 1 day',
        'requirement' => 5,
        'category' => 'speed'
    ],
    [
        'name' => 'Semester Hero',
        'icon' => '🦸',
        'description' => 'Complete 100 tasks in semester',
        'requirement' => 100,
        'category' => 'milestone'
    ],
    [
        'name' => 'Night Owl',
        'icon' => '🦉',
        'description' => 'Complete task between 12 AM - 5 AM',
        'requirement' => 1,
        'category' => 'special'
    ],
    [
        'name' => 'Focus Master',
        'icon' => '🎯',
        'description' => 'Complete 50 focus sessions',
        'requirement' => 50,
        'category' => 'focus'
    ],
    [
        'name' => 'Consistent',
        'icon' => '📅',
        'description' => 'Maintain 30 days streak',
        'requirement' => 30,
        'category' => 'streak'
    ]
];

// Check which achievements are earned
$earned_names = array_column($earned_achievements, 'badge_name');

// Get statistics for progress
$stats = [];
$stats['early_tasks'] = queryOne("
    SELECT COUNT(*) as count FROM " . table('tasks') . " 
    WHERE user_id = ? AND status = 'completed' AND completed_at < deadline
", "i", [$user_id])['count'] ?? 0;

$stats['completed_tasks'] = queryOne("
    SELECT COUNT(*) as count FROM " . table('tasks') . " 
    WHERE user_id = ? AND status = 'completed'
", "i", [$user_id])['count'] ?? 0;

$stats['focus_sessions'] = queryOne("
    SELECT COUNT(*) as count FROM " . table('focus_sessions') . " 
    WHERE user_id = ?
", "i", [$user_id])['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievements - Study Tracker</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 2rem;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .achievement-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .achievement-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .achievement-card.earned {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        }

        .achievement-card.locked {
            opacity: 0.5;
            filter: grayscale(100%);
        }

        .achievement-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }

        .achievement-card.earned .achievement-icon {
            animation: bounce 1s ease;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .achievement-ribbon {
            position: absolute;
            top: 15px;
            right: -30px;
            background: #10b981;
            color: white;
            padding: 5px 40px;
            transform: rotate(45deg);
            font-size: 0.75rem;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .progress-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }

        .category-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="page-header" data-aos="fade-up">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-trophy text-warning"></i> Achievements</h2>
                    <p class="text-muted mb-0">Unlock badges and show your progress!</p>
                </div>
                <div class="text-end">
                    <h4 class="mb-0"><?= count($earned_achievements) ?> / <?= count($all_achievements) ?></h4>
                    <small class="text-muted">Unlocked</small>
                </div>
            </div>
        </div>

        <!-- Progress Stats -->
        <div class="progress-section" data-aos="fade-up" data-aos-delay="100">
            <h5 class="mb-3"><i class="fas fa-chart-bar text-primary"></i> Your Progress</h5>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="stat-box">
                        <h3 class="mb-0"><?= $stats['early_tasks'] ?></h3>
                        <small>Early Completions</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <h3 class="mb-0"><?= $stats['completed_tasks'] ?></h3>
                        <small>Total Completed</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <h3 class="mb-0"><?= $user['streak'] ?></h3>
                        <small>Current Streak</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-box" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                        <h3 class="mb-0"><?= $stats['focus_sessions'] ?></h3>
                        <small>Focus Sessions</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Achievements Grid -->
        <div class="row">
            <?php 
            $delay = 200;
            foreach ($all_achievements as $achievement): 
                $is_earned = in_array($achievement['name'], $earned_names);
                
                // Calculate progress
                $progress = 0;
                switch($achievement['name']) {
                    case 'Early Bird':
                        $progress = min(100, ($stats['early_tasks'] / $achievement['requirement']) * 100);
                        break;
                    case 'Week Warrior':
                        $progress = min(100, ($user['streak'] / $achievement['requirement']) * 100);
                        break;
                    case 'Semester Hero':
                        $progress = min(100, ($stats['completed_tasks'] / $achievement['requirement']) * 100);
                        break;
                    case 'Focus Master':
                        $progress = min(100, ($stats['focus_sessions'] / $achievement['requirement']) * 100);
                        break;
                    case 'Consistent':
                        $progress = min(100, ($user['streak'] / $achievement['requirement']) * 100);
                        break;
                    default:
                        $progress = $is_earned ? 100 : 0;
                }
            ?>
            <div class="col-lg-3 col-md-4 col-sm-6" data-aos="fade-up" data-aos-delay="<?= $delay ?>">
                <div class="achievement-card <?= $is_earned ? 'earned' : 'locked' ?>">
                    <?php if ($is_earned): ?>
                    <div class="achievement-ribbon">UNLOCKED</div>
                    <?php endif; ?>
                    
                    <span class="achievement-icon"><?= $achievement['icon'] ?></span>
                    <h5 class="fw-bold mb-2"><?= e($achievement['name']) ?></h5>
                    <p class="text-muted small mb-3"><?= e($achievement['description']) ?></p>
                    
                    <?php if (!$is_earned): ?>
                    <div class="progress mb-2" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                    </div>
                    <small class="text-muted"><?= round($progress) ?>% Complete</small>
                    <?php else: ?>
                    <span class="badge bg-success">✓ Completed</span>
                    <?php 
                    foreach ($earned_achievements as $earned) {
                        if ($earned['badge_name'] == $achievement['name']) {
                            echo '<br><small class="text-muted">Earned: ' . formatDate($earned['earned_at']) . '</small>';
                            break;
                        }
                    }
                    ?>
                    <?php endif; ?>

                    <span class="category-badge bg-secondary"><?= ucfirst($achievement['category']) ?></span>
                </div>
            </div>
            <?php 
            $delay += 50;
            endforeach; 
            ?>
        </div>

        <!-- Tips -->
        <div class="progress-section" data-aos="fade-up">
            <h5 class="mb-3"><i class="fas fa-lightbulb text-warning"></i> Tips to Unlock Achievements</h5>
            <ul class="mb-0">
                <li>Complete tasks before their deadline to earn "Early Bird"</li>
                <li>Study consistently every day to build your streak</li>
                <li>Use Focus Mode regularly to unlock "Focus Master"</li>
                <li>Finish all tasks in a subject to become a "Perfectionist"</li>
                <li>Challenge yourself to complete multiple tasks in one day</li>
            </ul>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: true });
    </script>
</body>
</html>