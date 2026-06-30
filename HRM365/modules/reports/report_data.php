<?php

require_once '../../includes/attendance_math.php';
require_once '../../includes/payroll_math.php';

$today = date('Y-m-d');
$defaultStart = date('Y-m-01');
$start_date = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : $defaultStart;
$end_date = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : $today;
if (strtotime($end_date) < strtotime($start_date)) {
    $end_date = $start_date;
}

$employee_filter = intval($_GET['employee_id'] ?? 0);
$branch_filter = intval($_GET['branch_id'] ?? 0);
$employeeWhere = '';
$employeeParams = [];
$branchOptions = [];
$branchWhere = '';

if ($currentUser['role'] === 'employee') {
    $employee_filter = intval($currentUser['employee_id'] ?? 0);
    $employeeWhere = 'WHERE e.id = ?';
    $employeeParams[] = $employee_filter;
} elseif ($currentUser['role'] === 'manager') {
    $employeeWhere = 'WHERE e.department = ?';
    $employeeParams[] = $currentUser['department'] ?? '';
} elseif (in_array($currentUser['role'], ['admin', 'HR'], true)) {
    $branchOptions = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
    if ($branch_filter > 0) {
        $employeeWhere = 'WHERE e.branch_id = ?';
        $employeeParams[] = $branch_filter;
        $branchWhere = ' AND e.branch_id = ?';
    }
}

$employeeSql = "
    SELECT e.id, e.first_name, e.last_name, e.employee_code, e.branch_id
    FROM employees e
    {$employeeWhere}
    ORDER BY e.first_name ASC
";
$employeeStmt = $pdo->prepare($employeeSql);
$employeeStmt->execute($employeeParams);
$employees = $employeeStmt->fetchAll();

$scopedEmployeeIds = array_map(fn($employee) => intval($employee['id']), $employees);
if ($employee_filter > 0 && !in_array($employee_filter, $scopedEmployeeIds, true)) {
    $employee_filter = 0;
}

$scopeSql = '';
$scopeParams = [];
if ($employee_filter > 0) {
    $scopeSql = ' AND e.id = ?';
    $scopeParams[] = $employee_filter;
} elseif ($currentUser['role'] === 'manager') {
    $scopeSql = ' AND e.department = ?';
    $scopeParams[] = $currentUser['department'] ?? '';
} elseif ($currentUser['role'] === 'employee') {
    $scopeSql = ' AND e.id = ?';
    $scopeParams[] = intval($currentUser['employee_id'] ?? 0);
} elseif (in_array($currentUser['role'], ['admin', 'HR'], true) && $branch_filter > 0) {
    $scopeSql = ' AND e.branch_id = ?';
    $scopeParams[] = $branch_filter;
}

$attendanceStmt = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.employee_code
    FROM attendance_records a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.date BETWEEN ? AND ?
    {$scopeSql}
    ORDER BY a.date DESC, e.first_name ASC
");
$attendanceStmt->execute(array_merge([$start_date, $end_date], $scopeParams));
$attendanceRows = $attendanceStmt->fetchAll();

$leaveStmt = $pdo->prepare("
    SELECT la.*, e.first_name, e.last_name, e.employee_code, lt.name AS leave_type, lt.is_paid
    FROM leave_applications la
    JOIN employees e ON e.id = la.employee_id
    JOIN leave_types lt ON lt.id = la.leave_type_id
    WHERE la.start_date <= ?
      AND COALESCE(la.end_date, la.start_date) >= ?
    {$scopeSql}
    ORDER BY la.start_date DESC, e.first_name ASC
");
$leaveStmt->execute(array_merge([$end_date, $start_date], $scopeParams));
$leaveRows = $leaveStmt->fetchAll();

$payrollStartMonth = date('Y-m', strtotime($start_date));
$payrollEndMonth = date('Y-m', strtotime($end_date));
$payrollStmt = $pdo->prepare("
    SELECT p.*, e.first_name, e.last_name, e.employee_code
    FROM payroll_records p
    JOIN employees e ON e.id = p.employee_id
    WHERE p.payroll_month BETWEEN ? AND ?
    {$scopeSql}
    ORDER BY p.payroll_month DESC, e.first_name ASC
");
$payrollStmt->execute(array_merge([$payrollStartMonth, $payrollEndMonth], $scopeParams));
$payrollRows = $payrollStmt->fetchAll();

$summary = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'hours' => 0.00,
    'overtime' => 0.00,
    'unpaid_days' => 0.00,
    'advance_amount' => 0.00,
    'epf_employee' => 0.00,
    'epf_employer' => 0.00,
    'etf_employer' => 0.00,
    'net_salary' => 0.00,
];

foreach ($attendanceRows as $row) {
    if ($row['status'] === 'Present') {
        $summary['present']++;
    } elseif ($row['status'] === 'Absent') {
        $summary['absent']++;
    } elseif ($row['status'] === 'On Leave') {
        $summary['leave']++;
    }
    $summary['hours'] += floatval($row['total_hours']);
    $summary['overtime'] += floatval($row['overtime_hours']);
}

foreach ($payrollRows as $row) {
    $summary['unpaid_days'] += floatval($row['unpaid_days'] ?? 0);
    $summary['advance_amount'] += floatval($row['advance_amount'] ?? 0);
    $summary['epf_employee'] += floatval($row['epf_employee_amount'] ?? 0);
    $summary['epf_employer'] += floatval($row['epf_employer_amount'] ?? 0);
    $summary['etf_employer'] += floatval($row['etf_employer_amount'] ?? 0);
    $summary['net_salary'] += floatval($row['net_salary']);
}

$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';
$payrollFeatures = payroll_feature_settings($pdo);
$selectedEmployeeLabel = 'All employees';
foreach ($employees as $employee) {
    if (intval($employee['id']) === $employee_filter) {
        $selectedEmployeeLabel = $employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')';
        break;
    }
}
$selectedBranchLabel = 'All branches';
foreach ($branchOptions as $branch) {
    if (intval($branch['id']) === $branch_filter) {
        $selectedBranchLabel = $branch['name'];
        break;
    }
}
$periodLabel = date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date));

$leaveByEmployee = [];
foreach ($leaveRows as $row) {
    $employeeId = intval($row['employee_id']);
    if (!isset($leaveByEmployee[$employeeId])) {
        $leaveByEmployee[$employeeId] = [];
    }
    $leaveByEmployee[$employeeId][] = $row;
}

$leavePayrollRows = [];
$seenLeaveEmployees = [];
foreach ($payrollRows as $row) {
    $employeeId = intval($row['employee_id']);
    $seenLeaveEmployees[$employeeId] = true;
    $leavePayrollRows[] = [
        'employee_id' => $employeeId,
        'employee_name' => $row['first_name'] . ' ' . $row['last_name'],
        'employee_code' => $row['employee_code'],
        'leave_rows' => $leaveByEmployee[$employeeId] ?? [],
        'payroll' => $row,
    ];
}

foreach ($leaveByEmployee as $employeeId => $rows) {
    if (isset($seenLeaveEmployees[$employeeId]) || empty($rows)) {
        continue;
    }
    $first = $rows[0];
    $leavePayrollRows[] = [
        'employee_id' => $employeeId,
        'employee_name' => $first['first_name'] . ' ' . $first['last_name'],
        'employee_code' => $first['employee_code'],
        'leave_rows' => $rows,
        'payroll' => null,
    ];
}
