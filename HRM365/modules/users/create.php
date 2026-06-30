<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$employees = $pdo->query("
    SELECT id, employee_code, first_name, last_name, email, department, phone
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
            <input type="text" name="full_name" placeholder="Auto-filled from employee" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>
        
        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Email (Username)</label>
            <input type="email" name="username" placeholder="Auto-filled from employee email" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Temporary Password</label>
            <input type="password" name="password" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">SMS Phone Number</label>
            <input type="text" name="phone" placeholder="e.g. +94771234567" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            <small style="color: var(--text-muted); display: block; margin-top: 0.45rem;">If blank, the linked employee phone number will be used.</small>
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
                    <option
                        value="<?php echo $employee['id']; ?>"
                        data-name="<?php echo htmlspecialchars(trim($employee['first_name'] . ' ' . $employee['last_name'])); ?>"
                        data-email="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>"
                        data-phone="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>"
                        data-department="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>"
                    >
                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . $employee['employee_code'] . ' (' . $employee['department'] . ')' . (!empty($employee['phone']) ? ' - ' . $employee['phone'] : '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Save User</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const employeeSelect = document.querySelector('[name="employee_id"]');
    const fullNameInput = document.querySelector('[name="full_name"]');
    const usernameInput = document.querySelector('[name="username"]');
    const phoneInput = document.querySelector('[name="phone"]');
    const departmentInput = document.querySelector('[name="department"]');

    function normalizeLkPhone(value) {
        const cleaned = (value || '').trim().replace(/[^\d+]/g, '');
        if (cleaned.startsWith('+')) return cleaned;
        if (/^0\d{9}$/.test(cleaned)) return '+94' + cleaned.slice(1);
        if (/^7\d{8}$/.test(cleaned)) return '+94' + cleaned;
        return cleaned;
    }

    function fillFromEmployee() {
        const selected = employeeSelect.options[employeeSelect.selectedIndex];
        if (!selected || !selected.value) return;
        fullNameInput.value = selected.dataset.name || '';
        usernameInput.value = selected.dataset.email || '';
        phoneInput.value = normalizeLkPhone(selected.dataset.phone || '');
        departmentInput.value = selected.dataset.department || '';
    }

    employeeSelect.addEventListener('change', fillFromEmployee);
    fillFromEmployee();
    phoneInput.addEventListener('blur', function () {
        phoneInput.value = normalizeLkPhone(phoneInput.value);
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
