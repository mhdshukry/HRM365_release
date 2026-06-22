<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'])) {
    die("Unauthorized access.");
}

$stmt = $pdo->query("
    SELECT p.*, t.name as leave_type_name, t.color 
    FROM leave_policies p 
    JOIN leave_types t ON p.leave_type_id = t.id 
    ORDER BY p.name ASC
");
$policies = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Policies</h1>
        <div class="page-subtitle">Configure accrual logic and constraints for specific leave types.</div>
    </div>
    <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Leave Policy
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Policy Name</th>
                    <th>Target Leave Type</th>
                    <th>Accrual Engine</th>
                    <th>Application Limits</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($policies as $p): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars(substr($p['description'], 0, 50)); ?>...</div>
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo htmlspecialchars($p['color']); ?>;"></div>
                            <span style="font-weight: 500;"><?php echo htmlspecialchars($p['leave_type_name']); ?></span>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 0.9rem;"><strong><?php echo floatval($p['accrual_rate']); ?></strong> days / <?php echo htmlspecialchars($p['accrual_type']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Max Roll-over: <?php echo intval($p['carry_forward_limit']); ?> days</div>
                    </td>
                    <td>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Min: <strong><?php echo intval($p['min_days_per_application']); ?></strong> day(s)</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Max: <strong><?php echo intval($p['max_days_per_application']); ?></strong> day(s)</div>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: var(--bg-hover); color: var(--text-muted);">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
                            <div style="display: flex; gap: 0.5rem;">
                                <form action="toggle_status.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <?php if ($p['status'] === 'Active'): ?>
                                        <input type="hidden" name="status" value="Inactive">
                                        <button type="submit" class="action-btn" style="color: var(--accent-warning);" title="Deactivate Policy"><i class="fas fa-pause-circle"></i></button>
                                    <?php else: ?>
                                        <input type="hidden" name="status" value="Active">
                                        <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Activate Policy"><i class="fas fa-play-circle"></i></button>
                                    <?php endif; ?>
                                </form>
                                <a href="edit.php?id=<?php echo $p['id']; ?>" class="action-btn" title="Edit Engine Rules"><i class="fas fa-sliders-h"></i></a>
                            </div>
                        <?php else: ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">View only</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($policies)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No leave policies have been configured.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
