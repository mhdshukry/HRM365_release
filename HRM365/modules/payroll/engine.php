<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/payroll_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$month = isset($_GET['month']) ? trim($_GET['month']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month) || strtotime($month . '-01') === false) {
    die("Invalid payroll month.");
}

try {
    $pdo->beginTransaction();
    generate_payroll_for_month($pdo, $month);
    $pdo->commit();

    log_action($pdo, $currentUser['id'], 'PAYROLL_ENGINE_RUN', "Ran payroll engine for {$month}");

    header("Location: index.php?month={$month}&success=generated");
    exit();
} catch (\PDOException $e) {
    $pdo->rollBack();
    die("Engine Failure: " . $e->getMessage());
}
