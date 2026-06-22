<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$stmt = $pdo->query("
    SELECT u.*, e.employee_code, e.first_name, e.last_name
    FROM users u
    LEFT JOIN employees e ON u.employee_id = e.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">System Users</h1>
        <div class="page-subtitle">Manage administrative and employee login credentials.</div>
    </div>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Add User
    </a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
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
                    <td>
                        <?php if ($u['employee_id']): ?>
                            <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['employee_code']); ?></div>
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
