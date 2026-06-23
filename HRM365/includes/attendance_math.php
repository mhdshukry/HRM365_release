<?php

if (!defined('PAYROLL_STANDARD_MONTHLY_HOURS')) {
    define('PAYROLL_STANDARD_MONTHLY_HOURS', 240.00);
}

if (!defined('PAYROLL_STANDARD_MONTHLY_DAYS')) {
    define('PAYROLL_STANDARD_MONTHLY_DAYS', 30.00);
}

if (!defined('PAYROLL_MAX_OT_HOURS_PER_MONTH')) {
    define('PAYROLL_MAX_OT_HOURS_PER_MONTH', 60.00);
}

if (!defined('PAYROLL_NORMAL_OT_RATE')) {
    define('PAYROLL_NORMAL_OT_RATE', 1.50);
}

if (!defined('PAYROLL_DOUBLE_OT_RATE')) {
    define('PAYROLL_DOUBLE_OT_RATE', 2.00);
}

function calculate_shift_hours(?string $start_time, ?string $end_time): float
{
    if (empty($start_time) || empty($end_time)) {
        return 8.00;
    }

    $start = strtotime('2000-01-01 ' . $start_time);
    $end = strtotime('2000-01-01 ' . $end_time);

    if ($start === false || $end === false) {
        return 8.00;
    }

    if ($end <= $start) {
        $end = strtotime('+1 day', $end);
    }

    $hours = round(($end - $start) / 3600, 2);
    return $hours > 0 ? $hours : 8.00;
}

function calculate_overtime_amount(float $overtime_hours, float $base_salary, ?string $start_time, ?string $end_time, float $policy_rate): float
{
    if ($overtime_hours <= 0 || $base_salary <= 0) {
        return 0.00;
    }

    $hourly_rate = $base_salary / PAYROLL_STANDARD_MONTHLY_HOURS;
    $rate = $policy_rate > 0 ? $policy_rate : PAYROLL_NORMAL_OT_RATE;

    return round($overtime_hours * $hourly_rate * $rate, 2);
}

function calculate_expected_shift_end(string $date, ?string $start_time, ?string $end_time): ?int
{
    if (empty($start_time) || empty($end_time)) {
        return null;
    }

    $start = strtotime($date . ' ' . $start_time);
    $end = strtotime($date . ' ' . $end_time);

    if ($start === false || $end === false) {
        return null;
    }

    if ($end <= $start) {
        $end = strtotime('+1 day', $end);
    }

    return $end;
}

function format_hours_minutes(float $hours): string
{
    $totalMinutes = (int) round($hours * 60);
    if ($totalMinutes <= 0) {
        return '0m';
    }

    $wholeHours = intdiv($totalMinutes, 60);
    $minutes = $totalMinutes % 60;

    if ($wholeHours > 0 && $minutes > 0) {
        return $wholeHours . 'h ' . $minutes . 'm';
    }

    if ($wholeHours > 0) {
        return $wholeHours . 'h';
    }

    return $minutes . 'm';
}

function get_attendance_calendar_flags(PDO $pdo, string $date, ?int $branch_id): array
{
    $isWeekend = in_array(date('N', strtotime($date)), ['6', '7'], true);

    $holidaySql = "
        SELECT h.id
        FROM holidays h
        LEFT JOIN holiday_branches hb ON h.id = hb.holiday_id
        WHERE ? BETWEEN h.start_date AND COALESCE(h.end_date, h.start_date)
          AND (
              h.applies_to_all_branches = 1
              OR (? IS NOT NULL AND hb.branch_id = ?)
          )
        LIMIT 1
    ";
    $holidayStmt = $pdo->prepare($holidaySql);
    $holidayStmt->execute([$date, $branch_id, $branch_id]);

    return [
        'is_weekend' => $isWeekend ? 1 : 0,
        'is_holiday' => $holidayStmt->fetchColumn() ? 1 : 0,
    ];
}

function get_attendance_block_status(PDO $pdo, int $employee_id, string $date, ?int $branch_id): ?string
{
    $calendarFlags = get_attendance_calendar_flags($pdo, $date, $branch_id);
    if (!empty($calendarFlags['is_holiday'])) {
        return 'Holiday';
    }

    $leaveStmt = $pdo->prepare("
        SELECT la.id
        FROM leave_applications la
        WHERE la.employee_id = ?
          AND la.status = 'Approved'
          AND ? BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
        LIMIT 1
    ");
    $leaveStmt->execute([$employee_id, $date]);

    return $leaveStmt->fetchColumn() ? 'On Leave' : null;
}
