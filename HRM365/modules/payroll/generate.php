<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/payroll_math.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $month = trim($_POST['month'] ?? ''); // Format: YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $month) || strtotime($month . '-01') === false) {
        die("Invalid payroll month.");
    }

    try {
        $pdo->beginTransaction();
        generate_payroll_for_month($pdo, $month);

        $pdo->commit();
        log_action($pdo, $currentUser['id'], 'PAYROLL_GENERATED', "Generated payroll for {$month}");
        
        header("Location: index.php?month={$month}&success=generated");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error generating payroll: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
