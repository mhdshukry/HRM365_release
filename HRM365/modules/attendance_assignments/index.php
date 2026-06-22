<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$status_filter = $_GET['status'] ?? 'Active';
$assignment_filter = $_GET['assignment'] ?? '';

$where = [];
$params = [];

if (in_array($status_filter, ['Active', 'On Leave', 'Terminated'], true)) {
    $where[] = 'e.status = ?';
    $params[] = $status_filter;
}

if ($assignment_filter === 'missing') {
    $where[] = '(e.shift_id IS NULL OR e.attendance_policy_id IS NULL)';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.employee_code, e.status, e.shift_id, e.attendance_policy_id,
           s.name as shift_name, s.status as shift_status,
           p.name as policy_name, p.status as policy_status
    FROM employees e
    LEFT JOIN shifts s ON e.shift_id = s.id
    LEFT JOIN attendance_policies p ON e.attendance_policy_id = p.id
    {$whereSql}
    ORDER BY e.first_name ASC
");
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Fetch available options for the assignment dropdowns
$shifts = $pdo->query("SELECT id, name, status FROM shifts ORDER BY status ASC, name ASC")->fetchAll();
$policies = $pdo->query("SELECT id, name, status FROM attendance_policies ORDER BY status ASC, name ASC")->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Attendance Assignments</h1>
        <div class="page-subtitle">Map employees to specific Shift schedules and Attendance Policies.</div>
    </div>
</div>

<div class="card">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: end; justify-content: flex-end; margin-bottom: 1.5rem;">
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.85rem;">Employee Status</label>
            <select name="status" style="padding: 0.6rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All</option>
                <?php foreach (['Active', 'On Leave', 'Terminated'] as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.85rem;">Assignment</label>
            <select name="assignment" style="padding: 0.6rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <option value="" <?php echo $assignment_filter === '' ? 'selected' : ''; ?>>All</option>
                <option value="missing" <?php echo $assignment_filter === 'missing' ? 'selected' : ''; ?>>Missing Shift or Policy</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);"><i class="fas fa-times"></i></a>
    </form>

    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Current Shift</th>
                    <th>Current Policy</th>
                    <th style="text-align: right;">Assign New Rules</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($e['employee_code']); ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($e['status']); ?></div>
                    </td>
                    <td>
                        <?php if ($e['shift_id']): ?>
                            <span style="color: var(--accent-info); font-weight: 500;"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($e['shift_name'] . ($e['shift_status'] === 'Inactive' ? ' (Inactive)' : '')); ?></span>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($e['attendance_policy_id']): ?>
                            <span style="color: var(--accent-danger); font-weight: 500;"><i class="fas fa-gavel"></i> <?php echo htmlspecialchars($e['policy_name'] . ($e['policy_status'] === 'Inactive' ? ' (Inactive)' : '')); ?></span>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <form action="save.php" method="POST" style="margin: 0; display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                            <input type="hidden" name="employee_id" value="<?php echo $e['id']; ?>">
                            
                            <select name="shift_id" style="padding: 0.4rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; font-size: 0.85rem;">
                                <option value="">No Shift</option>
                                <?php foreach ($shifts as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo $e['shift_id'] == $s['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name'] . ($s['status'] === 'Inactive' ? ' (Inactive)' : '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select name="attendance_policy_id" style="padding: 0.4rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; font-size: 0.85rem;">
                                <option value="">No Policy</option>
                                <?php foreach ($policies as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo $e['attendance_policy_id'] == $p['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['name'] . ($p['status'] === 'Inactive' ? ' (Inactive)' : '')); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-save"></i> Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem;">No employees match these filters.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
