<?php
require_once '../config/database.php';
requireLogin();

$user = getCurrentUser();
$user_id = $user['id'];

$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = clean($_POST['full_name']);
    $email = clean($_POST['email']);
    
    try {
        queryExecute("
            UPDATE " . table('users') . " 
            SET full_name = ?, email = ? 
            WHERE id = ?
        ", "ssi", [$full_name, $email, $user_id]);
        
        $success_message = "Profile updated successfully!";
        $user = getCurrentUser();
    } catch (Exception $e) {
        $error_message = "Failed to update profile!";
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $file_type = $_FILES['avatar']['type'];
    $file_size = $_FILES['avatar']['size'];
    
    if (!in_array($file_type, $allowed_types)) {
        $error_message = "Only JPG, PNG, and GIF images are allowed!";
    } elseif ($file_size > $max_size) {
        $error_message = "File size must be less than 2MB!";
    } else {
        // Path untuk upload (dari pages/ folder)
        $upload_dir = '../assets/images/avatars/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
            // Delete old avatar if exists
            if (!empty($user['avatar'])) {
                $old_avatar_full_path = '../' . $user['avatar'];
                if (file_exists($old_avatar_full_path)) {
                    unlink($old_avatar_full_path);
                }
            }
            
            // Path untuk simpan di database (relative dari root)
            $avatar_path = 'assets/images/avatars/' . $new_filename;
            
            try {
                queryExecute("
                    UPDATE " . table('users') . " 
                    SET avatar = ? 
                    WHERE id = ?
                ", "si", [$avatar_path, $user_id]);
                
                $success_message = "Avatar updated successfully!";
                $user = getCurrentUser();
            } catch (Exception $e) {
                $error_message = "Failed to update avatar!";
            }
        } else {
            $error_message = "Failed to upload file!";
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Get user with password
    $user_with_pass = queryOne("
        SELECT password FROM " . table('users') . " WHERE id = ?
    ", "i", [$user_id]);
    
    if (password_verify($current_password, $user_with_pass['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                try {
                    queryExecute("
                        UPDATE " . table('users') . " 
                        SET password = ? 
                        WHERE id = ?
                    ", "si", [$hashed_password, $user_id]);
                    
                    $success_message = "Password changed successfully!";
                } catch (Exception $e) {
                    $error_message = "Failed to change password!";
                }
            } else {
                $error_message = "Password must be at least 6 characters!";
            }
        } else {
            $error_message = "Passwords do not match!";
        }
    } else {
        $error_message = "Current password is incorrect!";
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_text = $_POST['confirm_delete'] ?? '';
    
    if ($confirm_text === 'DELETE') {
        try {
            // Delete avatar file if exists
            if (!empty($user['avatar'])) {
                $avatar_full_path = '../' . $user['avatar'];
                if (file_exists($avatar_full_path)) {
                    unlink($avatar_full_path);
                }
            }
            
            // Delete user data
            queryExecute("DELETE FROM " . table('users') . " WHERE id = ?", "i", [$user_id]);
            
            // Logout
            session_destroy();
            header('Location: ../auth/login.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $error_message = "Failed to delete account!";
        }
    } else {
        $error_message = "You must type DELETE to confirm!";
    }
}

// Get user statistics
$stats = [];
$result = queryOne("SELECT COUNT(*) as total FROM " . table('tasks') . " WHERE user_id = ?", "i", [$user_id]);
$stats['total_tasks'] = $result['total'] ?? 0;

$result = queryOne("SELECT COUNT(*) as completed FROM " . table('tasks') . " WHERE user_id = ? AND status = 'completed'", "i", [$user_id]);
$stats['completed_tasks'] = $result['completed'] ?? 0;

$result = queryOne("SELECT COUNT(*) as achievements FROM " . table('achievements') . " WHERE user_id = ?", "i", [$user_id]);
$stats['achievements'] = $result['achievements'] ?? 0;

$result = queryOne("SELECT COUNT(*) as sessions FROM " . table('focus_sessions') . " WHERE user_id = ?", "i", [$user_id]);
$stats['focus_sessions'] = $result['sessions'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Study Tracker</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 2rem;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .profile-header {
            text-align: center;
            padding: 2rem 0;
            border-bottom: 2px solid #f3f4f6;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 1rem;
            position: relative;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #10b981;
            color: white;
            border: 3px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .avatar-upload-btn:hover {
            background: #059669;
            transform: scale(1.1);
        }

        .level-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            margin-top: 1rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
        }

        .form-section {
            background: #f9fafb;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        #avatarInput {
            display: none;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container mt-4">
        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= e($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?= e($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Info -->
            <div class="col-lg-4">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php 
                            $avatar_display_path = !empty($user['avatar']) ? '../' . $user['avatar'] : '';
                            if ($avatar_display_path && file_exists($avatar_display_path)): 
                            ?>
                                <img src="../<?= e($user['avatar']) ?>" alt="Avatar">
                            <?php else: ?>
                                <?= strtoupper(substr($user['username'], 0, 1)) ?>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data" id="avatarForm">
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" onchange="this.form.submit()">
                                <label for="avatarInput" class="avatar-upload-btn" title="Change Avatar">
                                    <i class="fas fa-camera"></i>
                                </label>
                            </form>
                        </div>
                        <h3 class="mb-0"><?= e($user['full_name'] ?? $user['username']) ?></h3>
                        <p class="text-muted">@<?= e($user['username']) ?></p>
                        <div class="level-badge-large">
                            <i class="fas fa-star"></i>
                            Level <?= $user['level'] ?>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="row mt-4">
                        <div class="col-6 mb-3">
                            <div class="stat-card">
                                <span class="stat-value"><?= $user['xp'] ?></span>
                                <small>Total XP</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                                <span class="stat-value"><?= $user['streak'] ?> 🔥</span>
                                <small>Day Streak</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                                <span class="stat-value"><?= $stats['completed_tasks'] ?></span>
                                <small>Tasks Done</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="stat-card" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">
                                <span class="stat-value"><?= $stats['achievements'] ?> 🏆</span>
                                <small>Achievements</small>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-muted small mb-1">Member since</p>
                        <p class="fw-bold"><?= date('d F Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
            </div>

            <!-- Edit Forms -->
            <div class="col-lg-8">
                <div class="profile-card">
                    <h4 class="mb-4"><i class="fas fa-user-edit text-primary"></i> Edit Profile</h4>

                    <!-- Update Profile Form -->
                    <div class="form-section">
                        <h5 class="mb-3">Personal Information</h5>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Full Name</label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= e($user['full_name'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Username</label>
                                <input type="text" class="form-control" 
                                       value="<?= e($user['username']) ?>" disabled>
                                <small class="text-muted">Username cannot be changed</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= e($user['email']) ?>" required>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>

                    <!-- Change Password Form -->
                    <div class="form-section">
                        <h5 class="mb-3">Change Password</h5>
                        <form method="POST" onsubmit="return validatePassword()">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Current Password</label>
                                <div class="input-group">
                                    <input type="password" name="current_password" id="currentPassword" class="form-control" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('currentPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">New Password</label>
                                <div class="input-group">
                                    <input type="password" name="new_password" id="newPassword" class="form-control" minlength="6" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('newPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirmPassword" class="form-control" minlength="6" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirmPassword', this)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Danger Zone -->
                    <div class="form-section" style="background: #fef2f2; border: 2px solid #fecaca;">
                        <h5 class="mb-3 text-danger">
                            <i class="fas fa-exclamation-triangle"></i> Danger Zone
                        </h5>
                        <p class="text-muted mb-3">Once you delete your account, there is no going back.</p>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash"></i> Delete Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete Account
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>⚠️ Warning!</strong> This action cannot be undone!
                        </div>
                        <p>This will permanently delete:</p>
                        <ul>
                            <li>Your profile and account</li>
                            <li>All tasks (<?= $stats['total_tasks'] ?>)</li>
                            <li>All achievements (<?= $stats['achievements'] ?>)</li>
                            <li>All focus sessions (<?= $stats['focus_sessions'] ?>)</li>
                            <li>Your XP and level progress</li>
                        </ul>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Type <code>DELETE</code> to confirm:</label>
                            <input type="text" name="confirm_delete" class="form-control" required placeholder="DELETE">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Yes, Delete Forever
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function validatePassword() {
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;
            
            if (newPass !== confirmPass) {
                alert('❌ Passwords do not match!');
                return false;
            }
            
            if (newPass.length < 6) {
                alert('❌ Password must be at least 6 characters!');
                return false;
            }
            
            return true;
        }

        // Avatar upload validation
        document.getElementById('avatarInput').addEventListener('change', function() {
            if (this.files.length > 0) {
                const fileSize = this.files[0].size;
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (fileSize > maxSize) {
                    alert('❌ File size must be less than 2MB!');
                    this.value = '';
                    return false;
                }
                
                // Show loading
                const avatar = document.querySelector('.profile-avatar');
                avatar.style.opacity = '0.5';
            }
        });
    </script>
</body>
</html>