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

$stmt = $pdo->prepare("SELECT * FROM attendance_policies WHERE id = ?");
$stmt->execute([$id]);
$policy = $stmt->fetch();

if (!$policy) {
    die("Attendance policy not found.");
}

function selected_policy_status($actual, $expected): string
{
    return (string)$actual === (string)$expected ? 'selected' : '';
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Attendance Policy</h1>
        <div class="page-subtitle">Update tolerance boundaries and overtime payroll rate.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Cancel
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="save.php" method="POST">
        <input type="hidden" name="id" value="<?php echo intval($policy['id']); ?>">

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Policy Identification</h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Name *</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($policy['name']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Description</label>
                <textarea name="description" rows="2" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"><?php echo htmlspecialchars($policy['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Tolerance Parameters (Minutes)</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Late Arrival Grace *</label>
                <input type="number" name="late_arrival_grace" required value="<?php echo intval($policy['late_arrival_grace']); ?>" min="0" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Early Departure Grace *</label>
                <input type="number" name="early_departure_grace" required value="<?php echo intval($policy['early_departure_grace']); ?>" min="0" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Financial Engine</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Overtime Rate Multiplier *</label>
                <input type="number" step="0.01" name="overtime_rate_per_hour" required value="<?php echo htmlspecialchars($policy['overtime_rate_per_hour']); ?>" min="0" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 2px solid var(--accent-primary); background: var(--bg-secondary); color: var(--text-primary); outline: none; font-weight: bold;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Status</label>
                <select name="status" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Active" <?php echo selected_policy_status($policy['status'], 'Active'); ?>>Active</option>
                    <option value="Inactive" <?php echo selected_policy_status($policy['status'], 'Inactive'); ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Update Policy Rule
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
