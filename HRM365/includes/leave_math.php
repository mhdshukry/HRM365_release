<?php

if (!defined('PROBATION_MONTHS')) {
    define('PROBATION_MONTHS', 6);
}

if (!defined('PROBATION_LEAVE_DAYS_PER_MONTH')) {
    define('PROBATION_LEAVE_DAYS_PER_MONTH', 0.50);
}

if (!defined('SHORT_LEAVE_UNITS_PER_DAY')) {
    define('SHORT_LEAVE_UNITS_PER_DAY', 3);
}

if (!defined('SHORT_LEAVE_UNIT_DAYS')) {
    define('SHORT_LEAVE_UNIT_DAYS', 0.25);
}

function is_short_leave_name(?string $leave_type_name): bool
{
    return stripos((string) $leave_type_name, 'short') !== false;
}

function calculate_short_leave_charge_from_count(int $short_leave_count): float
{
    if ($short_leave_count <= 0) {
        return 0.00;
    }

    $fullDayGroups = intdiv($short_leave_count, SHORT_LEAVE_UNITS_PER_DAY);
    $remainder = $short_leave_count % SHORT_LEAVE_UNITS_PER_DAY;

    return round($fullDayGroups + ($remainder * SHORT_LEAVE_UNIT_DAYS), 2);
}

function calculate_short_leave_charge_from_days(float $short_leave_days): float
{
    if ($short_leave_days <= 0) {
        return 0.00;
    }

    $count = (int) round($short_leave_days / SHORT_LEAVE_UNIT_DAYS);
    return calculate_short_leave_charge_from_count($count);
}

function calculate_leave_policy_allocation(array $policy): float
{
    $rate = floatval($policy['accrual_rate'] ?? 0);
    $type = $policy['accrual_type'] ?? 'Yearly';

    if ($type === 'Monthly') {
        return round($rate * 12, 2);
    }

    if ($type === 'Quarterly') {
        return round($rate * 4, 2);
    }

    return round($rate, 2);
}

function format_leave_days(float $days): string
{
    return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
}

function calculate_probation_leave_allocation_for_year(?string $hire_date, int $year): float
{
    if (empty($hire_date) || strtotime($hire_date) === false) {
        return 0.00;
    }

    $hire = new DateTime($hire_date);
    $probationEnd = (clone $hire)->modify('+' . PROBATION_MONTHS . ' months')->modify('-1 day');
    $yearStart = new DateTime($year . '-01-01');
    $yearEnd = new DateTime($year . '-12-31');

    $start = $hire > $yearStart ? $hire : $yearStart;
    $end = $probationEnd < $yearEnd ? $probationEnd : $yearEnd;

    if ($end < $start) {
        return 0.00;
    }

    $months = ((intval($end->format('Y')) - intval($start->format('Y'))) * 12)
        + intval($end->format('n')) - intval($start->format('n')) + 1;

    return round(max(0, $months) * PROBATION_LEAVE_DAYS_PER_MONTH, 2);
}

function is_employee_currently_probationary(?string $hire_date): bool
{
    if (empty($hire_date) || strtotime($hire_date) === false) {
        return false;
    }

    $hire = new DateTime($hire_date);
    $probationEnd = (clone $hire)->modify('+' . PROBATION_MONTHS . ' months')->modify('-1 day');
    $today = new DateTime(date('Y-m-d'));

    return $today <= $probationEnd;
}

function generate_leave_balances_for_year(PDO $pdo, int $year): int
{
    $employees = $pdo->query("SELECT id, hire_date FROM employees WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);
    $policies = $pdo->query("
        SELECT p.id, p.leave_type_id, p.accrual_type, p.accrual_rate, p.carry_forward_limit,
               t.name AS leave_type_name, t.is_paid
        FROM leave_policies p
        JOIN leave_types t ON t.id = p.leave_type_id
        WHERE p.status = 'Active'
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
            $probationAllocation = calculate_probation_leave_allocation_for_year($employee['hire_date'] ?? null, $year);
            $isProbationary = is_employee_currently_probationary($employee['hire_date'] ?? null);
            if ($isProbationary && intval($policy['is_paid']) === 1) {
                if (stripos($policy['leave_type_name'], 'casual') !== false || stripos($policy['leave_type_name'], 'annual') !== false) {
                    $allocated = stripos($policy['leave_type_name'], 'casual') !== false ? $probationAllocation : 0.00;
                }
            }
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
