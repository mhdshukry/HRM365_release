<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$month = $_POST['month'] ?? date('Y-m');

if (!in_array($status, ['Draft', 'Finalized', 'Paid'], true)) {
    die("Invalid payroll status.");
}

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$stmt = $pdo->prepare("
    SELECT p.id, p.payroll_month, e.employee_code
    FROM payroll_records p
    JOIN employees e ON e.id = p.employee_id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payroll = $stmt->fetch();

if (!$payroll) {
    die("Payroll record not found.");
}

$update = $pdo->prepare("UPDATE payroll_records SET status = ? WHERE id = ?");
$update->execute([$status, $id]);

log_action($pdo, $currentUser['id'], 'PAYROLL_STATUS_UPDATED', "Set payroll {$payroll['employee_code']} {$payroll['payroll_month']} to {$status}");

header('Location: index.php?month=' . urlencode($month) . '&success=status_updated');
exit();
