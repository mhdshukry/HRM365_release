<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Fetch employees and leave types
if ($currentUser['role'] === 'employee') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code FROM employees WHERE id = ? ORDER BY first_name ASC");
    $stmt->execute([$currentUser['employee_id'] ?? 0]);
    $employees = $stmt->fetchAll();
} elseif ($currentUser['role'] === 'manager') {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, employee_code FROM employees WHERE department = ? ORDER BY first_name ASC");
    $stmt->execute([$currentUser['department'] ?? '']);
    $employees = $stmt->fetchAll();
} else {
    $employees = $pdo->query("SELECT id, first_name, last_name, employee_code FROM employees ORDER BY first_name ASC")->fetchAll();
}
$leave_types = $pdo->query("SELECT id, name FROM leave_types WHERE status = 'Active' ORDER BY name ASC")->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Request Leave</h1>
        <div class="page-subtitle">Submit a time-off request for HR approval.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto; padding: 3rem;">
    <?php if (isset($_GET['error']) && $_GET['error'] === 'insufficient_balance'): ?>
        <div style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i> 
            <span style="font-weight: 500;">Error: You do not have enough remaining balance for this leave type!</span>
        </div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'policy_limit'): ?>
        <div style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 500;">Error: Requested days are outside the configured leave policy limits.</span>
        </div>
    <?php endif; ?>

    <form action="save.php" method="POST">
        <div style="margin-bottom: 2.5rem;">
            <h3 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;">Employee Details</h3>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">Select the employee and verify their available leave balances before proceeding.</p>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">Select Employee <span style="color: var(--accent-danger);">*</span></label>
                    <select name="employee_id" id="employeeSelect" required style="width: 100%; padding: 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; transition: all 0.2s; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                        <option value="">Choose employee...</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">Leave Type <span style="color: var(--accent-danger);">*</span></label>
                    <select name="leave_type_id" id="leaveTypeSelect" required style="width: 100%; padding: 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; transition: all 0.2s; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                        <option value="">Select leave type...</option>
                        <?php foreach ($leave_types as $lt): ?>
                            <option value="<?php echo $lt['id']; ?>"><?php echo htmlspecialchars($lt['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div id="balanceDisplayContainer" style="margin-top: 1.5rem; display: none;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em;">Available Ledger</label>
                <div id="balanceDisplay" style="background: var(--bg-main); border: 1px solid var(--border-color); padding: 1rem; border-radius: var(--radius-md); min-height: 3.5rem;">
                    <!-- Live balance injected here via JS -->
                </div>
            </div>
        </div>

        <div style="margin-bottom: 2.5rem; padding-top: 2rem; border-top: 1px dashed var(--border-color);">
            <h3 style="color: var(--text-primary); font-size: 1.25rem; font-weight: 600; margin-bottom: 0.25rem;">Timeline & Coverage</h3>
            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem;">Specify the duration and who will cover responsibilities.</p>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">Duration Type <span style="color: var(--accent-danger);">*</span></label>
                    <select name="duration_type" id="durationType" required style="width: 100%; padding: 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                        <option value="multi">Multiple Days</option>
                        <option value="1.00">Single Full Day (1.0)</option>
                        <option value="0.50">Half Day (0.5)</option>
                        <option value="0.25">Short Leave (0.25)</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">Start Date <span style="color: var(--accent-danger);">*</span></label>
                    <input type="date" name="start_date" id="startDate" required style="width: 100%; padding: 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div id="endDateContainer">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">End Date <span style="color: var(--accent-danger);">*</span></label>
                    <input type="date" name="end_date" id="endDate" style="width: 100%; padding: 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">Alternative / Covering Staff</label>
                    <select name="covering_employee_id" style="width: 100%; padding: 0.85rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02);">
                        <option value="">No covering staff needed...</option>
                        <?php foreach ($employees as $e): ?>
                            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['first_name'] . ' ' . $e['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 2.5rem; padding-top: 2rem; border-top: 1px dashed var(--border-color);">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500;">Reason for Leave <span style="color: var(--accent-danger);">*</span></label>
            <textarea name="reason" required rows="4" placeholder="Please provide detailed justification for this request..." style="width: 100%; padding: 1rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; resize: vertical; box-shadow: inset 0 1px 2px rgba(0,0,0,0.02); line-height: 1.5;"></textarea>
        </div>

        <div style="text-align: right; padding-top: 1.5rem;">
            <a href="index.php" class="btn" style="background: transparent; color: var(--text-secondary); margin-right: 1rem; font-weight: 500;">Cancel</a>
            <button type="submit" id="submitBtn" class="btn btn-primary" style="padding: 0.85rem 2.5rem; font-size: 1rem; font-weight: 600; border-radius: var(--radius-lg); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);">
                <i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i> Submit Application
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const empSelect = document.getElementById('employeeSelect');
    const typeSelect = document.getElementById('leaveTypeSelect');
    const displayContainer = document.getElementById('balanceDisplayContainer');
    const display = document.getElementById('balanceDisplay');
    const submitBtn = document.getElementById('submitBtn');
    
    const durationType = document.getElementById('durationType');
    const endDateContainer = document.getElementById('endDateContainer');
    const endDate = document.getElementById('endDate');
    
    let currentBalances = [];

    // Toggle End Date visibility based on Duration selection
    durationType.addEventListener('change', function() {
        if (this.value === 'multi') {
            endDateContainer.style.display = 'block';
            endDate.required = true;
        } else {
            endDateContainer.style.display = 'none';
            endDate.required = false;
        }
    });

    function updateSubmitButton() {
        const typeId = parseInt(typeSelect.value);
        if (!typeId) {
            submitBtn.disabled = false;
            return;
        }
        
        const balRecord = currentBalances.find(b => parseInt(b.type_id) === typeId);
        if (balRecord && parseFloat(balRecord.remaining_days) > 0) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    }

    function fetchBalances() {
        const empId = empSelect.value;

        if (empId) {
            displayContainer.style.display = 'block';
            display.innerHTML = '<span style="color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading balances...</span>';
            
            fetch(`ajax_get_balance.php?emp_id=${empId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.balances.length > 0) {
                        currentBalances = data.balances;
                        
                        let html = '<div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.25rem;">';
                        data.balances.forEach(b => {
                            let days = parseFloat(b.remaining_days);
                            let color = days > 0 ? 'var(--accent-success)' : 'var(--text-muted)';
                            let bg = days > 0 ? 'rgba(16, 185, 129, 0.1)' : 'var(--bg-hover)';
                            html += `<span style="background: ${bg}; color: ${color}; padding: 0.35rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; border: 1px solid ${color}; font-weight: 500;">
                                ${b.name}: <strong style="font-size: 0.85rem; margin-left: 0.2rem;">${days} days</strong>
                            </span>`;
                        });
                        html += '</div>';
                        display.innerHTML = html;
                        
                        updateSubmitButton();
                    } else {
                        currentBalances = [];
                        display.innerHTML = '<span style="color: var(--text-muted);">No leave balances found for this employee.</span>';
                        updateSubmitButton();
                    }
                });
        } else {
            currentBalances = [];
            displayContainer.style.display = 'none';
            display.innerHTML = '';
            submitBtn.disabled = false;
        }
    }

    empSelect.addEventListener('change', fetchBalances);
    typeSelect.addEventListener('change', updateSubmitButton);
});
</script>

<?php include '../../includes/footer.php'; ?>
