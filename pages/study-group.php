<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

// Get user's groups with member details
$my_groups = queryAll("
    SELECT g.*, 
           u.username as creator_name,
           (SELECT COUNT(*) FROM " . table('group_members') . " WHERE group_id = g.id) as member_count
    FROM " . table('groups') . " g
    LEFT JOIN " . table('users') . " u ON g.created_by = u.id
    WHERE g.id IN (SELECT group_id FROM " . table('group_members') . " WHERE user_id = ?)
    ORDER BY g.created_at DESC
", "i", [$user_id]);

// Get recent group activities
$activities = queryAll("
    SELECT 
        u.id as user_id, u.username, u.full_name, u.level, u.xp,
        t.title as task_title, t.completed_at,
        s.name as subject_name, s.color,
        g.id as group_id, g.name as group_name
    FROM " . table('tasks') . " t
    JOIN " . table('users') . " u ON t.user_id = u.id
    JOIN " . table('subjects') . " s ON t.subject_id = s.id
    JOIN " . table('group_members') . " gm ON t.user_id = gm.user_id
    JOIN " . table('groups') . " g ON gm.group_id = g.id
    WHERE t.status = 'completed' 
    AND g.id IN (SELECT group_id FROM " . table('group_members') . " WHERE user_id = ?)
    AND t.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY t.completed_at DESC
    LIMIT 30
", "i", [$user_id]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups - Study Tracker</title>
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }
        
        .page-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .group-card {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: transform 0.3s;
            cursor: pointer;
        }
        
        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .activity-item {
            background: #f9fafb;
            border-left: 4px solid #10b981;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background: #f3f4f6;
            transform: translateX(5px);
        }
        
        .member-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .member-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .member-card:hover {
            border-color: #10b981;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .comment-box {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .comment-input {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 0.75rem;
            width: 100%;
            transition: border 0.3s;
        }
        
        .comment-input:focus {
            outline: none;
            border-color: #10b981;
        }
        
        .user-search-item {
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .user-search-item:hover {
            background: #f3f4f6 !important;
            border-color: #10b981 !important;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="page-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-users text-primary"></i> Study Groups
                    </h2>
                    <p class="text-muted mb-0">Collaborate and track progress together</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                    <i class="fas fa-plus"></i> Create Group
                </button>
            </div>

            <!-- My Groups -->
            <h5 class="mb-3"><i class="fas fa-user-friends me-2"></i>My Groups (<?= count($my_groups) ?>)</h5>
            
            <?php if (empty($my_groups)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>You're not in any study group yet. Create one!
            </div>
            <?php else: ?>
            <div class="row">
                <?php foreach ($my_groups as $group): ?>
                <div class="col-md-6 mb-3">
                    <div class="group-card" onclick="viewGroup(<?= $group['id'] ?>)">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h5 class="mb-2"><?= e($group['name']) ?></h5>
                                <p class="mb-2 opacity-75">
                                    <i class="fas fa-user-circle me-1"></i> Created by <?= e($group['creator_name']) ?>
                                </p>
                                <p class="mb-0 opacity-75">
                                    <i class="fas fa-users me-1"></i> <?= $group['member_count'] ?> members
                                </p>
                            </div>
                            <i class="fas fa-arrow-right fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Recent Activities -->
            <h5 class="mb-3 mt-5">
                <i class="fas fa-history me-2"></i>Recent Activities (Last 7 Days)
            </h5>
            
            <?php if (empty($activities)): ?>
            <div class="alert alert-secondary">
                <i class="fas fa-info-circle me-2"></i>No recent activities in your groups.
            </div>
            <?php else: ?>
            <div class="activity-timeline">
                <?php foreach ($activities as $activity): ?>
                <div class="activity-item">
                    <div class="d-flex align-items-start gap-3">
                        <div class="member-avatar">
                            <?= strtoupper(substr($activity['username'], 0, 2)) ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start flex-wrap">
                                <div>
                                    <strong><?= e($activity['full_name'] ?? $activity['username']) ?></strong>
                                    <span class="badge bg-primary ms-2">Lv <?= $activity['level'] ?></span>
                                    <span class="badge bg-warning text-dark ms-1"><?= $activity['xp'] ?> XP</span>
                                    <p class="mb-1 text-muted small">completed a task</p>
                                </div>
                                <small class="text-muted">
                                    <?= timeAgo($activity['completed_at']) ?>
                                </small>
                            </div>
                            <div class="mt-2">
                                <span class="badge" style="background: <?= $activity['color'] ?>;">
                                    <?= e($activity['subject_name']) ?>
                                </span>
                                <span class="ms-2 fw-bold"><?= e($activity['task_title']) ?></span>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-users me-1"></i><?= e($activity['group_name']) ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i>Create Study Group
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="createGroupForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Group Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="group_name" required placeholder="e.g., Kelompok Pemrograman Web">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Optional group description..."></textarea>
                        </div>
                        <div class="alert alert-info mb-0">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                You'll be automatically added as a member after creating the group.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i>Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Group Detail Modal -->
    <div class="modal fade" id="groupDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="groupDetailTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="groupDetailBody">
                    <!-- Will be populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add Members to Group
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="addMemberGroupId">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Search Users</label>
                        <input type="text" class="form-control" id="userSearchInput" placeholder="Type username, email, or full name...">
                        <small class="text-muted">Minimum 2 characters</small>
                    </div>
                    <div id="userSearchResults">
                        <!-- Search results will appear here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let searchTimeout;
        
        // Create Group
        document.getElementById('createGroupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'create');
            
            fetch('../api/study-group.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to create group');
            });
        });

        // View Group Details
        function viewGroup(groupId) {
            fetch(`../api/study-group.php?action=get_group&group_id=${groupId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showGroupDetail(data.group, data.members, data.comments);
                } else {
                    alert('Failed to load group details');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load group');
            });
        }

        function showGroupDetail(group, members, comments) {
            document.getElementById('groupDetailTitle').innerHTML = `
                <i class="fas fa-users me-2"></i>${escapeHtml(group.name)}
            `;
            
            let membersHtml = '';
            members.forEach(member => {
                const achievementsCount = member.achievements_count || 0;
                const displayName = member.full_name || member.username;
                membersHtml += `
                    <div class="member-card">
                        <div class="d-flex align-items-center gap-3">
                            <div class="member-avatar">
                                ${member.username.substring(0, 2).toUpperCase()}
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${escapeHtml(displayName)}</h6>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <span class="badge bg-primary">Level ${member.level}</span>
                                    <span class="badge bg-warning text-dark">${member.xp} XP</span>
                                    <span class="badge bg-success">${member.streak} Day Streak 🔥</span>
                                    <span class="badge bg-info">${achievementsCount} 🏆</span>
                                </div>
                                <small class="text-muted">
                                    Last active: ${member.last_activity ? timeAgoJS(member.last_activity) : 'Never'}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            let commentsHtml = '';
            if (comments && comments.length > 0) {
                comments.forEach(comment => {
                    const displayName = comment.full_name || comment.username;
                    commentsHtml += `
                        <div class="comment-box">
                            <div class="d-flex gap-2 mb-2">
                                <strong>${escapeHtml(displayName)}</strong>
                                <small class="text-muted">${timeAgoJS(comment.created_at)}</small>
                            </div>
                            <p class="mb-0">${escapeHtml(comment.comment)}</p>
                        </div>
                    `;
                });
            }
            
            document.getElementById('groupDetailBody').innerHTML = `
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6><i class="fas fa-users me-2"></i>Members (${members.length})</h6>
                        <button class="btn btn-sm btn-success" onclick="openAddMemberModal(${group.id})">
                            <i class="fas fa-user-plus me-1"></i>Add Members
                        </button>
                    </div>
                    ${membersHtml}
                </div>
                
                <div class="mb-4">
                    <h6><i class="fas fa-comments me-2"></i>Group Comments</h6>
                    <div class="mb-3" style="max-height: 300px; overflow-y: auto;">
                        ${commentsHtml || '<p class="text-muted">No comments yet. Be the first!</p>'}
                    </div>
                    
                    <form onsubmit="postComment(event, ${group.id})">
                        <div class="input-group">
                            <input type="text" class="comment-input" placeholder="Write a comment..." name="comment" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </form>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('groupDetailModal'));
            modal.show();
        }

        function postComment(event, groupId) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            formData.append('action', 'post_comment');
            formData.append('group_id', groupId);
            
            fetch('../api/study-group.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    event.target.reset();
                    viewGroup(groupId);
                } else {
                    alert('❌ Failed to post comment');
                }
            });
        }

        // Add Member Functions
        function openAddMemberModal(groupId) {
            document.getElementById('addMemberGroupId').value = groupId;
            document.getElementById('userSearchInput').value = '';
            document.getElementById('userSearchResults').innerHTML = '<p class="text-muted text-center py-3">Type to search users...</p>';
            
            // Close group detail modal first
            const groupModal = bootstrap.Modal.getInstance(document.getElementById('groupDetailModal'));
            if (groupModal) {
                groupModal.hide();
            }
            
            // Open add member modal
            const addModal = new bootstrap.Modal(document.getElementById('addMemberModal'));
            addModal.show();
        }

        // Search users with debounce
        document.getElementById('userSearchInput').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            
            const search = e.target.value.trim();
            
            if (search.length < 2) {
                document.getElementById('userSearchResults').innerHTML = '<p class="text-muted text-center py-3">Type at least 2 characters...</p>';
                return;
            }
            
            document.getElementById('userSearchResults').innerHTML = '<p class="text-muted text-center py-3"><i class="fas fa-spinner fa-spin"></i> Searching...</p>';
            
            searchTimeout = setTimeout(() => {
                const groupId = document.getElementById('addMemberGroupId').value;
                
                fetch(`../api/study-group.php?action=search_users&search=${encodeURIComponent(search)}&group_id=${groupId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.users.length > 0) {
                        let html = '<div class="list-group">';
                        data.users.forEach(user => {
                            const displayName = user.full_name || user.username;
                            html += `
                                <button type="button" class="list-group-item list-group-item-action user-search-item" onclick="addMemberToGroup(${user.id}, ${groupId}, '${escapeHtml(displayName)}')">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="member-avatar" style="width: 40px; height: 40px; font-size: 1rem;">
                                            ${user.username.substring(0, 2).toUpperCase()}
                                        </div>
                                        <div>
                                            <strong>${escapeHtml(displayName)}</strong>
                                            <small class="text-muted d-block">@${escapeHtml(user.username)} • Level ${user.level} • ${user.xp} XP</small>
                                        </div>
                                    </div>
                                </button>
                            `;
                        });
                        html += '</div>';
                        document.getElementById('userSearchResults').innerHTML = html;
                    } else {
                        document.getElementById('userSearchResults').innerHTML = '<div class="alert alert-info">No users found matching your search.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('userSearchResults').innerHTML = '<div class="alert alert-danger">Error searching users</div>';
                });
            }, 500);
        });

        function addMemberToGroup(userId, groupId, userName) {
            if (!confirm(`Add ${userName} to this group?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'add_member');
            formData.append('group_id', groupId);
            formData.append('user_id', userId);
            
            fetch('../api/study-group.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addMemberModal'));
                    modal.hide();
                    viewGroup(groupId);
                } else {
                    alert('❌ ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to add member');
            });
        }

        function timeAgoJS(datetime) {
            const now = new Date();
            const past = new Date(datetime);
            const diff = Math.floor((now - past) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
            if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
            if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
            return past.toLocaleDateString('id-ID');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>