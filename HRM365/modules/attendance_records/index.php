<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'])) {
    die("Unauthorized access.");
}

$date_filter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$scopeSql = '';
$scopeParams = [];
if ($currentUser['role'] === 'employee') {
    $scopeSql = ' AND e.id = ?';
    $scopeParams[] = intval($currentUser['employee_id'] ?? 0);
} elseif ($currentUser['role'] === 'manager') {
    $scopeSql = ' AND e.department = ?';
    $scopeParams[] = $currentUser['department'] ?? '';
}

$stmt = $pdo->prepare("
    SELECT r.*, 
           e.first_name, e.last_name, e.employee_code,
           s.name as shift_name,
           p.name as policy_name
    FROM attendance_records r
    JOIN employees e ON r.employee_id = e.id
    LEFT JOIN shifts s ON r.shift_id = s.id
    LEFT JOIN attendance_policies p ON r.attendance_policy_id = p.id
    WHERE r.date = ?
    {$scopeSql}
    ORDER BY r.clock_in DESC
");
$stmt->execute(array_merge([$date_filter], $scopeParams));
$records = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Daily Attendance Records</h1>
        <div class="page-subtitle">Review mathematical timesheets, lateness, and overtime calculations.</div>
    </div>
    
    <form action="" method="GET" style="display: flex; gap: 0.5rem; align-items: center;">
        <label style="color: var(--text-secondary); font-size: 0.9rem;">Filter by Date:</label>
        <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
    </form>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Constraints (Shift & Rules)</th>
                    <th style="text-align: center;">Timeline (In - Out)</th>
                    <th style="text-align: center;">Total Hours</th>
                    <th style="text-align: center;">Overtime</th>
                    <th style="text-align: center;">Compliance Flags</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($r['employee_code']); ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 500; font-size: 0.85rem; color: var(--text-primary);"><i class="fas fa-clock" style="color: var(--text-muted);"></i> <?php echo htmlspecialchars($r['shift_name'] ?? 'No Shift'); ?></div>
                        <div style="font-size: 0.75rem; color: var(--accent-info); margin-top: 0.2rem;"><i class="fas fa-shield-alt"></i> <?php echo htmlspecialchars($r['policy_name'] ?? 'No Policy'); ?></div>
                    </td>
                    <td style="text-align: center; font-family: monospace; font-size: 0.95rem;">
                        <span style="color: var(--accent-success);"><?php echo $r['clock_in'] ? date('H:i', strtotime($r['clock_in'])) : '--:--'; ?></span>
                        <span style="color: var(--text-muted); margin: 0 0.5rem;">➔</span>
                        <span style="color: var(--accent-danger);"><?php echo $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) : '--:--'; ?></span>
                    </td>
                    <td style="text-align: center;">
                        <div style="font-weight: 700; color: var(--text-primary); font-size: 1.1rem;"><?php echo floatval($r['total_hours']); ?>h</div>
                    </td>
                    <td style="text-align: center;">
                        <?php if (floatval($r['overtime_hours']) > 0): ?>
                            <div style="font-weight: 700; color: var(--accent-warning); font-size: 1rem;"><?php echo htmlspecialchars(format_hours_minutes(floatval($r['overtime_hours']))); ?></div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;"><?php echo number_format(floatval($r['overtime_hours']), 2); ?>h</div>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.85rem;">0m</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; gap: 0.25rem; justify-content: center;">
                            <?php if ($r['is_late']): ?>
                                <span style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">LATE</span>
                            <?php endif; ?>
                            <?php if ($r['is_early_departure']): ?>
                                <span style="background: rgba(245, 158, 11, 0.1); color: var(--accent-warning); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">EARLY</span>
                            <?php endif; ?>
                            <?php if ($r['is_holiday']): ?>
                                <span style="background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">HOLIDAY</span>
                            <?php endif; ?>
                            <?php if ($r['is_weekend']): ?>
                                <span style="background: var(--bg-hover); color: var(--text-secondary); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">WEEKEND</span>
                            <?php endif; ?>
                            <?php if (!$r['is_late'] && !$r['is_early_departure'] && !$r['is_holiday'] && !$r['is_weekend']): ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">-</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'Present'): ?>
                            <span class="status-badge status-active">Present</span>
                        <?php elseif ($r['status'] === 'Pending'): ?>
                            <span class="status-badge" style="background: var(--bg-hover); color: var(--accent-info);">Working</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger);"><?php echo htmlspecialchars($r['status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.4rem;">
                            <a href="detail.php?id=<?php echo intval($r['id']); ?>" class="action-btn" title="View Timesheet Math Log"><i class="fas fa-file-invoice"></i></a>
                            <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
                                <a href="correct.php?id=<?php echo intval($r['id']); ?>" class="action-btn" title="Correct Attendance"><i class="fas fa-edit"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($records)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No timesheets recorded for this date.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
