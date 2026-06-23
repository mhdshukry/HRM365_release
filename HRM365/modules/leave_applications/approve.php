<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';
require_once '../../includes/leave_math.php';

function get_unpaid_leave_type_id(PDO $pdo): ?int
{
    $stmt = $pdo->query("SELECT id FROM leave_types WHERE is_paid = 0 AND status = 'Active' ORDER BY id ASC LIMIT 1");
    $id = $stmt->fetchColumn();
    return $id ? intval($id) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id']);
    $action = $_POST['action'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT la.*, lt.is_paid, lt.name AS leave_type_name
            FROM leave_applications la
            JOIN leave_types lt ON lt.id = la.leave_type_id
            WHERE la.id = ? AND la.status = 'Pending'
            FOR UPDATE
        ");
        $stmt->execute([$id]);
        $app = $stmt->fetch();

        if (!$app) {
            die("Application not found or already processed.");
        }

        $new_status = ($action === 'approve') ? 'Approved' : 'Rejected';

        // If Approved, deduct from leave_balances
        if ($new_status === 'Approved') {
            $requestedDays = floatval($app['total_days']);
            $isShortLeave = is_short_leave_name($app['leave_type_name'] ?? '');
            $paidDays = $requestedDays;
            $excessDays = 0.00;

            if (intval($app['is_paid']) === 1) {
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
                $remainingDays = $balance ? max(0.00, floatval($balance['remaining_days'])) : 0.00;

                if ($isShortLeave) {
                    $monthStart = date('Y-m-01', strtotime($app['start_date']));
                    $monthEnd = date('Y-m-t', strtotime($app['start_date']));
                    $shortStmt = $pdo->prepare("
                        SELECT COALESCE(SUM(la.total_days), 0) AS approved_short_days
                        FROM leave_applications la
                        JOIN leave_types lt ON lt.id = la.leave_type_id
                        WHERE la.employee_id = ?
                          AND la.status = 'Approved'
                          AND lt.name LIKE '%Short%'
                          AND la.start_date BETWEEN ? AND ?
                    ");
                    $shortStmt->execute([$app['employee_id'], $monthStart, $monthEnd]);
                    $previousRawShortDays = floatval($shortStmt->fetchColumn());
                    $previousChargedDays = calculate_short_leave_charge_from_days($previousRawShortDays);
                    $newChargedDays = calculate_short_leave_charge_from_days($previousRawShortDays + $requestedDays);
                    $requestedDays = round($newChargedDays - $previousChargedDays, 2);
                }

                $paidDays = min($requestedDays, $remainingDays);
                $excessDays = round($requestedDays - $paidDays, 2);

                if ($paidDays > 0 && $balance) {
                    $deductStmt = $pdo->prepare("
                        UPDATE leave_balances
                        SET used_days = used_days + ?
                        WHERE id = ?
                    ");
                    $deductStmt->execute([
                        $paidDays,
                        $balance['id']
                    ]);
                }

                if ($excessDays > 0) {
                    $unpaidLeaveTypeId = get_unpaid_leave_type_id($pdo);
                    if (!$unpaidLeaveTypeId) {
                        $pdo->rollBack();
                        die("No active unpaid leave type found.");
                    }

                    if ($paidDays > 0) {
                        $adjustPaidStmt = $pdo->prepare("UPDATE leave_applications SET total_days = ? WHERE id = ?");
                        $adjustPaidStmt->execute([$paidDays, $id]);

                        $unpaidStmt = $pdo->prepare("
                            INSERT INTO leave_applications
                            (employee_id, leave_type_id, covering_employee_id, start_date, end_date, total_days, reason, status, approved_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'Approved', ?)
                        ");
                        $unpaidStmt->execute([
                            $app['employee_id'],
                            $unpaidLeaveTypeId,
                            $app['covering_employee_id'],
                            $app['start_date'],
                            $app['end_date'],
                            $excessDays,
                            'Auto no-pay excess from leave request #' . $id . ': ' . $app['reason'],
                            $currentUser['id']
                        ]);
                    } else {
                        $convertStmt = $pdo->prepare("UPDATE leave_applications SET leave_type_id = ?, total_days = ? WHERE id = ?");
                        $convertStmt->execute([$unpaidLeaveTypeId, $requestedDays, $id]);
                    }
                }
            }
        }

        $updateReq = $pdo->prepare("UPDATE leave_applications SET status = ?, approved_by = ? WHERE id = ?");
        $updateReq->execute([$new_status, $currentUser['id'], $id]);

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
