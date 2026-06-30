<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'employee'], true)) {
    die("Unauthorized access.");
}

$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';
$month_filter = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month']) ? $_GET['month'] : date('Y-m');
$status_filter = $_GET['status'] ?? 'All';
if (!in_array($status_filter, ['All', 'Pending', 'Approved', 'Paid', 'Cancelled'], true)) {
    $status_filter = 'All';
}
$employee_filter = intval($_GET['employee_id'] ?? 0);
$branch_filter = intval($_GET['branch_id'] ?? 0);

$branches = [];
$employees = [];
$where = ["a.deduction_month = ?"];
$params = [$month_filter];

if ($currentUser['role'] === 'employee') {
    $where[] = "a.employee_id = ?";
    $params[] = intval($currentUser['employee_id'] ?? 0);
} else {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
    $employeeSql = "SELECT id, employee_code, first_name, last_name FROM employees WHERE status = 'Active'";
    $employeeParams = [];
    if ($branch_filter > 0) {
        $employeeSql .= " AND branch_id = ?";
        $employeeParams[] = $branch_filter;
    }
    $employeeSql .= " ORDER BY first_name ASC, last_name ASC";
    $employeeStmt = $pdo->prepare($employeeSql);
    $employeeStmt->execute($employeeParams);
    $employees = $employeeStmt->fetchAll();

    if ($branch_filter > 0) {
        $where[] = "e.branch_id = ?";
        $params[] = $branch_filter;
    }
    if ($employee_filter > 0) {
        $where[] = "a.employee_id = ?";
        $params[] = $employee_filter;
    }
}

if ($status_filter !== 'All') {
    $where[] = "a.status = ?";
    $params[] = $status_filter;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);
