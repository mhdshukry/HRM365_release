<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'], true)) {
    die("Unauthorized access.");
}

$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$branch_filter = intval($_GET['branch_id'] ?? 0);
$branches = [];
$scopeSql = '';
$scopeParams = [];

if ($currentUser['role'] === 'employee') {
    $scopeSql = ' AND e.id = ?';
    $scopeParams[] = intval($currentUser['employee_id'] ?? 0);
} elseif ($currentUser['role'] === 'manager') {
    $scopeSql = ' AND e.department = ?';
    $scopeParams[] = $currentUser['department'] ?? '';
} elseif (in_array($currentUser['role'], ['admin', 'HR'], true)) {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
    if ($branch_filter > 0) {
        $scopeSql = ' AND e.branch_id = ?';
        $scopeParams[] = $branch_filter;
    }
}

$stmt = $pdo->prepare("
    SELECT r.*,
           e.first_name, e.last_name, e.employee_code,
           b.name AS branch_name,
           s.name AS shift_name,
           p.name AS policy_name,
           COALESCE(reg.pending_count, 0) AS pending_regularizations,
           COALESCE(reg.approved_count, 0) AS approved_regularizations
    FROM attendance_records r
    JOIN employees e ON r.employee_id = e.id
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN shifts s ON r.shift_id = s.id
    LEFT JOIN attendance_policies p ON r.attendance_policy_id = p.id
    LEFT JOIN (
        SELECT attendance_record_id,
               SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
               SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_count
        FROM attendance_regularizations
        GROUP BY attendance_record_id
    ) reg ON reg.attendance_record_id = r.id
    WHERE r.date = ?
    {$scopeSql}
    ORDER BY COALESCE(r.clock_in, r.created_at) DESC
");
$stmt->execute(array_merge([$date_filter], $scopeParams));
$records = $stmt->fetchAll();

function attendance_short_time(?string $value): string
{
    return $value ? date('H:i', strtotime($value)) : '--:--';
}

function attendance_record_state(array $record): array
{
    $status = (string) ($record['status'] ?? 'Pending');
    $hasClockIn = !empty($record['clock_in']);
    $hasClockOut = !empty($record['clock_out']);

    if ($status === 'On Leave') {
        return ['label' => 'On Leave', 'class' => 'status-leave', 'summary' => 'Approved leave day'];
    }

    if ($status === 'Holiday' || intval($record['is_holiday'] ?? 0) === 1) {
        return ['label' => 'Holiday', 'class' => 'status-info', 'summary' => 'Holiday / non-working day'];
    }

    if ($status === 'Absent' || intval($record['is_absent'] ?? 0) === 1 || !$hasClockIn) {
        return ['label' => 'Absent', 'class' => 'status-danger', 'summary' => 'No valid sign-in'];
    }

    if ($hasClockIn && !$hasClockOut) {
        return ['label' => 'Open', 'class' => 'status-warning', 'summary' => 'Missing sign-out'];
    }

    if ($status === 'Pending') {
        return ['label' => 'Pending', 'class' => 'status-info', 'summary' => 'Awaiting calculation'];
    }

    return ['label' => 'Present', 'class' => 'status-active', 'summary' => 'Completed timesheet'];
}

function attendance_flag_badges(array $record): array
{
    $flags = [];
    if (intval($record['is_late'] ?? 0) === 1) {
        $flags[] = ['Late', 'danger'];
    }
    if (intval($record['is_early_departure'] ?? 0) === 1) {
        $flags[] = ['Early Out', 'warning'];
    }
    if (intval($record['is_absent'] ?? 0) === 1 || empty($record['clock_in'])) {
        $flags[] = ['No Punch', 'danger'];
    }
    if (!empty($record['clock_in']) && empty($record['clock_out'])) {
        $flags[] = ['Open Clock-In', 'warning'];
    }
    if (intval($record['is_holiday'] ?? 0) === 1) {
        $flags[] = ['Holiday', 'info'];
    }
    if (intval($record['is_weekend'] ?? 0) === 1) {
        $flags[] = ['Weekend', 'muted'];
    }
    if (intval($record['pending_regularizations'] ?? 0) > 0) {
        $flags[] = ['Correction Pending', 'info'];
    } elseif (intval($record['approved_regularizations'] ?? 0) > 0) {
        $flags[] = ['Corrected', 'success'];
    }

    return $flags;
}

include '../../includes/header.php';
?>

<style>
    .attendance-toolbar {
        display: flex;
        gap: 0.75rem;
        align-items: end;
        flex-wrap: wrap;
    }

    .attendance-filter-field {
        display: grid;
        gap: 0.35rem;
    }

    .attendance-filter-field label {
        color: var(--text-secondary);
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .attendance-filter-field input,
    .attendance-filter-field select {
        min-height: 40px;
        padding: 0.55rem 0.7rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-primary);
        outline: none;
        color-scheme: light;
    }

    .attendance-table td {
        vertical-align: middle;
    }

    .attendance-employee,
    .attendance-stack {
        display: grid;
        gap: 0.2rem;
    }

    .attendance-employee {
        min-width: 180px;
    }

    .attendance-employee strong,
    .attendance-stack strong {
        color: var(--text-primary);
        font-size: 0.95rem;
    }

    .attendance-muted {
        color: var(--text-muted);
        font-size: 0.76rem;
    }

    .attendance-timebox {
        display: inline-grid;
        grid-template-columns: minmax(56px, auto) 20px minmax(56px, auto);
        align-items: center;
        justify-content: center;
        gap: 0.2rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-weight: 800;
        color: var(--text-primary);
    }

    .attendance-time-in { color: var(--accent-success); }
    .attendance-time-out { color: var(--accent-danger); }
    .attendance-arrow { color: var(--text-muted); text-align: center; }

    .attendance-metric {
        display: grid;
        gap: 0.18rem;
        text-align: center;
    }

    .attendance-metric strong {
        color: var(--text-primary);
        font-size: 1rem;
    }

    .attendance-metric span {
        color: var(--text-muted);
        font-size: 0.72rem;
    }

    .attendance-flags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        justify-content: center;
        min-width: 150px;
    }

    .attendance-chip {
        display: inline-flex;
        align-items: center;
        min-height: 24px;
        padding: 0.18rem 0.5rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .attendance-chip-success { background: rgba(16, 185, 129, 0.1); color: var(--accent-success); }
    .attendance-chip-danger { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); }
    .attendance-chip-warning { background: rgba(245, 158, 11, 0.12); color: var(--accent-warning); }
    .attendance-chip-info { background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); }
    .attendance-chip-muted { background: var(--bg-hover); color: var(--text-secondary); }

    .attendance-actions {
        display: flex;
        gap: 0.45rem;
        align-items: center;
    }

    .status-danger { background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); }
    .status-warning { background: rgba(245, 158, 11, 0.12); color: var(--accent-warning); }
    .status-info { background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); }

    @media (max-width: 860px) {
        .attendance-toolbar {
            align-items: stretch;
        }

        .attendance-filter-field,
        .attendance-filter-field input,
        .attendance-filter-field select {
            width: 100%;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title">Daily Attendance Records</h1>
        <div class="page-subtitle">Review timesheets, punch gaps, lateness, corrections, and overtime calculations.</div>
    </div>

    <form action="" method="GET" class="attendance-toolbar">
        <div class="attendance-filter-field">
            <label for="attendanceDateFilter">Date</label>
            <input id="attendanceDateFilter" type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()">
        </div>
        <?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
            <div class="attendance-filter-field">
                <label for="attendanceBranchFilter">Branch</label>
                <select id="attendanceBranchFilter" name="branch_id" onchange="this.form.submit()">
                    <option value="0">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo intval($branch['id']); ?>" <?php echo $branch_filter === intval($branch['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="table-container">
        <table class="table attendance-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Shift & Policy</th>
                    <th style="text-align: center;">Timeline</th>
                    <th style="text-align: center;">Work</th>
                    <th style="text-align: center;">Flags</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <?php
                        $state = attendance_record_state($record);
                        $flags = attendance_flag_badges($record);
                    ?>
                    <tr>
                        <td>
                            <div class="attendance-employee">
                                <strong><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></strong>
                                <span class="attendance-muted"><?php echo htmlspecialchars($record['employee_code']); ?></span>
                                <span class="attendance-muted"><?php echo htmlspecialchars($record['branch_name'] ?? 'No Branch'); ?></span>
                            </div>
                        </td>
                        <td>
                            <div class="attendance-stack">
                                <strong><i class="fas fa-clock" style="color: var(--text-muted);"></i> <?php echo htmlspecialchars($record['shift_name'] ?? 'No Shift'); ?></strong>
                                <span class="attendance-muted"><i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($record['policy_name'] ?? 'No Policy'); ?></span>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <div class="attendance-timebox">
                                <span class="attendance-time-in"><?php echo htmlspecialchars(attendance_short_time($record['clock_in'])); ?></span>
                                <span class="attendance-arrow">→</span>
                                <span class="attendance-time-out"><?php echo htmlspecialchars(attendance_short_time($record['clock_out'])); ?></span>
                            </div>
                            <div class="attendance-muted"><?php echo htmlspecialchars($state['summary']); ?></div>
                        </td>
                        <td>
                            <div class="attendance-metric">
                                <strong><?php echo number_format(floatval($record['total_hours']), 2); ?>h</strong>
                                <span>total</span>
                            </div>
                            <div class="attendance-metric" style="margin-top: 0.45rem;">
                                <strong style="color: <?php echo floatval($record['overtime_hours']) > 0 ? 'var(--accent-warning)' : 'var(--text-muted)'; ?>;"><?php echo htmlspecialchars(format_hours_minutes(floatval($record['overtime_hours']))); ?></strong>
                                <span>overtime</span>
                            </div>
                        </td>
                        <td>
                            <div class="attendance-flags">
                                <?php foreach ($flags as $flag): ?>
                                    <span class="attendance-chip attendance-chip-<?php echo htmlspecialchars($flag[1]); ?>"><?php echo htmlspecialchars($flag[0]); ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($flags)): ?>
                                    <span class="attendance-chip attendance-chip-success">Clear</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo htmlspecialchars($state['class']); ?>"><?php echo htmlspecialchars($state['label']); ?></span>
                        </td>
                        <td>
                            <div class="attendance-actions">
                                <a href="detail.php?id=<?php echo intval($record['id']); ?>" class="action-btn" title="View Timesheet Math Log"><i class="fas fa-file-invoice"></i></a>
                                <?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
                                    <a href="correct.php?id=<?php echo intval($record['id']); ?>" class="action-btn" title="Correct Attendance"><i class="fas fa-edit"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No timesheets recorded for this date.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
