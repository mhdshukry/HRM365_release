<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Define Attendance Policy</h1>
        <div class="page-subtitle">Configure strict penalty boundaries and financial overtime calculations.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Cancel
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="save.php" method="POST">
        
        <!-- SECTION 1: Policy Identification -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Policy Identification</h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Name *</label>
                <input type="text" name="name" required placeholder="e.g. Standard Strict Policy" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Description</label>
                <textarea name="description" rows="2" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
            </div>
        </div>

        <!-- SECTION 2: Tolerances -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Tolerance Parameters (Minutes)</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Late Arrival Grace *</label>
                <input type="number" name="late_arrival_grace" required value="0" min="0" placeholder="e.g. 15" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Minutes allowed *after* start time before flagging as Late.</small>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Early Departure Grace *</label>
                <input type="number" name="early_departure_grace" required value="0" min="0" placeholder="e.g. 15" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Minutes allowed *before* end time before flagging as Early Departure.</small>
            </div>
        </div>

        <!-- SECTION 3: Financial Engine -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Financial Engine</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Overtime Rate Multiplier *</label>
                <input type="number" step="0.01" name="overtime_rate_per_hour" required value="1.00" min="0" placeholder="e.g. 1.5" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 2px solid var(--accent-primary); background: var(--bg-secondary); color: var(--text-primary); outline: none; font-weight: bold;">
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Multiplier applied to base hourly pay (e.g., 1.5 = Time and a half).</small>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Policy Status</label>
                <select name="status" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Build Policy Rule
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
