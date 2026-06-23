<?php

require_once __DIR__ . '/attendance_math.php';
require_once __DIR__ . '/leave_math.php';

function payroll_date_range(string $start_date, string $end_date): array
{
    $dates = [];
    $current = strtotime($start_date);
    $end = strtotime($end_date);

    while ($current !== false && $end !== false && $current <= $end) {
        $dates[] = date('Y-m-d', $current);
        $current = strtotime('+1 day', $current);
    }

    return $dates;
}

function calculate_leave_overlap_days(array $leave, string $start_date, string $end_date): float
{
    $rawLeaveStart = strtotime($leave['start_date']);
    $rawLeaveEnd = strtotime($leave['end_date'] ?: $leave['start_date']);
    $leaveStart = max($rawLeaveStart, strtotime($start_date));
    $leaveEnd = min($rawLeaveEnd, strtotime($end_date));

    if ($leaveStart === false || $leaveEnd === false || $leaveEnd < $leaveStart) {
        return 0.00;
    }

    $totalDays = floatval($leave['total_days']);
    if ($rawLeaveStart === $rawLeaveEnd) {
        return $totalDays;
    }

    $leaveCalendarDays = floatval(intdiv($rawLeaveEnd - $rawLeaveStart, 86400) + 1);
    $overlapCalendarDays = floatval(intdiv($leaveEnd - $leaveStart, 86400) + 1);

    if ($leaveCalendarDays <= 0) {
        return 0.00;
    }

    return round($totalDays * ($overlapCalendarDays / $leaveCalendarDays), 2);
}

