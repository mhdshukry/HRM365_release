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
        <h1 class="page-title">Define Shift Rules</h1>
        <div class="page-subtitle">Configure mathematical working hours, breaks, and payroll night-shift flags.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Cancel
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="save.php" method="POST">
        
        <!-- SECTION 1: Identity -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Shift Identification</h3>
        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Shift Name *</label>
                <input type="text" name="name" required placeholder="e.g. Standard Morning Shift" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Description</label>
                <textarea name="description" rows="2" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
            </div>
        </div>

        <!-- SECTION 2: Time Boundaries -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Working Boundaries</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Start Time *</label>
                <input type="time" name="start_time" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">End Time *</label>
                <input type="time" name="end_time" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
        </div>

        <!-- SECTION 3: Tolerances & Compliance -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">Tolerances & Compliance</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Lateness Grace Period (Minutes)</label>
                <input type="number" name="grace_period" value="0" min="0" placeholder="e.g. 15" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Employees clocking in within this window are not flagged as late.</small>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 1rem; justify-content: center; padding-top: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="is_night_shift" value="1" style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Is Night Shift? (Crosses Midnight)
                </label>
                
                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="status" value="Active" checked style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Active Schedule
                </label>
            </div>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-save"></i> Build Shift Profile
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
