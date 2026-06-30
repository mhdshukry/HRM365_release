<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/attendance_math.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$id = intval($_POST['id'] ?? 0);
$clock_in = trim($_POST['clock_in'] ?? '');
$clock_out = trim($_POST['clock_out'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if ($id <= 0 || $clock_in === '' || $clock_out === '' || $notes === '') {
    die("Sign-in, sign-out, and correction note are required.");
}

$clockInTs = strtotime($clock_in);
$clockOutTs = strtotime($clock_out);
if ($clockInTs === false || $clockOutTs === false || $clockOutTs <= $clockInTs) {
    die("Sign-out must be after sign-in.");
}

$stmt = $pdo->prepare("
    SELECT r.*, e.employee_code, e.base_salary, e.branch_id,
           s.start_time, s.end_time,
           p.late_arrival_grace, p.early_departure_grace, p.overtime_rate_per_hour
    FROM attendance_records r
    JOIN employees e ON e.id = r.employee_id
    LEFT JOIN shifts s ON s.id = r.shift_id
    LEFT JOIN attendance_policies p ON p.id = r.attendance_policy_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Attendance record not found.");
}

$date = date('Y-m-d', $clockInTs);
$branchId = $record['branch_id'] !== null ? intval($record['branch_id']) : null;
$dayContext = get_attendance_day_context(
    $pdo,
    intval($record['employee_id']),
    $date,
    $record['shift_id'] !== null ? intval($record['shift_id']) : null
);
$resolvedShift = $dayContext['shift'];
$blockStatus = $dayContext['block_status'];
if ($blockStatus !== null) {
    die("Attendance correction cannot be saved for {$date}: {$blockStatus}.");
}
if ($resolvedShift) {
    $record['shift_id'] = intval($resolvedShift['id']);
    $record['start_time'] = $resolvedShift['start_time'];
    $record['end_time'] = $resolvedShift['end_time'];
}

$clockInDb = date('Y-m-d H:i:s', $clockInTs);
$clockOutDb = date('Y-m-d H:i:s', $clockOutTs);
$totalHours = round(($clockOutTs - $clockInTs) / 3600, 2);
$isLate = 0;
$isEarly = 0;
$overtimeHours = 0.00;
$overtimeAmount = 0.00;

if (!empty($record['start_time'])) {
    $expectedStart = strtotime($date . ' ' . $record['start_time']);
    $lateGrace = intval($record['late_arrival_grace'] ?? 0) * 60;
    if ($expectedStart !== false && $clockInTs > ($expectedStart + $lateGrace)) {
        $isLate = 1;
    }
}

$expectedEnd = calculate_expected_shift_end($date, $record['start_time'], $record['end_time']);
if ($expectedEnd !== null) {
    $earlyGrace = intval($record['early_departure_grace'] ?? 0) * 60;
    if ($clockOutTs < ($expectedEnd - $earlyGrace)) {
        $isEarly = 1;
    }

    if ($clockOutTs > $expectedEnd) {
        $rawOvertimeHours = round(($clockOutTs - $expectedEnd) / 3600, 2);
        if ($rawOvertimeHours > 0.5) {
            $overtimeHours = $rawOvertimeHours;
            $overtimeAmount = calculate_overtime_amount(
                $overtimeHours,
                floatval($record['base_salary']),
                $record['start_time'],
                $record['end_time'],
                floatval($record['overtime_rate_per_hour'] ?? PAYROLL_NORMAL_OT_RATE)
            );
        }
    }
}

$calendarFlags = $dayContext['calendar'];
$status = $calendarFlags['is_holiday'] ? 'Holiday' : 'Present';

$update = $pdo->prepare("
    UPDATE attendance_records
    SET date = ?, shift_id = ?, clock_in = ?, clock_out = ?, total_hours = ?, is_late = ?, is_early_departure = ?,
        is_absent = 0, overtime_hours = ?, overtime_amount = ?, is_holiday = ?, is_weekend = ?, status = ?, notes = ?
    WHERE id = ?
");
$update->execute([
    $date, $record['shift_id'], $clockInDb, $clockOutDb, $totalHours, $isLate, $isEarly,
    $overtimeHours, $overtimeAmount, $calendarFlags['is_holiday'], $calendarFlags['is_weekend'],
    $status, $notes, $id
]);

log_action($pdo, $currentUser['id'], 'ATTENDANCE_CORRECTED', "Corrected attendance {$record['employee_code']} on {$date}");

header("Location: index.php?date=" . urlencode($date) . "&success=corrected");
exit();
