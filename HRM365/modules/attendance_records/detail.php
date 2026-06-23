<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'])) {
    die("Unauthorized access.");
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT r.*,
           e.first_name, e.last_name, e.employee_code, e.base_salary, e.department,
           s.name AS shift_name, s.start_time, s.end_time, s.grace_period, s.is_night_shift,
           p.name AS policy_name, p.late_arrival_grace, p.early_departure_grace, p.overtime_rate_per_hour
    FROM attendance_records r
    JOIN employees e ON r.employee_id = e.id
    LEFT JOIN shifts s ON r.shift_id = s.id
    LEFT JOIN attendance_policies p ON r.attendance_policy_id = p.id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Attendance record not found.");
}

if ($currentUser['role'] === 'employee' && intval($record['employee_id']) !== intval($currentUser['employee_id'] ?? 0)) {
    die("Unauthorized access.");
}

if ($currentUser['role'] === 'manager' && ($record['department'] ?? '') !== ($currentUser['department'] ?? '')) {
    die("Unauthorized access.");
}

$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';

function fmt_time(?string $value): string
{
    return $value ? date('Y-m-d H:i', strtotime($value)) : '--';
}

function minutes_between_positive(?int $start, ?int $end): int
{
    if ($start === null || $end === null || $end <= $start) {
        return 0;
    }

    return (int) round(($end - $start) / 60);
}

$date = $record['date'];
$clockInTs = $record['clock_in'] ? strtotime($record['clock_in']) : null;
$clockOutTs = $record['clock_out'] ? strtotime($record['clock_out']) : null;
$expectedStartTs = $record['start_time'] ? strtotime($date . ' ' . $record['start_time']) : null;
$expectedEndTs = calculate_expected_shift_end($date, $record['start_time'], $record['end_time']);

$lateGraceSeconds = intval($record['late_arrival_grace'] ?? 0) * 60;
$earlyGraceSeconds = intval($record['early_departure_grace'] ?? 0) * 60;
$lateMinutes = ($clockInTs && $expectedStartTs && $clockInTs > ($expectedStartTs + $lateGraceSeconds))
    ? minutes_between_positive($expectedStartTs + $lateGraceSeconds, $clockInTs)
    : 0;
$earlyMinutes = ($clockOutTs && $expectedEndTs && $clockOutTs < ($expectedEndTs - $earlyGraceSeconds))
    ? minutes_between_positive($clockOutTs, $expectedEndTs - $earlyGraceSeconds)
    : 0;

$shiftHours = calculate_shift_hours($record['start_time'], $record['end_time']);
$hourlyRate = floatval($record['base_salary']) > 0 ? floatval($record['base_salary']) / PAYROLL_STANDARD_MONTHLY_HOURS : 0.00;
$policyRate = floatval($record['overtime_rate_per_hour'] ?? 0) > 0 ? floatval($record['overtime_rate_per_hour']) : PAYROLL_NORMAL_OT_RATE;

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Timesheet Math Log</h1>
        <div class="page-subtitle">Saved attendance calculation details for <?php echo htmlspecialchars($record['date']); ?>.</div>
    </div>
    <a href="index.php?date=<?php echo urlencode($record['date']); ?>" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <div class="card">
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Employee & Punches</h3>
        <div style="display: grid; gap: 0.75rem;">
            <div><strong>Employee:</strong> <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?> (<?php echo htmlspecialchars($record['employee_code']); ?>)</div>
            <div><strong>Clock In:</strong> <?php echo htmlspecialchars(fmt_time($record['clock_in'])); ?></div>
            <div><strong>Clock Out:</strong> <?php echo htmlspecialchars(fmt_time($record['clock_out'])); ?></div>
            <div><strong>Total Hours:</strong> <?php echo number_format(floatval($record['total_hours']), 2); ?>h</div>
            <div><strong>Status:</strong> <?php echo htmlspecialchars($record['status']); ?></div>
        </div>
    </div>

    <div class="card">
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Shift & Policy</h3>
        <div style="display: grid; gap: 0.75rem;">
            <div><strong>Shift:</strong> <?php echo htmlspecialchars($record['shift_name'] ?? 'No Shift'); ?></div>
            <div><strong>Expected Time:</strong> <?php echo $record['start_time'] ? htmlspecialchars(substr($record['start_time'], 0, 5)) : '--:--'; ?> &rarr; <?php echo $record['end_time'] ? htmlspecialchars(substr($record['end_time'], 0, 5)) : '--:--'; ?></div>
            <div><strong>Shift Length:</strong> <?php echo number_format($shiftHours, 2); ?>h</div>
            <div><strong>Policy:</strong> <?php echo htmlspecialchars($record['policy_name'] ?? 'No Policy'); ?></div>
            <div><strong>Late / Early Grace:</strong> <?php echo intval($record['late_arrival_grace'] ?? 0); ?>m / <?php echo intval($record['early_departure_grace'] ?? 0); ?>m</div>
        </div>
    </div>

    <div class="card">
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Compliance</h3>
        <div style="display: grid; gap: 0.75rem;">
            <div><strong>Late Flag:</strong> <?php echo $record['is_late'] ? 'Yes' : 'No'; ?><?php echo $lateMinutes > 0 ? ' (' . $lateMinutes . 'm after grace)' : ''; ?></div>
            <div><strong>Early Departure Flag:</strong> <?php echo $record['is_early_departure'] ? 'Yes' : 'No'; ?><?php echo $earlyMinutes > 0 ? ' (' . $earlyMinutes . 'm before allowed end)' : ''; ?></div>
            <div><strong>Holiday:</strong> <?php echo $record['is_holiday'] ? 'Yes' : 'No'; ?></div>
            <div><strong>Weekend:</strong> <?php echo $record['is_weekend'] ? 'Yes' : 'No'; ?></div>
        </div>
    </div>

    <div class="card">
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Overtime & Payroll</h3>
        <div style="display: grid; gap: 0.75rem;">
            <div><strong>Overtime:</strong> <?php echo htmlspecialchars(format_hours_minutes(floatval($record['overtime_hours']))); ?> (<?php echo number_format(floatval($record['overtime_hours']), 2); ?>h)</div>
            <div><strong>Monthly Salary:</strong> <?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($record['base_salary']), 2); ?></div>
            <div><strong>Standard Hourly Rate:</strong> <?php echo htmlspecialchars($currency); ?> <?php echo number_format($hourlyRate, 2); ?> <span style="color: var(--text-muted);">(salary / <?php echo number_format(PAYROLL_STANDARD_MONTHLY_HOURS, 0); ?>)</span></div>
            <div><strong>Overtime Multiplier:</strong> <?php echo number_format($policyRate, 2); ?>x</div>
            <div><strong>Overtime Amount:</strong> <?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($record['overtime_amount']), 2); ?></div>
        </div>
    </div>
</div>

<?php if (!empty($record['notes'])): ?>
    <div class="card" style="margin-top: 1.5rem;">
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Notes</h3>
        <div style="color: var(--text-secondary);"><?php echo nl2br(htmlspecialchars($record['notes'])); ?></div>
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
