<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['employee_id']);
    $leave_type_id = intval($_POST['leave_type_id'] ?? 0);
    $normal_leave_type_id = intval($_POST['normal_leave_type_id'] ?? 0);
    $covering_employee_id = !empty($_POST['covering_employee_id']) ? intval($_POST['covering_employee_id']) : null;
    $duration_type = $_POST['duration_type'] ?? 'multi';
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $reason = trim($_POST['reason']);

    try {
        if ($currentUser['role'] === 'employee') {
            $employee_id = intval($currentUser['employee_id'] ?? 0);
        } elseif ($currentUser['role'] === 'manager') {
            $scopeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND department = ?");
            $scopeStmt->execute([$employee_id, $currentUser['department'] ?? '']);
            if (!$scopeStmt->fetchColumn()) {
                die("Unauthorized employee selection.");
            }
        }

        if ($employee_id <= 0) {
            die("Employee profile not linked to this user.");
        }

        $pdo->beginTransaction();

        if ($duration_type === '0.25') {
            $shortTypeStmt = $pdo->query("SELECT id FROM leave_types WHERE status = 'Active' AND LOWER(name) LIKE '%short%' ORDER BY id ASC LIMIT 1");
            $leave_type_id = intval($shortTypeStmt->fetchColumn() ?: 0);
        } elseif ($leave_type_id <= 0 && $normal_leave_type_id > 0) {
            $leave_type_id = $normal_leave_type_id;
        }

        $typeStmt = $pdo->prepare("SELECT name, is_paid FROM leave_types WHERE id = ? AND status = 'Active'");
        $typeStmt->execute([$leave_type_id]);
        $leaveType = $typeStmt->fetch();
        if (!$leaveType) {
            $pdo->rollBack();
            die("Invalid leave type.");
        }

        $isShortLeave = stripos($leaveType['name'], 'short') !== false;

        // 1. Calculate Total Days
        if ($duration_type === 'multi') {
            if ($isShortLeave) {
                $pdo->rollBack();
                header("Location: request_leave.php?error=policy_limit");
                exit();
            }
            $start_ts = strtotime($start_date);
            $end_ts = strtotime($end_date);
            if ($start_ts === false || $end_ts === false || $end_ts < $start_ts) {
                $pdo->rollBack();
                header("Location: request_leave.php?error=policy_limit");
                exit();
            }
            $diff = $end_ts - $start_ts;
            $total_days = round($diff / (60 * 60 * 24)) + 1; // +1 to include both start and end days
        } else {
            // For Single Full Day, Half Day, or Short Leave
            $total_days = floatval($duration_type);
            $end_date = $start_date; // End date is identical to start date
        }

        if ($isShortLeave && abs($total_days - 0.25) > 0.001) {
            $pdo->rollBack();
            header("Location: request_leave.php?error=policy_limit");
            exit();
        }

        if (!$isShortLeave && abs($total_days - 0.25) < 0.001) {
            $pdo->rollBack();
            header("Location: request_leave.php?error=policy_limit");
            exit();
        }

        // 2. Pre-flight Balance Check
        $balStmt = $pdo->prepare("
            SELECT lb.leave_policy_id,
                   (lb.allocated_days + lb.carried_forward + lb.manual_adjustment - lb.used_days) as remaining_days,
                   lp.min_days_per_application,
                   lp.max_days_per_application
            FROM leave_balances lb
            LEFT JOIN leave_policies lp ON lb.leave_policy_id = lp.id
            WHERE lb.employee_id = ? AND lb.leave_type_id = ? AND lb.year = ?
        ");
        $balStmt->execute([$employee_id, $leave_type_id, date('Y')]);
        $balance = $balStmt->fetch();

        $minDays = $balance && $balance['min_days_per_application'] !== null ? floatval($balance['min_days_per_application']) : 0.25;
        $maxDays = $balance && $balance['max_days_per_application'] !== null ? floatval($balance['max_days_per_application']) : 365;
        if ($total_days < $minDays || $total_days > $maxDays) {
            $pdo->rollBack();
            header("Location: request_leave.php?error=policy_limit");
            exit();
        }

        // 3. Save Application
        $stmt = $pdo->prepare("
            INSERT INTO leave_applications 
            (employee_id, leave_type_id, covering_employee_id, start_date, end_date, total_days, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $leave_type_id, $covering_employee_id, $start_date, $end_date, $total_days, $reason]);

        $pdo->commit();
        log_action($pdo, $currentUser['id'], 'LEAVE_REQUESTED', "Leave requested for Emp #{$employee_id}");
        
        header("Location: index.php?success=requested");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error processing leave request: " . $e->getMessage());
    }
}
header("Location: request_leave.php");
exit();
?>
