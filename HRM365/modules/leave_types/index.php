<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'])) {
    die("Unauthorized access.");
}

$stmt = $pdo->query("SELECT * FROM leave_types ORDER BY name ASC");
$leaveTypes = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Types Configuration</h1>
        <div class="page-subtitle">Define policies, quotas, and categories for employee time off.</div>
    </div>
    <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Leave Type
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Leave Type</th>
                    <th>Annual Quota</th>
                    <th>Payment Policy</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaveTypes as $lt): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 16px; height: 16px; border-radius: 50%; background-color: <?php echo htmlspecialchars($lt['color']); ?>;"></div>
                            <div>
                                <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($lt['name']); ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars(substr($lt['description'], 0, 50)); ?>...</div>
                            </div>
                        </div>
                    </td>
                    <td><strong><?php echo intval($lt['max_days_per_year']); ?></strong> Days</td>
                    <td>
                        <?php if ($lt['is_paid']): ?>
                            <span class="status-badge status-active">Paid Leave</span>
                        <?php else: ?>
                            <span class="status-badge status-leave">Unpaid Leave</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($lt['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: var(--bg-hover); color: var(--text-muted);">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
                            <div style="display: flex; gap: 0.5rem;">
                                <form action="toggle_status.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $lt['id']; ?>">
                                    <?php if ($lt['status'] === 'Active'): ?>
                                        <input type="hidden" name="status" value="Inactive">
                                        <button type="submit" class="action-btn" style="color: var(--accent-warning);" title="Deactivate"><i class="fas fa-pause-circle"></i></button>
                                    <?php else: ?>
                                        <input type="hidden" name="status" value="Active">
                                        <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Activate"><i class="fas fa-play-circle"></i></button>
                                    <?php endif; ?>
                                </form>
                                <a href="edit.php?id=<?php echo $lt['id']; ?>" class="action-btn" title="Edit Policy" style="color: var(--text-muted);"><i class="fas fa-edit"></i></a>
                                <form action="delete.php" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this Leave Type?');">
                                    <input type="hidden" name="id" value="<?php echo $lt['id']; ?>">
                                    <button type="submit" class="action-btn" style="color: var(--accent-danger);" title="Delete Policy"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">View only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
