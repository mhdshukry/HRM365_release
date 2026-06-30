<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once __DIR__ . '/report_data.php';

$perPage = 10;
$reportView = $_GET['view'] ?? 'attendance';
if (!in_array($reportView, ['attendance', 'leave_payroll'], true)) {
    $reportView = 'attendance';
}
$attendancePage = max(1, intval($_GET['attendance_page'] ?? 1));
$leavePayrollPage = max(1, intval($_GET['leave_payroll_page'] ?? 1));
$attendanceTotalPages = max(1, (int) ceil(count($attendanceRows) / $perPage));
$leavePayrollTotalPages = max(1, (int) ceil(count($leavePayrollRows) / $perPage));
$attendancePage = min($attendancePage, $attendanceTotalPages);
$leavePayrollPage = min($leavePayrollPage, $leavePayrollTotalPages);
$attendancePageRows = array_slice($attendanceRows, ($attendancePage - 1) * $perPage, $perPage);
$leavePayrollPageRows = array_slice($leavePayrollRows, ($leavePayrollPage - 1) * $perPage, $perPage);
$showOvertime = $payrollFeatures['payroll_enable_overtime'] ?? true;
$showEpf = $payrollFeatures['payroll_enable_epf'] ?? true;
$showEtf = $payrollFeatures['payroll_enable_etf'] ?? true;
$showStatutory = $showEpf || $showEtf;
$leavePayrollColspan = 6 + ($showStatutory ? 1 : 0);

function report_page_url(string $pageParam, int $page): string
{
    $params = $_GET;
    $params[$pageParam] = $page;
    return '?' . http_build_query($params);
}

function report_export_url(string $format, ?string $view = null): string
{
    $params = $_GET;
    unset($params['attendance_page'], $params['leave_payroll_page']);
    $params['format'] = $format;
    if ($view !== null) {
        $params['view'] = $view;
    }
    return 'export.php?' . http_build_query($params);
}

function report_view_url(string $view): string
{
    $params = $_GET;
    $params['view'] = $view;
    unset($params['attendance_page'], $params['leave_payroll_page']);
    return '?' . http_build_query($params);
}

