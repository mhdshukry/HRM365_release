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

function get_employee_shift_for_date(PDO $pdo, int $employee_id, string $date, ?int $default_shift_id): ?array
{
    $weekday = intval(date('N', strtotime($date)));

    if ($default_shift_id === null) {
        return null;
    }

    $weeklyStmt = $pdo->prepare("
        SELECT s.id, s.name, sw.start_time, sw.end_time, s.grace_period, sw.is_night_shift
        FROM shifts s
        JOIN shift_weekly_schedules sw ON sw.shift_id = s.id
        WHERE s.id = ?
          AND sw.weekday = ?
          AND s.status = 'Active'
        LIMIT 1
    ");
    $weeklyStmt->execute([$default_shift_id, $weekday]);
    $weekly = $weeklyStmt->fetch(PDO::FETCH_ASSOC);
    if ($weekly) {
        if (empty($weekly['start_time']) || empty($weekly['end_time'])) {
            return null;
        }

        return $weekly;
    }

    $defaultStmt = $pdo->prepare("
        SELECT id, name, start_time, end_time, grace_period, is_night_shift
        FROM shifts
        WHERE id = ?
        LIMIT 1
    ");
    $defaultStmt->execute([$default_shift_id]);
    $default = $defaultStmt->fetch(PDO::FETCH_ASSOC);

    return $default ?: null;
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

function get_employee_approved_leave_context(PDO $pdo, int $employee_id, string $date): array
{
    $leaveStmt = $pdo->prepare("
        SELECT la.id, la.total_days, lt.name AS leave_type_name, lt.is_paid
        FROM leave_applications la
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.employee_id = ?
          AND la.status = 'Approved'
          AND ? BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
    ");
    $leaveStmt->execute([$employee_id, $date]);

    $totalDays = 0.00;
    $types = [];
    $isShortLeave = false;
    $isPaid = true;
    foreach ($leaveStmt->fetchAll(PDO::FETCH_ASSOC) as $leave) {
        $days = min(max(floatval($leave['total_days']), 0.00), 1.00);
        $totalDays += $days;
        $types[] = $leave['leave_type_name'];
        if (stripos((string) $leave['leave_type_name'], 'short') !== false) {
            $isShortLeave = true;
        }
        if (intval($leave['is_paid']) === 0) {
            $isPaid = false;
        }
    }

    $totalDays = min(round($totalDays, 2), 1.00);

    return [
        'days' => $totalDays,
        'types' => array_values(array_unique($types)),
        'is_short_leave' => $isShortLeave,
        'is_paid' => $isPaid ? 1 : 0,
        'is_full_day' => $totalDays >= 1.00,
        'is_partial_day' => $totalDays > 0 && $totalDays < 1.00,
    ];
}

function get_attendance_day_context(PDO $pdo, int $employee_id, string $date, ?int $branch_id, ?int $default_shift_id): array
{
    $calendarFlags = get_attendance_calendar_flags($pdo, $date, $branch_id);
    $resolvedShift = get_employee_shift_for_date($pdo, $employee_id, $date, $default_shift_id);
    $leave = get_employee_approved_leave_context($pdo, $employee_id, $date);

    $blockStatus = null;
    $workStatus = 'Working Day';
    $canPunch = true;
    $shouldCreateAttendance = true;
    $countsAsAbsence = false;

    if (!empty($calendarFlags['is_holiday'])) {
        $blockStatus = 'Holiday';
        $workStatus = 'Holiday';
        $canPunch = false;
        $shouldCreateAttendance = false;
    } elseif ($leave['is_full_day']) {
        $blockStatus = 'On Leave';
        $workStatus = 'Full Leave';
        $canPunch = false;
        $shouldCreateAttendance = true;
    } elseif ($resolvedShift === null) {
        $blockStatus = 'No Shift';
        $workStatus = 'No Shift';
        $canPunch = false;
        $shouldCreateAttendance = false;
    } elseif ($leave['is_partial_day']) {
        $workStatus = $leave['is_short_leave'] ? 'Short Leave' : 'Partial Leave';
    }

    if ($blockStatus === null && $resolvedShift !== null && empty($calendarFlags['is_holiday'])) {
        $countsAsAbsence = true;
    }

    return [
        'date' => $date,
        'calendar' => $calendarFlags,
        'shift' => $resolvedShift,
        'leave' => $leave,
        'work_status' => $workStatus,
        'block_status' => $blockStatus,
        'can_punch' => $canPunch,
        'should_create_attendance' => $shouldCreateAttendance,
        'counts_as_absence' => $countsAsAbsence,
        'is_working_day' => $resolvedShift !== null && empty($calendarFlags['is_holiday']),
    ];
}

function get_attendance_block_status(PDO $pdo, int $employee_id, string $date, ?int $branch_id, ?int $default_shift_id = null): ?string
{
    if ($default_shift_id === null) {
        $calendarFlags = get_attendance_calendar_flags($pdo, $date, $branch_id);
        if (!empty($calendarFlags['is_holiday'])) {
            return 'Holiday';
        }

        $leave = get_employee_approved_leave_context($pdo, $employee_id, $date);
        return $leave['is_full_day'] ? 'On Leave' : null;
    }

    $context = get_attendance_day_context($pdo, $employee_id, $date, $branch_id, $default_shift_id);
    return $context['block_status'];
}
