<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$stmt = $pdo->query("SELECT * FROM attendance_policies ORDER BY name ASC");
$policies = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Attendance Policies</h1>
        <div class="page-subtitle">Configure strict operational rules for lateness, early departures, and automated overtime calculations.</div>
    </div>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Policy
    </a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Policy Ruleset</th>
                    <th>Grace Periods (Tolerances)</th>
                    <th>Financial Math (Overtime)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($policies as $p): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars(substr($p['description'], 0, 60)); ?>...</div>
                    </td>
                    <td>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Late Arrival: <strong style="color: var(--accent-warning);"><?php echo intval($p['late_arrival_grace']); ?> mins</strong></div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Early Departure: <strong style="color: var(--accent-danger);"><?php echo intval($p['early_departure_grace']); ?> mins</strong></div>
                    </td>
                    <td>
                        <span style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success); padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 600; font-size: 0.85rem;">
                            <?php echo number_format($p['overtime_rate_per_hour'], 2); ?>x Base Hourly Rate
                        </span>
                    </td>
                    <td>
                        <?php if ($p['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: var(--bg-hover); color: var(--text-muted);">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
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
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($policies)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No attendance policies have been configured.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
