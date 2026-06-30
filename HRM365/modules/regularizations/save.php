<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/attendance_math.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['employee_id']);
    $date = trim($_POST['date']);
    $reason = trim($_POST['reason']);
    $clockInInput = trim($_POST['requested_clock_in'] ?? '');
    $clockOutInput = trim($_POST['requested_clock_out'] ?? '');

    if ($currentUser['role'] === 'employee') {
        $employee_id = intval($currentUser['employee_id'] ?? 0);
    } elseif ($currentUser['role'] === 'manager') {
        $scopeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND department = ?");
        $scopeStmt->execute([$employee_id, $currentUser['department'] ?? '']);
        if (!$scopeStmt->fetchColumn()) {
            die("Unauthorized employee selection.");
        }
    }

    if ($employee_id <= 0 || strtotime($date) === false || $clockInInput === '' || $clockOutInput === '' || $reason === '') {
        die("Invalid regularization request.");
    }

    $requestedInTs = strtotime($date . ' ' . $clockInInput);
    $requestedOutTs = strtotime($date . ' ' . $clockOutInput);
    if ($requestedInTs === false || $requestedOutTs === false) {
        die("Invalid requested time.");
    }

    if ($requestedOutTs <= $requestedInTs) {
        $requestedOutTs = strtotime('+1 day', $requestedOutTs);
    }

    $requested_clock_in = date('Y-m-d H:i:s', $requestedInTs);
    $requested_clock_out = date('Y-m-d H:i:s', $requestedOutTs);

    try {
        $pdo->beginTransaction();

        $pendingStmt = $pdo->prepare("
            SELECT id
            FROM attendance_regularizations
            WHERE employee_id = ? AND date = ? AND status = 'Pending'
            LIMIT 1
        ");
        $pendingStmt->execute([$employee_id, $date]);
        if ($pendingStmt->fetch()) {
            $pdo->rollBack();
            header("Location: index.php?error=pending_exists");
            exit();
        }

        $empStmt = $pdo->prepare("SELECT shift_id, attendance_policy_id, branch_id FROM employees WHERE id = ?");
        $empStmt->execute([$employee_id]);
        $employee = $empStmt->fetch();
        if (!$employee) {
            $pdo->rollBack();
            die("Employee not found.");
        }

        $branchId = $employee['branch_id'] !== null ? intval($employee['branch_id']) : null;
        $dayContext = get_attendance_day_context(
            $pdo,
            $employee_id,
            $date,
            $branchId,
            $employee['shift_id'] !== null ? intval($employee['shift_id']) : null
        );
        if ($dayContext['block_status'] !== null) {
            $pdo->rollBack();
            die("Regularization cannot be requested for {$date}: {$dayContext['block_status']}.");
        }

        // 1. Fetch the target attendance record for this date
        $recStmt = $pdo->prepare("SELECT id, clock_in, clock_out FROM attendance_records WHERE employee_id = ? AND date = ?");
        $recStmt->execute([$employee_id, $date]);
        $record = $recStmt->fetch();

        $attendance_record_id = null;
        $original_clock_in = null;
        $original_clock_out = null;

        if ($record) {
            $attendance_record_id = $record['id'];
            $original_clock_in = $record['clock_in'];
            $original_clock_out = $record['clock_out'];
        } else {
            $calendarFlags = $dayContext['calendar'];
            $shiftId = $dayContext['shift'] ? intval($dayContext['shift']['id']) : null;

            // If they never signed in at all, create a blank record first.
            $insRec = $pdo->prepare("INSERT INTO attendance_records (employee_id, shift_id, attendance_policy_id, date, is_holiday, is_weekend) VALUES (?, ?, ?, ?, ?, ?)");
            $insRec->execute([$employee_id, $shiftId, $employee['attendance_policy_id'], $date, $calendarFlags['is_holiday'], $calendarFlags['is_weekend']]);
            $attendance_record_id = $pdo->lastInsertId();
        }

        // 2. Create the Regularization Request (Audit Trail)
        $regStmt = $pdo->prepare("
            INSERT INTO attendance_regularizations 
            (employee_id, attendance_record_id, date, requested_clock_in, requested_clock_out, original_clock_in, original_clock_out, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $regStmt->execute([
            $employee_id, $attendance_record_id, $date, 
            $requested_clock_in, $requested_clock_out, 
            $original_clock_in, $original_clock_out, $reason
        ]);

        $pdo->commit();
        log_action($pdo, $currentUser['id'], 'REGULARIZATION_SUBMITTED', "Submitted regularization request for Emp #{$employee_id} on {$date}");
        
        header("Location: index.php?success=submitted");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error saving request: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