$stmt = $pdo->prepare("
    SELECT a.*, e.first_name, e.last_name, e.employee_code, b.name AS branch_name,
           cu.username AS created_by_name, au.username AS approved_by_name, pu.username AS paid_by_name
    FROM advance_payments a
    JOIN employees e ON e.id = a.employee_id
    LEFT JOIN branches b ON b.id = e.branch_id
    LEFT JOIN users cu ON cu.id = a.created_by
    LEFT JOIN users au ON au.id = a.approved_by
    LEFT JOIN users pu ON pu.id = a.paid_by
    {$whereSql}
    ORDER BY a.payment_date DESC, a.created_at DESC
");
$stmt->execute($params);
$advances = $stmt->fetchAll();

$totals = ['Pending' => 0.00, 'Approved' => 0.00, 'Paid' => 0.00, 'Cancelled' => 0.00];
foreach ($advances as $advance) {
    $totals[$advance['status']] = ($totals[$advance['status']] ?? 0) + floatval($advance['amount']);
}

function advance_page_url(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

$perPage = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$totalRows = count($advances);
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$advancePageRows = array_slice($advances, ($page - 1) * $perPage, $perPage);

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Advance Payments</h1>
        <div class="page-subtitle">Track employee salary advances and deduct paid advances from the selected payroll month.</div>
    </div>
    <div class="page-header-actions">
        <form action="" method="GET" class="page-filter-form">
            <input type="month" name="month" value="<?php echo htmlspecialchars($month_filter); ?>" onchange="this.form.submit()" class="page-control">
            <select name="status" onchange="this.form.submit()" class="page-control">
                <?php foreach (['All', 'Pending', 'Approved', 'Paid', 'Cancelled'] as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
                <select name="branch_id" onchange="this.form.submit()" class="page-control">
                    <option value="0">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo intval($branch['id']); ?>" <?php echo $branch_filter === intval($branch['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="employee_id" onchange="this.form.submit()" class="page-control">
                    <option value="0">All Employees</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo intval($employee['id']); ?>" <?php echo $employee_filter === intval($employee['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (!empty($_GET['success'])): ?>
    <div class="card" style="margin-bottom: 1rem; border-left: 4px solid var(--accent-success);">
        <strong style="color: var(--accent-success);">Success.</strong>
        <span style="color: var(--text-secondary); margin-left: 0.35rem;">Advance payment updated.</span>
    </div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
    <div class="card" style="margin-bottom: 1rem; border-left: 4px solid var(--accent-danger);">
        <strong style="color: var(--accent-danger);">Could not save advance.</strong>
        <span style="color: var(--text-secondary); margin-left: 0.35rem;"><?php echo htmlspecialchars($_GET['error']); ?></span>
    </div>
<?php endif; ?>

<div class="payroll-summary-grid">
    <div class="payroll-summary-item"><span>Rows</span><strong><?php echo number_format($totalRows); ?></strong></div>
    <div class="payroll-summary-item"><span>Pending</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($totals['Pending'], 2); ?></strong></div>
    <div class="payroll-summary-item"><span>Approved</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($totals['Approved'], 2); ?></strong></div>
    <div class="payroll-summary-item payroll-summary-net"><span>Payroll Deductible</span><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format($totals['Paid'], 2); ?></strong></div>
</div>

<?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
    <div class="card" style="margin-bottom: 1rem;">
        <h3 style="margin-top: 0;">Add Advance Payment</h3>
        <form action="save.php" method="POST" class="page-filter-form" style="align-items: end;">
            <input type="hidden" name="deduction_month" value="<?php echo htmlspecialchars($month_filter); ?>">
            <select name="employee_id" required class="page-control">
                <option value="">Select Employee</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo intval($employee['id']); ?>"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['employee_code'] . ')'); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required class="page-control">
            <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required class="page-control">
            <select name="status" class="page-control">
                <option value="Paid">Paid - deduct in payroll</option>
                <option value="Approved">Approved - not deducted yet</option>
                <option value="Pending">Pending</option>
            </select>
            <input type="text" name="reason" placeholder="Reason / note" class="page-control">
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Advance</button>
        </form>
    </div>
<?php endif; ?>

<div class="card payroll-card">
    <div class="table-container">
        <table class="table payroll-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Payment Date</th>
                    <th>Deduction Month</th>
                    <th class="payroll-money-col">Amount</th>
                    <th>Status</th>
                    <th>Reason</th>
                    <th class="payroll-center-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($advancePageRows as $advance): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($advance['first_name'] . ' ' . $advance['last_name']); ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($advance['employee_code']); ?> · <?php echo htmlspecialchars($advance['branch_name'] ?? 'No Branch'); ?></div>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($advance['payment_date'])); ?></td>
                        <td><?php echo date('M Y', strtotime($advance['deduction_month'] . '-01')); ?></td>
                        <td class="payroll-money-col"><strong><?php echo htmlspecialchars($currency); ?> <?php echo number_format(floatval($advance['amount']), 2); ?></strong></td>
                        <td><span class="status-badge"><?php echo htmlspecialchars($advance['status']); ?></span></td>
                        <td><?php echo htmlspecialchars($advance['reason'] ?: '-'); ?></td>
                        <td class="payroll-center-col">
                            <?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
                                <div class="payroll-actions">
                                    <?php foreach (['Approved' => 'fa-check', 'Paid' => 'fa-money-check-alt', 'Cancelled' => 'fa-ban'] as $status => $icon): ?>
                                        <?php if ($advance['status'] !== $status): ?>
                                            <form action="update_status.php" method="POST" class="payroll-action-form">
                                                <input type="hidden" name="id" value="<?php echo intval($advance['id']); ?>">
                                                <input type="hidden" name="status" value="<?php echo $status; ?>">
                                                <input type="hidden" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
                                                <button type="submit" class="action-btn" title="Mark <?php echo $status; ?>"><i class="fas <?php echo $icon; ?>"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($advances)): ?>
                    <tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No advance payments for this filter.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($advances)): ?>
        <div class="table-pagination">
            <div class="table-pagination-info">Showing <?php echo (($page - 1) * $perPage) + 1; ?>-<?php echo min($page * $perPage, $totalRows); ?> of <?php echo $totalRows; ?></div>
            <div class="table-pagination-actions">
                <a class="table-page-button <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(advance_page_url($page - 1)); ?>"><i class="fas fa-chevron-left"></i></a>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a class="table-page-button <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(advance_page_url($i)); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <a class="table-page-button <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars(advance_page_url($page + 1)); ?>"><i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
