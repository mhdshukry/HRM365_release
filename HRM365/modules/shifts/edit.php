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

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Working Boundaries</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Start Time *</label>
                <input type="time" name="start_time" required value="<?php echo htmlspecialchars(substr($shift['start_time'], 0, 5)); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">End Time *</label>
                <input type="time" name="end_time" required value="<?php echo htmlspecialchars(substr($shift['end_time'], 0, 5)); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
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
</div>

<?php include '../../includes/footer.php'; ?>
