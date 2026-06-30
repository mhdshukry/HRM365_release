<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/sms.php';

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
    $phone = sms_normalize_phone(trim($_POST['phone'] ?? ''));
    $smsNotice = '';

    if ($employee_id) {
        $empStmt = $pdo->prepare("SELECT department, first_name, last_name, email, phone FROM employees WHERE id = ?");
        $empStmt->execute([$employee_id]);
        $employee = $empStmt->fetch();
        if (!$employee) {
            die("Linked employee profile not found.");
        }

        $department = $employee['department'] ?? $department;
        $full_name = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
        $username = trim($employee['email'] ?? $username);
        if ($phone === '' && !empty($employee['phone'])) {
            $phone = sms_normalize_phone($employee['phone']);
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
                    SET username = ?, password = ?, full_name = ?, phone = ?, role = ?, status = ?, department = ?, employee_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $password_hash, $full_name, $phone, $role, $status, $department, $employee_id, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET username = ?, full_name = ?, phone = ?, role = ?, status = ?, department = ?, employee_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$username, $full_name, $phone, $role, $status, $department, $employee_id, $id]);
            }

            $auditAction = 'USER_UPDATED';
            $message = "Updated system user: {$username}";
        } else {
            if ($password === '') {
                die("Password is required for new users.");
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, full_name, phone, role, status, department, employee_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $password_hash, $full_name, $phone, $role, $status, $department, $employee_id]);

            $auditAction = 'USER_CREATED';
            $message = "Created new system user: {$username}";
        }

        if ($password !== '' && $phone !== '') {
            $smsResult = sms_send($pdo, $phone, sms_login_message($pdo, $full_name, $username, $password, $role));
            $smsError = urlencode(substr($smsResult['message'] ?? 'SMS failed.', 0, 180));
            $smsNotice = $smsResult['success'] ? '&sms=sent' : '&sms=failed&sms_error=' . $smsError;
            log_action(
                $pdo,
                $currentUser['id'],
                $smsResult['success'] ? 'USER_CREDENTIAL_SMS_SENT' : 'USER_CREDENTIAL_SMS_FAILED',
                "Credential SMS for {$username}: {$smsResult['message']}"
            );
        } elseif ($password !== '') {
            $smsNotice = '&sms=no_phone';
        }
        
        log_action($pdo, $currentUser['id'], $auditAction, $message);
        
        header("Location: index.php?success=" . ($id > 0 ? "user_updated" : "user_added") . $smsNotice);
        exit();
    } catch (\PDOException $e) {
        die("Error saving user: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
