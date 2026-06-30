<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';
require_once '../../includes/payroll_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'employee'])) {
    die("Unauthorized access.");
}

$month_filter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_filter) || strtotime($month_filter . '-01') === false) {
    $month_filter = date('Y-m');
}
$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';
$payrollFeatures = payroll_feature_settings($pdo);
$branch_filter = intval($_GET['branch_id'] ?? 0);
$employee_filter = intval($_GET['employee_id'] ?? 0);
$employee_search = trim($_GET['employee_search'] ?? '');
$branches = [];
$employeeOptions = [];
$scopeSql = '';
$scopeParams = [];
if ($currentUser['role'] === 'employee') {
    $scopeSql = ' AND p.employee_id = ?';
    $scopeParams[] = intval($currentUser['employee_id'] ?? 0);
} elseif (in_array($currentUser['role'], ['admin', 'HR'], true)) {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
    $employeeOptionSql = "SELECT id, first_name, last_name, employee_code FROM employees WHERE status = 'Active'";
    $employeeOptionParams = [];
    if ($branch_filter > 0) {
        $employeeOptionSql .= " AND branch_id = ?";
        $employeeOptionParams[] = $branch_filter;
    }
    $employeeOptionSql .= " ORDER BY first_name ASC, last_name ASC";
    $employeeOptionStmt = $pdo->prepare($employeeOptionSql);
    $employeeOptionStmt->execute($employeeOptionParams);
    $employeeOptions = $employeeOptionStmt->fetchAll();

    if ($branch_filter > 0) {
        $scopeSql = ' AND e.branch_id = ?';
        $scopeParams[] = $branch_filter;
    }
    if ($employee_filter > 0) {
        $scopeSql .= ' AND e.id = ?';
        $scopeParams[] = $employee_filter;
    }
    if ($employee_search !== '') {
        $scopeSql .= ' AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)';
        $searchLike = '%' . $employee_search . '%';
        $scopeParams[] = $searchLike;
        $scopeParams[] = $searchLike;
        $scopeParams[] = $searchLike;
    }
}

