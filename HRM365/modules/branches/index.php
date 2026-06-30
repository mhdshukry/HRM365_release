<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Only Admin and HR can view branches
if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$stmt = $pdo->query("
    SELECT b.*,
           (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.id) AS employee_count,
           (SELECT COUNT(*) FROM biometric_punches bp WHERE bp.terminal_sn = b.biometric_terminal_sn AND DATE(bp.punch_time) = CURDATE()) AS today_punch_count
    FROM branches b
    ORDER BY b.created_at DESC
");
$branches = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Branches</h1>
        <div class="page-subtitle">Manage company office locations and regional hubs.</div>
    </div>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Branch
    </a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Branch Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Machine SN</th>
                    <th>Employees</th>
                    <th>Today Punches</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($b['name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($b['address']); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($b['email']); ?></td>
                    <td><?php echo htmlspecialchars($b['phone']); ?></td>
                    <td><code style="background: var(--bg-hover); padding: 0.2rem 0.4rem; border-radius: 4px;"><?php echo htmlspecialchars($b['biometric_terminal_sn'] ?: 'Not set'); ?></code></td>
                    <td><?php echo intval($b['employee_count']); ?></td>
                    <td><?php echo intval($b['today_punch_count']); ?></td>
                    <td>
                        <?php if ($b['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-leave">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="view.php?id=<?php echo $b['id']; ?>" class="action-btn" style="color: var(--accent-primary);" title="Branch Dashboard"><i class="fas fa-chart-line"></i></a>
                            <!-- Toggle Status Form -->
                            <form action="toggle_status.php" method="POST" style="margin: 0;">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <?php if ($b['status'] === 'Active'): ?>
                                    <input type="hidden" name="status" value="Inactive">
                                    <button type="submit" class="action-btn" style="color: var(--accent-danger);" title="Deactivate"><i class="fas fa-ban"></i></button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="Active">
                                    <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Activate"><i class="fas fa-check-circle"></i></button>
                                <?php endif; ?>
                            </form>
                            <a href="edit.php?id=<?php echo $b['id']; ?>" class="action-btn" title="Edit Branch"><i class="fas fa-edit"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($branches)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">No branches have been added yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
