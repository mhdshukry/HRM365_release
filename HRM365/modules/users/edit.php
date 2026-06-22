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

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    die("User not found.");
}

$employees = $pdo->query("
    SELECT id, employee_code, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name ASC, last_name ASC
")->fetchAll();

function selected_user_value($actual, $expected): string
{
    return (string)$actual === (string)$expected ? 'selected' : '';
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit User</h1>
        <div class="page-subtitle">Update login access, role, status, and employee mapping.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="save.php" method="POST">
        <input type="hidden" name="id" value="<?php echo intval($user['id']); ?>">

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Full Name</label>
            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>
        
        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Email / Username</label>
            <input type="text" name="username" required value="<?php echo htmlspecialchars($user['username']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">New Password</label>
            <input type="password" name="password" placeholder="Leave blank to keep current password" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;" class="mb-4">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Type (Role)</label>
                <select name="role" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="employee" <?php echo selected_user_value($user['role'], 'employee'); ?>>Employee</option>
                    <option value="manager" <?php echo selected_user_value($user['role'], 'manager'); ?>>Manager</option>
                    <option value="HR" <?php echo selected_user_value($user['role'], 'HR'); ?>>HR Professional</option>
                    <option value="admin" <?php echo selected_user_value($user['role'], 'admin'); ?>>Administrator</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Status</label>
                <select name="status" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Active" <?php echo selected_user_value($user['status'], 'Active'); ?>>Active</option>
                    <option value="Inactive" <?php echo selected_user_value($user['status'], 'Inactive'); ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Department</label>
            <input type="text" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>" placeholder="e.g. IT, Sales" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Linked Employee Profile</label>
            <select name="employee_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <option value="">No employee mapping</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo $employee['id']; ?>" <?php echo selected_user_value($user['employee_id'], $employee['id']); ?>>
                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . $employee['employee_code'] . ' (' . $employee['department'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Save User</button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