function calculate_employee_leave_days(PDO $pdo, int $employee_id, string $start_date, string $end_date, ?bool $is_paid = null): float
{
    $paidFilter = '';
    $params = [$employee_id, $end_date, $start_date];
    if ($is_paid !== null) {
        $paidFilter = ' AND lt.is_paid = ?';
        $params[] = $is_paid ? 1 : 0;
    }

    $stmt = $pdo->prepare("
        SELECT la.start_date, COALESCE(la.end_date, la.start_date) AS end_date, la.total_days, lt.name AS leave_type_name
        FROM leave_applications la
        JOIN leave_types lt ON la.leave_type_id = lt.id
        WHERE la.employee_id = ?
          AND la.status = 'Approved'
          AND la.start_date <= ?
          AND COALESCE(la.end_date, la.start_date) >= ?
          {$paidFilter}
    ");
    $stmt->execute($params);

    $days = 0.00;
    $shortLeaveCountsByMonth = [];
    foreach ($stmt->fetchAll() as $leave) {
        $overlapDays = calculate_leave_overlap_days($leave, $start_date, $end_date);
        if (is_short_leave_name($leave['leave_type_name'] ?? '')) {
            $monthKey = date('Y-m', strtotime($leave['start_date']));
            $shortLeaveCountsByMonth[$monthKey] = ($shortLeaveCountsByMonth[$monthKey] ?? 0)
                + (int) round($overlapDays / SHORT_LEAVE_UNIT_DAYS);
            continue;
        }

        $days += $overlapDays;
    }

    foreach ($shortLeaveCountsByMonth as $shortLeaveCount) {
        $days += calculate_short_leave_charge_from_count($shortLeaveCount);
    }

    return round($days, 2);
}

function employee_has_approved_leave_on(PDO $pdo, int $employee_id, string $date): bool
{
    return employee_approved_leave_days_on($pdo, $employee_id, $date) > 0;
}

function employee_approved_leave_days_on(PDO $pdo, int $employee_id, string $date): float
{
    $stmt = $pdo->prepare("
        SELECT la.start_date, COALESCE(la.end_date, la.start_date) AS end_date, la.total_days
        FROM leave_applications la
        WHERE la.employee_id = ?
          AND la.status = 'Approved'
          AND ? BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
    ");
    $stmt->execute([$employee_id, $date]);

    $days = 0.00;
    foreach ($stmt->fetchAll() as $leave) {
        $days += calculate_leave_overlap_days($leave, $date, $date);
    }

    return min(round($days, 2), 1.00);
}

function ensure_month_attendance_exceptions(PDO $pdo, array $employee, string $start_date, string $end_date): float
{
    $employee_id = intval($employee['id']);
    $branch_id = $employee['branch_id'] !== null ? intval($employee['branch_id']) : null;
    $shift_id = $employee['shift_id'] !== null ? intval($employee['shift_id']) : null;
    $policy_id = $employee['attendance_policy_id'] !== null ? intval($employee['attendance_policy_id']) : null;
    $hireDate = !empty($employee['hire_date']) ? $employee['hire_date'] : $start_date;
    $effectiveStart = max($start_date, $hireDate);
    $effectiveEnd = min($end_date, date('Y-m-d'));

    if (strtotime($effectiveEnd) < strtotime($effectiveStart)) {
        return 0.00;
    }

    $recordStmt = $pdo->prepare("
        SELECT id, clock_in, clock_out, is_absent, status
        FROM attendance_records
        WHERE employee_id = ? AND date = ?
        LIMIT 1
    ");
    $insertStmt = $pdo->prepare("
        INSERT INTO attendance_records
        (employee_id, shift_id, attendance_policy_id, date, is_absent, is_holiday, is_weekend, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        is_holiday = VALUES(is_holiday),
        is_weekend = VALUES(is_weekend)
    ");

    $absentDays = 0.00;
    foreach (payroll_date_range($effectiveStart, $effectiveEnd) as $date) {
        $calendarFlags = get_attendance_calendar_flags($pdo, $date, $branch_id);

        $recordStmt->execute([$employee_id, $date]);
        $record = $recordStmt->fetch();

        $approvedLeaveDays = employee_approved_leave_days_on($pdo, $employee_id, $date);
        if ($approvedLeaveDays > 0) {
            if (!$record && $approvedLeaveDays >= 1) {
                $insertStmt->execute([
                    $employee_id, $shift_id, $policy_id, $date, 0,
                    $calendarFlags['is_holiday'], $calendarFlags['is_weekend'],
                    'On Leave', 'Generated from approved leave during payroll run'
                ]);
            }
            if ($approvedLeaveDays >= 1 || $record || $calendarFlags['is_holiday'] || $calendarFlags['is_weekend']) {
                continue;
            }
        }

        if ($calendarFlags['is_holiday'] || $calendarFlags['is_weekend']) {
            continue;
        }

        if (!$record) {
            $insertStmt->execute([
                $employee_id, $shift_id, $policy_id, $date, 1,
                $calendarFlags['is_holiday'], $calendarFlags['is_weekend'],
                'Absent', 'Generated as unpaid absence by payroll engine'
            ]);
            $absentDays += max(0.00, 1.00 - $approvedLeaveDays);
            continue;
        }

        if (empty($record['clock_in']) && (intval($record['is_absent']) === 1 || $record['status'] === 'Absent')) {
            $absentDays += 1.00;
        }
    }

    return round($absentDays, 2);
}

function calculate_employee_overtime_summary(PDO $pdo, int $employee_id, float $base_salary, string $start_date, string $end_date): array
{
    $stmt = $pdo->prepare("
        SELECT r.overtime_hours,
               COALESCE(rs.start_time, es.start_time) AS start_time,
               COALESCE(rs.end_time, es.end_time) AS end_time,
               COALESCE(rp.overtime_rate_per_hour, ep.overtime_rate_per_hour, " . PAYROLL_NORMAL_OT_RATE . ") AS overtime_rate_per_hour
        FROM attendance_records r
        LEFT JOIN employees e ON r.employee_id = e.id
        LEFT JOIN shifts rs ON r.shift_id = rs.id
        LEFT JOIN shifts es ON e.shift_id = es.id
        LEFT JOIN attendance_policies rp ON r.attendance_policy_id = rp.id
        LEFT JOIN attendance_policies ep ON e.attendance_policy_id = ep.id
        WHERE r.employee_id = ?
          AND r.date BETWEEN ? AND ?
          AND r.overtime_hours > 0.5
          AND r.status = 'Present'
    ");
    $stmt->execute([$employee_id, $start_date, $end_date]);

    $hours = 0.00;
    $amount = 0.00;
    foreach ($stmt->fetchAll() as $row) {
        $remainingMonthlyHours = PAYROLL_MAX_OT_HOURS_PER_MONTH - $hours;
        if ($remainingMonthlyHours <= 0) {
            break;
        }

        $rowHours = min(floatval($row['overtime_hours']), $remainingMonthlyHours);
        $hours += $rowHours;
        $amount += calculate_overtime_amount(
            $rowHours,
            $base_salary,
            $row['start_time'],
            $row['end_time'],
            floatval($row['overtime_rate_per_hour'])
        );
    }

    return [
        'hours' => round($hours, 2),
        'amount' => round($amount, 2),
    ];
}

function calculate_employee_overtime_pay(PDO $pdo, int $employee_id, float $base_salary, string $start_date, string $end_date): float
{
    $summary = calculate_employee_overtime_summary($pdo, $employee_id, $base_salary, $start_date, $end_date);
    return $summary['amount'];
}

function generate_payroll_for_month(PDO $pdo, string $month): int
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new InvalidArgumentException('Invalid payroll month.');
    }

    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    if (strtotime($start_date) === false) {
        throw new InvalidArgumentException('Invalid payroll month.');
    }

    $processed = 0;

    $empStmt = $pdo->query("
        SELECT id, base_salary, hire_date, shift_id, attendance_policy_id, branch_id
        FROM employees
        WHERE status = 'Active'
    ");
    $employees = $empStmt->fetchAll();

    foreach ($employees as $emp) {
        $emp_id = intval($emp['id']);
        $base_salary = floatval($emp['base_salary']);
        $unpaid_leave_days = calculate_employee_leave_days($pdo, $emp_id, $start_date, $end_date, false);
        $absent_days = ensure_month_attendance_exceptions($pdo, $emp, $start_date, $end_date);
        $unpaid_days = round($unpaid_leave_days + $absent_days, 2);
        $overtime = calculate_employee_overtime_summary($pdo, $emp_id, $base_salary, $start_date, $end_date);
        $overtime_hours = $overtime['hours'];
        $overtime_amount = $overtime['amount'];

        $daily_rate = $base_salary / PAYROLL_STANDARD_MONTHLY_DAYS;
        $deductions = round($unpaid_days * $daily_rate, 2);
        $net_salary = round(($base_salary + $overtime_amount) - $deductions, 2);

        $upsert = $pdo->prepare("
            INSERT INTO payroll_records
            (employee_id, payroll_month, base_salary, overtime_hours, overtime_amount, deductions, unpaid_days, net_salary, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Finalized')
            ON DUPLICATE KEY UPDATE
            base_salary = IF(status = 'Paid', base_salary, VALUES(base_salary)),
            overtime_hours = IF(status = 'Paid', overtime_hours, VALUES(overtime_hours)),
            overtime_amount = IF(status = 'Paid', overtime_amount, VALUES(overtime_amount)),
            deductions = IF(status = 'Paid', deductions, VALUES(deductions)),
            unpaid_days = IF(status = 'Paid', unpaid_days, VALUES(unpaid_days)),
            net_salary = IF(status = 'Paid', net_salary, VALUES(net_salary)),
            status = IF(status = 'Paid', status, 'Finalized')
        ");
        $upsert->execute([
            $emp_id, $month, $base_salary, $overtime_hours, $overtime_amount,
            $deductions, $unpaid_days, $net_salary
        ]);
        $processed++;
    }

    return $processed;
}
