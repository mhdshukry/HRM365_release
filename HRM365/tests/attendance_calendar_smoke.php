<?php

require_once __DIR__ . '/../includes/payroll_math.php';

function assert_same_value($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assert_float_value(float $expected, float $actual, string $message): void
{
    if (abs($expected - $actual) > 0.001) {
        throw new RuntimeException($message . ' Expected ' . $expected . ', got ' . $actual);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("
    CREATE TABLE shifts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        start_time TEXT,
        end_time TEXT,
        grace_period INTEGER DEFAULT 0,
        is_night_shift INTEGER DEFAULT 0,
        status TEXT DEFAULT 'Active'
    );
    CREATE TABLE shift_weekly_schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        shift_id INTEGER NOT NULL,
        weekday INTEGER NOT NULL,
        start_time TEXT,
        end_time TEXT,
        is_night_shift INTEGER DEFAULT 0
    );
    CREATE TABLE employee_shift_overrides (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        weekday INTEGER NOT NULL,
        shift_id INTEGER
    );
    CREATE TABLE employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_code TEXT NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        branch_id INTEGER,
        shift_id INTEGER,
        attendance_policy_id INTEGER,
        base_salary REAL DEFAULT 0
    );
    CREATE TABLE attendance_policies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        late_arrival_grace INTEGER DEFAULT 0,
        early_departure_grace INTEGER DEFAULT 0,
        overtime_rate_per_hour REAL DEFAULT 1.5
    );
    CREATE TABLE attendance_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        attendance_policy_id INTEGER,
        date TEXT NOT NULL,
        overtime_hours REAL DEFAULT 0,
        is_holiday INTEGER DEFAULT 0,
        is_weekend INTEGER DEFAULT 0,
        status TEXT DEFAULT 'Pending'
    );
    CREATE TABLE holidays (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT,
        applies_to_all_branches INTEGER DEFAULT 1
    );
    CREATE TABLE holiday_branches (
        holiday_id INTEGER NOT NULL,
        branch_id INTEGER NOT NULL
    );
    CREATE TABLE leave_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        is_paid INTEGER DEFAULT 1
    );
    CREATE TABLE leave_applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        leave_type_id INTEGER NOT NULL,
        start_date TEXT NOT NULL,
        end_date TEXT,
        total_days REAL NOT NULL,
        status TEXT DEFAULT 'Pending'
    );
");

$pdo->exec("
    INSERT INTO shifts (id, name, start_time, end_time, status) VALUES (1, 'Office Shift', '09:00:00', '17:00:00', 'Active');
    INSERT INTO shift_weekly_schedules (shift_id, weekday, start_time, end_time) VALUES
        (1, 1, '09:00:00', '17:00:00'),
        (1, 2, '09:00:00', '17:00:00'),
        (1, 3, '09:00:00', '17:00:00'),
        (1, 4, '09:00:00', '17:00:00'),
        (1, 5, '09:00:00', '17:00:00'),
        (1, 6, NULL, NULL),
        (1, 7, NULL, NULL);
    INSERT INTO attendance_policies (id, name, overtime_rate_per_hour) VALUES (1, 'Standard Policy', 1.5);
    INSERT INTO employees (id, employee_code, first_name, last_name, branch_id, shift_id, attendance_policy_id, base_salary)
        VALUES (1, 'EMP-001', 'Test', 'Employee', 1, 1, 1, 24000);
    INSERT INTO holidays (name, start_date, end_date, applies_to_all_branches)
        VALUES ('Public Holiday', '2026-07-01', '2026-07-01', 1);
    INSERT INTO leave_types (id, name, is_paid) VALUES
        (1, 'Annual Leave', 1),
        (2, 'Casual Leave', 1),
        (3, 'Short Leave', 1);
    INSERT INTO leave_applications (employee_id, leave_type_id, start_date, end_date, total_days, status) VALUES
        (1, 1, '2026-07-02', '2026-07-02', 1.00, 'Approved'),
        (1, 2, '2026-07-03', '2026-07-03', 0.50, 'Approved'),
        (1, 3, '2026-07-06', '2026-07-06', 0.25, 'Approved');
    INSERT INTO attendance_records (employee_id, attendance_policy_id, date, overtime_hours, is_holiday, is_weekend, status) VALUES
        (1, 1, '2026-06-29', 1.50, 0, 0, 'Present'),
        (1, 1, '2026-07-01', 2.00, 1, 0, 'Present'),
        (1, 1, '2026-07-02', 2.00, 0, 0, 'Present');
");

$workingDay = get_attendance_day_context($pdo, 1, '2026-06-29', 1, 1);
assert_same_value(null, $workingDay['block_status'], 'Working day should not be blocked.');
assert_same_value(true, $workingDay['can_punch'], 'Working day should allow punching.');
assert_same_value(true, $workingDay['counts_as_absence'], 'Working day should count as absence if missing.');

$sunday = get_attendance_day_context($pdo, 1, '2026-06-28', 1, 1);
assert_same_value('No Shift', $sunday['block_status'], 'Sunday without a weekly shift should be No Shift.');
assert_same_value(false, $sunday['should_create_attendance'], 'No Shift should not generate attendance.');
assert_same_value(false, $sunday['counts_as_absence'], 'No Shift should not count as unpaid absence.');

$holiday = get_attendance_day_context($pdo, 1, '2026-07-01', 1, 1);
assert_same_value('Holiday', $holiday['block_status'], 'Holiday should block attendance.');
assert_same_value(false, $holiday['can_punch'], 'Holiday should not allow punching.');
assert_same_value(false, $holiday['should_create_attendance'], 'Holiday should not create attendance.');

$fullLeave = get_attendance_day_context($pdo, 1, '2026-07-02', 1, 1);
assert_same_value('On Leave', $fullLeave['block_status'], 'Full approved leave should block punching.');
assert_same_value(true, $fullLeave['should_create_attendance'], 'Full leave can create an On Leave marker.');
assert_same_value(false, $fullLeave['counts_as_absence'], 'Full paid leave should not count as unpaid absence.');

$partialLeave = get_attendance_day_context($pdo, 1, '2026-07-03', 1, 1);
assert_same_value(null, $partialLeave['block_status'], 'Partial leave should not block punching.');
assert_same_value('Partial Leave', $partialLeave['work_status'], 'Casual half-day should be Partial Leave.');
assert_float_value(0.50, $partialLeave['leave']['days'], 'Partial leave should keep its fraction.');
assert_same_value(true, $partialLeave['counts_as_absence'], 'Partial leave still has a working-day remainder.');

$shortLeave = get_attendance_day_context($pdo, 1, '2026-07-06', 1, 1);
assert_same_value(null, $shortLeave['block_status'], 'Short leave should not block punching.');
assert_same_value('Short Leave', $shortLeave['work_status'], 'Short leave should be identified by type.');
assert_float_value(0.25, $shortLeave['leave']['days'], 'Short leave should keep the 0.25 day value.');

$otSummary = calculate_employee_overtime_summary($pdo, 1, 24000.00, '2026-06-01', '2026-07-31');
assert_float_value(1.50, $otSummary['hours'], 'OT should include only unblocked working days.');
assert_float_value(225.00, $otSummary['amount'], 'OT amount should use base salary / 240 * policy rate.');

echo "Attendance calendar smoke tests passed.\n";
