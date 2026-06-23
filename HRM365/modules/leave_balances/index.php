<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/leave_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'])) {
    die("Unauthorized access.");
}

$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$employee_filter = trim($_GET['employee'] ?? '');
$leave_type_filter = intval($_GET['leave_type_id'] ?? 0);
$department_filter = trim($_GET['department'] ?? 'all');
$balance_filter = $_GET['balance_status'] ?? 'all';

if (!in_array($balance_filter, ['all', 'available', 'zero', 'negative'], true)) {
    $balance_filter = 'all';
}

$scopeSql = '';
$scopeParams = [];
if ($currentUser['role'] === 'employee') {
    $scopeSql = ' AND e.id = ?';
    $scopeParams[] = intval($currentUser['employee_id'] ?? 0);
} elseif ($currentUser['role'] === 'manager') {
    $scopeSql = ' AND e.department = ?';
    $scopeParams[] = $currentUser['department'] ?? '';
}

$filterSql = '';
$filterParams = [];
if ($employee_filter !== '') {
    $filterSql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ?)";
    $needle = '%' . $employee_filter . '%';
    $filterParams[] = $needle;
    $filterParams[] = $needle;
    $filterParams[] = $needle;
}

if ($leave_type_filter > 0) {
    $filterSql .= " AND t.id = ?";
    $filterParams[] = $leave_type_filter;
}

if (in_array($currentUser['role'], ['admin', 'HR'], true) && $department_filter !== 'all' && $department_filter !== '') {
    $filterSql .= " AND e.department = ?";
    $filterParams[] = $department_filter;
}

