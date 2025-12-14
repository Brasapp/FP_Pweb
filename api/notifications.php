<?php
require_once '../config/database.php';
require_once '../functions/notification.php';
header('Content-Type: application/json');

requireLogin();

$user_id = $_SESSION['st_user_id'];
$response = ['success' => false, 'message' => ''];

// GET: list unread notifications
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'list') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
    $notifications = getUnreadNotifications($user_id, $limit);
    $response['success'] = true;
    $response['notifications'] = $notifications;
    echo json_encode($response);
    exit();
}

// POST: mark a single notification as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $nid = intval($_POST['notification_id'] ?? 0);
    if ($nid > 0) {
        $ok = markNotificationAsRead($nid, $user_id);
        $response['success'] = $ok ? true : false;
        $response['message'] = $ok ? 'Marked as read' : 'Failed to mark';
    } else {
        $response['message'] = 'Invalid notification id';
    }
    echo json_encode($response);
    exit();
}

// POST: mark all as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all') {
    $ok = markAllNotificationsAsRead($user_id);
    $response['success'] = $ok ? true : false;
    $response['message'] = $ok ? 'All marked as read' : 'Failed to mark all';
    echo json_encode($response);
    exit();
}

$response['message'] = 'Invalid request';
echo json_encode($response);

?>
