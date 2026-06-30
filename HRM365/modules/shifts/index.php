<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$stmt = $pdo->query("SELECT * FROM shifts ORDER BY name ASC");
$shifts = $stmt->fetchAll();

$scheduleRows = $pdo->query("
    SELECT sw.*, s.name AS shift_name
    FROM shift_weekly_schedules sw
    JOIN shifts s ON sw.shift_id = s.id
    ORDER BY sw.shift_id ASC, sw.weekday ASC
")->fetchAll();
$weeklySchedules = [];
foreach ($scheduleRows as $row) {
    $weeklySchedules[intval($row['shift_id'])][intval($row['weekday'])] = $row;
}
$dayLabels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Shift Management</h1>
        <div class="page-subtitle">Define working hours, breaks, and tolerance periods for automated timesheets.</div>
    </div>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Add Shift
    </a>
</div>

<?php if (!empty($_GET['success'])): ?>
    <div class="card" style="margin-bottom: 1rem; border-left: 4px solid var(--accent-success);">
        <strong style="color: var(--accent-success);">Success.</strong>
        <span style="color: var(--text-secondary); margin-left: 0.35rem;">
            <?php
            $successMessages = [
                'shift_deleted' => 'Shift deleted successfully.',
                'shift_updated' => 'Shift updated successfully.',
                'shift_created' => 'Shift created successfully.',
                'status_updated' => 'Shift status updated successfully.',
            ];
            echo htmlspecialchars($successMessages[$_GET['success']] ?? 'Shift action completed.');
            ?>
        </span>
    </div>
<?php endif; ?>

<?php if (!empty($_GET['error'])): ?>
    <div class="card" style="margin-bottom: 1rem; border-left: 4px solid var(--accent-danger);">
        <strong style="color: var(--accent-danger);">Shift action failed.</strong>
        <span style="color: var(--text-secondary); margin-left: 0.35rem;">
            <?php
            $errorMessages = [
                'shift_not_found' => 'Shift not found.',
                'delete_failed' => 'Could not delete the shift.',
            ];
            echo htmlspecialchars($errorMessages[$_GET['error']] ?? 'Could not complete the shift action.');
            ?>
        </span>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Shift Configuration</th>
                    <th>Weekly Schedule</th>
                    <th>Tolerances</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $s): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                            <?php echo htmlspecialchars($s['name']); ?>
                            <?php if ($s['is_night_shift']): ?>
                                <i class="fas fa-moon" style="color: var(--accent-info);" title="Night Shift"></i>
                            <?php else: ?>
                                <i class="fas fa-sun" style="color: var(--accent-warning);" title="Day Shift"></i>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars(substr($s['description'], 0, 50)); ?></div>
                    </td>
                    <td>
                        <div style="display: grid; gap: 0.25rem;">
                            <?php foreach ($dayLabels as $dayNumber => $dayLabel): ?>
                                <?php $day = $weeklySchedules[intval($s['id'])][$dayNumber] ?? null; ?>
                                <div style="font-size: 0.82rem; color: var(--text-secondary); white-space: nowrap;">
                                    <strong style="display: inline-block; width: 2.2rem; color: var(--text-primary);"><?php echo $dayLabel; ?></strong>
                                    <?php if ($day && intval($day['is_working']) === 1 && !empty($day['start_time']) && !empty($day['end_time'])): ?>
                                        <?php echo date('h:i A', strtotime($day['start_time'])); ?> - <?php echo date('h:i A', strtotime($day['end_time'])); ?>
                                    <?php elseif (!$day && $dayNumber <= 5): ?>
                                        <?php echo date('h:i A', strtotime($s['start_time'])); ?> - <?php echo date('h:i A', strtotime($s['end_time'])); ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">No shift</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 0.85rem; color: var(--text-secondary);">Grace Period: <strong><?php echo intval($s['grace_period']); ?> mins</strong></div>
                    </td>
                    <td>
                        <?php if ($s['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge" style="background: var(--bg-hover); color: var(--text-muted);">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <form action="toggle_status.php" method="POST" style="margin: 0;">
                                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">
                                <?php if ($s['status'] === 'Active'): ?>
                                    <input type="hidden" name="status" value="Inactive">
                                    <button type="submit" class="action-btn" style="color: var(--accent-warning);" title="Deactivate Shift"><i class="fas fa-pause-circle"></i></button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="Active">
                                    <button type="submit" class="action-btn" style="color: var(--accent-success);" title="Activate Shift"><i class="fas fa-play-circle"></i></button>
                                <?php endif; ?>
                            </form>
                            <a href="edit.php?id=<?php echo $s['id']; ?>" class="action-btn" title="Edit Shift Rules"><i class="fas fa-edit"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($shifts)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No shift schedules have been defined.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
