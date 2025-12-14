<?php
/**
 * ====================================
 * STUDY TRACKER - DATABASE CONFIG
 * ====================================
 * Shared database dengan LaundryCraft
 * Prefix: st_ (Study Tracker)
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'jvfafdzr_laundryuser'); 
define('DB_PASS', 'LexmRZ5YW@Zpqm6'); 
define('DB_NAME', 'jvfafdzr_laundrycraft_db'); 

// Table prefix
define('ST_PREFIX', 'st_');

// Production mode (set display_errors = 1 untuk debugging)
ini_set('display_errors', 0);
error_reporting(0);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// ====================================
// DATABASE CONNECTION
// ====================================
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        error_log("DB Connection Error: " . $conn->connect_error);
        die("Connection failed. Please contact administrator.");
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

// ====================================
// SESSION MANAGEMENT
// ====================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['st_user_id']);
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDBConnection();
    $user_id = $_SESSION['st_user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM st_users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    return $user;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /auth/login.php");
        exit();
    }
}

// Logout function
function logout() {
    $_SESSION = array();
    session_destroy();
    header("Location: /auth/login.php");
    exit();
}

// ====================================
// HELPER FUNCTIONS
// ====================================

// Get table name with prefix
function table($name) {
    return ST_PREFIX . $name;
}

// Sanitize input
function clean($data) {
    if (is_array($data)) {
        return array_map('clean', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Escape output
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Format date
function formatDate($date, $format = 'd M Y') {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

// Format datetime
function formatDateTime($datetime, $format = 'd M Y H:i') {
    if (!$datetime) return '-';
    return date($format, strtotime($datetime));
}

// Time ago
function timeAgo($datetime) {
    if (!$datetime) return '-';
    
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    
    return formatDateTime($datetime);
}

// Get deadline status
function getDeadlineStatus($deadline) {
    if (!$deadline) return 'safe';
    
    $now = time();
    $deadlineTime = strtotime($deadline);
    $diff = $deadlineTime - $now;
    $diffDays = floor($diff / 86400);
    
    if ($diff < 0) return 'overdue';
    if ($diffDays < 1) return 'danger';
    if ($diffDays < 3) return 'warning';
    return 'safe';
}

// Get deadline color
function getDeadlineColor($deadline) {
    $status = getDeadlineStatus($deadline);
    
    switch ($status) {
        case 'overdue': return 'danger';
        case 'danger': return 'danger';
        case 'warning': return 'warning';
        default: return 'success';
    }
}

// Get priority badge
function getPriorityBadge($priority) {
    $badges = [
        'low' => '<span class="badge bg-success"><i class="fas fa-arrow-down"></i> Low</span>',
        'medium' => '<span class="badge bg-warning"><i class="fas fa-minus"></i> Medium</span>',
        'high' => '<span class="badge bg-danger"><i class="fas fa-arrow-up"></i> High</span>'
    ];
    
    return $badges[$priority] ?? $badges['medium'];
}

// Get status badge
function getStatusBadge($status) {
    $badges = [
        'todo' => '<span class="badge bg-secondary"><i class="fas fa-circle"></i> To Do</span>',
        'in_progress' => '<span class="badge bg-info"><i class="fas fa-spinner"></i> In Progress</span>',
        'completed' => '<span class="badge bg-success"><i class="fas fa-check-circle"></i> Completed</span>'
    ];
    
    return $badges[$status] ?? $badges['todo'];
}

// Calculate XP for next level
function getXPForLevel($level) {
    return $level * 1000; // Simple: Level 1 = 1000 XP, Level 2 = 2000 XP, dst.
}

// Get level progress percentage
function getLevelProgress($xp, $level) {
    $currentLevelXP = ($level - 1) * 1000;
    $nextLevelXP = $level * 1000;
    $progressXP = $xp - $currentLevelXP;
    $requiredXP = $nextLevelXP - $currentLevelXP;
    
    return round(($progressXP / $requiredXP) * 100);
}

// ====================================
// DATABASE QUERY HELPERS
// ====================================

// Execute query and return all results
function queryAll($query, $types = '', $params = []) {
    $conn = getDBConnection();
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query($query);
        $data = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    $conn->close();
    return $data;
}

// Execute query and return single row
function queryOne($query, $types = '', $params = []) {
    $conn = getDBConnection();
    
    if (!empty($params)) {
        $stmt = $conn->prepare($query);
        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
    } else {
        $result = $conn->query($query);
        $data = $result->fetch_assoc();
    }
    
    $conn->close();
    return $data;
}

// Execute INSERT/UPDATE/DELETE
function queryExecute($query, $types = '', $params = []) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare($query);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    
    $success = $stmt->execute();
    $insertId = $conn->insert_id;
    
    $stmt->close();
    $conn->close();
    
    return $insertId ?: $success;
}

// ====================================
// AVATAR HELPERS
// ====================================

function getUserAvatar($user) {
    if (!empty($user['avatar']) && file_exists($user['avatar'])) {
        return $user['avatar'];
    }
    return null;
}

function getAvatarInitial($username) {
    return strtoupper(substr($username, 0, 1));
}

function deleteOldAvatar($avatar_path) {
    if (!empty($avatar_path) && file_exists($avatar_path)) {
        return unlink($avatar_path);
    }
    return false;
}
?>