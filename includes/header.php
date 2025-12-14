<?php
// Header harus dipanggil setelah session_start() dan getCurrentUser()
if (!isset($user)) {
    die("Error: User not defined. Call getCurrentUser() before including header.php");
}

// Set page title default
if (!isset($page_title)) {
    $page_title = "Study Tracker";
}

// Set active page
if (!isset($active_page)) {
    $active_page = "dashboard";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Study Tracker</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .navbar-nav .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .navbar-nav .nav-link:hover {
            color: white !important;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        
        .main-content {
            min-height: calc(100vh - 120px);
            padding-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .color-blue { color: #3b82f6; }
        .color-green { color: #10b981; }
        .color-purple { color: #8b5cf6; }
        .color-red { color: #ef4444; }
        .color-orange { color: #f59e0b; }
        .color-yellow { color: #eab308; }
        .color-cyan { color: #06b6d4; }
        
        .bg-blue { background: #3b82f6; }
        .bg-green { background: #10b981; }
        .bg-purple { background: #8b5cf6; }
        .bg-red { background: #ef4444; }
        .bg-orange { background: #f59e0b; }
        .bg-yellow { background: #eab308; }
        .bg-cyan { background: #06b6d4; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/index.php">
                <i class="fas fa-graduation-cap"></i> Study Tracker
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page == 'dashboard' ? 'active' : '' ?>" href="/index.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page == 'subjects' ? 'active' : '' ?>" href="/pages/subjects.php">
                            <i class="fas fa-book me-1"></i> Subjects
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page == 'tasks' ? 'active' : '' ?>" href="/pages/tasks.php">
                            <i class="fas fa-tasks me-1"></i> Tasks
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page == 'calendar' ? 'active' : '' ?>" href="/pages/calendar.php">
                            <i class="fas fa-calendar me-1"></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page == 'statistics' ? 'active' : '' ?>" href="/pages/statistics.php">
                            <i class="fas fa-chart-bar me-1"></i> Stats
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active_page == 'achievements' ? 'active' : '' ?>" href="/pages/achievements.php">
                            <i class="fas fa-trophy me-1"></i> Achievements
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i> <?= e($user['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <div class="dropdown-item-text">
                                    <strong>Level <?= $user['level'] ?></strong><br>
                                    <small class="text-muted"><?= $user['xp'] ?> XP</small>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/pages/profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="/pages/focus-mode.php"><i class="fas fa-clock me-2"></i>Focus Mode</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">