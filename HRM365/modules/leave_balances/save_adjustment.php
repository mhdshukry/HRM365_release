<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id']);
    $manual_adjustment = floatval($_POST['manual_adjustment']);
    $adjustment_reason = trim($_POST['adjustment_reason']);

    if ($id > 0 && !empty($adjustment_reason)) {
        try {
            // First fetch the ledger to get the employee ID and year for the audit log
            $fetchStmt = $pdo->prepare("SELECT employee_id, year, leave_type_id FROM leave_balances WHERE id = ?");
            $fetchStmt->execute([$id]);
            $ledger = $fetchStmt->fetch();

            if ($ledger) {
                // Update the mathematical injection
                $stmt = $pdo->prepare("
                    UPDATE leave_balances 
                    SET manual_adjustment = ?, adjustment_reason = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$manual_adjustment, $adjustment_reason, $id]);
                
                log_action(
                    $pdo, 
                    $currentUser['id'], 
                    'LEAVE_BALANCE_ADJUSTED', 
                    "Injected {$manual_adjustment} days to Employee #{$ledger['employee_id']} for {$ledger['year']} (Reason: {$adjustment_reason})"
                );
                
                header("Location: index.php?year={$ledger['year']}&success=balance_adjusted");
                exit();
            }
        } catch (\PDOException $e) {
            die("Error adjusting ledger: " . $e->getMessage());
        }
    }
}
header("Location: index.php");
exit();
?>
