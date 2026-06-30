<?php
session_start();
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'Inactive') {
                    $error = "Account is deactivated. Contact HR.";
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['full_name'] = $user['full_name'];

                    require_once 'includes/audit.php';
                    log_action($pdo, $user['id'], 'LOGIN_SUCCESS', "User authenticated successfully via Web Portal.");
                    
                    header('Location: ' . app_url('modules/dashboard/index.php'));
                    exit();
                }
            } else {
                $error = 'Invalid credentials';
            }
        } catch (\PDOException $e) {
            // If the table doesn't exist yet, we catch the error
            $error = 'Database not initialized properly yet. Ensure database.sql was executed.';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HRM365</title>
    <link rel="stylesheet" href="<?php echo app_url('css/styles.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 50%, #f1f5f9 100%);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            background: #ffffff;
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.12);
            border: 1px solid var(--border-color);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-control {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: var(--accent-primary);
        }
        .btn-block {
            width: 100%;
            justify-content: center;
            padding: 0.85rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div style="text-align: center; margin-bottom: 2rem;">
        <img src="LOGO.png" alt="Logo" style="max-width: 180px; height: auto; background: none;">
    </div>

    <?php if ($error): ?>
        <div style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; border: 1px solid rgba(239, 68, 68, 0.2);">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500;">Username</label>
            <input type="text" name="username" class="form-control" required placeholder="admin" value="admin">
        </div>
        
        <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <label style="color: var(--text-secondary); font-size: 0.85rem; font-weight: 500;">Password</label>
            </div>
            <input type="password" name="password" class="form-control" required placeholder="admin123" value="admin123">
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
            Sign In <i class="fas fa-arrow-right" style="margin-left: 0.5rem;"></i>
        </button>
    </form>
</div>

<div style="position: absolute; bottom: 20px; width: 100%; text-align: center; font-size: 0.85rem; color: #6b7280;">
    &copy; <?php echo date('Y'); ?> <strong style="color: #ea580c;">HRM365</strong>. All rights reserved. <span style="margin: 0 0.75rem; color: #d1d5db;">|</span> Developed by &lt;/&gt; <a href="https://lushanth.com/" target="_blank" style="color: #ea580c; text-decoration: none; font-weight: bold;">Lushanth Pvt Ltd</a>
</div>

</body>
</html>
