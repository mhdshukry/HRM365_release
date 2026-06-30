<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

/** @var array<string, mixed> $currentUser */

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$date_filter = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
$branch_filter = intval($_GET['branch_id'] ?? 0);
$branches = $pdo->query("SELECT id, name FROM branches WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$branchSql = '';
$params = [$date_filter];
if ($branch_filter > 0) {
    $branchSql = ' AND br.id = ?';
    $params[] = $branch_filter;
}

// Fetch raw biometric punches for the given date
$stmt = $pdo->prepare("
    SELECT b.*, COALESCE(b.log_status, 'Pending') AS log_status, e.first_name, e.last_name, e.employee_code,
           br.name AS branch_name
    FROM biometric_punches b
    LEFT JOIN employees e ON b.biometric_user_id = e.biometric_user_id
        OR CONCAT('EMP-', b.biometric_user_id) = e.biometric_user_id
    LEFT JOIN branches br ON b.terminal_sn = br.biometric_terminal_sn
    WHERE DATE(b.punch_time) = ?
    {$branchSql}
    ORDER BY b.punch_time DESC
");
$stmt->execute($params);
$punches = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Biometric Device Logs (ADMS)</h1>
        <div class="page-subtitle">Raw hardware logs pushed from your branch devices.</div>
    </div>
    
    <div class="adms-header-actions">
        <form action="" method="GET" class="adms-filter-form">
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()" class="adms-control">
            <select name="branch_id" onchange="this.form.submit()" class="adms-control">
                <option value="0">All Branches</option>
                <?php foreach ($branches as $branch): ?>
                    <option value="<?php echo intval($branch['id']); ?>" <?php echo $branch_filter === intval($branch['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        
        <!-- Simulate Hardware Data (For Testing) -->
        <form action="simulate.php" method="POST" class="adms-action-form">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
            <button type="submit" class="btn btn-secondary" title="Inject test data">
                <i class="fas fa-flask"></i> Simulate Device Push
            </button>
        </form>

        <form action="sync.php" method="POST" class="adms-action-form">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sync fa-spin-hover"></i> Sync & Calculate Math
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Terminal / Device SN</th>
                    <th>Biometric User ID</th>
                    <th>Employee Mapping</th>
                    <th>Exact Punch Time</th>
                    <th>Direction (State)</th>
                    <th>Log Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($punches as $p): ?>
                <tr>
                    <td>
                        <div style="font-family: monospace; font-size: 0.85rem; color: var(--text-muted); background: var(--bg-hover); padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block;">
                            <i class="fas fa-microchip"></i> <?php echo htmlspecialchars($p['terminal_sn'] ?? 'N/A'); ?>
                        </div>
                        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.25rem;"><?php echo htmlspecialchars($p['branch_name'] ?? 'No branch'); ?></div>
                    </td>
                    <td>
                        <span style="font-weight: bold; color: var(--accent-primary);"><?php echo htmlspecialchars($p['biometric_user_id']); ?></span>
                    </td>
                    <td>
                        <?php if ($p['first_name']): ?>
                            <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($p['employee_code']); ?></div>
                        <?php else: ?>
                            <span style="color: var(--accent-danger); font-size: 0.85rem;"><i class="fas fa-exclamation-triangle"></i> Unmapped User</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-family: monospace; font-size: 1rem; color: var(--text-primary);">
                        <?php echo date('H:i:s', strtotime($p['punch_time'])); ?>
                    </td>
                    <td>
                        <?php if ($p['punch_direction'] === 'CHECK_IN'): ?>
                            <span style="color: var(--accent-success); font-weight: bold;"><i class="fas fa-sign-in-alt"></i> IN</span>
                        <?php elseif ($p['punch_direction'] === 'CHECK_OUT'): ?>
                            <span style="color: var(--accent-danger); font-weight: bold;"><i class="fas fa-sign-out-alt"></i> OUT</span>
                        <?php else: ?>
                            <span style="color: var(--text-muted);"><i class="fas fa-fingerprint"></i> <?php echo htmlspecialchars($p['punch_direction']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (in_array($p['log_status'], ['Clock In', 'Sign-In'], true)): ?>
                            <span style="color: var(--accent-success); font-size: 0.85rem;"><i class="fas fa-sign-in-alt"></i> Sign-In</span>
                        <?php elseif (in_array($p['log_status'], ['Clock Out', 'Sign-Out'], true)): ?>
                            <span style="color: var(--accent-warning); font-size: 0.85rem;"><i class="fas fa-sign-out-alt"></i> Sign-Out</span>
                        <?php elseif ($p['log_status'] === 'Redundant'): ?>
                            <span style="color: var(--text-muted); font-size: 0.85rem;"><i class="fas fa-ban"></i> Redundant</span>
                        <?php elseif ($p['log_status'] === 'Unmapped'): ?>
                            <span style="color: var(--accent-danger); font-size: 0.85rem;"><i class="fas fa-exclamation-triangle"></i> Unmapped</span>
                        <?php elseif ($p['log_status'] === 'Holiday'): ?>
                            <span style="color: var(--accent-info); font-size: 0.85rem;"><i class="fas fa-calendar-day"></i> Holiday Blocked</span>
                        <?php elseif ($p['log_status'] === 'On Leave'): ?>
                            <span style="color: var(--accent-info); font-size: 0.85rem;"><i class="fas fa-umbrella-beach"></i> Leave Blocked</span>
                        <?php elseif ($p['log_status'] === 'No Shift'): ?>
                            <span style="color: var(--text-muted); font-size: 0.85rem;"><i class="fas fa-calendar-times"></i> No Shift</span>
                        <?php else: ?>
                            <span style="color: var(--accent-warning); font-size: 0.85rem;"><i class="fas fa-clock"></i> Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($punches)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No biometric logs found for this date.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
