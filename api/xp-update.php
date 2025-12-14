<?php
require_once '../config/database.php';
require_once '../functions/gamification.php';
header('Content-Type: application/json');

requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Add XP manually (for admin/testing)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_xp') {
    $xp_amount = intval($_POST['xp_amount']);
    
    if ($xp_amount <= 0) {
        $response['message'] = 'XP amount must be positive!';
    } else {
        $result = addXP($user_id, $xp_amount);
        
        $response['success'] = true;
        $response['xp_gained'] = $result['xp_gained'];
        $response['total_xp'] = $result['total_xp'];
        $response['level_up'] = $result['level_up'];
        $response['new_level'] = $result['new_level'];
        $response['message'] = 'XP added successfully!';
    }
}

// Get current XP progress
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_progress') {
    $user = getCurrentUser();
    
    $xp_needed = getXPForNextLevel($user['level']);
    $xp_progress = ($user['xp'] / $xp_needed) * 100;
    
    $response['success'] = true;
    $response['current_level'] = $user['level'];
    $response['current_xp'] = $user['xp'];
    $response['xp_needed'] = $xp_needed;
    $response['xp_progress'] = round($xp_progress, 2);
    $response['level_title'] = getLevelTitle($user['level']);
}

// Get XP leaderboard (top users)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'leaderboard') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    
    $result = $conn->query("
        SELECT username, full_name, level, xp, streak
        FROM users
        ORDER BY level DESC, xp DESC
        LIMIT $limit
    ");
    
    $leaderboard = [];
    $rank = 1;
    while ($row = $result->fetch_assoc()) {
        $row['rank'] = $rank++;
        $leaderboard[] = $row;
    }
    
    $response['success'] = true;
    $response['leaderboard'] = $leaderboard;
}

// Calculate potential XP for task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'calculate_xp') {
    $priority = $_POST['priority'] ?? 'medium';
    $estimated_time = intval($_POST['estimated_time'] ?? 30);
    $deadline = $_POST['deadline'] ?? null;
    
    $base_xp = calculateXPReward($priority, $estimated_time);
    $bonus_xp = 0;
    
    if ($deadline) {
        $bonus_xp = calculateBonusXP($deadline);
    }
    
    $response['success'] = true;
    $response['base_xp'] = $base_xp;
    $response['bonus_xp'] = $bonus_xp;
    $response['total_xp'] = $base_xp + $bonus_xp;
}

$conn->close();
echo json_encode($response);
?>