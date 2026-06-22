<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

header('Content-Type: application/json');

$employee_id = intval($_GET['employee_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

if ($currentUser['role'] === 'employee') {
    $employee_id = intval($currentUser['employee_id'] ?? 0);
} elseif ($currentUser['role'] === 'manager') {
    $scopeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND department = ?");
    $scopeStmt->execute([$employee_id, $currentUser['department'] ?? '']);
    if (!$scopeStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized employee selection.']);
        exit();
    }
}

if ($employee_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
    http_response_code(422);
    echo json_encode(['error' => 'Employee and date are required.']);
    exit();
}

$empStmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.employee_code, e.branch_id,
           s.name AS shift_name, s.start_time, s.end_time,
           p.name AS policy_name, p.late_arrival_grace, p.early_departure_grace
    FROM employees e
    LEFT JOIN shifts s ON s.id = e.shift_id
    LEFT JOIN attendance_policies p ON p.id = e.attendance_policy_id
    WHERE e.id = ?
");
$empStmt->execute([$employee_id]);
$employee = $empStmt->fetch();

if (!$employee) {
    http_response_code(404);
    echo json_encode(['error' => 'Employee not found.']);
    exit();
}

$recordStmt = $pdo->prepare("
    SELECT id, clock_in, clock_out, total_hours, is_late, is_early_departure, is_absent, is_holiday, is_weekend, status, notes
    FROM attendance_records
    WHERE employee_id = ? AND date = ?
    LIMIT 1
");
$recordStmt->execute([$employee_id, $date]);
$record = $recordStmt->fetch() ?: null;

$leaveStmt = $pdo->prepare("
    SELECT la.status, la.start_date, la.end_date, la.total_days, lt.name AS leave_type, lt.is_paid
    FROM leave_applications la
    JOIN leave_types lt ON lt.id = la.leave_type_id
    WHERE la.employee_id = ?
      AND ? BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
    ORDER BY FIELD(la.status, 'Approved', 'Pending', 'Rejected'), la.created_at DESC
");
$leaveStmt->execute([$employee_id, $date]);
$leaves = $leaveStmt->fetchAll();

$calendarFlags = get_attendance_calendar_flags($pdo, $date, $employee['branch_id'] !== null ? intval($employee['branch_id']) : null);

echo json_encode([
    'employee' => [
        'name' => trim($employee['first_name'] . ' ' . $employee['last_name']),
        'code' => $employee['employee_code'],
        'shift' => $employee['shift_name'] ?: 'No Shift',
        'policy' => $employee['policy_name'] ?: 'No Policy',
        'start_time' => $employee['start_time'],
        'end_time' => $employee['end_time'],
        'late_grace' => intval($employee['late_arrival_grace'] ?? 0),
        'early_grace' => intval($employee['early_departure_grace'] ?? 0),
    ],
    'attendance' => $record,
    'leaves' => $leaves,
    'calendar' => [
        'is_holiday' => intval($calendarFlags['is_holiday']),
        'is_weekend' => intval($calendarFlags['is_weekend']),
    ],
]);
