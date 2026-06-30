<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$branch_filter = intval($_GET['branch_id'] ?? 0);
$branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$whereSql = '';
$params = [];
if ($branch_filter > 0) {
    $whereSql = 'WHERE e.branch_id = ?';
    $params[] = $branch_filter;
}

$stmt = $pdo->prepare("
    SELECT u.*, e.employee_code, e.first_name, e.last_name, b.name AS branch_name
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    LEFT JOIN branches b ON e.branch_id = b.id
    {$whereSql}
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">System Users</h1>
        <div class="page-subtitle">Manage administrative and employee login credentials.</div>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: flex-end;">
        <?php if ($currentUser['role'] === 'admin'): ?>
            <a href="reset_system.php" class="btn" style="background: rgba(239, 68, 68, 0.12); color: var(--accent-danger);">
                <i class="fas fa-trash-alt"></i> Reset People Data
            </a>
        <?php endif; ?>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add User
        </a>
    </div>
</div>

<?php if (!empty($_GET['sms'])): ?>
    <div class="card" style="margin-bottom: 1rem; border-left: 4px solid <?php echo $_GET['sms'] === 'sent' ? 'var(--accent-success)' : 'var(--accent-warning)'; ?>;">
        <?php if ($_GET['sms'] === 'sent'): ?>
            <strong style="color: var(--accent-success);">Credential SMS sent.</strong>
            <span style="color: var(--text-secondary); margin-left: 0.35rem;">The username and temporary password were sent to the user's phone.</span>
        <?php elseif ($_GET['sms'] === 'no_phone'): ?>
            <strong style="color: var(--accent-warning);">Credential SMS not sent.</strong>
            <span style="color: var(--text-secondary); margin-left: 0.35rem;">No phone number was available for this user.</span>
        <?php else: ?>
            <strong style="color: var(--accent-warning);">Credential SMS failed.</strong>
            <span style="color: var(--text-secondary); margin-left: 0.35rem;">
                <?php echo htmlspecialchars($_GET['sms_error'] ?? 'Check SMS settings and server internet access.'); ?>
            </span>
        <?php endif; ?>
    </div>
<?php endif; ?>

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

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Phone</th>
                    <th>Employee Mapping</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($u['full_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                    <td>
                        <?php if ($u['employee_id']): ?>
                            <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['employee_code']); ?></div>
                            <div style="font-size: 0.72rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['branch_name'] ?? 'No Branch'); ?></div>
                        <?php else: ?>
                            <span style="color: var(--accent-warning); font-size: 0.85rem;"><i class="fas fa-unlink"></i> Not Linked</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="status-badge" style="text-transform: uppercase;"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td>
                        <?php if ($u['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-leave">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <!-- Toggle Status Form -->
                            <form action="toggle_status.php" method="POST" style="margin: 0;">
                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                <?php if ($u['status'] === 'Active'): ?>
                                    <input type="hidden" name="status" value="Inactive">
                                    <button type="submit" class="action-btn" style="color: var(--accent-danger);" title="Deactivate"><i class="fas fa-ban"></i></button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="Active">
                                    <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Activate"><i class="fas fa-check-circle"></i></button>
                                <?php endif; ?>
                            </form>
                            <a href="edit.php?id=<?php echo $u['id']; ?>" class="action-btn" title="Edit User"><i class="fas fa-edit"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
