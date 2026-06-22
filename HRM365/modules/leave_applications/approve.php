<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id']);
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM leave_applications WHERE id = ? AND status = 'Pending' FOR UPDATE");
        $stmt->execute([$id]);
        $app = $stmt->fetch();

        if (!$app) {
            die("Application not found or already processed.");
        }

        $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';
        
        $updateReq = $pdo->prepare("UPDATE leave_applications SET status = ?, approved_by = ? WHERE id = ?");
        $updateReq->execute([$new_status, $currentUser['id'], $id]);

        // If Approved, deduct from leave_balances
        if ($new_status === 'Approved') {
            $balanceStmt = $pdo->prepare("
                SELECT id, (allocated_days + carried_forward + manual_adjustment - used_days) AS remaining_days
                FROM leave_balances
                WHERE employee_id = ? AND leave_type_id = ? AND year = ?
                FOR UPDATE
            ");
            $balanceStmt->execute([
                $app['employee_id'],
                $app['leave_type_id'],
                date('Y', strtotime($app['start_date']))
            ]);
            $balance = $balanceStmt->fetch();

            if (!$balance || floatval($balance['remaining_days']) < floatval($app['total_days'])) {
                $pdo->rollBack();
                header("Location: index.php?error=insufficient_balance");
                exit();
            }

            $deductStmt = $pdo->prepare("
                UPDATE leave_balances 
                SET used_days = used_days + ?
                WHERE id = ?
            ");
            $deductStmt->execute([
                $app['total_days'], 
                $balance['id']
            ]);
        }

        $pdo->commit();
        log_action($pdo, $currentUser['id'], 'LEAVE_' . strtoupper($new_status), "{$new_status} leave request #{$id}");
        
        header("Location: index.php?success=processed");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error processing request: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
