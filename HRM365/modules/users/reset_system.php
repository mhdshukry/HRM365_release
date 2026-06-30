<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($currentUser['role'] !== 'admin') {
    die("Unauthorized access.");
}

$preservedTables = [
    'branches',
    'shifts',
    'shift_weekly_schedules',
    'attendance_policies',
    'leave_types',
    'leave_policies',
    'system_settings',
    'holidays',
    'holiday_branches',
];

$clearedTables = [
    'documents',
    'attendance_regularizations',
    'attendance_records',
    'biometric_punches',
    'employee_shift_overrides',
    'employee_bank_details',
    'leave_applications',
    'leave_balances',
    'advance_payments',
    'payroll_records',
    'meetings',
    'leave_requests',
    'payroll',
    'audit_logs',
    'employees',
    'users',
];

function reset_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return intval($stmt->fetchColumn()) > 0;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmation = trim($_POST['confirmation'] ?? '');
    $adminUsername = trim($_POST['admin_username'] ?? 'admin');
    $adminFullName = trim($_POST['admin_full_name'] ?? 'System Administrator');
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminPasswordConfirm = $_POST['admin_password_confirm'] ?? '';

    if ($confirmation !== 'RESET HRM365') {
        $error = 'Type RESET HRM365 exactly to confirm this cleanup.';
    } elseif ($adminUsername === '' || $adminFullName === '' || $adminPassword === '') {
        $error = 'Admin username, full name, and password are required.';
    } elseif ($adminPassword !== $adminPasswordConfirm) {
        $error = 'Admin password confirmation does not match.';
    } elseif (strlen($adminPassword) < 8) {
        $error = 'Admin password must be at least 8 characters.';
    } else {
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($clearedTables as $table) {
                if (!reset_table_exists($pdo, $table)) {
                    continue;
                }

                $quotedTable = '`' . str_replace('`', '``', $table) . '`';
                $pdo->exec("DELETE FROM {$quotedTable}");
                $pdo->exec("ALTER TABLE {$quotedTable} AUTO_INCREMENT = 1");
            }

            $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (id, username, password, full_name, role, status, department, employee_id)
                VALUES (1, ?, ?, ?, 'admin', 'Active', NULL, NULL)
            ");
            $stmt->execute([$adminUsername, $passwordHash, $adminFullName]);

            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            log_action(
                $pdo,
                1,
                'SYSTEM_PEOPLE_DATA_RESET',
                'Cleared employee, user, attendance, leave, payroll, meeting, biometric, document, and audit data; recreated single admin user.'
            );

            $message = 'People and transaction data were cleared. Only the new admin login remains.';
        } catch (Throwable $e) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (Throwable $ignored) {
            }
            $error = 'Reset failed: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reset People Data</h1>
        <div class="page-subtitle">Clear employee and transaction data while keeping company setup and policies.</div>
    </div>
    <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
        <i class="fas fa-arrow-left"></i> Back to Users
    </a>
</div>

<?php if ($message): ?>
    <div class="card" style="border-left: 4px solid var(--accent-success); margin-bottom: 1rem;">
        <strong style="color: var(--accent-success);">Reset complete.</strong>
        <div style="color: var(--text-secondary); margin-top: 0.35rem;"><?php echo htmlspecialchars($message); ?></div>
        <div style="margin-top: 1rem;">
            <a href="../../logout.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Log in with New Admin</a>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="card" style="border-left: 4px solid var(--accent-danger); margin-bottom: 1rem;">
        <strong style="color: var(--accent-danger);">Reset not completed.</strong>
        <div style="color: var(--text-secondary); margin-top: 0.35rem;"><?php echo htmlspecialchars($error); ?></div>
    </div>
<?php endif; ?>

<div class="card" style="margin-bottom: 1rem;">
    <h3 style="margin-top: 0;">This will be deleted</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem;">
        <?php foreach ($clearedTables as $table): ?>
            <div style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-secondary);">
                <i class="fas fa-trash-alt" style="color: var(--accent-danger);"></i>
                <?php echo htmlspecialchars($table); ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card" style="margin-bottom: 1rem;">
    <h3 style="margin-top: 0;">This will stay</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.75rem;">
        <?php foreach ($preservedTables as $table): ?>
            <div style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-secondary);">
                <i class="fas fa-check-circle" style="color: var(--accent-success);"></i>
                <?php echo htmlspecialchars($table); ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <form method="POST" style="display: grid; gap: 1rem; max-width: 680px;">
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">New Admin Username</label>
            <input type="text" name="admin_username" value="admin" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary); color: var(--text-primary);">
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">New Admin Full Name</label>
            <input type="text" name="admin_full_name" value="System Administrator" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary); color: var(--text-primary);">
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
            <div>
                <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">New Admin Password</label>
                <input type="password" name="admin_password" required minlength="8" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary); color: var(--text-primary);">
            </div>
            <div>
                <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">Confirm Password</label>
                <input type="password" name="admin_password_confirm" required minlength="8" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-secondary); color: var(--text-primary);">
            </div>
        </div>
        <div>
            <label style="display: block; margin-bottom: 0.4rem; color: var(--text-secondary);">Confirmation Text</label>
            <input type="text" name="confirmation" placeholder="RESET HRM365" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--accent-danger); border-radius: var(--radius-md); background: var(--bg-secondary); color: var(--text-primary);">
        </div>
        <button type="submit" class="btn" style="background: var(--accent-danger); color: #fff; justify-self: start;" onclick="return confirm('This permanently clears people and transaction data. Continue?');">
            <i class="fas fa-trash-alt"></i> Clear Data and Create Single Admin
        </button>
    </form>
</div>

<?php include '../../includes/footer.php'; ?>
