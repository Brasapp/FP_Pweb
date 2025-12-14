<?php
require_once '../config/database.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

$error = '';
$success = '';

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'All fields are required!';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format!';
    } else {
        $conn = getDBConnection();
        
        // Check if username or email exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = 'Username or email already exists!';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Create default subjects
                $subjects = [
                    ['Pemrograman Berorientasi Objek', 'blue', 'fa-laptop-code'],
                    ['Pemrograman Web', 'green', 'fa-globe'],
                    ['Teori Graf', 'purple', 'fa-project-diagram'],
                    ['Matematika Diskrit', 'red', 'fa-calculator'],
                    ['Jaringan Komputer', 'orange', 'fa-network-wired'],
                    ['Konsep Kecerdasan Artifisial', 'yellow', 'fa-brain'],
                    ['Konsep Penerapan Perangkat Lunak', 'cyan', 'fa-cogs']
                ];
                
                $subject_stmt = $conn->prepare("INSERT INTO subjects (user_id, name, color, icon) VALUES (?, ?, ?, ?)");
                foreach ($subjects as $subject) {
                    $subject_stmt->bind_param("isss", $user_id, $subject[0], $subject[1], $subject[2]);
                    $subject_stmt->execute();
                }
                $subject_stmt->close();
                
                $success = 'Account created successfully! Redirecting to login...';
                
                // Auto login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                
                header("refresh:2;url=../index.php");
            } else {
                $error = 'Registration failed! Please try again.';
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
    <title>Register - Study Tracker</title>
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
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 2rem;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .register-body {
            padding: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
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
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1 class="mb-3"><i class="fas fa-graduation-cap"></i> Study Tracker</h1>
            <p class="mb-0">Create Your Account</p>
        </div>
        
        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $success ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" placeholder="Bambang Pamungkas" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="bambang123" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Email *</label>
                    <input type="email" name="email" class="form-control" placeholder="your@email.com" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" minlength="6" required>
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" minlength="6" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mb-3">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
                
                <p class="text-center mb-0">
                    Already have an account? 
                    <a href="login.php" class="text-decoration-none fw-bold">Login here</a>
                </p>
            </form>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>