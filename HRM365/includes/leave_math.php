<?php

function calculate_leave_policy_allocation(array $policy): float
{
    $rate = floatval($policy['accrual_rate'] ?? 0);
    $type = $policy['accrual_type'] ?? 'Yearly';

    if ($type === 'Monthly') {
        return round($rate * 12, 0);
    }

    if ($type === 'Quarterly') {
        return round($rate * 4, 0);
    }

    return round($rate, 0);
}

function format_leave_days(float $days): string
{
    return (string) intval(round($days, 0));
}

function generate_leave_balances_for_year(PDO $pdo, int $year): int
{
    $employees = $pdo->query("SELECT id FROM employees WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
    $policies = $pdo->query("
        SELECT id, leave_type_id, accrual_type, accrual_rate, carry_forward_limit
        FROM leave_policies
        WHERE status = 'Active'
    ")->fetchAll(PDO::FETCH_ASSOC);

    $upsert = $pdo->prepare("
        INSERT INTO leave_balances
            (employee_id, leave_type_id, leave_policy_id, year, allocated_days, carried_forward)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            leave_policy_id = VALUES(leave_policy_id),
            allocated_days = VALUES(allocated_days),
            carried_forward = VALUES(carried_forward)
    ");

    $previousStmt = $pdo->prepare("
        SELECT allocated_days, carried_forward, manual_adjustment, used_days
        FROM leave_balances
        WHERE employee_id = ? AND leave_type_id = ? AND year = ?
        LIMIT 1
    ");

    $affected = 0;
    foreach ($employees as $employee) {
        foreach ($policies as $policy) {
            $allocated = calculate_leave_policy_allocation($policy);
            $carriedForward = 0.00;

            $previousStmt->execute([$employee['id'], $policy['leave_type_id'], $year - 1]);
            $previous = $previousStmt->fetch();
            if ($previous) {
                $previousRemaining = floatval($previous['allocated_days']) + floatval($previous['carried_forward']) + floatval($previous['manual_adjustment']) - floatval($previous['used_days']);
                $carriedForward = min(max($previousRemaining, 0), floatval($policy['carry_forward_limit']));
            }

            $upsert->execute([
                $employee['id'],
                $policy['leave_type_id'],
                $policy['id'],
                $year,
                $allocated,
                $carriedForward
            ]);
            $affected += $upsert->rowCount();
        }
    }

    return $affected;
}
