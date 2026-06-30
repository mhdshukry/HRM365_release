<?php

require_once __DIR__ . '/../includes/payroll_math.php';

function payroll_assert_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function payroll_assert_float(float $expected, float $actual, string $message): void
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
    CREATE TABLE attendance_policies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        late_arrival_grace INTEGER DEFAULT 0,
        early_departure_grace INTEGER DEFAULT 0,
        overtime_rate_per_hour REAL DEFAULT 1.5
    );
    CREATE TABLE employees (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_code TEXT NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        branch_id INTEGER,
        department TEXT,
        hire_date TEXT NOT NULL,
        base_salary REAL DEFAULT 0,
        status TEXT DEFAULT 'Active',
        shift_id INTEGER,
        attendance_policy_id INTEGER
    );
    CREATE TABLE attendance_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        shift_id INTEGER,
        attendance_policy_id INTEGER,
        date TEXT NOT NULL,
        clock_in TEXT,
        clock_out TEXT,
        overtime_hours REAL DEFAULT 0,
        overtime_amount REAL DEFAULT 0,
        is_absent INTEGER DEFAULT 0,
        is_holiday INTEGER DEFAULT 0,
        is_weekend INTEGER DEFAULT 0,
        status TEXT DEFAULT 'Pending',
        notes TEXT,
        UNIQUE(employee_id, date)
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
    CREATE TABLE system_settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    );
    CREATE TABLE payroll_records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        payroll_month TEXT NOT NULL,
        base_salary REAL DEFAULT 0,
        overtime_hours REAL DEFAULT 0,
        overtime_amount REAL DEFAULT 0,
        deductions REAL DEFAULT 0,
        unpaid_days REAL DEFAULT 0,
        advance_amount REAL DEFAULT 0,
        epf_employee_amount REAL DEFAULT 0,
        epf_employer_amount REAL DEFAULT 0,
        etf_employer_amount REAL DEFAULT 0,
        net_salary REAL DEFAULT 0,
        status TEXT DEFAULT 'Draft',
        UNIQUE(employee_id, payroll_month)
    );
    CREATE TABLE advance_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        employee_id INTEGER NOT NULL,
        amount REAL NOT NULL,
        payment_date TEXT NOT NULL,
        deduction_month TEXT NOT NULL,
        reason TEXT,
        status TEXT DEFAULT 'Paid'
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
    INSERT INTO employees (id, employee_code, first_name, last_name, branch_id, department, hire_date, base_salary, status, shift_id, attendance_policy_id) VALUES
        (1, 'EMP-001', 'Payroll', 'Employee', 1, 'IT', '2026-06-01', 30000, 'Active', 1, 1),
        (2, 'EMP-002', 'Paid', 'Locked', 1, 'IT', '2026-06-01', 50000, 'Active', 1, 1);
    INSERT INTO leave_types (id, name, is_paid) VALUES
        (1, 'Annual Leave', 1),
        (2, 'Unpaid Leave', 0),
        (3, 'Casual Leave', 1),
        (4, 'Short Leave', 1);
    INSERT INTO leave_applications (employee_id, leave_type_id, start_date, end_date, total_days, status) VALUES
        (1, 2, '2026-06-02', '2026-06-02', 1.00, 'Approved'),
        (1, 1, '2026-06-03', '2026-06-03', 1.00, 'Approved'),
        (1, 3, '2026-06-04', '2026-06-04', 0.50, 'Approved'),
        (1, 4, '2026-06-05', '2026-06-05', 0.25, 'Approved');
    INSERT INTO holidays (name, start_date, end_date, applies_to_all_branches)
        VALUES ('Company Holiday', '2026-06-09', '2026-06-09', 1);
    INSERT INTO system_settings (setting_key, setting_value) VALUES
        ('payroll_enable_overtime', '1'),
        ('payroll_enable_epf', '1'),
        ('payroll_enable_etf', '1'),
        ('epf_employee_rate', '8'),
        ('epf_employer_rate', '12'),
        ('etf_employer_rate', '3');
    INSERT INTO payroll_records
        (employee_id, payroll_month, base_salary, overtime_hours, overtime_amount, deductions, unpaid_days, advance_amount, epf_employee_amount, epf_employer_amount, etf_employer_amount, net_salary, status)
    VALUES
        (2, '2026-06', 11111, 9.99, 999.99, 888.88, 7.77, 222.22, 666.66, 555.55, 444.44, 333.33, 'Paid');
    INSERT INTO advance_payments (employee_id, amount, payment_date, deduction_month, reason, status) VALUES
        (1, 1500.00, '2026-06-01', '2026-06', 'Salary advance', 'Paid'),
        (1, 500.00, '2026-06-01', '2026-06', 'Pending advance', 'Pending'),
        (1, 700.00, '2026-05-31', '2026-05', 'Previous month', 'Paid');
");

