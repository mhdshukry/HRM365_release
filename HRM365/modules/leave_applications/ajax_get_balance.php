<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$emp_id = isset($_GET['emp_id']) ? intval($_GET['emp_id']) : 0;
$year = date('Y');

if ($currentUser['role'] === 'employee') {
    $emp_id = intval($currentUser['employee_id'] ?? 0);
} elseif ($currentUser['role'] === 'manager' && $emp_id > 0) {
    $scopeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND department = ?");
    $scopeStmt->execute([$emp_id, $currentUser['department'] ?? '']);
    if (!$scopeStmt->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized employee selection']);
        exit();
    }
}

if ($emp_id > 0) {
    $stmt = $pdo->prepare("
        SELECT lt.id as type_id, lt.name, (lb.allocated_days + lb.carried_forward + lb.manual_adjustment - lb.used_days) as remaining_days 
        FROM leave_balances lb 
        JOIN leave_types lt ON lb.leave_type_id = lt.id
        WHERE lb.employee_id = ? AND lb.year = ?
    ");
    $stmt->execute([$emp_id, $year]);
    $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'balances' => $balances]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
}
?>