$stmt = $pdo->prepare("
    SELECT p.*, e.first_name, e.last_name, e.employee_code, b.name AS branch_name
    FROM payroll_records p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN branches b ON e.branch_id = b.id
    WHERE p.payroll_month = ?
    {$scopeSql}
    ORDER BY e.first_name ASC
");
$start_date = $month_filter . '-01';
$end_date = date('Y-m-t', strtotime($start_date));
$stmt->execute(array_merge([$month_filter], $scopeParams));
$payrolls = $stmt->fetchAll();
$payrollTotals = [
    'base_salary' => 0.00,
    'overtime_amount' => 0.00,
    'deductions' => 0.00,
    'advance_amount' => 0.00,
    'epf_employee_amount' => 0.00,
    'epf_employer_amount' => 0.00,
    'etf_employer_amount' => 0.00,
    'net_salary' => 0.00,
];
foreach ($payrolls as $payrollRow) {
    foreach ($payrollTotals as $key => $value) {
        $payrollTotals[$key] += floatval($payrollRow[$key] ?? 0);
    }
}
$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalRows = count($payrolls);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$payrollPageRows = array_slice($payrolls, ($page - 1) * $perPage, $perPage);
$payrollTableColumns = 7
    + ($payrollFeatures['payroll_enable_overtime'] ? 1 : 0)
    + (($payrollFeatures['payroll_enable_epf'] || $payrollFeatures['payroll_enable_etf']) ? 1 : 0);

function payroll_page_url(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Payroll Engine</h1>
        <div class="page-subtitle">
            Calculates salaries using enabled company payroll rules:
            <?php echo $payrollFeatures['payroll_enable_overtime'] ? 'OT on' : 'OT off'; ?>,
            <?php echo $payrollFeatures['payroll_enable_epf'] ? 'EPF on' : 'EPF off'; ?>,
            <?php echo $payrollFeatures['payroll_enable_etf'] ? 'ETF on' : 'ETF off'; ?>.
        </div>
    </div>
    
    <div class="page-header-actions">
        <form action="" method="GET" class="page-filter-form">
            <input type="month" name="month" value="<?php echo htmlspecialchars($month_filter); ?>" onchange="this.form.submit()" class="page-control">
            <?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
                <select name="branch_id" onchange="this.form.submit()" class="page-control">
                    <option value="0">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo intval($branch['id']); ?>" <?php echo $branch_filter === intval($branch['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="employee_id" onchange="this.form.submit()" class="page-control">
                    <option value="0">All Employees</option>
                    <?php foreach ($employeeOptions as $employee): ?>
                        <option value="<?php echo intval($employee['id']); ?>" <?php echo $employee_filter === intval($employee['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="search" name="employee_search" value="<?php echo htmlspecialchars($employee_search); ?>" placeholder="Search employee" class="page-control">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Search
                </button>
            <?php endif; ?>
        </form>
        
        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
            <form action="generate.php" method="POST" class="page-action-form">
                <input type="hidden" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-cogs fa-spin-hover"></i> Generate Payroll for <?php echo date('M Y', strtotime($month_filter . '-01')); ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($payrolls)): ?>
    <div class="payroll-summary-grid">
        <div class="payroll-summary-item">
            <span>Employees</span>
            <strong><?php echo number_format($totalRows); ?></strong>
        </div>
        <div class="payroll-summary-item">
            <span>Base Salary</span>
            <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($payrollTotals['base_salary'], 2); ?></strong>
        </div>
        <?php if ($payrollFeatures['payroll_enable_overtime']): ?>
            <div class="payroll-summary-item">
                <span>Overtime</span>
                <strong class="payroll-positive">+<?php echo htmlspecialchars($currency); ?> <?php echo number_format($payrollTotals['overtime_amount'], 2); ?></strong>
            </div>
        <?php endif; ?>
        <div class="payroll-summary-item">
            <span>Unpaid Deduction</span>
            <strong class="payroll-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format($payrollTotals['deductions'], 2); ?></strong>
        </div>
        <div class="payroll-summary-item">
            <span>Advance Deduction</span>
            <strong class="payroll-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format($payrollTotals['advance_amount'], 2); ?></strong>
        </div>
        <?php if ($payrollFeatures['payroll_enable_epf'] || $payrollFeatures['payroll_enable_etf']): ?>
            <div class="payroll-summary-item">
                <span>Employer Contributions</span>
                <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($payrollTotals['epf_employer_amount'] + $payrollTotals['etf_employer_amount'], 2); ?></strong>
            </div>
        <?php endif; ?>
        <div class="payroll-summary-item payroll-summary-net">
            <span>Net Payable</span>
            <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($payrollTotals['net_salary'], 2); ?></strong>
        </div>
    </div>
<?php endif; ?>

<div class="card payroll-card">
    <div class="table-container">
        <table class="table payroll-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="payroll-money-col">Base</th>
                    <?php if ($payrollFeatures['payroll_enable_overtime']): ?><th class="payroll-money-col">Overtime</th><?php endif; ?>
                    <th class="payroll-money-col">Unpaid</th>
                    <th class="payroll-money-col">Advance</th>
                    <?php if ($payrollFeatures['payroll_enable_epf'] || $payrollFeatures['payroll_enable_etf']): ?><th class="payroll-money-col">EPF / ETF</th><?php endif; ?>
                    <th class="payroll-money-col">Net Salary</th>
                    <th class="payroll-center-col">Status</th>
                    <th class="payroll-center-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payrollPageRows as $p): ?>
                <tr>
                    <td class="payroll-employee-cell">
                        <div class="payroll-employee-name"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                        <div class="payroll-employee-meta">
                            <span><?php echo htmlspecialchars($p['employee_code']); ?></span>
                            <span><?php echo htmlspecialchars($p['branch_name'] ?? 'No Branch'); ?></span>
                        </div>
                    </td>
                    <td class="payroll-money-col">
                        <div class="payroll-money-block">
                            <span class="payroll-line-label">Basic</span>
                            <div class="payroll-amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['base_salary'], 2); ?></div>
                        </div>
                    </td>
                    <?php if ($payrollFeatures['payroll_enable_overtime']): ?>
                        <td class="payroll-money-col">
                            <div class="payroll-money-block payroll-money-block-positive">
                                <span class="payroll-line-label">Earned</span>
                                <div class="payroll-amount payroll-positive">+<?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['overtime_amount'], 2); ?></div>
                                <div class="payroll-chip"><?php echo htmlspecialchars(format_hours_minutes(floatval($p['overtime_hours'] ?? 0))); ?> OT</div>
                            </div>
                        </td>
                    <?php endif; ?>
                    <td class="payroll-money-col">
                        <div class="payroll-money-block payroll-money-block-danger">
                            <span class="payroll-line-label">No-pay</span>
                            <div class="payroll-amount payroll-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['deductions'], 2); ?></div>
                            <div class="payroll-chip payroll-chip-danger"><?php echo number_format(floatval($p['unpaid_days'] ?? 0), 2); ?> day(s)</div>
                        </div>
                    </td>
                    <td class="payroll-money-col">
                        <div class="payroll-money-block payroll-money-block-danger">
                            <span class="payroll-line-label">Advance</span>
                            <div class="payroll-amount payroll-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($p['advance_amount'] ?? 0), 2); ?></div>
                            <div class="payroll-chip payroll-chip-danger">Salary advance</div>
                        </div>
                    </td>
                    <?php if ($payrollFeatures['payroll_enable_epf'] || $payrollFeatures['payroll_enable_etf']): ?>
                        <td class="payroll-money-col payroll-statutory-cell">
                            <div class="payroll-statutory-stack">
                                <?php if ($payrollFeatures['payroll_enable_epf']): ?>
                                    <div class="payroll-statutory-row">
                                        <span>Employee EPF</span>
                                        <strong class="payroll-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($p['epf_employee_amount'] ?? 0), 2); ?></strong>
                                    </div>
                                    <div class="payroll-statutory-row">
                                        <span>Employer EPF</span>
                                        <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($p['epf_employer_amount'] ?? 0), 2); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($payrollFeatures['payroll_enable_etf']): ?>
                                    <div class="payroll-statutory-row">
                                        <span>ETF</span>
                                        <strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($p['etf_employer_amount'] ?? 0), 2); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    <?php endif; ?>
                    <td class="payroll-money-col">
                        <div class="payroll-net-block">
                            <span>Take-home</span>
                            <div class="payroll-net"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($p['net_salary'], 2); ?></div>
                        </div>
                    </td>
                    <td class="payroll-center-col">
                        <?php if ($p['status'] === 'Paid'): ?>
                            <span class="status-badge status-active"><i class="fas fa-check-double"></i> Paid</span>
                        <?php elseif ($p['status'] === 'Finalized'): ?>
                            <span class="status-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success);"><i class="fas fa-check"></i> Finalized</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: var(--bg-hover); color: var(--text-secondary);"><i class="fas fa-edit"></i> Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="payroll-center-col">
                        <div class="payroll-actions">
                            <a href="payslip.php?id=<?php echo $p['id']; ?>" target="_blank" class="action-btn" title="View Payslip"><i class="fas fa-file-invoice-dollar"></i></a>
                            <?php if (in_array($currentUser['role'], ['admin', 'HR']) && $p['status'] !== 'Paid'): ?>
                                <form action="update_status.php" method="POST" class="payroll-action-form">
                                    <input type="hidden" name="id" value="<?php echo intval($p['id']); ?>">
                                    <input type="hidden" name="status" value="Paid">
                                    <input type="hidden" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
                                    <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Mark as Paid"><i class="fas fa-money-check-alt"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if (in_array($currentUser['role'], ['admin', 'HR']) && $p['status'] !== 'Draft'): ?>
                                <form action="update_status.php" method="POST" class="payroll-action-form">
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
                    <td colspan="<?php echo $payrollTableColumns; ?>" style="text-align: center; color: var(--text-muted); padding: 3rem;">
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
    <?php if (!empty($payrolls)): ?>
        <div class="table-pagination">
            <div class="table-pagination-info">
                Showing <?php echo (($page - 1) * $perPage) + 1; ?>-<?php echo min($page * $perPage, $totalRows); ?> of <?php echo $totalRows; ?>
            </div>
            <div class="table-pagination-actions">
                <a class="table-page-button <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(payroll_page_url($page - 1)); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="table-page-button <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(payroll_page_url($i)); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a class="table-page-button <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(payroll_page_url($page + 1)); ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
