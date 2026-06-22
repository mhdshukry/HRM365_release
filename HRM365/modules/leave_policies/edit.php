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

$stmt = $pdo->prepare("SELECT * FROM leave_policies WHERE id = ?");
$stmt->execute([$id]);
$policy = $stmt->fetch();

if (!$policy) {
    die("Leave policy not found.");
}

$typeStmt = $pdo->prepare("
    SELECT id, name, status
    FROM leave_types
    WHERE status = 'Active' OR id = ?
    ORDER BY name ASC
");
$typeStmt->execute([$policy['leave_type_id']]);
$leaveTypes = $typeStmt->fetchAll();

function selected_leave_value($actual, $expected): string
{
    return (string)$actual === (string)$expected ? 'selected' : '';
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Leave Policy</h1>
        <div class="page-subtitle">Update accrual logic and application constraints.</div>
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
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Name *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($policy['name']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Target Leave Type *</label>
                    <select name="leave_type_id" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                        <option value="">Select a Base Leave Type...</option>
                        <?php foreach ($leaveTypes as $lt): ?>
                            <option value="<?php echo $lt['id']; ?>" <?php echo selected_leave_value($policy['leave_type_id'], $lt['id']); ?>><?php echo htmlspecialchars($lt['name'] . ($lt['status'] === 'Inactive' ? ' (Inactive)' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Description</label>
                <textarea name="description" rows="2" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"><?php echo htmlspecialchars($policy['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Accrual Engine</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Accrual Frequency *</label>
                <select name="accrual_type" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <?php foreach (['Monthly', 'Quarterly', 'Yearly', 'Fixed allocation'] as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo selected_leave_value($policy['accrual_type'], $type); ?>><?php echo htmlspecialchars($type === 'Fixed allocation' ? 'Fixed Allocation' : $type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Accrual Rate (Days) *</label>
                <input type="number" step="0.25" name="accrual_rate" required value="<?php echo htmlspecialchars($policy['accrual_rate']); ?>" min="0" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Max Carry Forward</label>
                <input type="number" name="carry_forward_limit" value="<?php echo intval($policy['carry_forward_limit']); ?>" min="0" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Enforcement Constraints</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Min Days / Request *</label>
                <input type="number" name="min_days_per_application" required value="<?php echo intval($policy['min_days_per_application']); ?>" min="1" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Max Days / Request *</label>
                <input type="number" name="max_days_per_application" required value="<?php echo intval($policy['max_days_per_application']); ?>" min="1" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Status</label>
                <select name="status" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Active" <?php echo selected_leave_value($policy['status'], 'Active'); ?>>Active</option>
                    <option value="Inactive" <?php echo selected_leave_value($policy['status'], 'Inactive'); ?>>Inactive</option>
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
