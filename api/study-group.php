<?php
require_once '../config/database.php';
header('Content-Type: application/json');
requireLogin();

$user_id = $_SESSION['st_user_id'];
$response = ['success' => false, 'message' => ''];

// CREATE GROUP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $group_name = clean($_POST['group_name']);
    $description = clean($_POST['description'] ?? '');
    
    try {
        $group_id = queryExecute("
            INSERT INTO " . table('groups') . " 
            (name, description, created_by, created_at)
            VALUES (?, ?, ?, NOW())
        ", "ssi", [$group_name, $description, $user_id]);
        
        if ($group_id) {
            // Add creator as member
            queryExecute("
                INSERT INTO " . table('group_members') . " 
                (group_id, user_id, joined_at)
                VALUES (?, ?, NOW())
            ", "ii", [$group_id, $user_id]);
            
            $response['success'] = true;
            $response['message'] = 'Group created successfully!';
            $response['group_id'] = $group_id;
        }
    } catch (Exception $e) {
        $response['message'] = 'Failed to create group: ' . $e->getMessage();
    }
}

// GET GROUP DETAILS
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_group') {
    $group_id = intval($_GET['group_id']);
    
    // Get group info
    $group = queryOne("
        SELECT * FROM " . table('groups') . " 
        WHERE id = ?
    ", "i", [$group_id]);
    
    if ($group) {
        // Get ALL members dari grup ini
        $members = queryAll("
            SELECT u.id, u.username, u.full_name, u.level, u.xp, u.streak, u.last_activity,
                   (SELECT COUNT(*) FROM " . table('achievements') . " WHERE user_id = u.id) as achievements_count
            FROM " . table('users') . " u
            JOIN " . table('group_members') . " gm ON u.id = gm.user_id
            WHERE gm.group_id = ?
            ORDER BY u.level DESC, u.xp DESC
        ", "i", [$group_id]);
        
        // Get comments
        $comments = queryAll("
            SELECT gc.*, u.username, u.full_name
            FROM " . table('group_comments') . " gc
            JOIN " . table('users') . " u ON gc.user_id = u.id
            WHERE gc.group_id = ?
            ORDER BY gc.created_at DESC
            LIMIT 50
        ", "i", [$group_id]);
        
        $response['success'] = true;
        $response['group'] = $group;
        $response['members'] = $members;
        $response['comments'] = $comments;
    } else {
        $response['message'] = 'Group not found';
    }
}

// POST COMMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_comment') {
    $group_id = intval($_POST['group_id']);
    $comment = clean($_POST['comment']);
    
    try {
        $comment_id = queryExecute("
            INSERT INTO " . table('group_comments') . " 
            (group_id, user_id, comment, created_at)
            VALUES (?, ?, ?, NOW())
        ", "iis", [$group_id, $user_id, $comment]);
        
        if ($comment_id) {
            $response['success'] = true;
            $response['message'] = 'Comment posted!';
        }
    } catch (Exception $e) {
        $response['message'] = 'Failed to post comment: ' . $e->getMessage();
    }
}

// SEARCH USERS (untuk add member - exclude yang sudah jadi member)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'search_users') {
    $search = clean($_GET['search']);
    $group_id = intval($_GET['group_id']);
    
    $users = queryAll("
        SELECT u.id, u.username, u.full_name, u.level, u.xp
        FROM " . table('users') . " u
        WHERE (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)
        AND u.id NOT IN (
            SELECT user_id FROM " . table('group_members') . " WHERE group_id = ?
        )
        AND u.id != ?
        LIMIT 10
    ", "sssii", ["%$search%", "%$search%", "%$search%", $group_id, $user_id]);
    
    $response['success'] = true;
    $response['users'] = $users;
}

// ADD MEMBER TO GROUP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_member') {
    $group_id = intval($_POST['group_id']);
    $new_user_id = intval($_POST['user_id']);
    
    // Check if user is already a member
    $exists = queryOne("
        SELECT id FROM " . table('group_members') . " 
        WHERE group_id = ? AND user_id = ?
    ", "ii", [$group_id, $new_user_id]);
    
    if ($exists) {
        $response['message'] = 'User is already a member!';
    } else {
        try {
            queryExecute("
                INSERT INTO " . table('group_members') . " 
                (group_id, user_id, joined_at)
                VALUES (?, ?, NOW())
            ", "ii", [$group_id, $new_user_id]);
            
            $response['success'] = true;
            $response['message'] = 'Member added successfully!';
        } catch (Exception $e) {
            $response['message'] = 'Failed to add member: ' . $e->getMessage();
        }
    }
}

echo json_encode($response);