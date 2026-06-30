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

$id = intval($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';
$month = trim($_POST['month'] ?? date('Y-m'));
if ($id <= 0 || !in_array($status, ['Approved', 'Paid', 'Cancelled'], true)) {
    header('Location: index.php?error=' . urlencode('Invalid status update.'));
    exit();
}

$stmt = $pdo->prepare("
    SELECT a.*, e.employee_code
    FROM advance_payments a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$advance = $stmt->fetch();
if (!$advance) {
    header('Location: index.php?error=' . urlencode('Advance payment not found.'));
    exit();
}

$approvedBySql = '';
$paidBySql = '';
$params = [$status];
if ($status === 'Approved') {
    $approvedBySql = ', approved_by = ?, approved_at = ?';
    $params[] = intval($currentUser['id']);
    $params[] = date('Y-m-d H:i:s');
} elseif ($status === 'Paid') {
    $approvedBySql = ', approved_by = COALESCE(approved_by, ?), approved_at = COALESCE(approved_at, ?)';
    $paidBySql = ', paid_by = ?, paid_at = ?';
    $params[] = intval($currentUser['id']);
    $params[] = date('Y-m-d H:i:s');
    $params[] = intval($currentUser['id']);
    $params[] = date('Y-m-d H:i:s');
}
$params[] = $id;

$update = $pdo->prepare("UPDATE advance_payments SET status = ? {$approvedBySql} {$paidBySql} WHERE id = ?");
$update->execute($params);

log_action($pdo, $currentUser['id'], 'ADVANCE_PAYMENT_STATUS_UPDATED', "Set advance {$advance['employee_code']} {$advance['deduction_month']} to {$status}");

header('Location: index.php?month=' . urlencode($month) . '&success=status_updated');
exit();
?>
