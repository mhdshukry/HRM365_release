<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/leave_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'])) {
    die("Unauthorized access.");
}

$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$scopeSql = '';
$scopeParams = [];
if ($currentUser['role'] === 'employee') {
    $scopeSql = ' AND e.id = ?';
    $scopeParams[] = intval($currentUser['employee_id'] ?? 0);
} elseif ($currentUser['role'] === 'manager') {
    $scopeSql = ' AND e.department = ?';
    $scopeParams[] = $currentUser['department'] ?? '';
}

// Fetch the central ledger
$stmt = $pdo->prepare("
    SELECT 
        b.*, 
        e.first_name, e.last_name, e.employee_code,
        t.name as leave_type_name, t.color,
        p.name as policy_name
    FROM leave_balances b
    JOIN employees e ON b.employee_id = e.id
    JOIN leave_types t ON b.leave_type_id = t.id
    LEFT JOIN leave_policies p ON b.leave_policy_id = p.id
    WHERE b.year = ?
    {$scopeSql}
    ORDER BY e.first_name ASC, t.name ASC
");
$stmt->execute(array_merge([$year_filter], $scopeParams));
$balances = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Balances Ledger (<?php echo $year_filter; ?>)</h1>
        <div class="page-subtitle">Track mathematical allocations, roll-overs, and precise employee entitlements.</div>
    </div>
    
    <div style="display: flex; gap: 1rem; align-items: center;">
        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
            <form action="generate.php" method="POST" style="margin: 0;">
                <input type="hidden" name="year" value="<?php echo $year_filter; ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-calculator"></i> Generate Ledger
                </button>
            </form>
        <?php endif; ?>
        <form action="" method="GET" style="margin: 0; display: flex; gap: 0.5rem; align-items: center;">
            <label style="color: var(--text-secondary); font-size: 0.9rem;">Fiscal Year:</label>
            <select name="year" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <?php for($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y === $year_filter ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Target</th>
                    <th style="text-align: center;">Allocated</th>
                    <th style="text-align: center;">Rollover</th>
                    <th style="text-align: center;">Adjustment</th>
                    <th style="text-align: center;">Used</th>
                    <th style="text-align: center; color: var(--accent-primary);">Remaining</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($balances as $b): 
                    // Core Mathematics
                    $total_allocated = round(floatval($b['allocated_days']) + floatval($b['carried_forward']) + floatval($b['manual_adjustment']), 0);
                    $remaining = $total_allocated - floatval($b['used_days']);
                ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($b['employee_code']); ?></div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo htmlspecialchars($b['color']); ?>;"></div>
                            <span style="font-weight: 500;"><?php echo htmlspecialchars($b['leave_type_name']); ?></span>
                        </div>
                        <?php if ($b['policy_name']): ?>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;"><?php echo htmlspecialchars($b['policy_name']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; color: var(--text-secondary);"><?php echo htmlspecialchars(format_leave_days(floatval($b['allocated_days']))); ?></td>
                    <td style="text-align: center; color: var(--text-secondary);"><?php echo htmlspecialchars(format_leave_days(floatval($b['carried_forward']))); ?></td>
                    <td style="text-align: center;">
                        <?php if (floatval($b['manual_adjustment']) > 0): ?>
                            <span style="color: var(--accent-success); font-weight: 600;">+<?php echo htmlspecialchars(format_leave_days(floatval($b['manual_adjustment']))); ?></span>
                        <?php elseif (floatval($b['manual_adjustment']) < 0): ?>
                            <span style="color: var(--accent-danger); font-weight: 600;"><?php echo htmlspecialchars(format_leave_days(floatval($b['manual_adjustment']))); ?></span>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">0</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; color: var(--accent-warning); font-weight: 500;"><?php echo htmlspecialchars(format_leave_days(floatval($b['used_days']))); ?></td>
                    <td style="text-align: center; color: var(--accent-primary); font-weight: 700; font-size: 1.1rem;">
                        <?php echo htmlspecialchars(format_leave_days($remaining)); ?>
                    </td>
                    <td>
                        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
                            <a href="adjust.php?id=<?php echo $b['id']; ?>" class="action-btn" title="Manual Adjustment" style="color: var(--text-primary); text-decoration: none;">
                                <i class="fas fa-balance-scale"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">View only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($balances)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No leave ledgers found for <?php echo $year_filter; ?>.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