function report_return_url(): string
{
    $params = $_GET;
    unset($params['employee_id'], $params['attendance_page'], $params['leave_payroll_page'], $params['format']);
    return 'index.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}

function report_employee_url(int $employeeId): string
{
    $params = [
        'employee_id' => $employeeId,
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'view' => $_GET['view'] ?? null,
        'return_url' => report_return_url(),
    ];
    return 'employee.php?' . http_build_query(array_filter($params, fn($value) => $value !== null && $value !== ''));
}

include '../../includes/header.php';
?>

<style>
    .report-filter-panel {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        padding: 1rem;
        margin-bottom: 1.25rem;
        box-shadow: var(--shadow-sm);
    }
    .report-filter-grid {
        display: grid;
        grid-template-columns: minmax(150px, 1fr) minmax(150px, 1fr) minmax(200px, 1.2fr) minmax(220px, 1.4fr) auto;
        gap: 0.85rem;
        align-items: end;
    }
    .report-field label {
        display: block;
        color: var(--text-muted);
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 0.4rem;
    }
    .report-field input,
    .report-field select {
        width: 100%;
        min-height: 42px;
        padding: 0.65rem 0.75rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-primary);
        outline: none;
        color-scheme: light;
    }
    .report-scope {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        margin-top: 0.9rem;
        color: var(--text-secondary);
        font-size: 0.86rem;
    }
    .report-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.65rem;
        border-radius: 999px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
    }
    .report-view-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.55rem;
        margin-bottom: 1.25rem;
    }
    .report-view-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        min-height: 42px;
        padding: 0.65rem 0.9rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--text-secondary);
        font-size: 0.88rem;
        font-weight: 800;
        text-decoration: none;
    }
    .report-view-tab:hover {
        color: var(--accent-primary);
        border-color: var(--accent-primary);
    }
    .report-view-tab.active {
        background: var(--accent-primary);
        color: #fff;
        border-color: var(--accent-primary);
        box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
    }
    .report-view-tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        padding: 0 0.45rem;
        border-radius: 999px;
        background: var(--bg-secondary);
        color: var(--text-muted);
        font-size: 0.74rem;
    }
    .report-view-tab.active .report-view-tab-count {
        background: rgba(255, 255, 255, 0.18);
        color: #fff;
    }
    .report-export-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        justify-content: flex-end;
    }
    .report-export-btn {
        min-height: 42px;
        white-space: nowrap;
    }
    .report-kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .report-kpi {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-left: 4px solid var(--accent-primary);
        border-radius: var(--radius-lg);
        padding: 1rem;
        box-shadow: var(--shadow-sm);
        min-height: 112px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .report-kpi-success { border-left-color: var(--accent-success); }
    .report-kpi-danger { border-left-color: var(--accent-danger); }
    .report-kpi-warning { border-left-color: var(--accent-warning); }
    .report-kpi-info { border-left-color: var(--accent-info); }
    .report-kpi-label {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        color: var(--text-muted);
        font-size: 0.78rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    .report-kpi-value {
        color: var(--text-primary);
        font-size: 1.55rem;
        font-weight: 850;
        line-height: 1.1;
        margin-top: 0.75rem;
    }
    .report-kpi-sub {
        color: var(--text-muted);
        font-size: 0.78rem;
        margin-top: 0.35rem;
    }
    .report-section {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        margin-bottom: 1.25rem;
        overflow: hidden;
    }
    .report-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-secondary);
    }
    .report-section-title {
        margin: 0;
        color: var(--text-primary);
        font-size: 1rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .report-section-count {
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 700;
    }
    .report-section-tools {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 0.75rem;
    }
    .report-split-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 1.25rem;
    }
    .report-person {
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
    }
    .report-code {
        color: var(--text-muted);
        font-size: 0.75rem;
        margin-top: 0.15rem;
    }
    .report-timeline {
        font-family: monospace;
        color: var(--text-secondary);
        white-space: nowrap;
    }
    .attendance-report-table {
        min-width: 1040px;
        border-collapse: separate;
        border-spacing: 0;
    }
    .attendance-report-table th:nth-child(1) { width: 130px; }
    .attendance-report-table th:nth-child(2) { width: 230px; }
    .attendance-report-table th:nth-child(3) { width: 190px; }
    .attendance-report-table th:nth-child(4) { width: 210px; text-align: right; }
    .attendance-report-table th:nth-child(5) { width: 230px; }
    .attendance-report-table th:nth-child(6) { width: 74px; }
    .attendance-report-table td {
        vertical-align: middle;
    }
    .attendance-report-table tbody tr[data-attendance-status="absent"] td {
        background: rgba(239, 68, 68, 0.025);
    }
    .attendance-report-table tbody tr[data-attendance-status="present"] td {
        background: rgba(16, 185, 129, 0.018);
    }
    .report-date-badge {
        display: inline-flex;
        align-items: center;
        min-width: 104px;
        padding: 0.4rem 0.55rem;
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        color: var(--text-secondary);
        font-size: 0.78rem;
        font-weight: 800;
        white-space: nowrap;
        justify-content: center;
    }
    .report-timeline-box {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        min-width: 150px;
        padding: 0.45rem 0.65rem;
        border-radius: var(--radius-md);
        background: #fff;
        border: 1px solid var(--border-color);
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        color: var(--text-primary);
        font-weight: 800;
        white-space: nowrap;
    }
    .report-timeline-box i {
        color: var(--text-muted);
        font-size: 0.72rem;
    }
    .report-work-stack {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.35rem;
        min-width: 188px;
    }
    .report-metric-pill {
        display: inline-flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.35rem;
        min-width: 86px;
        padding: 0.35rem 0.55rem;
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        color: var(--text-secondary);
        font-size: 0.74rem;
        font-weight: 800;
        white-space: nowrap;
    }
    .report-metric-pill strong {
        color: var(--text-primary);
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font-size: 0.78rem;
    }
    .report-metric-pill-ot {
        background: rgba(16, 185, 129, 0.1);
        color: var(--accent-success);
    }
    .report-metric-pill-ot strong {
        color: var(--accent-success);
    }
    .report-status-stack {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
        min-width: 185px;
    }
    .report-status-stack .status-badge {
        min-width: 72px;
        text-align: center;
    }
    .report-action-link {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--accent-primary);
        text-decoration: none;
    }
    .report-action-link:hover {
        border-color: var(--accent-primary);
        background: rgba(59, 130, 246, 0.08);
    }
    .report-flags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    .report-flag {
        padding: 0.18rem 0.45rem;
        border-radius: 999px;
        background: var(--bg-hover);
        color: var(--text-secondary);
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .report-flag-danger { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); }
    .report-flag-warning { background: rgba(245, 158, 11, 0.1); color: var(--accent-warning); }
    .report-flag-info { background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); }
    .report-empty {
        text-align: center;
        color: var(--text-muted);
        padding: 2rem;
    }
    .leave-payroll-table {
        min-width: 980px;
    }
    .report-employee-cell {
        min-width: 210px;
    }
    .report-money {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font-weight: 800;
        color: var(--text-primary);
        white-space: nowrap;
    }
    .report-money-positive {
        color: var(--accent-success);
    }
    .report-money-negative {
        color: var(--accent-danger);
    }
    .report-subline {
        color: var(--text-muted);
        font-size: 0.74rem;
        margin-top: 0.28rem;
        line-height: 1.35;
    }
    .report-summary-stack {
        display: grid;
        gap: 0.45rem;
        min-width: 170px;
    }
    .report-summary-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.85rem;
    }
    .report-summary-label {
        color: var(--text-muted);
        font-size: 0.72rem;
        font-weight: 800;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .report-leave-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.28rem 0.55rem;
        border-radius: 999px;
        background: rgba(59, 130, 246, 0.1);
        color: var(--accent-primary);
        font-size: 0.75rem;
        font-weight: 800;
    }
    .report-leave-empty {
        color: var(--text-muted);
        font-size: 0.82rem;
        font-weight: 700;
    }
    .report-net-amount {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        color: var(--text-primary);
        font-size: 1rem;
        font-weight: 900;
        white-space: nowrap;
    }
    .report-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 0.85rem 1rem;
        border-top: 1px solid var(--border-color);
        background: var(--bg-secondary);
    }
    .report-pagination-info {
        color: var(--text-muted);
        font-size: 0.82rem;
        font-weight: 700;
    }
    .report-pagination-actions {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    .report-page-link {
        min-width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0 0.65rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
        background: var(--bg-card);
        text-decoration: none;
        font-size: 0.82rem;
        font-weight: 800;
    }
    .report-page-link:hover {
        color: var(--accent-primary);
        border-color: var(--accent-primary);
    }
    .report-page-link.active {
        background: var(--accent-primary);
        color: #fff;
        border-color: var(--accent-primary);
    }
    .report-page-link.disabled {
        opacity: 0.45;
        pointer-events: none;
    }
    @media (max-width: 1100px) {
        .report-filter-grid,
        .report-kpi-grid,
        .report-split-grid {
            grid-template-columns: 1fr 1fr;
        }
    }
    @media (max-width: 720px) {
        .report-filter-grid,
        .report-kpi-grid,
        .report-split-grid {
            grid-template-columns: 1fr;
        }
        .report-export-actions {
            justify-content: stretch;
        }
        .report-export-actions .btn {
            flex: 1 1 150px;
        }
        .report-section-header {
            align-items: flex-start;
            flex-direction: column;
        }
        .report-pagination {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Reports</h1>
        <div class="page-subtitle">Attendance, leave, payroll, and compliance in one filtered view.</div>
    </div>
</div>

<div class="report-filter-panel">
    <form method="GET">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($reportView); ?>">
        <div class="report-filter-grid">
            <div class="report-field">
                <label>From</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="report-field">
                <label>To</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
                <div class="report-field">
                    <label>Branch</label>
                    <select name="branch_id" onchange="this.form.submit()">
                        <option value="0">All Branches</option>
                        <?php foreach ($branchOptions as $branch): ?>
                            <option value="<?php echo intval($branch['id']); ?>" <?php echo intval($branch['id']) === $branch_filter ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="branch_id" value="<?php echo intval($branch_filter); ?>">
            <?php endif; ?>
            <div class="report-field">
                <label>Employee</label>
                <select name="employee_id" <?php echo $currentUser['role'] === 'employee' ? 'disabled' : ''; ?>>
                    <option value="0">All Employees</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo intval($employee['id']); ?>" <?php echo intval($employee['id']) === $employee_filter ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($currentUser['role'] === 'employee'): ?>
                    <input type="hidden" name="employee_id" value="<?php echo intval($employee_filter); ?>">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary" style="min-height: 42px;"><i class="fas fa-filter"></i> Filter</button>
        </div>
        <div class="report-scope">
            <span class="report-chip"><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($periodLabel); ?></span>
            <span class="report-chip"><i class="fas fa-code-branch"></i> <?php echo htmlspecialchars($selectedBranchLabel); ?></span>
            <span class="report-chip"><i class="fas fa-user"></i> <?php echo htmlspecialchars($selectedEmployeeLabel); ?></span>
            <span class="report-chip"><i class="fas fa-table"></i> <?php echo count($attendanceRows); ?> attendance row(s)</span>
            <span class="report-chip"><i class="fas fa-download"></i> Exports use the active filters</span>
        </div>
    </form>
</div>

<div class="report-view-tabs" aria-label="Report pages">
    <a href="<?php echo htmlspecialchars(report_view_url('attendance')); ?>" class="report-view-tab <?php echo $reportView === 'attendance' ? 'active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        Attendance Records
        <span class="report-view-tab-count"><?php echo count($attendanceRows); ?></span>
    </a>
    <a href="<?php echo htmlspecialchars(report_view_url('leave_payroll')); ?>" class="report-view-tab <?php echo $reportView === 'leave_payroll' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i>
        Leave & Payroll
        <span class="report-view-tab-count"><?php echo count($leavePayrollRows); ?></span>
    </a>
</div>

<div class="report-kpi-grid">
    <div class="report-kpi report-kpi-success">
        <div class="report-kpi-label"><i class="fas fa-user-check"></i> Present</div>
        <div>
            <div class="report-kpi-value"><?php echo $summary['present']; ?></div>
            <div class="report-kpi-sub"><?php echo number_format($summary['hours'], 2); ?> total hour(s)</div>
        </div>
    </div>
    <div class="report-kpi report-kpi-danger">
        <div class="report-kpi-label"><i class="fas fa-user-times"></i> Absent</div>
        <div>
            <div class="report-kpi-value"><?php echo $summary['absent']; ?></div>
            <div class="report-kpi-sub"><?php echo number_format($summary['unpaid_days'], 2); ?> unpaid day(s)</div>
        </div>
    </div>
    <?php if ($showOvertime): ?>
        <div class="report-kpi report-kpi-warning">
            <div class="report-kpi-label"><i class="fas fa-business-time"></i> Overtime</div>
            <div>
                <div class="report-kpi-value"><?php echo htmlspecialchars(format_hours_minutes($summary['overtime'])); ?></div>
                <div class="report-kpi-sub">From attendance records</div>
            </div>
        </div>
    <?php endif; ?>
    <div class="report-kpi report-kpi-info">
        <div class="report-kpi-label"><i class="fas fa-file-invoice-dollar"></i> Payroll Net</div>
        <div>
            <div class="report-kpi-value" style="font-size: 1.25rem;"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($summary['net_salary'], 2); ?></div>
            <div class="report-kpi-sub"><?php echo count($payrollRows); ?> payroll row(s)</div>
        </div>
    </div>
    <?php if ($showStatutory): ?>
        <div class="report-kpi">
            <div class="report-kpi-label"><i class="fas fa-piggy-bank"></i> <?php echo $showEpf && $showEtf ? 'EPF / ETF' : ($showEpf ? 'EPF' : 'ETF'); ?></div>
            <div>
                <div class="report-kpi-value" style="font-size: 1.1rem;"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(($showEpf ? ($summary['epf_employee'] + $summary['epf_employer']) : 0) + ($showEtf ? $summary['etf_employer'] : 0), 2); ?></div>
                <div class="report-kpi-sub">Enabled statutory contribution(s)</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($reportView === 'attendance'): ?>
<section class="report-section" id="attendanceReportSection">
    <div class="report-section-header">
        <h3 class="report-section-title"><i class="fas fa-clipboard-list"></i> Attendance Records</h3>
        <div class="report-section-tools">
            <span class="report-section-count"><?php echo count($attendanceRows); ?> row(s)</span>
            <div class="report-export-actions">
                <a href="<?php echo htmlspecialchars(report_export_url('excel', 'attendance')); ?>" class="btn btn-primary report-export-btn">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="<?php echo htmlspecialchars(report_export_url('pdf', 'attendance')); ?>" class="btn report-export-btn" style="background: var(--accent-danger); color: #fff;">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
    </div>
    <div class="table-container">
        <table class="table attendance-report-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Sign-In / Sign-Out</th>
                    <th>Work Summary</th>
                    <th>Status & Flags</th>
                    <th style="text-align: center;">View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendancePageRows as $row): ?>
                    <tr data-attendance-status="<?php echo strtolower(htmlspecialchars($row['status'])); ?>">
                        <td><span class="report-date-badge"><?php echo date('M d, Y', strtotime($row['date'])); ?></span></td>
                        <td>
                            <div class="report-person"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                            <div class="report-code"><?php echo htmlspecialchars($row['employee_code']); ?></div>
                        </td>
                        <td>
                            <span class="report-timeline-box">
                                <?php echo $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : '--:--'; ?>
                                <i class="fas fa-arrow-right"></i>
                                <?php echo $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : '--:--'; ?>
                            </span>
                        </td>
                        <td>
                            <div class="report-work-stack">
                                <span class="report-metric-pill">Total <strong><?php echo number_format(floatval($row['total_hours']), 2); ?>h</strong></span>
                                <?php if ($showOvertime): ?>
                                    <span class="report-metric-pill report-metric-pill-ot">OT <strong><?php echo htmlspecialchars(format_hours_minutes(floatval($row['overtime_hours']))); ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="report-status-stack">
                                <span class="status-badge"><?php echo htmlspecialchars($row['status']); ?></span>
                            <?php
                            $flags = [];
                            if ($row['is_late']) $flags[] = ['Late', 'report-flag-danger'];
                            if ($row['is_early_departure']) $flags[] = ['Early', 'report-flag-warning'];
                            if ($row['is_absent']) $flags[] = ['No punch', 'report-flag-danger'];
                            if ($row['is_holiday']) $flags[] = ['Holiday', 'report-flag-info'];
                            if ($row['is_weekend']) $flags[] = ['Weekend', ''];
                            ?>
                                <?php if ($flags): ?>
                                    <?php foreach ($flags as $flag): ?>
                                        <span class="report-flag <?php echo htmlspecialchars($flag[1]); ?>"><?php echo htmlspecialchars($flag[0]); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="report-flag">Clear</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <a class="report-action-link" href="<?php echo htmlspecialchars(report_employee_url(intval($row['employee_id']))); ?>" title="View employee report">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($attendanceRows)): ?><tr><td colspan="6"><div class="report-empty">No attendance rows for this filter.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($attendanceRows)): ?>
        <div class="report-pagination">
            <div class="report-pagination-info">
                Showing <?php echo (($attendancePage - 1) * $perPage) + 1; ?>-<?php echo min($attendancePage * $perPage, count($attendanceRows)); ?> of <?php echo count($attendanceRows); ?>
            </div>
            <div class="report-pagination-actions">
                <a class="report-page-link <?php echo $attendancePage <= 1 ? 'disabled' : ''; ?>" data-report-target="attendanceReportSection" href="<?php echo htmlspecialchars(report_page_url('attendance_page', $attendancePage - 1)); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php for ($page = 1; $page <= $attendanceTotalPages; $page++): ?>
                    <a class="report-page-link <?php echo $page === $attendancePage ? 'active' : ''; ?>" data-report-target="attendanceReportSection" href="<?php echo htmlspecialchars(report_page_url('attendance_page', $page)); ?>"><?php echo $page; ?></a>
                <?php endfor; ?>
                <a class="report-page-link <?php echo $attendancePage >= $attendanceTotalPages ? 'disabled' : ''; ?>" data-report-target="attendanceReportSection" href="<?php echo htmlspecialchars(report_page_url('attendance_page', $attendancePage + 1)); ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($reportView === 'leave_payroll'): ?>
