<?php
// Detect current page
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get base path (untuk handle root vs pages folder)
$is_root = (basename(dirname($_SERVER['PHP_SELF'])) === basename($_SERVER['DOCUMENT_ROOT']));
$base_path = $is_root ? '' : '/';
?>
<nav class="navbar navbar-expand-lg navbar-light" style="background: rgba(255, 255, 255, 0.95) !important; backdrop-filter: blur(10px); box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= $base_path ?>index.php" style="color: #10b981; font-size: 1.5rem;">
            <i class="fas fa-graduation-cap"></i> Study Tracker
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>index.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'tasks' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>pages/tasks.php">
                        <i class="fas fa-tasks"></i> Tasks
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'subjects' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>pages/subjects.php">
                        <i class="fas fa-book"></i> Subjects
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'calendar' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>pages/calendar.php">
                        <i class="fas fa-calendar"></i> Calendar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'statistics' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>pages/statistics.php">
                        <i class="fas fa-chart-bar"></i> Statistics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'achievements' ? 'active fw-bold' : '' ?>" href="<?= $base_path ?>pages/achievements.php">
                        <i class="fas fa-trophy"></i> Achievements
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <!-- Notification Bell -->
                <?php include ($is_root ? 'includes/' : '../includes/') . 'notifications.php'; ?>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="navbarDropdown" data-bs-toggle="dropdown">
                        <div class="d-flex align-items-center gap-2">
                            <!-- Avatar Image or Initial -->
                            <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                <img src="<?= $base_path . e($user['avatar']) ?>" alt="Avatar" 
                                     style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #10b981;">
                            <?php else: ?>
                                <div style="width: 35px; height: 35px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.9rem; border: 2px solid #10b981;">
                                    <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <div class="fw-bold"><?= e($user['full_name'] ?? $user['username'] ?? 'User') ?></div>
                                <small class="text-muted">Level <?= $user['level'] ?? 1 ?> | <?= $user['xp'] ?? 0 ?> XP</small>
                            </div>
                            <?php if (isset($user['streak']) && $user['streak'] > 0): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="fas fa-fire"></i> <?= $user['streak'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?= $base_path ?>pages/profile.php">
                                <i class="fas fa-user-circle me-2"></i>Profile
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= $base_path ?>pages/focus-mode.php">
                                <i class="fas fa-clock me-2"></i>Focus Mode
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= $base_path ?>pages/study-group.php">
                                <i class="fas fa-users me-2"></i>Study Groups
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= $base_path ?>auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>