<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';
require_once '../../includes/payroll_math.php';

$employeeId = intval($_GET['employee_id'] ?? 0);
$startDate = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
if (strtotime($endDate) < strtotime($startDate)) {
    $endDate = $startDate;
}

if ($currentUser['role'] === 'employee') {
    $employeeId = intval($currentUser['employee_id'] ?? 0);
}

$employeeSql = "
    SELECT e.*, b.name AS branch_name, s.name AS shift_name, ap.name AS policy_name
    FROM employees e
    LEFT JOIN branches b ON b.id = e.branch_id
    LEFT JOIN shifts s ON s.id = e.shift_id
    LEFT JOIN attendance_policies ap ON ap.id = e.attendance_policy_id
    WHERE e.id = ?
";
$employeeParams = [$employeeId];
if ($currentUser['role'] === 'manager') {
    $employeeSql .= " AND e.department = ?";
    $employeeParams[] = $currentUser['department'] ?? '';
}
$employeeStmt = $pdo->prepare($employeeSql);
$employeeStmt->execute($employeeParams);
$employee = $employeeStmt->fetch();

if (!$employee || !in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'], true)) {
    die('Unauthorized access.');
}

$attendanceStmt = $pdo->prepare("
    SELECT *
    FROM attendance_records
    WHERE employee_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$attendanceStmt->execute([$employeeId, $startDate, $endDate]);
$attendanceRows = $attendanceStmt->fetchAll();

$leaveStmt = $pdo->prepare("
    SELECT la.*, lt.name AS leave_type, lt.is_paid
    FROM leave_applications la
    JOIN leave_types lt ON lt.id = la.leave_type_id
    WHERE la.employee_id = ?
      AND la.start_date <= ?
      AND COALESCE(la.end_date, la.start_date) >= ?
    ORDER BY la.start_date DESC
");
$leaveStmt->execute([$employeeId, $endDate, $startDate]);
$leaveRows = $leaveStmt->fetchAll();

$payrollStartMonth = date('Y-m', strtotime($startDate));
$payrollEndMonth = date('Y-m', strtotime($endDate));
$payrollStmt = $pdo->prepare("
    SELECT *
    FROM payroll_records
    WHERE employee_id = ? AND payroll_month BETWEEN ? AND ?
    ORDER BY payroll_month DESC
");
$payrollStmt->execute([$employeeId, $payrollStartMonth, $payrollEndMonth]);
$payrollRows = $payrollStmt->fetchAll();
$payrollFeatures = payroll_feature_settings($pdo);
$showOvertime = $payrollFeatures['payroll_enable_overtime'] ?? true;
$showEpf = $payrollFeatures['payroll_enable_epf'] ?? true;
$showEtf = $payrollFeatures['payroll_enable_etf'] ?? true;
$employeeAttendanceColspan = 5 + ($showOvertime ? 1 : 0);
$employeePayrollColspan = 6 + ($showOvertime ? 1 : 0) + ($showEpf ? 2 : 0) + ($showEtf ? 1 : 0);

$summary = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'hours' => 0.00,
    'overtime' => 0.00,
    'unpaid_days' => 0.00,
    'net_salary' => 0.00,
];
foreach ($attendanceRows as $row) {
    if ($row['status'] === 'Present') {
        $summary['present']++;
    } elseif ($row['status'] === 'Absent') {
        $summary['absent']++;
    } elseif ($row['status'] === 'On Leave') {
        $summary['leave']++;
    }
    $summary['hours'] += floatval($row['total_hours']);
    $summary['overtime'] += floatval($row['overtime_hours']);
}
foreach ($payrollRows as $row) {
    $summary['unpaid_days'] += floatval($row['unpaid_days'] ?? 0);
    $summary['net_salary'] += floatval($row['net_salary']);
}

$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';
$employeeName = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
$initials = strtoupper(substr($employee['first_name'] ?? 'E', 0, 1) . substr($employee['last_name'] ?? '', 0, 1));
$periodLabel = date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
$employeeExportParams = [
    'employee_id' => $employeeId,
    'start_date' => $startDate,
    'end_date' => $endDate,
];
$returnUrl = $_GET['return_url'] ?? ('index.php?' . http_build_query([
    'start_date' => $startDate,
    'end_date' => $endDate,
    'view' => $_GET['view'] ?? 'attendance',
]));
$returnParts = parse_url($returnUrl);
if (
    isset($returnParts['scheme'])
    || isset($returnParts['host'])
    || (!empty($returnParts['path']) && basename($returnParts['path']) !== 'index.php')
) {
    $returnUrl = 'index.php?' . http_build_query([
        'start_date' => $startDate,
        'end_date' => $endDate,
        'view' => $_GET['view'] ?? 'attendance',
    ]);
}

include '../../includes/header.php';
?>

<style>
    .employee-report-hero {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 1.15rem;
        margin-bottom: 1.25rem;
        box-shadow: var(--shadow-sm);
    }
    .employee-report-top {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        align-items: stretch;
        margin-bottom: 1.15rem;
    }
    .employee-profile-panel {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }
    .employee-avatar {
        width: 64px;
        height: 64px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #111827;
        color: #fff;
        font-size: 1.25rem;
        font-weight: 900;
        flex: 0 0 auto;
    }
    .employee-report-name {
        color: var(--text-primary);
        font-size: clamp(1.25rem, 2vw, 1.65rem);
        font-weight: 900;
        line-height: 1.1;
    }
    .employee-report-meta {
        color: var(--text-secondary);
        margin-top: 0.4rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        align-items: center;
        font-weight: 700;
    }
    .employee-meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.25rem 0.5rem;
        border-radius: 999px;
        background: var(--bg-secondary);
        color: var(--text-secondary);
        font-size: 0.78rem;
    }
    .employee-report-grid,
    .employee-report-kpis {
        display: grid;
        gap: 1rem;
    }
    .employee-report-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
    .employee-report-kpis {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        margin-bottom: 1.25rem;
    }
    .employee-detail-card,
    .employee-kpi {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 0.9rem;
    }
    .employee-kpi {
        min-height: 104px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border-left: 4px solid var(--accent-primary);
        box-shadow: var(--shadow-sm);
    }
    .employee-kpi:nth-child(1) { border-left-color: var(--accent-success); }
    .employee-kpi:nth-child(2) { border-left-color: var(--accent-danger); }
    .employee-kpi:nth-child(4) { border-left-color: var(--accent-warning); }
    .employee-detail-label,
    .employee-kpi-label {
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0;
    }
    .employee-detail-value,
    .employee-kpi-value {
        color: var(--text-primary);
        font-weight: 850;
        margin-top: 0.35rem;
        overflow-wrap: anywhere;
    }
    .employee-kpi-value {
        font-size: 1.25rem;
    }
    .employee-kpi-note {
        margin-top: 0.3rem;
        color: var(--text-muted);
        font-size: 0.76rem;
        font-weight: 700;
    }
    .employee-report-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        margin-bottom: 1.25rem;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    .employee-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin: 0;
        padding: 1rem 1.15rem;
        background: var(--bg-secondary);
        border-bottom: 1px solid var(--border-color);
    }
    .employee-report-section h3 {
        margin: 0;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-primary);
    }
    .employee-section-count {
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 800;
    }
    .employee-filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        align-items: end;
        justify-content: flex-end;
    }
    .employee-filter-form input {
        min-height: 42px;
        padding: 0.65rem 0.75rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-primary);
        color-scheme: light;
    }
    .employee-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.6rem;
    }
    .employee-export-actions {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.55rem;
    }
    .employee-table {
        min-width: 900px;
    }
    .employee-table th,
    .employee-table td {
        vertical-align: middle;
    }
    .employee-date-badge,
    .employee-time-pill,
    .employee-flag {
        display: inline-flex;
        align-items: center;
        white-space: nowrap;
        font-weight: 800;
    }
    .employee-date-badge {
        justify-content: center;
        min-width: 104px;
        padding: 0.38rem 0.55rem;
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        color: var(--text-secondary);
        font-size: 0.78rem;
    }
    .employee-time-pill {
        gap: 0.45rem;
        min-width: 150px;
        padding: 0.42rem 0.6rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: #fff;
        color: var(--text-primary);
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
    }
    .employee-time-pill i {
        color: var(--text-muted);
        font-size: 0.72rem;
    }
    .employee-flag-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }
    .employee-flag {
        padding: 0.24rem 0.48rem;
        border-radius: 999px;
        background: var(--bg-secondary);
        color: var(--text-secondary);
        font-size: 0.72rem;
    }
    .employee-flag-danger { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); }
    .employee-flag-warning { background: rgba(245, 158, 11, 0.12); color: var(--accent-warning); }
    .employee-flag-info { background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); }
    .report-money {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font-weight: 800;
        white-space: nowrap;
    }
    .employee-empty {
        text-align: center;
        color: var(--text-muted);
        padding: 2rem;
        font-weight: 700;
    }
    @media (max-width: 900px) {
        .employee-report-grid,
        .employee-report-kpis {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .employee-report-top {
            flex-direction: column;
        }
        .employee-filter-form {
            justify-content: flex-start;
        }
        .employee-header-actions {
            justify-content: flex-start;
        }
    }
    @media (max-width: 620px) {
        .employee-profile-panel {
            flex-direction: column;
            align-items: flex-start;
        }
        .employee-report-grid,
        .employee-report-kpis {
            grid-template-columns: 1fr;
        }
        .employee-filter-form .btn,
        .employee-filter-form input,
        .employee-export-actions .btn,
        .employee-header-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Employee Report</h1>
        <div class="page-subtitle">Profile, attendance, leave, and payroll history.</div>
    </div>
    <div class="employee-header-actions">
        <div class="employee-export-actions">
            <a href="employee_export.php?<?php echo htmlspecialchars(http_build_query($employeeExportParams + ['format' => 'excel'])); ?>" class="btn btn-primary">
                <i class="fas fa-file-excel"></i> Excel
            </a>
            <a href="employee_export.php?<?php echo htmlspecialchars(http_build_query($employeeExportParams + ['format' => 'pdf'])); ?>" class="btn" style="background: var(--accent-danger); color: #fff;">
                <i class="fas fa-file-pdf"></i> PDF
            </a>
        </div>
        <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
    </div>
</div>

<div class="employee-report-hero">
    <div class="employee-report-top">
        <div class="employee-profile-panel">
            <div class="employee-avatar"><?php echo htmlspecialchars($initials ?: 'E'); ?></div>
            <div>
                <div class="employee-report-name"><?php echo htmlspecialchars($employeeName); ?></div>
                <div class="employee-report-meta">
                    <span class="employee-meta-chip"><i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($employee['employee_code']); ?></span>
                    <span class="employee-meta-chip"><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['branch_name'] ?? 'No Branch'); ?></span>
                    <span class="employee-meta-chip"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($periodLabel); ?></span>
                    <span class="status-badge"><?php echo htmlspecialchars($employee['status']); ?></span>
                </div>
            </div>
        </div>
        <form class="employee-filter-form" method="GET">
            <input type="hidden" name="employee_id" value="<?php echo intval($employeeId); ?>">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($_GET['view'] ?? 'attendance'); ?>">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($returnUrl); ?>">
            <div>
                <div class="employee-detail-label">From</div>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div>
                <div class="employee-detail-label">To</div>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>

    <div class="employee-report-grid">
        <div class="employee-detail-card"><div class="employee-detail-label">Department</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['department'] ?: 'Not assigned'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Designation</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['designation'] ?: 'Not assigned'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Shift</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['shift_name'] ?: 'No shift'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Policy</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['policy_name'] ?: 'No policy'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Email</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['email']); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Phone</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['phone'] ?: '-'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Hire Date</div><div class="employee-detail-value"><?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Base Salary</div><div class="employee-detail-value"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($employee['base_salary']), 2); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Employment Type</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['employment_type'] ?: '-'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">NIC</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['nic_number'] ?: '-'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Biometric ID</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['biometric_user_id'] ?: '-'); ?></div></div>
        <div class="employee-detail-card"><div class="employee-detail-label">Address</div><div class="employee-detail-value"><?php echo htmlspecialchars($employee['address'] ?: '-'); ?></div></div>
    </div>
