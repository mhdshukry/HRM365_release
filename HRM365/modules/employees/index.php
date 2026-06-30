<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/avatar.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager'], true)) {
    die("Unauthorized access.");
}

// Fetch real data from the database using PDO
$branch_filter = intval($_GET['branch_id'] ?? 0);
$branches = [];
if (in_array($currentUser['role'], ['admin', 'HR'], true)) {
    $branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
}

$query = "SELECT e.*, b.name AS branch_name FROM employees e LEFT JOIN branches b ON e.branch_id = b.id ";
$params = [];
$where = [];
if ($currentUser['role'] === 'manager') {
    $where[] = "e.department = ?";
    $params[] = $currentUser['department'];
} elseif (in_array($currentUser['role'], ['admin', 'HR'], true) && $branch_filter > 0) {
    $where[] = "e.branch_id = ?";
    $params[] = $branch_filter;
}
if ($where) {
    $query .= "WHERE " . implode(' AND ', $where) . " ";
}
$query .= "ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employees</h1>
        <div class="page-subtitle">Manage your organization's workforce and biometric ID mappings.</div>
    </div>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Add New Employee
    </a>
</div>

<?php if (in_array($currentUser['role'], ['admin', 'HR'], true)): ?>
    <div class="card" style="margin-bottom: 1rem; padding: 1rem;">
        <form action="" method="GET" style="display: flex; gap: 0.75rem; align-items: end; justify-content: flex-end;">
            <div>
                <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.85rem;">Branch</label>
                <select name="branch_id" onchange="this.form.submit()" style="padding: 0.6rem; min-width: 220px; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="0">All Branches</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo intval($branch['id']); ?>" <?php echo $branch_filter === intval($branch['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);"><i class="fas fa-times"></i></a>
        </form>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee / Biometric ID</th>
                    <th>Name</th>
                    <th>NIC Number</th>
                    <th>Branch</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($emp['employee_code']); ?></strong></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <?php echo render_avatar($emp['first_name'], $emp['last_name'], $emp['profile_photo'] ?? null, $emp['employee_code'], 'avatar', 'width: 32px; height: 32px; font-size: 0.85rem;'); ?>
                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($emp['nic_number'] ?: '-'); ?></td>
                    <td><?php echo htmlspecialchars($emp['branch_name'] ?: 'No Branch'); ?></td>
                    <td><?php echo htmlspecialchars($emp['department']); ?></td>
                    <td><span class="text-secondary"><?php echo htmlspecialchars($emp['designation']); ?></span></td>
                    <td>
                        <?php if ($emp['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-leave"><?php echo htmlspecialchars($emp['status']); ?></span>
                            <?php if (!empty($emp['resignation_termination_date'])): ?>
                                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;"><?php echo date('M d, Y', strtotime($emp['resignation_termination_date'])); ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="documents.php?id=<?php echo $emp['id']; ?>" class="action-btn" style="color: var(--accent-primary);" title="Document Vault"><i class="fas fa-folder-open"></i></a>
                            <a href="edit.php?id=<?php echo $emp['id']; ?>" class="action-btn" style="font-size: 1rem;" title="Edit Profile"><i class="fas fa-edit"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
