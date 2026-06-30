<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT r.*, e.first_name, e.last_name, e.employee_code
    FROM attendance_records r
    JOIN employees e ON e.id = r.employee_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Attendance record not found.");
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Correct Attendance</h1>
        <div class="page-subtitle">Update sign-in/sign-out times and recalculate attendance math.</div>
    </div>
    <a href="index.php?date=<?php echo urlencode($record['date']); ?>" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="card" style="max-width: 680px; margin: 0 auto;">
    <form action="save_correction.php" method="POST">
        <input type="hidden" name="id" value="<?php echo intval($record['id']); ?>">

        <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
            <div style="font-weight: 700; color: var(--text-primary);"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></div>
            <div style="color: var(--text-muted); font-size: 0.85rem;"><?php echo htmlspecialchars($record['employee_code']); ?> · <?php echo htmlspecialchars($record['date']); ?></div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;" class="mb-4">
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Sign-In</label>
                <input type="datetime-local" name="clock_in" required value="<?php echo $record['clock_in'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($record['clock_in']))) : ''; ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Sign-Out</label>
                <input type="datetime-local" name="clock_out" required value="<?php echo $record['clock_out'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($record['clock_out']))) : ''; ?>" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
            </div>
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Correction Note</label>
            <textarea name="notes" rows="3" required placeholder="Reason for this correction..." style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;"><?php echo htmlspecialchars($record['notes'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;"><i class="fas fa-calculator"></i> Save & Recalculate</button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
