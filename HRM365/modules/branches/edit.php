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

$stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$id]);
$branch = $stmt->fetch();

if (!$branch) {
    die("Branch not found.");
}

function selected_status($actual, $expected): string
{
    return (string)$actual === (string)$expected ? 'selected' : '';
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Branch</h1>
        <div class="page-subtitle">Update company office location and operational status.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="save.php" method="POST">
        <input type="hidden" name="id" value="<?php echo intval($branch['id']); ?>">

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Branch Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($branch['name']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Physical Address</label>
            <textarea name="address" rows="3" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"><?php echo htmlspecialchars($branch['address'] ?? ''); ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;" class="mb-4">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Contact Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($branch['phone'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($branch['email'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Operational Status</label>
            <select name="status" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <option value="Active" <?php echo selected_status($branch['status'], 'Active'); ?>>Active</option>
                <option value="Inactive" <?php echo selected_status($branch['status'], 'Inactive'); ?>>Inactive</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Update Branch</button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
