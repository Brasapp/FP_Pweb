<?php
require_once '../config/database.php';
header('Content-Type: application/json');

requireLogin();

$user_id = $_SESSION['st_user_id'];
$response = ['success' => false, 'message' => ''];

// ==========================================
// CREATE SUBJECT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = clean($_POST['name']);
    $color = clean($_POST['color']);
    $icon = clean($_POST['icon']);
    
    $subject_id = queryExecute("
        INSERT INTO " . table('subjects') . " 
        (user_id, name, color, icon, total_tasks, completed_tasks, progress_percentage, created_at)
        VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
    ", "isss", [$user_id, $name, $color, $icon]);
    
    if ($subject_id) {
        $response['success'] = true;
        $response['message'] = 'Subject created successfully!';
        $response['subject_id'] = $subject_id;
    } else {
        $response['message'] = 'Failed to create subject!';
    }
}

// ==========================================
// READ SUBJECTS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    if ($_GET['action'] === 'list') {
        $subjects = queryAll("
            SELECT * FROM " . table('subjects') . " 
            WHERE user_id = ? 
            ORDER BY name
        ", "i", [$user_id]);
        
        $response['success'] = true;
        $response['subjects'] = $subjects;
    } 
    elseif ($_GET['action'] === 'get' && isset($_GET['subject_id'])) {
        $subject_id = intval($_GET['subject_id']);
        
        $subject = queryOne("
            SELECT * FROM " . table('subjects') . " 
            WHERE id = ? AND user_id = ?
        ", "ii", [$subject_id, $user_id]);
        
        if ($subject) {
            $response['success'] = true;
            $response['subject'] = $subject;
        } else {
            $response['message'] = 'Subject not found';
        }
    }
}

// ==========================================
// UPDATE SUBJECT
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $subject_id = intval($_POST['subject_id']);
    $name = clean($_POST['name']);
    $color = clean($_POST['color']);
    $icon = clean($_POST['icon']);
    
    $success = queryExecute("
        UPDATE " . table('subjects') . " 
        SET name = ?, color = ?, icon = ?
        WHERE id = ? AND user_id = ?
    ", "sssii", [$name, $color, $icon, $subject_id, $user_id]);
    
    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Subject updated successfully!';
    } else {
        $response['message'] = 'Failed to update subject!';
    }
}

// ==========================================
// DELETE SUBJECT (CASCADE DELETE TASKS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $subject_id = intval($_POST['subject_id']);
    
    // Check if subject exists and belongs to user
    $subject = queryOne("
        SELECT * FROM " . table('subjects') . " 
        WHERE id = ? AND user_id = ?
    ", "ii", [$subject_id, $user_id]);
    
    if ($subject) {
        // Delete all tasks in this subject first
        queryExecute("
            DELETE FROM " . table('tasks') . " 
            WHERE subject_id = ? AND user_id = ?
        ", "ii", [$subject_id, $user_id]);
        
        // Delete focus sessions related to tasks in this subject
        queryExecute("
            DELETE fs FROM " . table('focus_sessions') . " fs
            JOIN " . table('tasks') . " t ON fs.task_id = t.id
            WHERE t.subject_id = ? AND t.user_id = ?
        ", "ii", [$subject_id, $user_id]);
        
        // Delete the subject
        $success = queryExecute("
            DELETE FROM " . table('subjects') . " 
            WHERE id = ? AND user_id = ?
        ", "ii", [$subject_id, $user_id]);
        
        if ($success) {
            $response['success'] = true;
            $response['message'] = 'Subject and all related tasks deleted successfully!';
        } else {
            $response['message'] = 'Failed to delete subject!';
        }
    } else {
        $response['message'] = 'Subject not found!';
    }
}

echo json_encode($response);