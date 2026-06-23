<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/attendance_math.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $emp_id = $currentUser['employee_id'] ?? null;
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $currentTime = date('H:i:s');

    if (!$emp_id) {
        die("Employee profile not linked to this user.");
    }

    // Load Employee Profile & Linked Constraints (Shift + Policy)
    $stmt = $pdo->prepare("
        SELECT e.id, e.shift_id, e.attendance_policy_id, e.base_salary, e.branch_id,
               s.start_time, s.end_time,
               p.late_arrival_grace, p.early_departure_grace, p.overtime_rate_per_hour
        FROM employees e
        LEFT JOIN shifts s ON e.shift_id = s.id
        LEFT JOIN attendance_policies p ON e.attendance_policy_id = p.id
        WHERE e.id = ?
    ");
    $stmt->execute([$emp_id]);
    $emp = $stmt->fetch();

    if (!$emp) {
        die("Employee profile not found or mapped properly.");
    }

    $shift_id = $emp['shift_id'];
    $policy_id = $emp['attendance_policy_id'];
    $branchId = $emp['branch_id'] !== null ? intval($emp['branch_id']) : null;
    $calendarFlags = get_attendance_calendar_flags($pdo, $today, $branchId);
    $blockStatus = get_attendance_block_status($pdo, intval($emp_id), $today, $branchId);
    
    // Core Engine Math Variables
    $is_late = 0;
    $is_early_departure = 0;
    $overtime_hours = 0.00;
    $overtime_amount = 0.00;

    if ($blockStatus !== null) {
        log_action($pdo, $emp_id, 'ATTENDANCE_BLOCKED', "Punch blocked for {$today}: {$blockStatus}");
        header("Location: index.php?error=blocked&reason=" . urlencode($blockStatus));
        exit();
    }

    if ($action === 'clock_in') {
        // Evaluate Lateness Math
        if ($emp['start_time']) {
            $expected_start_str = $today . ' ' . $emp['start_time'];
            $expected_start = strtotime($expected_start_str);
            $grace = intval($emp['late_arrival_grace']) * 60; // in seconds
            $actual_time = strtotime($now);
            
            if ($actual_time > ($expected_start + $grace)) {
                $is_late = 1;
            }
        }

        try {
            $insert = $pdo->prepare("
                INSERT INTO attendance_records 
                (employee_id, shift_id, attendance_policy_id, date, clock_in, is_late, is_holiday, is_weekend, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ");
            $insert->execute([$emp_id, $shift_id, $policy_id, $today, $now, $is_late, $calendarFlags['is_holiday'], $calendarFlags['is_weekend']]);
            log_action($pdo, $emp_id, 'ATTENDANCE_CLOCK_IN', "Clocked in at {$currentTime}" . ($is_late ? " (Flagged Late)" : ""));
        } catch (\PDOException $e) {
            die("Error clocking in: " . $e->getMessage());
        }

    } elseif ($action === 'clock_out') {
        // Fetch existing record
        $recStmt = $pdo->prepare("SELECT * FROM attendance_records WHERE employee_id = ? AND date = ?");
        $recStmt->execute([$emp_id, $today]);
        $record = $recStmt->fetch();

        if ($record && empty($record['clock_out'])) {
            $clock_in_time = strtotime($record['clock_in']);
            $clock_out_time = strtotime($now);
            
            // Raw Total Hours (Clock out minus Clock in)
            $total_seconds = $clock_out_time - $clock_in_time;
            $total_hours = round($total_seconds / 3600, 2);

            // Evaluate Early Departure Math
            if ($emp['end_time']) {
                $expected_end = calculate_expected_shift_end($today, $emp['start_time'], $emp['end_time']);
                $grace = intval($emp['early_departure_grace']) * 60; // in seconds
                
                if ($expected_end !== null && $clock_out_time < ($expected_end - $grace)) {
                    $is_early_departure = 1;
                }
                
                // Evaluate Overtime Math
                // If they clocked out AFTER their shift end time + some buffer, calculate OT
                if ($expected_end !== null && $clock_out_time > $expected_end) {
                    $ot_seconds = $clock_out_time - $expected_end;
                    $raw_ot_hours = round($ot_seconds / 3600, 2);
                    if ($raw_ot_hours > 0.5) { // Only count OT if > 30 minutes
                        $overtime_hours = $raw_ot_hours;
                        $rate = floatval($emp['overtime_rate_per_hour']) > 0 ? floatval($emp['overtime_rate_per_hour']) : PAYROLL_NORMAL_OT_RATE;
                        $overtime_amount = calculate_overtime_amount(
                            $overtime_hours,
                            floatval($emp['base_salary']),
                            $emp['start_time'],
                            $emp['end_time'],
                            $rate
                        ); 
                    }
                }
            }

            // Determine final status
            $final_status = 'Present';
            // Half day logic could go here based on total_hours vs shift hours

            $update = $pdo->prepare("
                UPDATE attendance_records 
                SET clock_out = ?, total_hours = ?, is_early_departure = ?, is_absent = 0, overtime_hours = ?, overtime_amount = ?, is_holiday = ?, is_weekend = ?, status = ?
                WHERE id = ?
            ");
            $update->execute([$now, $total_hours, $is_early_departure, $overtime_hours, $overtime_amount, $calendarFlags['is_holiday'], $calendarFlags['is_weekend'], $final_status, $record['id']]);
            
            log_action($pdo, $emp_id, 'ATTENDANCE_CLOCK_OUT', "Clocked out at {$currentTime}. Total Hours: {$total_hours}h");
        }
    }
}
header("Location: index.php");
exit();
?>
