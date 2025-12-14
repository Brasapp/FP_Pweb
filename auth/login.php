<?php
require_once '../config/database.php';

$error = '';
$success = '';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required!';
    } else {
        $conn = getDBConnection();
        // FIX: Pakai st_users bukan users
        $stmt = $conn->prepare("SELECT * FROM st_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['st_user_id'] = $user['id'];
                $_SESSION['st_username'] = $user['username'];
                $_SESSION['st_full_name'] = $user['full_name'];
                $_SESSION['st_level'] = $user['level'];
                $_SESSION['st_xp'] = $user['xp'];
                
                // Update last activity
                $update = $conn->prepare("UPDATE st_users SET last_activity = NOW() WHERE id = ?");
                $update->bind_param("i", $user['id']);
                $update->execute();
                $update->close();
                
                // FIX: Redirect ke pages/index.php
                header("Location: /pages/index.php");
                exit();
            } else {
                $error = 'Invalid password!';
            }
        } else {
            $error = 'Email not found!';
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['reg_confirm_password'];
    $full_name = trim($_POST['reg_full_name']);
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'All fields are required!';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
    } else {
        $conn = getDBConnection();
        
        // FIX: Check di st_users
        $check = $conn->prepare("SELECT id FROM st_users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = 'Username or email already exists!';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // FIX: Insert ke st_users
            $stmt = $conn->prepare("INSERT INTO st_users (username, email, password, full_name, level, xp, streak) VALUES (?, ?, ?, ?, 1, 0, 0)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Create default 7 subjects
                $subjects = [
                    ['PBO', 'Pemrograman Berorientasi Objek', 'blue', 'code'],
                    ['Web', 'Pemrograman Web', 'green', 'globe'],
                    ['Graf', 'Teori Graf', 'purple', 'share-2'],
                    ['Diskrit', 'Matematika Diskrit', 'red', 'calculator'],
                    ['Jarkom', 'Jaringan Komputer', 'orange', 'wifi'],
                    ['AI', 'Konsep Kecerdasan Artifisial', 'yellow', 'cpu'],
                    ['KPPL', 'Konsep Penerapan Perangkat Lunak', 'cyan', 'layers']
                ];
                
                // FIX: Insert ke st_subjects
                $subject_stmt = $conn->prepare("INSERT INTO st_subjects (user_id, name, full_name, color, icon) VALUES (?, ?, ?, ?, ?)");
                foreach ($subjects as $subject) {
                    $subject_stmt->bind_param("issss", $user_id, $subject[0], $subject[1], $subject[2], $subject[3]);
                    $subject_stmt->execute();
                }
                $subject_stmt->close();
                
                $success = 'Account created successfully! Please login.';
            } else {
                $error = 'Registration failed! ' . $conn->error;
            }
            
            $stmt->close();
        }
        
        $check->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Tracker - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .auth-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            margin: 2rem;
        }
        
        .auth-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 600px;
        }
        
        .auth-sidebar h1 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .auth-sidebar .lead {
            font-size: 1.1rem;
            opacity: 0.95;
        }
        
        .feature-list li {
            font-size: 1rem;
            padding: 0.5rem 0;
        }
        
        .auth-form {
            padding: 3rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .nav-pills .nav-link {
            border-radius: 10px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .feature-icon {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container row g-0">
            <div class="col-lg-5 auth-sidebar">
                <div>
                    <h1><i class="fas fa-graduation-cap"></i> Study Tracker</h1>
                    <p class="lead">Sistem Manajemen Tugas Kuliah dengan Gamifikasi</p>
                    <hr class="my-4" style="background: rgba(255,255,255,0.3); height: 2px; border: none;">
                    <ul class="list-unstyled feature-list">
                        <li class="mb-3">
                            <span class="feature-icon"><i class="fas fa-star"></i></span>
                            XP & Level System
                        </li>
                        <li class="mb-3">
                            <span class="feature-icon"><i class="fas fa-fire"></i></span>
                            Streak Counter
                        </li>
                        <li class="mb-3">
                            <span class="feature-icon"><i class="fas fa-trophy"></i></span>
                            Achievement Badges
                        </li>
                        <li class="mb-3">
                            <span class="feature-icon"><i class="fas fa-clock"></i></span>
                            Pomodoro Timer
                        </li>
                        <li class="mb-3">
                            <span class="feature-icon"><i class="fas fa-chart-line"></i></span>
                            Analytics & Stats
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="col-lg-7 auth-form">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <ul class="nav nav-pills mb-4 justify-content-center" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#login-tab">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </li>
                    <li class="nav-item ms-2" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#register-tab">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Login Tab -->
                    <div class="tab-pane fade show active" id="login-tab">
                        <h3 class="mb-4 text-center">Welcome Back! 👋</h3>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-envelope me-2"></i>Email</label>
                                <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i class="fas fa-lock me-2"></i>Password</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                            </button>
                            <p class="text-center text-muted small">
                                <i class="fas fa-info-circle me-1"></i>
                                Demo: bambang@study.com / 123456
                            </p>
                        </form>
                    </div>
                    
                    <!-- Register Tab -->
                    <div class="tab-pane fade" id="register-tab">
                        <h3 class="mb-4 text-center">Create Account 🚀</h3>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-user me-2"></i>Full Name</label>
                                <input type="text" name="reg_full_name" class="form-control" placeholder="Bambang Pamungkas" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-at me-2"></i>Username</label>
                                <input type="text" name="reg_username" class="form-control" placeholder="bambang123" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-envelope me-2"></i>Email</label>
                                <input type="email" name="reg_email" class="form-control" placeholder="your@email.com" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><i class="fas fa-lock me-2"></i>Password</label>
                                <input type="password" name="reg_password" class="form-control" placeholder="••••••••" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold"><i class="fas fa-lock me-2"></i>Confirm Password</label>
                                <input type="password" name="reg_confirm_password" class="form-control" placeholder="••••••••" required>
                            </div>
                            <button type="submit" name="register" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>