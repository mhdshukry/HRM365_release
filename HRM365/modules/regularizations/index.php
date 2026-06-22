<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

$query = "
    SELECT r.*, 
           e.first_name, e.last_name, e.employee_code,
           u.username as approver_name
    FROM attendance_regularizations r
    JOIN employees e ON r.employee_id = e.id
    LEFT JOIN users u ON r.approved_by = u.id
";
$params = [];

if ($currentUser['role'] === 'employee') {
    $query .= " WHERE r.employee_id = ?";
    $params[] = $currentUser['employee_id'] ?? 0;
} elseif ($currentUser['role'] === 'manager') {
    $query .= " WHERE e.department = ?";
    $params[] = $currentUser['department'] ?? '';
}

$query .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Attendance Regularizations</h1>
        <div class="page-subtitle">Review, approve, and recalculate broken timesheets.</div>
    </div>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Submit Request
    </a>
</div>

<?php if (isset($_GET['error']) && $_GET['error'] === 'pending_exists'): ?>
    <div style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 0.75rem;">
        <i class="fas fa-exclamation-circle"></i>
        <span style="font-weight: 500;">A pending regularization already exists for that employee and date.</span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Date</th>
                    <th style="text-align: center;">Original Timeline</th>
                    <th style="text-align: center;">Requested Timeline</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($requests as $r): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($r['employee_code']); ?></div>
                    </td>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo date('M d, Y', strtotime($r['date'])); ?></div>
                    </td>
                    <td style="text-align: center; font-family: monospace; font-size: 0.9rem;">
                        <span style="color: var(--text-muted);"><?php echo $r['original_clock_in'] ? date('H:i', strtotime($r['original_clock_in'])) : '--:--'; ?></span>
                        <span style="color: var(--border-color); margin: 0 0.2rem;">➔</span>
                        <span style="color: var(--text-muted);"><?php echo $r['original_clock_out'] ? date('H:i', strtotime($r['original_clock_out'])) : '--:--'; ?></span>
                    </td>
                    <td style="text-align: center; font-family: monospace; font-size: 0.95rem; font-weight: bold;">
                        <span style="color: var(--accent-success);"><?php echo $r['requested_clock_in'] ? date('H:i', strtotime($r['requested_clock_in'])) : '--:--'; ?></span>
                        <span style="color: var(--text-muted); margin: 0 0.2rem;">➔</span>
                        <span style="color: var(--accent-danger);"><?php echo $r['requested_clock_out'] ? date('H:i', strtotime($r['requested_clock_out'])) : '--:--'; ?></span>
                    </td>
                    <td>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($r['reason']); ?>">
                            <?php echo htmlspecialchars($r['reason']); ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'Pending'): ?>
                            <span class="status-badge" style="background: rgba(245, 158, 11, 0.1); color: var(--accent-warning);">Pending</span>
                        <?php elseif ($r['status'] === 'Approved'): ?>
                            <span class="status-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success);">Approved</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger);">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'Pending' && in_array($currentUser['role'], ['admin', 'HR'])): ?>
                            <div style="display: flex; gap: 0.5rem;">
                                <form action="approve.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Approve & Recalculate"><i class="fas fa-check-circle"></i></button>
                                </form>
                                <form action="approve.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="action-btn" style="color: var(--accent-danger);" title="Reject Request"><i class="fas fa-times-circle"></i></button>
                                </form>
                            </div>
                        <?php elseif ($r['status'] === 'Pending'): ?>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="fas fa-hourglass-half"></i> Waiting</span>
                        <?php else: ?>
                            <span style="font-size: 0.75rem; color: var(--text-muted);"><i class="fas fa-lock"></i> Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($requests)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No regularization requests found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
