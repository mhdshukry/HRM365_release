<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$employees = $pdo->query("
    SELECT id, employee_code, first_name, last_name, department
    FROM employees
    WHERE status = 'Active'
    ORDER BY first_name ASC, last_name ASC
")->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Add New User</h1>
        <div class="page-subtitle">Provision access and roles for the platform.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="save.php" method="POST">
        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Full Name</label>
            <input type="text" name="full_name" placeholder="Auto-filled from employee if blank" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>
        
        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Email (Username)</label>
            <input type="email" name="username" placeholder="Auto-filled from employee email if blank" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Temporary Password</label>
            <input type="password" name="password" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;" class="mb-4">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Type (Role)</label>
                <select name="role" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="employee">Employee</option>
                    <option value="manager">Manager</option>
                    <option value="HR">HR Professional</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Status</label>
                <select name="status" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;" class="mb-4">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Department (Optional)</label>
                <input type="text" name="department" placeholder="e.g. IT, Sales" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Linked Employee Profile</label>
            <select name="employee_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <option value="">No employee mapping</option>
                <?php foreach ($employees as $employee): ?>
                    <option value="<?php echo $employee['id']; ?>">
                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . $employee['employee_code'] . ' (' . $employee['department'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Save User</button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
