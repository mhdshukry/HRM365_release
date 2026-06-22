<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR', 'manager'])) {
    die("Unauthorized access.");
}

// Fetch all holidays
$stmt = $pdo->query("SELECT * FROM holidays ORDER BY start_date ASC");
$holidays = $stmt->fetchAll();

$holidayCountry = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'holiday_country'")->fetchColumn();
if (!$holidayCountry) {
    $timezone = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'timezone'")->fetchColumn();
    $holidayCountry = $timezone === 'Asia/Colombo' ? 'LK' : 'US';
}

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Company Holidays</h1>
        <div class="page-subtitle">Configure regional and global holidays for payroll and attendance tracking.</div>
    </div>
    <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <form action="import_public.php" method="POST" style="margin: 0; display: flex; gap: 0.5rem; align-items: center;">
            <input type="number" name="year" value="<?php echo date('Y'); ?>" min="2000" max="2100" style="width: 90px; padding: 0.55rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            <button type="submit" class="btn" style="background: var(--bg-hover); color: var(--text-primary);" title="Import public holidays for <?php echo htmlspecialchars($holidayCountry); ?>">
                <i class="fas fa-cloud-download-alt"></i> Import <?php echo htmlspecialchars($holidayCountry); ?> Holidays
            </button>
        </form>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-calendar-plus"></i> Add Holiday
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Holiday Name</th>
                    <th>Date Range</th>
                    <th>Category</th>
                    <th>Payment Status</th>
                    <th>Recurrence</th>
                    <th>Locations</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($holidays as $h): ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($h['name']); ?></div>
                        <?php if ($h['is_half_day']): ?>
                            <span style="font-size: 0.7rem; background: var(--accent-warning); color: white; padding: 0.1rem 0.3rem; border-radius: 3px;">Half Day</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        echo date('M d, Y', strtotime($h['start_date'])); 
                        if ($h['end_date'] && $h['start_date'] !== $h['end_date']) {
                            echo ' - ' . date('M d, Y', strtotime($h['end_date']));
                        }
                        ?>
                    </td>
                    <td><span class="status-badge" style="text-transform: uppercase;"><?php echo htmlspecialchars($h['category']); ?></span></td>
                    <td>
                        <?php if ($h['is_paid']): ?>
                            <span class="status-badge status-active">Paid</span>
                        <?php else: ?>
                            <span class="status-badge status-leave">Unpaid</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($h['is_recurring']): ?>
                            <span style="color: var(--accent-primary);"><i class="fas fa-sync-alt"></i> Annual</span>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">One-time</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        if ($h['applies_to_all_branches']) {
                            echo '<span class="status-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success);">Global (All Branches)</span>';
                        } else {
                            // Fetch specific branches
                            $brStmt = $pdo->prepare("SELECT b.name FROM holiday_branches hb JOIN branches b ON hb.branch_id = b.id WHERE hb.holiday_id = ?");
                            $brStmt->execute([$h['id']]);
                            $branchNames = $brStmt->fetchAll(PDO::FETCH_COLUMN);
                            echo htmlspecialchars(implode(', ', $branchNames));
                        }
                        ?>
                    </td>
                    <td>
                        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
                        <div style="display: flex; gap: 0.5rem;">
                            <form action="delete.php" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this holiday?');">
                                <input type="hidden" name="id" value="<?php echo $h['id']; ?>">
                                <button type="submit" class="action-btn" style="color: var(--accent-danger);" title="Delete Holiday"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($holidays)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No holidays have been scheduled.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