</div>

<div class="employee-report-kpis">
    <div class="employee-kpi"><div><div class="employee-kpi-label">Present Days</div><div class="employee-kpi-value"><?php echo intval($summary['present']); ?></div></div><div class="employee-kpi-note"><?php echo count($attendanceRows); ?> attendance row(s)</div></div>
    <div class="employee-kpi"><div><div class="employee-kpi-label">Absent Days</div><div class="employee-kpi-value"><?php echo intval($summary['absent']); ?></div></div><div class="employee-kpi-note">No punch / unpaid candidates</div></div>
    <div class="employee-kpi"><div><div class="employee-kpi-label">Total Hours</div><div class="employee-kpi-value"><?php echo number_format($summary['hours'], 2); ?>h</div></div><div class="employee-kpi-note">Within selected period</div></div>
    <?php if ($showOvertime): ?>
        <div class="employee-kpi"><div><div class="employee-kpi-label">Overtime</div><div class="employee-kpi-value"><?php echo htmlspecialchars(format_hours_minutes($summary['overtime'])); ?></div></div><div class="employee-kpi-note">Payroll calculation basis</div></div>
    <?php endif; ?>
    <div class="employee-kpi"><div><div class="employee-kpi-label">Net Payroll</div><div class="employee-kpi-value"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($summary['net_salary'], 2); ?></div></div><div class="employee-kpi-note"><?php echo count($payrollRows); ?> payroll row(s)</div></div>
