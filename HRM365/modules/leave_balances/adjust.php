<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT 
        b.*, 
        e.first_name, e.last_name, e.employee_code,
        t.name as leave_type_name
    FROM leave_balances b
    JOIN employees e ON b.employee_id = e.id
    JOIN leave_types t ON b.leave_type_id = t.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$balance = $stmt->fetch();

if (!$balance) {
    die("Ledger record not found.");
}

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Manual Balance Adjustment</h1>
        <div class="page-subtitle">Inject positive or negative math into an employee's ledger.</div>
    </div>
    <a href="index.php?year=<?php echo $balance['year']; ?>" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back to Ledger
    </a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="background: var(--bg-hover); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 2rem;">
        <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);">Target Ledger Information</h4>
        <p style="margin: 0; color: var(--text-secondary); font-size: 0.9rem;">
            <strong>Employee:</strong> <?php echo htmlspecialchars($balance['first_name'] . ' ' . $balance['last_name'] . ' (' . $balance['employee_code'] . ')'); ?><br>
            <strong>Leave Target:</strong> <?php echo htmlspecialchars($balance['leave_type_name']); ?><br>
            <strong>Year:</strong> <?php echo $balance['year']; ?>
        </p>
    </div>

    <form action="save_adjustment.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $balance['id']; ?>">
        
        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Current Manual Adjustment Value</label>
            <input type="text" value="<?php echo floatval($balance['manual_adjustment']); ?>" disabled style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; opacity: 0.7;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--accent-primary); font-size: 0.9rem; font-weight: 600;">New Adjustment Injection *</label>
            <small style="color: var(--text-muted); display: block; margin-bottom: 0.5rem;">Use positive numbers (e.g. `2`) to grant extra days. Use negative numbers (e.g. `-1`) to dock days.</small>
            <input type="number" step="0.25" name="manual_adjustment" required placeholder="e.g. 2.5 or -1" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 2px solid var(--accent-primary); background: var(--bg-secondary); color: var(--text-primary); outline: none; font-size: 1.1rem; font-weight: 700;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Adjustment Reason (Audit Log) *</label>
            <textarea name="adjustment_reason" required rows="3" placeholder="Provide a detailed administrative reason for this ledger injection..." style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem;"><i class="fas fa-balance-scale"></i> Apply Mathematical Adjustment</button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
