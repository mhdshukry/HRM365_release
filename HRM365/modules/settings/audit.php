<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if ($currentUser['role'] !== 'admin') {
    die("Unauthorized access to audit logs.");
}

$action_filter = trim($_GET['action'] ?? '');
$user_filter = trim($_GET['user'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

if ($action_filter !== '') {
    $where[] = 'a.action LIKE ?';
    $params[] = '%' . $action_filter . '%';
}

if ($user_filter !== '') {
    $where[] = '(u.username LIKE ? OR a.user_id = ?)';
    $params[] = '%' . $user_filter . '%';
    $params[] = ctype_digit($user_filter) ? intval($user_filter) : -1;
}

if ($date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $where[] = 'DATE(a.created_at) >= ?';
    $params[] = $date_from;
}

if ($date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $where[] = 'DATE(a.created_at) <= ?';
    $params[] = $date_to;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    {$whereSql}
    ORDER BY a.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Compliance Audit Logs</h1>
        <div class="page-subtitle">Immutable cryptographic ledger of system activity.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back to Settings
    </a>
</div>

<div class="card">
    <form method="GET" style="display: grid; grid-template-columns: 1.4fr 1fr 1fr 1fr auto; gap: 0.75rem; align-items: end; margin-bottom: 1.5rem;">
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.85rem;">Action</label>
            <input list="auditActions" type="text" name="action" value="<?php echo htmlspecialchars($action_filter); ?>" placeholder="e.g. PAYROLL" style="width: 100%; padding: 0.65rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            <datalist id="auditActions">
                <?php foreach ($actions as $action): ?>
                    <option value="<?php echo htmlspecialchars($action); ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.85rem;">User</label>
            <input type="text" name="user" value="<?php echo htmlspecialchars($user_filter); ?>" placeholder="username or ID" style="width: 100%; padding: 0.65rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.85rem;">From</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="width: 100%; padding: 0.65rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary); font-size: 0.85rem;">To</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" style="width: 100%; padding: 0.65rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
            <a href="audit.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);"><i class="fas fa-times"></i></a>
        </div>
    </form>

    <div style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
        Showing latest <?php echo count($logs); ?> matching audit event(s), capped at 100.
    </div>

    <div class="table-container">
        <table class="table" style="font-size: 0.85rem;">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="color: var(--text-muted);"><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($log['username'] ?? 'SYSTEM'); ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;"><?php echo htmlspecialchars($log['role'] ?? 'N/A'); ?></div>
                    </td>
                    <td><span class="status-badge" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); border-radius: 4px; padding: 0.2rem 0.5rem;"><?php echo htmlspecialchars($log['action']); ?></span></td>
                    <td style="color: var(--text-secondary);"><?php echo htmlspecialchars($log['details']); ?></td>
                    <td><code style="background: var(--bg-hover); padding: 0.2rem 0.4rem; border-radius: 4px;"><?php echo htmlspecialchars($log['ip_address']); ?></code></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No audit logs recorded yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
