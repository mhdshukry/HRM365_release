<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager'])) {
    die("Unauthorized access.");
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$query = "
    SELECT e.*, b.bank_name, b.account_name, b.account_number, b.swift_code, b.bank_branch, b.tax_id
    FROM employees e
    LEFT JOIN employee_bank_details b ON b.employee_id = e.id
    WHERE e.id = ?
    ORDER BY b.id ASC
    LIMIT 1
";
$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found.");
}

if ($currentUser['role'] === 'manager' && $employee['department'] !== ($currentUser['department'] ?? null)) {
    die("Unauthorized access.");
}

$branchStmt = $pdo->prepare("
    SELECT id, name, status
    FROM branches
    WHERE status = 'Active' OR id = ?
    ORDER BY name ASC
");
$branchStmt->execute([$employee['branch_id']]);
$branches = $branchStmt->fetchAll();
$shifts = $pdo->query("SELECT id, name FROM shifts WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$policies = $pdo->query("SELECT id, name FROM attendance_policies WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';
$employeeIdNumber = preg_replace('/^EMP-/i', '', $employee['employee_code'] ?? '');

function selected_value($actual, $expected): string
{
    return (string)$actual === (string)$expected ? 'selected' : '';
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Employee</h1>
        <div class="page-subtitle">Update profile, biometric mapping, payroll salary, and attendance rules.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back to Directory
    </a>
</div>

<div class="card">
    <form action="save.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?php echo intval($employee['id']); ?>">

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-user"></i> Personal Information</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">First Name *</label>
                <input type="text" name="first_name" required value="<?php echo htmlspecialchars($employee['first_name']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Last Name *</label>
                <input type="text" name="last_name" required value="<?php echo htmlspecialchars($employee['last_name']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Email Address *</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($employee['email']); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">NIC Number</label>
                <input type="text" name="nic_number" value="<?php echo htmlspecialchars($employee['nic_number'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Date of Birth</label>
                <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($employee['date_of_birth'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Gender</label>
                <select name="gender" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">Select Gender...</option>
                    <option value="Male" <?php echo selected_value($employee['gender'], 'Male'); ?>>Male</option>
                    <option value="Female" <?php echo selected_value($employee['gender'], 'Female'); ?>>Female</option>
                    <option value="Other" <?php echo selected_value($employee['gender'], 'Other'); ?>>Other</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Residential Address</label>
                <textarea name="address" rows="2" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-briefcase"></i> Employment, Payroll & Attendance</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--accent-warning); font-size: 0.9rem;"><i class="fas fa-fingerprint"></i> Employee ID / Biometric ID *</label>
                <div style="display: flex; width: 100%;">
                    <span style="display: inline-flex; align-items: center; padding: 0 0.85rem; border: 1px solid var(--accent-warning); border-right: 0; border-radius: var(--radius-md) 0 0 var(--radius-md); background: var(--bg-hover); color: var(--accent-warning); font-weight: 700;">EMP-</span>
                    <input type="text" id="employee_code" name="employee_code" required value="<?php echo htmlspecialchars($employeeIdNumber); ?>" inputmode="numeric" pattern="[0-9]+" style="width: 100%; padding: 0.75rem; border-radius: 0 var(--radius-md) var(--radius-md) 0; border: 1px solid var(--accent-warning); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                </div>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Branch Location</label>
                <select name="branch_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">Select Branch...</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>" <?php echo selected_value($employee['branch_id'], $b['id']); ?>><?php echo htmlspecialchars($b['name'] . ($b['status'] === 'Inactive' ? ' (Inactive)' : '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Department</label>
                <select name="department" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <?php foreach (['IT', 'HR', 'Sales', 'Operations', 'Finance'] as $department): ?>
                        <option value="<?php echo $department; ?>" <?php echo selected_value($employee['department'], $department); ?>><?php echo $department; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Designation / Job Title</label>
                <input type="text" name="designation" value="<?php echo htmlspecialchars($employee['designation'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Joining Date</label>
                <input type="date" name="hire_date" required value="<?php echo htmlspecialchars($employee['hire_date'] ?? date('Y-m-d')); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Employment Type</label>
                <select name="employment_type" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <?php foreach (['Full-time', 'Part-time', 'Contract'] as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo selected_value($employee['employment_type'], $type); ?>><?php echo $type; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Status</label>
                <select name="status" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <?php foreach (['Active', 'On Leave', 'Resigned', 'Terminated'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo selected_value($employee['status'], $status); ?>><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Resigned / Termination Date</label>
                <input type="date" name="resignation_termination_date" value="<?php echo htmlspecialchars($employee['resignation_termination_date'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Base Salary (Monthly <?php echo htmlspecialchars($currency); ?>)</label>
                <input type="number" step="0.01" name="base_salary" value="<?php echo htmlspecialchars($employee['base_salary'] ?? '0.00'); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Default Shift</label>
                <select name="shift_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">No Shift</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo selected_value($employee['shift_id'], $s['id']); ?>><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Attendance Policy</label>
                <select name="attendance_policy_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">No Policy</option>
                    <?php foreach ($policies as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo selected_value($employee['attendance_policy_id'], $p['id']); ?>><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-university"></i> Banking Information</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Bank Name</label>
                <input type="text" name="bank_name" value="<?php echo htmlspecialchars($employee['bank_name'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Account Holder Name</label>
                <input type="text" name="account_name" value="<?php echo htmlspecialchars($employee['account_name'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Account Number</label>
                <input type="text" name="account_number" value="<?php echo htmlspecialchars($employee['account_number'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Bank Identifier Code (BIC/SWIFT)</label>
                <input type="text" name="swift_code" value="<?php echo htmlspecialchars($employee['swift_code'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Bank Branch</label>
                <input type="text" name="bank_branch" value="<?php echo htmlspecialchars($employee['bank_branch'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Tax Payer ID</label>
                <input type="text" name="tax_id" value="<?php echo htmlspecialchars($employee['tax_id'] ?? ''); ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
        </div>

        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-file-upload"></i> Profile Photo</h3>
        <div style="margin-bottom: 2rem;">
            <input type="file" name="profile_photo" accept="image/jpeg,image/png" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px dashed var(--accent-primary); background: rgba(59, 130, 246, 0.05); color: var(--text-primary); outline: none;">
            <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">JPG or PNG only, maximum 2 MB.</small>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Save Employee
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