$leaveTypes = $pdo->query("SELECT id, name FROM leave_types WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$departments = [];
if (in_array($currentUser['role'], ['admin', 'HR'], true)) {
    $departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
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
    {$filterSql}
    ORDER BY e.first_name ASC, t.name ASC
");
$stmt->execute(array_merge([$year_filter], $scopeParams, $filterParams));
$balances = $stmt->fetchAll();

$employeeLedgers = [];
foreach ($balances as $balance) {
    $employeeKey = (string) $balance['employee_id'];
    if (!isset($employeeLedgers[$employeeKey])) {
        $employeeLedgers[$employeeKey] = [
            'employee_name' => $balance['first_name'] . ' ' . $balance['last_name'],
            'employee_code' => $balance['employee_code'],
            'items' => [],
        ];
    }

    $totalAllocated = round(
        floatval($balance['allocated_days']) + floatval($balance['carried_forward']) + floatval($balance['manual_adjustment']),
        2
    );
    $remaining = round($totalAllocated - floatval($balance['used_days']), 2);
    $balance['total_allocated'] = $totalAllocated;
    $balance['remaining_days'] = $remaining;

    if ($balance_filter === 'available' && $remaining <= 0) {
        continue;
    }

    if ($balance_filter === 'zero' && abs($remaining) > 0.001) {
        continue;
    }

    if ($balance_filter === 'negative' && $remaining >= 0) {
        continue;
    }

    $employeeLedgers[$employeeKey]['items'][] = $balance;
}

$employeeLedgers = array_filter($employeeLedgers, function (array $ledger): bool {
    return !empty($ledger['items']);
});

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
    </div>
</div>

<div class="card leave-ledger-filter-card">
    <form action="" method="GET" class="leave-ledger-filter-form">
        <div>
            <label>Fiscal Year</label>
            <select name="year">
                <?php for($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y === $year_filter ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <?php if ($currentUser['role'] !== 'employee'): ?>
            <div>
                <label>Employee</label>
                <input type="text" name="employee" value="<?php echo htmlspecialchars($employee_filter); ?>" placeholder="Name or code">
            </div>
        <?php endif; ?>

        <?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
            <div>
                <label>Department</label>
                <select name="department">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo htmlspecialchars($department); ?>" <?php echo $department_filter === $department ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($department); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div>
            <label>Leave Type</label>
            <select name="leave_type_id">
                <option value="0">All Leave Types</option>
                <?php foreach ($leaveTypes as $leaveType): ?>
                    <option value="<?php echo intval($leaveType['id']); ?>" <?php echo $leave_type_filter === intval($leaveType['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($leaveType['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Balance</label>
            <select name="balance_status">
                <option value="all" <?php echo $balance_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="available" <?php echo $balance_filter === 'available' ? 'selected' : ''; ?>>Available Only</option>
                <option value="zero" <?php echo $balance_filter === 'zero' ? 'selected' : ''; ?>>Zero Balance</option>
                <option value="negative" <?php echo $balance_filter === 'negative' ? 'selected' : ''; ?>>Negative Balance</option>
            </select>
        </div>

        <div class="leave-ledger-filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="index.php?year=<?php echo intval($year_filter); ?>" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">Reset</a>
        </div>
    </form>
</div>

<style>
    .leave-ledger-filter-card {
        margin-bottom: 1.25rem;
        padding: 1rem;
    }

    .leave-ledger-filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 0.85rem;
        align-items: end;
    }

    .leave-ledger-filter-form label {
        display: block;
        color: var(--text-secondary);
        font-size: 0.78rem;
        font-weight: 800;
        margin-bottom: 0.4rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .leave-ledger-filter-form input,
    .leave-ledger-filter-form select {
        width: 100%;
        padding: 0.65rem 0.75rem;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        background: var(--bg-secondary);
        color: var(--text-primary);
        outline: none;
    }

    .leave-ledger-filter-actions {
        display: flex;
        gap: 0.5rem;
    }

    .leave-ledger-stack {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .leave-ledger-card {
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }

    .leave-ledger-person {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border-color);
        background: var(--bg-hover);
    }

    .leave-ledger-name {
        color: var(--text-primary);
        font-weight: 800;
    }

    .leave-ledger-code {
        color: var(--text-muted);
        font-size: 0.76rem;
        margin-top: 0.15rem;
    }

    .leave-ledger-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 0.8rem;
        padding: 1rem;
    }

    .leave-ledger-item {
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 0.85rem;
        min-width: 0;
    }

    .leave-ledger-type {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .leave-ledger-type-name {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        color: var(--text-primary);
        font-weight: 800;
        min-width: 0;
    }

    .leave-ledger-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .leave-ledger-policy {
        color: var(--text-muted);
        font-size: 0.72rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .leave-ledger-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.5rem;
    }

    .leave-ledger-metric {
        background: var(--bg-main);
        border-radius: var(--radius-sm);
        padding: 0.55rem 0.45rem;
        text-align: center;
    }

    .leave-ledger-label {
        color: var(--text-muted);
        font-size: 0.66rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .leave-ledger-value {
        color: var(--text-primary);
        font-weight: 800;
        margin-top: 0.2rem;
    }

    .leave-ledger-value.remaining {
        color: var(--accent-primary);
    }

    .leave-ledger-adjust {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        color: var(--text-secondary);
        background: var(--bg-main);
        text-decoration: none;
        flex-shrink: 0;
    }

    .leave-ledger-adjust:hover {
        color: var(--accent-primary);
        background: #eff6ff;
    }

    .leave-ledger-empty {
        background: #fff;
        border: 1px dashed var(--border-color);
        border-radius: var(--radius-md);
        color: var(--text-muted);
        padding: 2rem;
        text-align: center;
    }

    @media (max-width: 640px) {
        .leave-ledger-filter-actions,
        .leave-ledger-filter-actions .btn {
            width: 100%;
        }

        .leave-ledger-filter-actions {
            flex-direction: column;
        }

        .leave-ledger-filter-actions .btn {
            justify-content: center;
        }

        .leave-ledger-person {
            align-items: flex-start;
            flex-direction: column;
        }

        .leave-ledger-grid {
            grid-template-columns: 1fr;
            padding: 0.85rem;
        }

        .leave-ledger-metrics {
            grid-template-columns: 1fr 1fr;
        }

        .leave-ledger-metric:last-child {
            grid-column: 1 / -1;
        }
    }
</style>

<?php if (!empty($employeeLedgers)): ?>
    <div class="leave-ledger-stack">
        <?php foreach ($employeeLedgers as $ledger): ?>
            <section class="leave-ledger-card">
                <div class="leave-ledger-person">
                    <div>
                        <div class="leave-ledger-name"><?php echo htmlspecialchars($ledger['employee_name']); ?></div>
                        <div class="leave-ledger-code"><?php echo htmlspecialchars($ledger['employee_code']); ?></div>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.78rem; font-weight: 700;">
                        <?php echo count($ledger['items']); ?> leave ledger item(s)
                    </div>
                </div>
                <div class="leave-ledger-grid">
                    <?php foreach ($ledger['items'] as $item): ?>
                        <div class="leave-ledger-item">
                            <div class="leave-ledger-type">
                                <div style="min-width: 0;">
                                    <div class="leave-ledger-type-name">
                                        <span class="leave-ledger-dot" style="background: <?php echo htmlspecialchars($item['color']); ?>;"></span>
                                        <span><?php echo htmlspecialchars($item['leave_type_name']); ?></span>
                                    </div>
                                    <?php if ($item['policy_name']): ?>
                                        <div class="leave-ledger-policy"><?php echo htmlspecialchars($item['policy_name']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
                                    <a href="adjust.php?id=<?php echo intval($item['id']); ?>" class="leave-ledger-adjust" title="Manual Adjustment">
                                        <i class="fas fa-balance-scale"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="leave-ledger-metrics">
                                <div class="leave-ledger-metric">
                                    <div class="leave-ledger-label">Allocated</div>
                                    <div class="leave-ledger-value"><?php echo htmlspecialchars(format_leave_days(floatval($item['total_allocated']))); ?></div>
                                </div>
                                <div class="leave-ledger-metric">
                                    <div class="leave-ledger-label">Used</div>
                                    <div class="leave-ledger-value"><?php echo htmlspecialchars(format_leave_days(floatval($item['used_days']))); ?></div>
                                </div>
                                <div class="leave-ledger-metric">
                                    <div class="leave-ledger-label">Remaining</div>
                                    <div class="leave-ledger-value remaining"><?php echo htmlspecialchars(format_leave_days(floatval($item['remaining_days']))); ?></div>
                                </div>
                            </div>
                            <?php if (abs(floatval($item['manual_adjustment'])) > 0.001 || floatval($item['carried_forward']) > 0): ?>
                                <div style="margin-top: 0.65rem; color: var(--text-muted); font-size: 0.74rem;">
                                    Rollover <?php echo htmlspecialchars(format_leave_days(floatval($item['carried_forward']))); ?>,
                                    Adjustment <?php echo htmlspecialchars(format_leave_days(floatval($item['manual_adjustment']))); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="leave-ledger-empty">
        No leave ledgers found for <?php echo intval($year_filter); ?>.
    </div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
