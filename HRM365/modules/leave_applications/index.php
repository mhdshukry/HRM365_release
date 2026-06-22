<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

$query = "
    SELECT la.*, 
           e.first_name, e.last_name, e.employee_code, 
           lt.name as leave_name,
           ce.first_name as cover_first, ce.last_name as cover_last
    FROM leave_applications la
    JOIN employees e ON la.employee_id = e.id
    JOIN leave_types lt ON la.leave_type_id = lt.id
    LEFT JOIN employees ce ON la.covering_employee_id = ce.id
";
$params = [];

if ($currentUser['role'] === 'employee') {
    $query .= " WHERE la.employee_id = ?";
    $params[] = $currentUser['employee_id'] ?? 0;
} elseif ($currentUser['role'] === 'manager') {
    $query .= " WHERE e.department = ?";
    $params[] = $currentUser['department'] ?? '';
}

$query .= " ORDER BY la.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Leave Applications</h1>
        <div class="page-subtitle">Approve or reject time-off requests. Approvals automatically deduct from ledger balances.</div>
    </div>
    <a href="request_leave.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Request Leave
    </a>
</div>

<?php if (isset($_GET['error']) && $_GET['error'] === 'insufficient_balance'): ?>
    <div style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 0.75rem;">
        <i class="fas fa-exclamation-circle"></i>
        <span style="font-weight: 500;">Approval blocked: the employee no longer has enough remaining leave balance.</span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Duration</th>
                    <th>Days</th>
                    <th>Reason & Covering Staff</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $app): ?>
                <tr>
                    <td style="vertical-align: middle;">
                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 0.2rem;"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace; background: var(--bg-hover); padding: 0.1rem 0.4rem; border-radius: 4px; display: inline-block; border: 1px solid var(--border-color);"><?php echo htmlspecialchars($app['employee_code']); ?></div>
                    </td>
                    <td style="vertical-align: middle;">
                        <span style="color: var(--accent-primary); font-weight: 600; font-size: 0.9rem;"><i class="fas fa-tag" style="font-size: 0.8rem; margin-right: 0.3rem; opacity: 0.7;"></i> <?php echo htmlspecialchars($app['leave_name']); ?></span>
                    </td>
                    <td style="vertical-align: middle; font-family: monospace; font-size: 0.85rem; color: var(--text-secondary);">
                        <?php if ($app['start_date'] === $app['end_date']): ?>
                            <div style="font-weight: 600; color: var(--text-primary);"><?php echo date('M d, Y', strtotime($app['start_date'])); ?></div>
                        <?php else: ?>
                            <div style="color: var(--text-primary); margin-bottom: 0.2rem;"><?php echo date('M d, Y', strtotime($app['start_date'])); ?></div>
                            <div style="color: var(--text-muted);"><i class="fas fa-arrow-down" style="font-size: 0.7rem;"></i> <?php echo date('M d, Y', strtotime($app['end_date'])); ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle;">
                        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; font-weight: 700; color: var(--text-primary); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                            <?php echo floatval($app['total_days']); ?>
                        </div>
                    </td>
                    <td style="vertical-align: middle;">
                        <div style="font-size: 0.85rem; color: var(--text-secondary); max-width: 250px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($app['reason']); ?>">
                            <?php echo htmlspecialchars($app['reason']); ?>
                        </div>
                        <?php if (!empty($app['cover_first'])): ?>
                            <div style="font-size: 0.75rem; color: var(--accent-primary); margin-top: 0.4rem; background: rgba(37, 99, 235, 0.05); padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block; border: 1px solid rgba(37, 99, 235, 0.1);">
                                <i class="fas fa-user-shield"></i> Covering: <?php echo htmlspecialchars($app['cover_first'] . ' ' . $app['cover_last']); ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle;">
                        <?php if ($app['status'] === 'Pending'): ?>
                            <span class="status-badge" style="background: rgba(245, 158, 11, 0.1); color: var(--accent-warning); border: 1px solid rgba(245, 158, 11, 0.2);"><i class="fas fa-hourglass-half"></i> Pending</span>
                        <?php elseif ($app['status'] === 'Approved'): ?>
                            <span class="status-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success); border: 1px solid rgba(16, 185, 129, 0.2);"><i class="fas fa-check-circle"></i> Approved</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.2);"><i class="fas fa-times-circle"></i> Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle;">
                        <?php if ($app['status'] === 'Pending' && in_array($currentUser['role'], ['admin', 'HR'])): ?>
                            <div style="display: flex; gap: 0.5rem;">
                                <form action="approve.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="action-btn" style="color: var(--accent-success); background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); width: 32px; height: 32px;" title="Approve & Deduct"><i class="fas fa-check"></i></button>
                                </form>
                                <form action="approve.php" method="POST" style="margin: 0;">
                                    <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="action-btn" style="color: var(--accent-danger); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); width: 32px; height: 32px;" title="Reject"><i class="fas fa-times"></i></button>
                                </form>
                            </div>
                        <?php elseif ($app['status'] === 'Pending'): ?>
                            <span style="font-size: 0.8rem; color: var(--text-muted); background: var(--bg-hover); padding: 0.3rem 0.6rem; border-radius: var(--radius-md);"><i class="fas fa-hourglass-half" style="font-size: 0.7rem;"></i> Waiting</span>
                        <?php else: ?>
                            <span style="font-size: 0.8rem; color: var(--text-muted); background: var(--bg-hover); padding: 0.3rem 0.6rem; border-radius: var(--radius-md);"><i class="fas fa-lock" style="font-size: 0.7rem;"></i> Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 4rem 2rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: var(--border-color); margin-bottom: 1rem; display: block;"></i>
                        <span style="font-size: 1.1rem; font-weight: 500;">No Pending Leave Applications</span>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
