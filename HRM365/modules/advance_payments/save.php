<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

if (!in_array($currentUser['role'], ['admin', 'HR'], true)) {
    die("Unauthorized access.");
}

$employeeId = intval($_POST['employee_id'] ?? 0);
$amount = round(floatval($_POST['amount'] ?? 0), 2);
$paymentDate = trim($_POST['payment_date'] ?? '');
$deductionMonth = trim($_POST['deduction_month'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$status = $_POST['status'] ?? 'Paid';
if (!in_array($status, ['Pending', 'Approved', 'Paid'], true)) {
    $status = 'Pending';
}

if ($employeeId <= 0 || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate) || !preg_match('/^\d{4}-\d{2}$/', $deductionMonth)) {
    header('Location: index.php?error=' . urlencode('Invalid advance payment details.'));
    exit();
}

$empStmt = $pdo->prepare("SELECT employee_code FROM employees WHERE id = ?");
$empStmt->execute([$employeeId]);
$employeeCode = $empStmt->fetchColumn();
if (!$employeeCode) {
    header('Location: index.php?error=' . urlencode('Employee not found.'));
    exit();
}

$approvedBy = in_array($status, ['Approved', 'Paid'], true) ? intval($currentUser['id']) : null;
$paidBy = $status === 'Paid' ? intval($currentUser['id']) : null;
$approvedAt = in_array($status, ['Approved', 'Paid'], true) ? date('Y-m-d H:i:s') : null;
$paidAt = $status === 'Paid' ? date('Y-m-d H:i:s') : null;

$stmt = $pdo->prepare("
    INSERT INTO advance_payments
    (employee_id, amount, payment_date, deduction_month, reason, status, created_by, approved_by, paid_by, approved_at, paid_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $employeeId, $amount, $paymentDate, $deductionMonth, $reason, $status,
    intval($currentUser['id']), $approvedBy, $paidBy, $approvedAt, $paidAt
]);

log_action($pdo, $currentUser['id'], 'ADVANCE_PAYMENT_CREATED', "Created {$status} advance for {$employeeCode}: {$amount} deducting {$deductionMonth}");

header('Location: index.php?month=' . urlencode($deductionMonth) . '&success=advance_added');
exit();
?>
