<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'employee'])) {
    die("Unauthorized access.");
}

$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_filter) || strtotime($month_filter . '-01') === false) {
    $month_filter = date('Y-m');
}
$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';
$scopeSql = '';
$scopeParams = [];
if ($currentUser['role'] === 'employee') {
    $scopeSql = ' AND p.employee_id = ?';
    $scopeParams[] = intval($currentUser['employee_id'] ?? 0);
}

$stmt = $pdo->prepare("
    SELECT p.*, e.first_name, e.last_name, e.employee_code
    FROM payroll_records p
    JOIN employees e ON p.employee_id = e.id
    WHERE p.payroll_month = ?
    {$scopeSql}
    ORDER BY e.first_name ASC
");
$start_date = $month_filter . '-01';
$end_date = date('Y-m-t', strtotime($start_date));
$stmt->execute(array_merge([$month_filter], $scopeParams));
$payrolls = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Payroll Engine</h1>
        <div class="page-subtitle">Calculates OT using Basic Salary / 240 hours, caps OT at 60 hours, and deducts no-pay leave proportionately.</div>
    </div>
    
    <div style="display: flex; gap: 1rem; align-items: center;">
        <form action="" method="GET" style="margin: 0; display: flex; gap: 0.5rem; align-items: center;">
            <input type="month" name="month" value="<?php echo htmlspecialchars($month_filter); ?>" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
        </form>
        
        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
            <form action="generate.php" method="POST" style="margin: 0;">
                <input type="hidden" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-cogs fa-spin-hover"></i> Generate Payroll for <?php echo date('M Y', strtotime($month_filter . '-01')); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th style="text-align: right;">Base Salary</th>
                    <th style="text-align: right; color: var(--accent-success);">Overtime Earned</th>
                    <th style="text-align: right; color: var(--accent-danger);">Deductions (Unpaid Leave)</th>
                    <th style="text-align: right; font-weight: bold;">Net Salary</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payrolls as $p): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($p['employee_code']); ?></div>
                    </td>
                    <td style="text-align: right; font-family: monospace;"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['base_salary'], 2); ?></td>
                    <td style="text-align: right; font-family: monospace; color: var(--accent-success);">
                        <div>+<?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['overtime_amount'], 2); ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;"><?php echo htmlspecialchars(format_hours_minutes(floatval($p['overtime_hours'] ?? 0))); ?> OT</div>
                    </td>
                    <td style="text-align: right; font-family: monospace; color: var(--accent-danger);">
                        <div>-<?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['deductions'], 2); ?></div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.2rem;"><?php echo number_format(floatval($p['unpaid_days'] ?? 0), 2); ?> unpaid day(s)</div>
                    </td>
                    <td style="text-align: right; font-family: monospace; font-weight: bold; font-size: 1.1rem;"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['net_salary'], 2); ?></td>
                    <td style="text-align: center;">
                        <?php if ($p['status'] === 'Paid'): ?>
                            <span class="status-badge status-active"><i class="fas fa-check-double"></i> Paid</span>
                        <?php elseif ($p['status'] === 'Finalized'): ?>
                            <span class="status-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success);"><i class="fas fa-check"></i> Finalized</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: var(--bg-hover); color: var(--text-secondary);"><i class="fas fa-edit"></i> Draft</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <div style="display: flex; justify-content: center; gap: 0.4rem;">
                            <a href="payslip.php?id=<?php echo $p['id']; ?>" target="_blank" class="action-btn" title="View Payslip"><i class="fas fa-file-invoice-dollar"></i></a>
                            <?php if (in_array($currentUser['role'], ['admin', 'HR']) && $p['status'] !== 'Paid'): ?>
                                <form action="update_status.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo intval($p['id']); ?>">
                                    <input type="hidden" name="status" value="Paid">
                                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
                                    <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Mark as Paid"><i class="fas fa-money-check-alt"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($currentUser['role'], ['admin', 'HR']) && $p['status'] !== 'Draft'): ?>
                                <form action="update_status.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo intval($p['id']); ?>">
                                    <input type="hidden" name="status" value="Draft">
                                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
                                    <button type="submit" class="action-btn" style="color: var(--accent-warning);" title="Reopen Draft"><i class="fas fa-undo-alt"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($payrolls)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 3rem;">
                        <i class="fas fa-file-invoice-dollar" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i><br>
                        No payroll generated for <?php echo date('F Y', strtotime($month_filter . '-01')); ?>.
                        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
                            <br>Click "Generate Payroll" to run the mathematical engine.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