</div>

<section class="employee-report-section">
    <div class="employee-section-header">
        <h3><i class="fas fa-clipboard-list"></i> Attendance Records</h3>
        <span class="employee-section-count"><?php echo count($attendanceRows); ?> row(s)</span>
    </div>
    <div class="table-container">
        <table class="table employee-table">
            <thead><tr><th>Date</th><th>In - Out</th><th style="text-align:right;">Total</th><?php if ($showOvertime): ?><th style="text-align:right;">OT</th><?php endif; ?><th>Status</th><th>Flags</th></tr></thead>
            <tbody>
                <?php foreach ($attendanceRows as $row): ?>
                    <?php
                    $flags = [];
                    if ($row['is_late']) $flags[] = ['Late', 'employee-flag-danger'];
                    if ($row['is_early_departure']) $flags[] = ['Early out', 'employee-flag-warning'];
                    if ($row['is_absent']) $flags[] = ['No punch', 'employee-flag-danger'];
                    if ($row['is_holiday']) $flags[] = ['Holiday', 'employee-flag-info'];
                    if ($row['is_weekend']) $flags[] = ['Weekend', ''];
                    ?>
                    <tr>
                        <td><span class="employee-date-badge"><?php echo date('M d, Y', strtotime($row['date'])); ?></span></td>
                        <td><span class="employee-time-pill"><?php echo $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : '--:--'; ?> <i class="fas fa-arrow-right"></i> <?php echo $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : '--:--'; ?></span></td>
                        <td style="text-align:right;"><?php echo number_format(floatval($row['total_hours']), 2); ?>h</td>
                        <?php if ($showOvertime): ?><td style="text-align:right;"><?php echo htmlspecialchars(format_hours_minutes(floatval($row['overtime_hours']))); ?></td><?php endif; ?>
                        <td><span class="status-badge"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td>
                            <div class="employee-flag-list">
                                <?php if ($flags): ?>
                                    <?php foreach ($flags as $flag): ?>
                                        <span class="employee-flag <?php echo htmlspecialchars($flag[1]); ?>"><?php echo htmlspecialchars($flag[0]); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="employee-flag">Clear</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($attendanceRows)): ?><tr><td colspan="<?php echo $employeeAttendanceColspan; ?>"><div class="employee-empty">No attendance records for this filter.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="employee-report-section">
    <div class="employee-section-header">
        <h3><i class="fas fa-calendar-check"></i> Leave Applications</h3>
        <span class="employee-section-count"><?php echo count($leaveRows); ?> row(s)</span>
    </div>
    <div class="table-container">
        <table class="table employee-table">
            <thead><tr><th>Type</th><th>Date Range</th><th style="text-align:right;">Days</th><th>Paid</th><th>Status</th><th>Reason</th></tr></thead>
            <tbody>
                <?php foreach ($leaveRows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?> to <?php echo date('M d, Y', strtotime($row['end_date'] ?: $row['start_date'])); ?></td>
                        <td style="text-align:right;"><?php echo number_format(floatval($row['total_days']), 2); ?></td>
                        <td><?php echo intval($row['is_paid']) === 1 ? 'Paid' : 'Unpaid'; ?></td>
                        <td><span class="status-badge"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($row['reason'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leaveRows)): ?><tr><td colspan="6"><div class="employee-empty">No leave records for this filter.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="employee-report-section">
    <div class="employee-section-header">
        <h3><i class="fas fa-file-invoice-dollar"></i> Payroll</h3>
        <span class="employee-section-count"><?php echo count($payrollRows); ?> row(s)</span>
    </div>
    <div class="table-container">
        <table class="table employee-table">
            <thead><tr><th>Month</th><th style="text-align:right;">Base</th><?php if ($showOvertime): ?><th style="text-align:right;">OT</th><?php endif; ?><th style="text-align:right;">Unpaid</th><th style="text-align:right;">Advance</th><?php if ($showEpf): ?><th style="text-align:right;">Employee EPF</th><th style="text-align:right;">Employer EPF</th><?php endif; ?><?php if ($showEtf): ?><th style="text-align:right;">ETF</th><?php endif; ?><th style="text-align:right;">Net</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($payrollRows as $row): ?>
                    <tr>
                        <td><?php echo date('M Y', strtotime($row['payroll_month'] . '-01')); ?></td>
                        <td style="text-align:right;" class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($row['base_salary']), 2); ?></td>
                        <?php if ($showOvertime): ?><td style="text-align:right;" class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($row['overtime_amount']), 2); ?></td><?php endif; ?>
                        <td style="text-align:right;"><?php echo number_format(floatval($row['unpaid_days'] ?? 0), 2); ?> day(s)</td>
                        <td style="text-align:right;" class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($row['advance_amount'] ?? 0), 2); ?></td>
                        <?php if ($showEpf): ?>
                            <td style="text-align:right;" class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($row['epf_employee_amount'] ?? 0), 2); ?></td>
                            <td style="text-align:right;" class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($row['epf_employer_amount'] ?? 0), 2); ?></td>
                        <?php endif; ?>
                        <?php if ($showEtf): ?><td style="text-align:right;" class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($row['etf_employer_amount'] ?? 0), 2); ?></td><?php endif; ?>
                        <td style="text-align:right;" class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($row['net_salary']), 2); ?></td>
                        <td><span class="status-badge"><?php echo htmlspecialchars($row['status']); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($payrollRows)): ?><tr><td colspan="<?php echo $employeePayrollColspan; ?>"><div class="employee-empty">No payroll records for this filter.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include '../../includes/footer.php'; ?>
