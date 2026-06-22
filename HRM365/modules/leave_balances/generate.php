<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/leave_math.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $year = intval($_POST['year'] ?? date('Y'));
    if ($year < 2000 || $year > 2100) {
        $year = intval(date('Y'));
    }

    try {
        $affected = generate_leave_balances_for_year($pdo, $year);
        log_action($pdo, $currentUser['id'], 'LEAVE_BALANCES_GENERATED', "Generated leave balances for {$year}. Affected rows: {$affected}");

        header("Location: index.php?year={$year}&success=generated");
        exit();
    } catch (\PDOException $e) {
        die("Error generating leave balances: " . $e->getMessage());
    }
}

header("Location: index.php");
exit();
?>
