<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

// Fetch active branches for multi-select
$branchStmt = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC");
$branches = $branchStmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create Holiday</h1>
        <div class="page-subtitle">Schedule organizational off-days and geographic observances.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back to Calendar
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="save.php" method="POST">
        
        <!-- SECTION 1: Holiday Details -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-info-circle"></i> Holiday Details</h3>
        
        <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Holiday Name *</label>
                <input type="text" name="name" required placeholder="e.g. Thanksgiving Day" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Start Date *</label>
                    <input type="date" name="start_date" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">End Date (Optional for multi-day)</label>
                    <input type="date" name="end_date" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Category</label>
                    <select name="category" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                        <option value="National">National / Public Holiday</option>
                        <option value="Religious">Religious Observance</option>
                        <option value="Company-specific">Company Specific Event</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Description (Optional)</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
            </div>
        </div>

        <!-- SECTION 2: Rules & Targeting -->
        <h3 class="mb-4" style="color: var(--accent-primary); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;"><i class="fas fa-cogs"></i> Policies & Targeting</h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="is_recurring" value="1" style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Is Recurring Annually?
                </label>
                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="is_paid" value="1" checked style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Is Paid Holiday?
                </label>
                <label style="display: flex; align-items: center; gap: 0.75rem; color: var(--text-primary); cursor: pointer;">
                    <input type="checkbox" name="is_half_day" value="1" style="width: 1.2rem; height: 1.2rem; accent-color: var(--accent-primary);">
                    Is Half Day?
                </label>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Target Branches (Geographic Mapping)</label>
                <select name="branches[]" multiple style="width: 100%; height: 120px; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
                    <?php foreach ($branches as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Hold CMD/CTRL to select multiple. Leave completely unselected to apply globally to ALL branches.</small>
            </div>
        </div>

        <div style="text-align: right; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">
                <i class="fas fa-calendar-check"></i> Create Holiday
            </button>
        </div>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
