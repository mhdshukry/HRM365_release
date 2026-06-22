<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $employee_id = intval($_POST['employee_id'] ?? 0);
    $shift_id = !empty($_POST['shift_id']) ? intval($_POST['shift_id']) : null;
    $attendance_policy_id = !empty($_POST['attendance_policy_id']) ? intval($_POST['attendance_policy_id']) : null;

    if ($employee_id > 0) {
        try {
            $empStmt = $pdo->prepare("SELECT employee_code FROM employees WHERE id = ?");
            $empStmt->execute([$employee_id]);
            $employee_code = $empStmt->fetchColumn();
            if (!$employee_code) {
                die("Employee not found.");
            }

            if ($shift_id !== null) {
                $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE id = ?");
                $shiftStmt->execute([$shift_id]);
                if (!$shiftStmt->fetchColumn()) {
                    die("Shift not found.");
                }
            }

            if ($attendance_policy_id !== null) {
                $policyStmt = $pdo->prepare("SELECT id FROM attendance_policies WHERE id = ?");
                $policyStmt->execute([$attendance_policy_id]);
                if (!$policyStmt->fetchColumn()) {
                    die("Attendance policy not found.");
                }
            }

            $stmt = $pdo->prepare("UPDATE employees SET shift_id = ?, attendance_policy_id = ? WHERE id = ?");
            $stmt->execute([$shift_id, $attendance_policy_id, $employee_id]);
            
            log_action($pdo, $currentUser['id'], 'ATTENDANCE_ASSIGNMENT', "Updated Shift & Policy bindings for {$employee_code}");
            
            header("Location: index.php?success=assigned");
            exit();
        } catch (\PDOException $e) {
            die("Error updating assignment: " . $e->getMessage());
        }
    }
}
header("Location: index.php");
exit();
?>