<section class="report-section" id="leavePayrollReportSection">
    <div class="report-section-header">
        <h3 class="report-section-title"><i class="fas fa-file-invoice-dollar"></i> Leave & Payroll</h3>
        <div class="report-section-tools">
            <span class="report-section-count"><?php echo count($leavePayrollRows); ?> row(s)</span>
            <div class="report-export-actions">
                <a href="<?php echo htmlspecialchars(report_export_url('excel', 'leave_payroll')); ?>" class="btn btn-primary report-export-btn">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="<?php echo htmlspecialchars(report_export_url('pdf', 'leave_payroll')); ?>" class="btn report-export-btn" style="background: var(--accent-danger); color: #fff;">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
    </div>
    <div class="table-container">
        <table class="table leave-payroll-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave in Period</th>
                    <th>Payroll Summary</th>
                    <?php if ($showStatutory): ?><th><?php echo $showEpf && $showEtf ? 'EPF / ETF' : ($showEpf ? 'EPF' : 'ETF'); ?></th><?php endif; ?>
                    <th style="text-align: right;">Net Salary</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">View</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leavePayrollPageRows as $row): ?>
                    <?php $payroll = $row['payroll']; ?>
                    <tr>
                        <td class="report-employee-cell">
                            <div class="report-person"><?php echo htmlspecialchars($row['employee_name']); ?></div>
                            <div class="report-code"><?php echo htmlspecialchars($row['employee_code']); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($row['leave_rows'])): ?>
                                <?php foreach ($row['leave_rows'] as $leave): ?>
                                    <div class="report-leave-pill">
                                        <i class="fas fa-calendar-check"></i>
                                        <?php echo htmlspecialchars($leave['leave_type']); ?>
                                        <span><?php echo htmlspecialchars($leave['status']); ?></span>
                                    </div>
                                    <div class="report-subline"><?php echo date('M d', strtotime($leave['start_date'])); ?> to <?php echo date('M d, Y', strtotime($leave['end_date'] ?: $leave['start_date'])); ?> - <?php echo number_format(floatval($leave['total_days']), 2); ?> day(s)</div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="report-leave-empty">No leave</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payroll): ?>
                                <div class="report-summary-stack">
                                    <div class="report-summary-row">
                                        <span class="report-summary-label">Month</span>
                                        <span class="report-money"><?php echo date('M Y', strtotime($payroll['payroll_month'] . '-01')); ?></span>
                                    </div>
                                    <?php if ($showOvertime): ?>
                                        <div class="report-summary-row">
                                            <span class="report-summary-label">OT</span>
                                            <span class="report-money report-money-positive"><?php echo htmlspecialchars(format_hours_minutes(floatval($payroll['overtime_hours'] ?? 0))); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="report-summary-row">
                                        <span class="report-summary-label">Unpaid</span>
                                        <span class="report-money report-money-negative"><?php echo number_format(floatval($payroll['unpaid_days'] ?? 0), 2); ?> day(s)</span>
                                    </div>
                                    <div class="report-summary-row">
                                        <span class="report-summary-label">Advance</span>
                                        <span class="report-money report-money-negative"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($payroll['advance_amount'] ?? 0), 2); ?></span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="report-leave-empty">No payroll</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($showStatutory): ?>
                            <td>
                                <?php if ($payroll): ?>
                                    <div class="report-summary-stack">
                                        <?php if ($showEpf): ?>
                                            <div class="report-summary-row">
                                                <span class="report-summary-label">Employee EPF</span>
                                                <span class="report-money report-money-negative"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($payroll['epf_employee_amount'] ?? 0), 2); ?></span>
                                            </div>
                                            <div class="report-summary-row">
                                                <span class="report-summary-label">Employer EPF</span>
                                                <span class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($payroll['epf_employer_amount'] ?? 0), 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($showEtf): ?>
                                            <div class="report-summary-row">
                                                <span class="report-summary-label">ETF</span>
                                                <span class="report-money"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($payroll['etf_employer_amount'] ?? 0), 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="report-leave-empty">--</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                        <td style="text-align: right;"><?php echo $payroll ? '<span class="report-net-amount">' . htmlspecialchars($currency) . ' ' . number_format(floatval($payroll['net_salary']), 2) . '</span>' : '--'; ?></td>
                        <td style="text-align: center;"><?php echo $payroll ? '<span class="status-badge">' . htmlspecialchars($payroll['status']) . '</span>' : '<span class="report-leave-empty">No payroll</span>'; ?></td>
                        <td style="text-align: center;">
                            <a class="report-action-link" href="<?php echo htmlspecialchars(report_employee_url(intval($row['employee_id']))); ?>" title="View employee report">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leavePayrollRows)): ?><tr><td colspan="<?php echo $leavePayrollColspan; ?>"><div class="report-empty">No leave or payroll rows for this filter.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($leavePayrollRows)): ?>
        <div class="report-pagination">
            <div class="report-pagination-info">
                Showing <?php echo (($leavePayrollPage - 1) * $perPage) + 1; ?>-<?php echo min($leavePayrollPage * $perPage, count($leavePayrollRows)); ?> of <?php echo count($leavePayrollRows); ?>
            </div>
            <div class="report-pagination-actions">
                <a class="report-page-link <?php echo $leavePayrollPage <= 1 ? 'disabled' : ''; ?>" data-report-target="leavePayrollReportSection" href="<?php echo htmlspecialchars(report_page_url('leave_payroll_page', $leavePayrollPage - 1)); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php for ($page = 1; $page <= $leavePayrollTotalPages; $page++): ?>
                    <a class="report-page-link <?php echo $page === $leavePayrollPage ? 'active' : ''; ?>" data-report-target="leavePayrollReportSection" href="<?php echo htmlspecialchars(report_page_url('leave_payroll_page', $page)); ?>"><?php echo $page; ?></a>
                <?php endfor; ?>
                <a class="report-page-link <?php echo $leavePayrollPage >= $leavePayrollTotalPages ? 'disabled' : ''; ?>" data-report-target="leavePayrollReportSection" href="<?php echo htmlspecialchars(report_page_url('leave_payroll_page', $leavePayrollPage + 1)); ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<script>
    document.addEventListener('click', async function(event) {
        const link = event.target.closest('.report-page-link[data-report-target]');
        if (!link || link.classList.contains('disabled') || link.classList.contains('active')) {
            return;
        }

        event.preventDefault();

        const targetId = link.dataset.reportTarget;
        const target = document.getElementById(targetId);
        if (!target) {
            window.location.href = link.href;
            return;
        }

        target.style.opacity = '0.55';
        target.style.pointerEvents = 'none';

        try {
            const response = await fetch(link.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const fresh = doc.getElementById(targetId);

            if (!fresh) {
                window.location.href = link.href;
                return;
            }

            target.replaceWith(fresh);
            window.history.replaceState({}, '', link.href);
        } catch (error) {
            window.location.href = link.href;
        } finally {
            const updatedTarget = document.getElementById(targetId);
            if (updatedTarget) {
                updatedTarget.style.opacity = '';
                updatedTarget.style.pointerEvents = '';
            }
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>
