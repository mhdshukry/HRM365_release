<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Only Admin, HR, Manager can create employees
if (!in_array($currentUser['role'], ['admin', 'HR', 'manager'])) {
    die("Unauthorized access.");
}

// Fetch active branches for dropdown
$branchStmt = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC");
$branches = $branchStmt->fetchAll();
$shifts = $pdo->query("SELECT id, name FROM shifts WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$policies = $pdo->query("SELECT id, name FROM attendance_policies WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';

// Auto-generate next Employee Code
$empCodeStmt = $pdo->query("SELECT id FROM employees ORDER BY id DESC LIMIT 1");
$lastEmp = $empCodeStmt->fetch();
$nextId = $lastEmp ? $lastEmp['id'] + 1 : 1;
$autoEmpCode = 'EMP-' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Onboard New Employee</h1>
        <div class="page-subtitle">Comprehensive profile creation with banking and biometric integration.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back to Directory
    </a>
</div>

<div class="card">
    <form action="save.php" method="POST" enctype="multipart/form-data">
        
        <!-- SECTION 1: Personal Information -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-user"></i> Personal Information</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">First Name *</label>
                <input type="text" name="first_name" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Last Name *</label>
                <input type="text" name="last_name" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Email Address *</label>
                <input type="email" name="email" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Phone Number</label>
                <input type="text" name="phone" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Date of Birth</label>
                <input type="date" name="date_of_birth" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Gender</label>
                <select name="gender" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">Select Gender...</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div style="grid-column: 1 / -1;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Residential Address</label>
                <textarea name="address" rows="2" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
            </div>
        </div>

        <!-- SECTION 2: Employment Details -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-briefcase"></i> Employment Details</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Employee Code (Internal ID) *</label>
                <input type="text" name="employee_code" value="<?php echo htmlspecialchars($autoEmpCode); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-warning); font-size: 0.9rem;">
                    <i class="fas fa-fingerprint"></i> Biometric ZKTeco ID
                </label>
                <input type="text" name="biometric_user_id" placeholder="ID from hardware scanner" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--accent-warning); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Branch Location</label>
                <select name="branch_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">Select Branch...</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Department</label>
                <select name="department" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="IT">IT</option>
                    <option value="HR">HR</option>
                    <option value="Sales">Sales</option>
                    <option value="Operations">Operations</option>
                    <option value="Finance">Finance</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Designation / Job Title</label>
                <input type="text" name="designation" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Joining Date</label>
                <input type="date" name="hire_date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Employment Type</label>
                <select name="employment_type" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Full-time">Full-time</option>
                    <option value="Part-time">Part-time</option>
                    <option value="Contract">Contract</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Base Salary (Monthly <?php echo htmlspecialchars($currency); ?>)</label>
                <input type="number" step="0.01" name="base_salary" placeholder="0.00" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Default Shift</label>
                <select name="shift_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">No Shift</option>
                    <?php foreach ($shifts as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Attendance Policy</label>
                <select name="attendance_policy_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="">No Policy</option>
                    <?php foreach ($policies as $p): ?>
                        <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- SECTION 3: Banking Information -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-university"></i> Banking Information</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Bank Name</label>
                <input type="text" name="bank_name" placeholder="e.g. Chase Bank" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Account Holder Name</label>
                <input type="text" name="account_name" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Account Number</label>
                <input type="text" name="account_number" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Bank Identifier Code (BIC/SWIFT)</label>
                <input type="text" name="swift_code" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Bank Branch</label>
                <input type="text" name="bank_branch" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Tax Payer ID (SSN/TIN)</label>
                <input type="text" name="tax_id" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
        </div>

        <!-- SECTION 4: Profile Documents -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-file-upload"></i> Document Management</h3>
        <div style="margin-bottom: 2rem;">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Profile Photo (Optional)</label>
            <input type="file" name="profile_photo" accept="image/jpeg,image/png" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px dashed var(--accent-primary); background: rgba(59, 130, 246, 0.05); color: var(--text-primary); outline: none;">
            <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">JPG or PNG only, maximum 2 MB. Upload official contracts and IDs via the secure Document Vault after creating the profile.</small>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-user-check"></i> Complete Onboarding
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
