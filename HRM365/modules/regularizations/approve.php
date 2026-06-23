<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/attendance_math.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id']);
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        // Fetch the request
        $stmt = $pdo->prepare("SELECT * FROM attendance_regularizations WHERE id = ? AND status = 'Pending' FOR UPDATE");
        $stmt->execute([$id]);
        $request = $stmt->fetch();

        if (!$request) {
            $pdo->rollBack();
            die("Request not found or already processed.");
        }

        $now = date('Y-m-d H:i:s');
        $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';

        // 1. Update the Request Status
        $updateReq = $pdo->prepare("UPDATE attendance_regularizations SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?");
        $updateReq->execute([$new_status, $currentUser['id'], $now, $id]);

        // 2. If Approved, Fire the Math Recalculation Engine
        if ($new_status === 'Approved') {
            $emp_id = $request['employee_id'];
            $date = $request['date'];
            $req_in = $request['requested_clock_in'];
            $req_out = $request['requested_clock_out'];

            // Fetch Employee Constraints
            $empStmt = $pdo->prepare("
                SELECT e.shift_id, e.attendance_policy_id, e.base_salary, e.branch_id, s.start_time, s.end_time,
                       p.late_arrival_grace, p.early_departure_grace, p.overtime_rate_per_hour
                FROM employees e
                LEFT JOIN shifts s ON e.shift_id = s.id
                LEFT JOIN attendance_policies p ON e.attendance_policy_id = p.id
                WHERE e.id = ?
            ");
            $empStmt->execute([$emp_id]);
            $emp = $empStmt->fetch();

            if (!$emp) {
                $pdo->rollBack();
                die("Employee not found.");
            }

            $is_late = 0;
            $is_early_departure = 0;
            $overtime_hours = 0.00;
            $overtime_amount = 0.00;

            $clock_in_time = strtotime($req_in);
            $clock_out_time = strtotime($req_out);

            if ($clock_in_time === false || $clock_out_time === false) {
                $pdo->rollBack();
                die("Invalid requested clock times.");
            }

            if ($clock_out_time <= $clock_in_time) {
                $clock_out_time = strtotime('+1 day', $clock_out_time);
                $req_out = date('Y-m-d H:i:s', $clock_out_time);
            }

            // Raw Total Hours
            $total_seconds = $clock_out_time - $clock_in_time;
            $total_hours = round($total_seconds / 3600, 2);

            if ($emp && $emp['start_time']) {
                // Check Lateness
                $expected_start = strtotime($date . ' ' . $emp['start_time']);
                $late_grace = intval($emp['late_arrival_grace']) * 60;
                if ($clock_in_time > ($expected_start + $late_grace)) {
                    $is_late = 1;
                }

                // Check Early Departure & Overtime
                if ($emp['end_time']) {
                    $expected_end = calculate_expected_shift_end($date, $emp['start_time'], $emp['end_time']);
                    $early_grace = intval($emp['early_departure_grace']) * 60;
                    
                    if ($expected_end !== null && $clock_out_time < ($expected_end - $early_grace)) {
                        $is_early_departure = 1;
                    }

                    if ($expected_end !== null && $clock_out_time > $expected_end) {
                        $ot_seconds = $clock_out_time - $expected_end;
                        $raw_ot_hours = round($ot_seconds / 3600, 2);
                        if ($raw_ot_hours > 0.5) {
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
            }

            // Update the Core Timesheet Record
            $calendarFlags = get_attendance_calendar_flags($pdo, $date, $emp['branch_id'] !== null ? intval($emp['branch_id']) : null);
            $updateRec = $pdo->prepare("
                UPDATE attendance_records 
                SET shift_id = ?, attendance_policy_id = ?, clock_in = ?, clock_out = ?, total_hours = ?, 
                    is_late = ?, is_early_departure = ?, is_absent = 0,
                    overtime_hours = ?, overtime_amount = ?, is_holiday = ?, is_weekend = ?,
                    status = 'Present'
                WHERE id = ?
            ");
            $updateRec->execute([
                $emp['shift_id'], $emp['attendance_policy_id'],
                $req_in, $req_out, $total_hours, 
                $is_late, $is_early_departure, 
                $overtime_hours, $overtime_amount, $calendarFlags['is_holiday'], $calendarFlags['is_weekend'],
                $request['attendance_record_id']
            ]);
        }

        $pdo->commit();
        log_action($pdo, $currentUser['id'], 'REGULARIZATION_' . strtoupper($new_status), "{$new_status} regularization request #{$id}");
        
        header("Location: index.php?success=processed");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error processing request: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
