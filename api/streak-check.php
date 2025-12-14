<?php
require_once '../config/database.php';
header('Content-Type: application/json');

requireLogin();

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Get user data
$user = getCurrentUser();
$last_activity = $user['last_activity'];
$current_streak = $user['streak'];
$today = date('Y-m-d');

// Check if user has done something today
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM tasks 
    WHERE user_id = ? AND DATE(completed_at) = ? AND status = 'completed'
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$completed_today = $result['count'];
$stmt->close();

// Update streak logic
if ($last_activity) {
    $last_date = new DateTime($last_activity);
    $today_date = new DateTime($today);
    $diff = $last_date->diff($today_date)->days;
    
    if ($diff == 0) {
        // Same day, no change
        $new_streak = $current_streak;
    } elseif ($diff == 1 && $completed_today > 0) {
        // Consecutive day with activity
        $new_streak = $current_streak + 1;
        
        // Update database
        $stmt = $conn->prepare("UPDATE users SET streak = ?, last_activity = ? WHERE id = ?");
        $stmt->bind_param("isi", $new_streak, $today, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Check for streak bonus
        if ($new_streak == 7 || $new_streak % 7 == 0) {
            $bonus_xp = 100;
            $stmt = $conn->prepare("UPDATE users SET xp = xp + ? WHERE id = ?");
            $stmt->bind_param("ii", $bonus_xp, $user_id);
            $stmt->execute();
            $stmt->close();
            
            $response['streak_bonus'] = true;
            $response['bonus_xp'] = $bonus_xp;
        }
    } elseif ($diff > 1) {
        // Streak broken, reset to 1 if activity today
        $new_streak = $completed_today > 0 ? 1 : 0;
        
        $stmt = $conn->prepare("UPDATE users SET streak = ?, last_activity = ? WHERE id = ?");
        $stmt->bind_param("isi", $new_streak, $today, $user_id);
        $stmt->execute();
        $stmt->close();
        
        $response['streak_broken'] = true;
    }
} else {
    // First activity
    if ($completed_today > 0) {
        $new_streak = 1;
        $stmt = $conn->prepare("UPDATE users SET streak = ?, last_activity = ? WHERE id = ?");
        $stmt->bind_param("isi", $new_streak, $today, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        $new_streak = 0;
    }
}

$response['success'] = true;
$response['streak'] = $new_streak ?? $current_streak;
$response['completed_today'] = $completed_today;

$conn->close();
echo json_encode($response);
?>