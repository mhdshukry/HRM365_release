<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $leave_type_id = intval($_POST['leave_type_id'] ?? 0);
    
    $accrual_type = $_POST['accrual_type'] ?? '';
    $accrual_rate = max(0, floatval($_POST['accrual_rate'] ?? 0));
    $carry_forward_limit = max(0, floatval($_POST['carry_forward_limit'] ?? 0));
    
    $min_days = max(0.25, floatval($_POST['min_days_per_application'] ?? 1));
    $max_days = max(0.25, floatval($_POST['max_days_per_application'] ?? 365));
    
    $status = $_POST['status'] ?? 'Active';

    if ($max_days < $min_days) $max_days = $min_days;

    if ($name === '') {
        die("Leave policy name is required.");
    }

    if (!in_array($accrual_type, ['Monthly', 'Quarterly', 'Yearly', 'Fixed allocation'], true)) {
        die("Invalid accrual frequency.");
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        die("Invalid policy status.");
    }

    try {
        $typeStmt = $pdo->prepare("SELECT id FROM leave_types WHERE id = ?");
        $typeStmt->execute([$leave_type_id]);
        if (!$typeStmt->fetchColumn()) {
            die("Please choose a valid leave type.");
        }

        if ($id > 0) {
            $existsStmt = $pdo->prepare("SELECT id FROM leave_policies WHERE id = ?");
            $existsStmt->execute([$id]);
            if (!$existsStmt->fetchColumn()) {
                die("Leave policy not found.");
            }

            $stmt = $pdo->prepare("
                UPDATE leave_policies
                SET name = ?, description = ?, leave_type_id = ?, accrual_type = ?, accrual_rate = ?,
                    carry_forward_limit = ?, min_days_per_application = ?, max_days_per_application = ?, status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name, $description, $leave_type_id,
                $accrual_type, $accrual_rate, $carry_forward_limit,
                $min_days, $max_days, $status, $id
            ]);

            log_action($pdo, $currentUser['id'], 'LEAVE_POLICY_UPDATED', "Updated Leave Policy engine: {$name}");

            header("Location: index.php?success=policy_updated");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO leave_policies
                (name, description, leave_type_id, accrual_type, accrual_rate, carry_forward_limit, min_days_per_application, max_days_per_application, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name, $description, $leave_type_id,
                $accrual_type, $accrual_rate, $carry_forward_limit,
                $min_days, $max_days, $status
            ]);

            log_action($pdo, $currentUser['id'], 'LEAVE_POLICY_CREATED', "Defined new Leave Policy engine: {$name}");

            header("Location: index.php?success=policy_created");
        }
        exit();
    } catch (\PDOException $e) {
        die("Error saving policy: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
