<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once __DIR__ . '/report_data.php';

$perPage = 10;
$attendancePage = max(1, intval($_GET['attendance_page'] ?? 1));
$leavePayrollPage = max(1, intval($_GET['leave_payroll_page'] ?? 1));
$attendanceTotalPages = max(1, (int) ceil(count($attendanceRows) / $perPage));
$leavePayrollTotalPages = max(1, (int) ceil(count($leavePayrollRows) / $perPage));
$attendancePage = min($attendancePage, $attendanceTotalPages);
$leavePayrollPage = min($leavePayrollPage, $leavePayrollTotalPages);
$attendancePageRows = array_slice($attendanceRows, ($attendancePage - 1) * $perPage, $perPage);
$leavePayrollPageRows = array_slice($leavePayrollRows, ($leavePayrollPage - 1) * $perPage, $perPage);

function report_page_url(string $pageParam, int $page): string
{
    $params = $_GET;
    $params[$pageParam] = $page;
    return '?' . http_build_query($params);
}

function report_export_url(string $format): string
{
    $params = $_GET;
    unset($params['attendance_page'], $params['leave_payroll_page']);
    $params['format'] = $format;
    return 'export.php?' . http_build_query($params);
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
        grid-template-columns: minmax(150px, 1fr) minmax(150px, 1fr) minmax(220px, 1.4fr) auto;
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
        grid-template-columns: repeat(4, minmax(150px, 1fr));
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
    <div class="report-export-actions">
        <a href="<?php echo htmlspecialchars(report_export_url('excel')); ?>" class="btn btn-primary report-export-btn">
            <i class="fas fa-file-excel"></i> Excel
        </a>
        <a href="<?php echo htmlspecialchars(report_export_url('pdf')); ?>" class="btn report-export-btn" style="background: var(--accent-danger); color: #fff;">
            <i class="fas fa-file-pdf"></i> PDF
        </a>
    </div>
</div>

<div class="report-filter-panel">
    <form method="GET">
        <div class="report-filter-grid">
            <div class="report-field">
                <label>From</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="report-field">
                <label>To</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
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
            <span class="report-chip"><i class="fas fa-user"></i> <?php echo htmlspecialchars($selectedEmployeeLabel); ?></span>
            <span class="report-chip"><i class="fas fa-table"></i> <?php echo count($attendanceRows); ?> attendance row(s)</span>
            <span class="report-chip"><i class="fas fa-download"></i> Exports use the active filters</span>
        </div>
    </form>
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
    <div class="report-kpi report-kpi-warning">
        <div class="report-kpi-label"><i class="fas fa-business-time"></i> Overtime</div>
        <div>
            <div class="report-kpi-value"><?php echo htmlspecialchars(format_hours_minutes($summary['overtime'])); ?></div>
            <div class="report-kpi-sub">From attendance records</div>
        </div>
    </div>
    <div class="report-kpi report-kpi-info">
        <div class="report-kpi-label"><i class="fas fa-file-invoice-dollar"></i> Payroll Net</div>
        <div>
            <div class="report-kpi-value" style="font-size: 1.25rem;"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($summary['net_salary'], 2); ?></div>
            <div class="report-kpi-sub"><?php echo count($payrollRows); ?> payroll row(s)</div>
        </div>
    </div>
</div>

<section class="report-section" id="attendanceReportSection">
    <div class="report-section-header">
        <h3 class="report-section-title"><i class="fas fa-clipboard-list"></i> Attendance Records</h3>
        <span class="report-section-count"><?php echo count($attendanceRows); ?> row(s)</span>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Timeline</th>
                    <th style="text-align: right;">Total</th>
                    <th style="text-align: right;">OT</th>
                    <th>Status</th>
                    <th>Flags</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendancePageRows as $row): ?>
                    <tr>
                        <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                        <td>
                            <div class="report-person"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></div>
                            <div class="report-code"><?php echo htmlspecialchars($row['employee_code']); ?></div>
                        </td>
                        <td><span class="report-timeline"><?php echo $row['clock_in'] ? date('H:i', strtotime($row['clock_in'])) : '--:--'; ?> to <?php echo $row['clock_out'] ? date('H:i', strtotime($row['clock_out'])) : '--:--'; ?></span></td>
                        <td style="text-align: right; font-weight: 700;"><?php echo number_format(floatval($row['total_hours']), 2); ?>h</td>
                        <td style="text-align: right;"><?php echo htmlspecialchars(format_hours_minutes(floatval($row['overtime_hours']))); ?></td>
                        <td><span class="status-badge"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td>
                            <?php
                            $flags = [];
                            if ($row['is_late']) $flags[] = ['Late', 'report-flag-danger'];
                            if ($row['is_early_departure']) $flags[] = ['Early', 'report-flag-warning'];
                            if ($row['is_absent']) $flags[] = ['No punch', 'report-flag-danger'];
                            if ($row['is_holiday']) $flags[] = ['Holiday', 'report-flag-info'];
                            if ($row['is_weekend']) $flags[] = ['Weekend', ''];
                            ?>
                            <div class="report-flags">
                                <?php if ($flags): ?>
                                    <?php foreach ($flags as $flag): ?>
                                        <span class="report-flag <?php echo htmlspecialchars($flag[1]); ?>"><?php echo htmlspecialchars($flag[0]); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="report-flag">Clear</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($attendanceRows)): ?><tr><td colspan="7"><div class="report-empty">No attendance rows for this filter.</div></td></tr><?php endif; ?>
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

