<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM shifts WHERE id = ?");
$stmt->execute([$id]);
$shift = $stmt->fetch();

if (!$shift) {
    die("Shift not found.");
}

function checked_value($value): string
{
    return $value ? 'checked' : '';
}

$weekDays = [
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
    7 => 'Sunday',
];

$scheduleStmt = $pdo->prepare("SELECT * FROM shift_weekly_schedules WHERE shift_id = ? ORDER BY weekday ASC");
$scheduleStmt->execute([$id]);
$weeklySchedules = [];
foreach ($scheduleStmt->fetchAll() as $row) {
    $weeklySchedules[intval($row['weekday'])] = $row;
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Shift Rules</h1>
        <div class="page-subtitle">Update working hours, shift type, and tolerance periods.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Cancel
    </a>
</div>

<?php if (!empty($_GET['error'])): ?>
    <div class="card" style="max-width: 800px; margin: 0 auto 1rem; border-left: 4px solid var(--accent-danger);">
        <strong style="color: var(--accent-danger);">Shift not deleted.</strong>
        <div style="color: var(--text-secondary); margin-top: 0.35rem;">
            <?php
            $errorMessages = [
                'shift_has_employees' => 'This shift is still assigned to employees. Move those employees to another shift first.',
                'shift_has_overrides' => 'This shift is still used in employee shift overrides. Remove those overrides first.',
                'shift_not_found' => 'Shift not found.',
                'delete_failed' => 'Could not delete the shift. Please try again.',
            ];
            echo htmlspecialchars($errorMessages[$_GET['error']] ?? 'Could not delete the shift.');
            ?>
        </div>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="save.php" method="POST">
        <input type="hidden" name="id" value="<?php echo intval($shift['id']); ?>">

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Shift Identification</h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Shift Name *</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($shift['name']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Description</label>
                <textarea name="description" rows="2" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"><?php echo htmlspecialchars($shift['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Weekly Working Boundaries</h3>
        <div style="display: grid; gap: 0.75rem; margin-bottom: 2rem;">
            <?php foreach ($weekDays as $dayNumber => $dayName): ?>
                <?php
                    $row = $weeklySchedules[$dayNumber] ?? null;
                    $isWorking = $row ? intval($row['is_working']) === 1 : $dayNumber <= 5;
                    $startValue = $row && !empty($row['start_time']) ? substr($row['start_time'], 0, 5) : ($isWorking ? substr($shift['start_time'], 0, 5) : '');
                    $endValue = $row && !empty($row['end_time']) ? substr($row['end_time'], 0, 5) : ($isWorking ? substr($shift['end_time'], 0, 5) : '');
                ?>
                <div style="display: grid; grid-template-columns: 150px 1fr 1fr; gap: 1rem; align-items: center; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary);">
                    <label style="display: flex; align-items: center; gap: 0.6rem; color: var(--text-primary); font-weight: 500;">
                        <input type="checkbox" name="working_days[<?php echo $dayNumber; ?>]" value="1" <?php echo $isWorking ? 'checked' : ''; ?> style="width: 1.1rem; height: 1.1rem; accent-color: var(--accent-primary);">
                        <?php echo $dayName; ?>
                    </label>
                    <input type="time" name="day_start_time[<?php echo $dayNumber; ?>]" value="<?php echo htmlspecialchars($startValue); ?>" style="width: 100%; padding: 0.65rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); outline: none; color-scheme: light;">
                    <input type="time" name="day_end_time[<?php echo $dayNumber; ?>]" value="<?php echo htmlspecialchars($endValue); ?>" style="width: 100%; padding: 0.65rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); outline: none; color-scheme: light;">
                </div>
            <?php endforeach; ?>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Tolerances & Compliance</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Lateness Grace Period (Minutes)</label>
                <input type="number" name="grace_period" value="<?php echo intval($shift['grace_period']); ?>" min="0" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem; justify-content: center; padding-top: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="is_night_shift" value="1" <?php echo checked_value($shift['is_night_shift']); ?> style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Is Night Shift? (Crosses Midnight)
                </label>

                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="status" value="Active" <?php echo checked_value($shift['status'] === 'Active'); ?> style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Active Schedule
                </label>
            </div>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Update Shift Profile
            </button>
        </div>
    </form>

    <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color);">
        <form action="delete.php" method="POST" onsubmit="return confirm('Delete this shift permanently? This cannot be undone.');">
            <input type="hidden" name="id" value="<?php echo intval($shift['id']); ?>">
            <button type="submit" class="btn" style="width: 100%; background: rgba(239, 68, 68, 0.12); color: var(--accent-danger); border: 1px solid rgba(239, 68, 68, 0.28);">
                <i class="fas fa-trash-alt"></i> Delete Shift
            </button>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
