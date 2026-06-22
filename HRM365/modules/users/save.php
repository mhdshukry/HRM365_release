<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = in_array(($_POST['role'] ?? 'employee'), ['admin', 'HR', 'manager', 'employee'], true) ? $_POST['role'] : 'employee';
    $status = in_array(($_POST['status'] ?? 'Active'), ['Active', 'Inactive'], true) ? $_POST['status'] : 'Active';
    $department = trim($_POST['department'] ?? '');
    $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : null;

    if ($employee_id) {
        $empStmt = $pdo->prepare("SELECT department, first_name, last_name, email FROM employees WHERE id = ?");
        $empStmt->execute([$employee_id]);
        $employee = $empStmt->fetch();
        if (!$employee) {
            die("Linked employee profile not found.");
        }

        if ($department === '') {
            $department = $employee['department'] ?? '';
        }
        if ($full_name === '') {
            $full_name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        }
        if ($username === '' && !empty($employee['email'])) {
            $username = $employee['email'];
        }
    }

    if ($username === '' || $full_name === '') {
        die("Full name and username are required.");
    }

    try {
        if ($id > 0) {
            $existingStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $existingStmt->execute([$id]);
            if (!$existingStmt->fetchColumn()) {
                die("User not found.");
            }

            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, password = ?, full_name = ?, role = ?, status = ?, department = ?, employee_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $password_hash, $full_name, $role, $status, $department, $employee_id, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, full_name = ?, role = ?, status = ?, department = ?, employee_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $full_name, $role, $status, $department, $employee_id, $id]);
            }

            $auditAction = 'USER_UPDATED';
            $message = "Updated system user: {$username}";
        } else {
            if ($password === '') {
                die("Password is required for new users.");
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, role, status, department, employee_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $password_hash, $full_name, $role, $status, $department, $employee_id]);

            $auditAction = 'USER_CREATED';
            $message = "Created new system user: {$username}";
        }
        
        log_action($pdo, $currentUser['id'], $auditAction, $message);
        
        header("Location: index.php?success=" . ($id > 0 ? "user_updated" : "user_added"));
        exit();
    } catch (\PDOException $e) {
        die("Error saving user: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