<section class="report-section" id="leavePayrollReportSection">
    <div class="report-section-header">
        <h3 class="report-section-title"><i class="fas fa-file-invoice-dollar"></i> Leave & Payroll</h3>
        <span class="report-section-count"><?php echo count($leavePayrollRows); ?> row(s)</span>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave in Period</th>
                    <th>Payroll Month</th>
                    <th style="text-align: right;">OT</th>
                    <th style="text-align: right;">Unpaid</th>
                    <th style="text-align: right;">Net</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leavePayrollPageRows as $row): ?>
                    <?php $payroll = $row['payroll']; ?>
                    <tr>
                        <td>
                            <div class="report-person"><?php echo htmlspecialchars($row['employee_name']); ?></div>
                            <div class="report-code"><?php echo htmlspecialchars($row['employee_code']); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($row['leave_rows'])): ?>
                                <?php foreach ($row['leave_rows'] as $leave): ?>
                                    <div style="font-weight: 700;"><?php echo htmlspecialchars($leave['leave_type']); ?> <span class="report-code">(<?php echo htmlspecialchars($leave['status']); ?>)</span></div>
                                    <div class="report-code"><?php echo date('M d', strtotime($leave['start_date'])); ?> to <?php echo date('M d, Y', strtotime($leave['end_date'] ?: $leave['start_date'])); ?> · <?php echo number_format(floatval($leave['total_days']), 2); ?> day(s)</div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">No leave</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space: nowrap;"><?php echo $payroll ? date('M Y', strtotime($payroll['payroll_month'] . '-01')) : '--'; ?></td>
                        <td style="text-align: right;"><?php echo $payroll ? htmlspecialchars(format_hours_minutes(floatval($payroll['overtime_hours'] ?? 0))) : '--'; ?></td>
                        <td style="text-align: right;"><?php echo $payroll ? number_format(floatval($payroll['unpaid_days'] ?? 0), 2) . ' day(s)' : '--'; ?></td>
                        <td style="text-align: right; font-weight: 800;"><?php echo $payroll ? htmlspecialchars($currency) . ' ' . number_format(floatval($payroll['net_salary']), 2) : '--'; ?></td>
                        <td><?php echo $payroll ? '<span class="status-badge">' . htmlspecialchars($payroll['status']) . '</span>' : '<span style="color: var(--text-muted);">No payroll</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($leavePayrollRows)): ?><tr><td colspan="7"><div class="report-empty">No leave or payroll rows for this filter.</div></td></tr><?php endif; ?>
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
