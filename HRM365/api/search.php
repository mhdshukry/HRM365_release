<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit();
}

$results = [];
$like = '%' . $q . '%';

$employeeSql = "
    SELECT id, employee_code, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
      AND (employee_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)
";
$params = [$like, $like, $like, $like];

if ($currentUser['role'] === 'employee') {
    $employeeSql .= " AND id = ?";
    $params[] = $currentUser['employee_id'] ?? 0;
} elseif ($currentUser['role'] === 'manager') {
    $employeeSql .= " AND department = ?";
    $params[] = $currentUser['department'] ?? '';
}

$employeeSql .= " ORDER BY first_name ASC LIMIT 5";
$stmt = $pdo->prepare($employeeSql);
$stmt->execute($params);
foreach ($stmt->fetchAll() as $employee) {
    $employeeUrl = $currentUser['role'] === 'employee'
        ? app_url('profile.php')
        : app_url('modules/employees/edit.php?id=' . $employee['id']);

    $results[] = [
        'type' => 'Employee',
        'title' => $employee['first_name'] . ' ' . $employee['last_name'],
        'meta' => $employee['employee_code'] . ' · ' . ($employee['department'] ?: 'No department'),
        'url' => $employeeUrl,
    ];
}

if (in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'], true)) {
    $leaveSql = "
        SELECT la.id, la.status, la.start_date, la.end_date, e.first_name, e.last_name, e.employee_code
        FROM leave_applications la
        JOIN employees e ON la.employee_id = e.id
        WHERE (e.employee_code LIKE ? OR e.first_name LIKE ? OR e.last_name LIKE ? OR la.status LIKE ?)
    ";
    $params = [$like, $like, $like, $like];

    if ($currentUser['role'] === 'employee') {
        $leaveSql .= " AND la.employee_id = ?";
        $params[] = $currentUser['employee_id'] ?? 0;
    } elseif ($currentUser['role'] === 'manager') {
        $leaveSql .= " AND e.department = ?";
        $params[] = $currentUser['department'] ?? '';
    }

    $leaveSql .= " ORDER BY la.created_at DESC LIMIT 5";
    $stmt = $pdo->prepare($leaveSql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $leave) {
        $results[] = [
            'type' => 'Leave',
            'title' => $leave['first_name'] . ' ' . $leave['last_name'] . ' · ' . $leave['status'],
            'meta' => $leave['employee_code'] . ' · ' . $leave['start_date'] . ' to ' . ($leave['end_date'] ?: $leave['start_date']),
            'url' => app_url('modules/leave_applications/index.php'),
        ];
    }
}

echo json_encode(['results' => array_slice($results, 0, 10)]);
?>