$insertPresent = $pdo->prepare("
    INSERT INTO attendance_records
        (employee_id, shift_id, attendance_policy_id, date, clock_in, clock_out, overtime_hours, status)
    VALUES
        (1, 1, 1, ?, ?, ?, ?, 'Present')
");

foreach (payroll_date_range('2026-06-01', '2026-06-29') as $date) {
    $weekday = intval(date('N', strtotime($date)));
    if ($weekday > 5 || in_array($date, ['2026-06-02', '2026-06-03', '2026-06-04', '2026-06-05', '2026-06-08', '2026-06-09'], true)) {
        continue;
    }

    $ot = $date === '2026-06-10' ? 2.00 : 0.00;
    $insertPresent->execute([$date, $date . ' 09:00:00', $date . ' 17:00:00', $ot]);
}

$processed = generate_payroll_for_month($pdo, '2026-06');
payroll_assert_same(2, $processed, 'Payroll should process both active employees.');

$payroll = $pdo->query("SELECT * FROM payroll_records WHERE employee_id = 1 AND payroll_month = '2026-06'")->fetch();
payroll_assert_float(30000.00, floatval($payroll['base_salary']), 'Base salary should be stored.');
payroll_assert_float(2.00, floatval($payroll['overtime_hours']), 'OT hours should include valid working-day OT.');
payroll_assert_float(375.00, floatval($payroll['overtime_amount']), 'OT amount should use salary / 240 * policy rate.');
payroll_assert_float(3.25, floatval($payroll['unpaid_days']), 'Unpaid days should include unpaid leave plus missing working-day remainders.');
payroll_assert_float(3250.00, floatval($payroll['deductions']), 'Unpaid deduction should use salary / 30.');
payroll_assert_float(1500.00, floatval($payroll['advance_amount']), 'Paid advances for the payroll month should be deducted.');
payroll_assert_float(2400.00, floatval($payroll['epf_employee_amount']), 'Employee EPF should use the configured rate.');
payroll_assert_float(3600.00, floatval($payroll['epf_employer_amount']), 'Employer EPF should use the configured rate.');
payroll_assert_float(900.00, floatval($payroll['etf_employer_amount']), 'ETF should use the configured rate.');
payroll_assert_float(23225.00, floatval($payroll['net_salary']), 'Net salary should include OT, statutory, unpaid, and advance deductions.');
payroll_assert_same('Finalized', $payroll['status'], 'Generated payroll should be finalized.');

$paidPayroll = $pdo->query("SELECT * FROM payroll_records WHERE employee_id = 2 AND payroll_month = '2026-06'")->fetch();
payroll_assert_float(11111.00, floatval($paidPayroll['base_salary']), 'Paid payroll base salary must not be overwritten.');
payroll_assert_float(333.33, floatval($paidPayroll['net_salary']), 'Paid payroll net salary must not be overwritten.');
payroll_assert_same('Paid', $paidPayroll['status'], 'Paid payroll status must be preserved.');

$absence = $pdo->query("SELECT status, is_absent FROM attendance_records WHERE employee_id = 1 AND date = '2026-06-08'")->fetch();
payroll_assert_same('Absent', $absence['status'], 'Missing working day should generate an Absent record.');
payroll_assert_same(1, intval($absence['is_absent']), 'Missing working day should be marked absent.');
payroll_assert_same(false, (bool) $pdo->query("SELECT id FROM attendance_records WHERE employee_id = 1 AND date = '2026-06-07'")->fetch(), 'No-shift Sunday should not create attendance.');
payroll_assert_same(false, (bool) $pdo->query("SELECT id FROM attendance_records WHERE employee_id = 1 AND date = '2026-06-09'")->fetch(), 'Holiday should not create attendance.');

$onLeave = $pdo->query("SELECT status, is_absent FROM attendance_records WHERE employee_id = 1 AND date = '2026-06-03'")->fetch();
payroll_assert_same('On Leave', $onLeave['status'], 'Full paid leave should create an On Leave marker.');
payroll_assert_same(0, intval($onLeave['is_absent']), 'Full paid leave should not be absent.');

$pdo->exec("
    UPDATE system_settings SET setting_value = '0'
    WHERE setting_key IN ('payroll_enable_overtime', 'payroll_enable_epf', 'payroll_enable_etf')
");
generate_payroll_for_month($pdo, '2026-06');
$disabled = $pdo->query("SELECT * FROM payroll_records WHERE employee_id = 1 AND payroll_month = '2026-06'")->fetch();
payroll_assert_float(0.00, floatval($disabled['overtime_hours']), 'Disabled OT should zero OT hours.');
payroll_assert_float(0.00, floatval($disabled['overtime_amount']), 'Disabled OT should zero OT amount.');
payroll_assert_float(0.00, floatval($disabled['epf_employee_amount']), 'Disabled EPF should zero employee EPF.');
payroll_assert_float(0.00, floatval($disabled['epf_employer_amount']), 'Disabled EPF should zero employer EPF.');
payroll_assert_float(0.00, floatval($disabled['etf_employer_amount']), 'Disabled ETF should zero ETF.');
payroll_assert_float(1500.00, floatval($disabled['advance_amount']), 'Advance deduction should still apply when statutory features are disabled.');
payroll_assert_float(25250.00, floatval($disabled['net_salary']), 'Net salary should recalculate without OT or statutory deductions but include advances.');

echo "Payroll smoke tests passed.\n";
