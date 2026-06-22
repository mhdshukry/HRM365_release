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
        <h1 class="page-title">Add Leave Type</h1>
        <div class="page-subtitle">Configure a new time off policy.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Cancel
    </a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="save.php" method="POST">
        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Leave Policy Name *</label>
            <input type="text" name="name" required placeholder="e.g. Mental Health Day" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Description</label>
            <textarea name="description" rows="3" placeholder="Explain when this leave should be used..." style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;" class="mb-4">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Max Days Per Year *</label>
                <input type="number" name="max_days_per_year" required min="0" placeholder="e.g. 14" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Calendar Color</label>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <input type="color" name="color" value="#3b82f6" style="width: 40px; height: 40px; padding: 0; border: none; border-radius: var(--radius-md); cursor: pointer; background: transparent;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Choose a visual identifier</span>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;" class="mb-4">
            <div style="display: flex; align-items: center;">
                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="is_paid" value="1" checked style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Is this a Paid Leave?
                </label>
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Status</label>
                <select name="status" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Save Leave Policy</button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
