<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['id'] ?? 0);

    // 1. Personal
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $address = trim($_POST['address'] ?? '');

    // 2. Employment
    $employee_code = trim($_POST['employee_code'] ?? '');
    $biometric_user_id = trim($_POST['biometric_user_id'] ?? '');
    $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
    $department = trim($_POST['department'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $hire_date = !empty($_POST['hire_date']) ? $_POST['hire_date'] : date('Y-m-d');
    $employment_type = !empty($_POST['employment_type']) ? $_POST['employment_type'] : 'Full-time';
    $base_salary = !empty($_POST['base_salary']) ? floatval($_POST['base_salary']) : 0.00;
    $shift_id = !empty($_POST['shift_id']) ? intval($_POST['shift_id']) : null;
    $attendance_policy_id = !empty($_POST['attendance_policy_id']) ? intval($_POST['attendance_policy_id']) : null;
    $status = in_array(($_POST['status'] ?? 'Active'), ['Active', 'On Leave', 'Terminated'], true) ? $_POST['status'] : 'Active';

    if (empty($biometric_user_id)) {
        $biometric_user_id = null;
    }

    // 3. Banking
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $swift_code = trim($_POST['swift_code'] ?? '');
    $bank_branch = trim($_POST['bank_branch'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');

    // 4. Document Processing (Profile Photo)
    $profile_photo_path = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $maxPhotoBytes = 2 * 1024 * 1024;
        if (filesize($_FILES['profile_photo']['tmp_name']) > $maxPhotoBytes) {
            die("Security Violation: Maximum profile photo size is 2 MB.");
        }

        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        $file_mime = mime_content_type($_FILES['profile_photo']['tmp_name']);
        
        if (isset($allowed_mimes[$file_mime])) {
            $ext = $allowed_mimes[$file_mime];
            $filename = 'profile_' . bin2hex(random_bytes(12)) . '_' . time() . '.' . $ext;
            $upload_dir = '../../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_dir . $filename)) {
                $profile_photo_path = 'uploads/profiles/' . $filename;
            }
        } else {
            die("Security Violation: Only JPG and PNG profile photos are allowed.");
        }
    }

    try {
        $pdo->beginTransaction();

        if ($currentUser['role'] === 'manager') {
            if ($employee_id > 0) {
                $scopeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND department = ?");
                $scopeStmt->execute([$employee_id, $currentUser['department'] ?? '']);
                if (!$scopeStmt->fetchColumn()) {
                    die("Unauthorized employee selection.");
                }
            }
            $department = $currentUser['department'] ?? $department;
        }

        if ($employee_id > 0) {
            $currentStmt = $pdo->prepare("SELECT profile_photo FROM employees WHERE id = ?");
            $currentStmt->execute([$employee_id]);
            $currentEmployee = $currentStmt->fetch();
            if (!$currentEmployee) {
                die("Employee not found.");
            }

            $oldProfilePhoto = $currentEmployee['profile_photo'];
            if ($profile_photo_path === null) {
                $profile_photo_path = $currentEmployee['profile_photo'];
            } elseif (!empty($oldProfilePhoto)) {
                $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldProfilePhoto);
                $absolutePath = realpath(__DIR__ . '/../../' . $relativePath);
                $profileRoot = realpath(__DIR__ . '/../../uploads/profiles');
                if ($absolutePath && $profileRoot && strpos($absolutePath, $profileRoot) === 0 && is_file($absolutePath)) {
                    unlink($absolutePath);
                }
            }

            $stmt = $pdo->prepare("
                UPDATE employees
                SET employee_code = ?, first_name = ?, last_name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ?, address = ?,
                    branch_id = ?, department = ?, designation = ?, hire_date = ?, employment_type = ?, base_salary = ?,
                    biometric_user_id = ?, profile_photo = ?, shift_id = ?, attendance_policy_id = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $employee_code, $first_name, $last_name, $email, $phone, $date_of_birth, $gender, $address,
                $branch_id, $department, $designation, $hire_date, $employment_type, $base_salary,
                $biometric_user_id, $profile_photo_path, $shift_id, $attendance_policy_id, $status, $employee_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO employees 
                (employee_code, first_name, last_name, email, phone, date_of_birth, gender, address, 
                 branch_id, department, designation, hire_date, employment_type, base_salary, biometric_user_id, profile_photo,
                 shift_id, attendance_policy_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employee_code, $first_name, $last_name, $email, $phone, $date_of_birth, $gender, $address,
                $branch_id, $department, $designation, $hire_date, $employment_type, $base_salary, $biometric_user_id, $profile_photo_path,
                $shift_id, $attendance_policy_id, $status
            ]);

            $employee_id = intval($pdo->lastInsertId());
        }

        $existingBankStmt = $pdo->prepare("SELECT id FROM employee_bank_details WHERE employee_id = ? ORDER BY id ASC LIMIT 1");
        $existingBankStmt->execute([$employee_id]);
        $bankId = $existingBankStmt->fetchColumn();

        if ($bankId) {
            $bankStmt = $pdo->prepare("
                UPDATE employee_bank_details
                SET bank_name = ?, account_name = ?, account_number = ?, swift_code = ?, bank_branch = ?, tax_id = ?
                WHERE id = ?
            ");
            $bankStmt->execute([$bank_name, $account_name, $account_number, $swift_code, $bank_branch, $tax_id, $bankId]);
        } else {
            $bankStmt = $pdo->prepare("
                INSERT INTO employee_bank_details 
                (employee_id, bank_name, account_name, account_number, swift_code, bank_branch, tax_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $bankStmt->execute([$employee_id, $bank_name, $account_name, $account_number, $swift_code, $bank_branch, $tax_id]);
        }

        $pdo->commit();

        $action = !empty($_POST['id']) ? 'EMPLOYEE_UPDATED' : 'EMPLOYEE_CREATED';
        log_action($pdo, $currentUser['id'], $action, "{$action}: {$employee_code} ({$first_name} {$last_name})");

        header("Location: index.php?success=" . (!empty($_POST['id']) ? "updated" : "onboarded"));
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error saving employee: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
