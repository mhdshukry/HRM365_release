<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $firstBranchId = intval($pdo->query("SELECT id FROM branches ORDER BY status ASC, name ASC LIMIT 1")->fetchColumn() ?: 0);
    if ($firstBranchId > 0) {
        header("Location: view.php?id={$firstBranchId}");
        exit();
    }
    header("Location: index.php");
    exit();
}

$date_filter = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');

$stmt = $pdo->prepare("SELECT * FROM branches WHERE id = ?");
$stmt->execute([$id]);
$branch = $stmt->fetch();

if (!$branch) {
    die("Branch not found.");
}

$formatLogStatus = function (?string $status): string {
    if ($status === 'Clock In' || $status === 'Sign-In') {
        return 'Sign-In';
    }
    if ($status === 'Clock Out' || $status === 'Sign-Out') {
        return 'Sign-Out';
    }
    return $status ?: 'Pending';
};

$employeeStmt = $pdo->prepare("
    SELECT id, employee_code, first_name, last_name, department, designation, status, shift_id
    FROM employees
    WHERE branch_id = ?
    ORDER BY first_name ASC, last_name ASC
");
$employeeStmt->execute([$id]);
$employees = $employeeStmt->fetchAll();

$terminalSn = trim($branch['biometric_terminal_sn'] ?? '');

$punches = [];
if ($terminalSn !== '') {
    $punchStmt = $pdo->prepare("
        SELECT b.*, e.first_name, e.last_name, e.employee_code
        FROM biometric_punches b
        LEFT JOIN employees e ON b.biometric_user_id = e.biometric_user_id
            OR CONCAT('EMP-', b.biometric_user_id) = e.biometric_user_id
        WHERE b.terminal_sn = ?
          AND DATE(b.punch_time) = ?
        ORDER BY b.punch_time DESC
        LIMIT 100
    ");
    $punchStmt->execute([$terminalSn, $date_filter]);
    $punches = $punchStmt->fetchAll();
}

$attendanceStmt = $pdo->prepare("
    SELECT r.*, e.first_name, e.last_name, e.employee_code, s.name AS shift_name
    FROM attendance_records r
    JOIN employees e ON r.employee_id = e.id
    LEFT JOIN shifts s ON r.shift_id = s.id
    WHERE e.branch_id = ?
      AND r.date = ?
    ORDER BY e.first_name ASC, e.last_name ASC
");
$attendanceStmt->execute([$id, $date_filter]);
$attendanceRecords = $attendanceStmt->fetchAll();

$activeEmployees = array_filter($employees, fn($employee) => ($employee['status'] ?? '') === 'Active');
$mappedPunches = array_filter($punches, fn($punch) => !empty($punch['employee_code']));
$unmappedPunches = array_filter($punches, fn($punch) => empty($punch['employee_code']));

include '../../includes/header.php';
?>

<style>
    .branch-dashboard-header-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    .branch-dashboard-date-form {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        margin: 0;
    }
    .branch-metric-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(180px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .branch-metric {
        min-height: 112px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border-left: 4px solid var(--accent-primary);
    }
    .branch-metric-success { border-left-color: var(--accent-success); }
    .branch-metric-warning { border-left-color: var(--accent-warning); }
    .branch-metric-danger { border-left-color: var(--accent-danger); }
    .branch-metric-label {
        color: var(--text-muted);
        font-size: 0.76rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .branch-metric-value {
        color: var(--text-primary);
        font-size: 1.8rem;
        font-weight: 800;
        line-height: 1.1;
        margin-top: 0.6rem;
        overflow-wrap: anywhere;
    }
    .branch-metric-code {
        font-size: 0.98rem;
        color: var(--accent-warning);
    }
    .branch-section-title {
        color: var(--accent-primary);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
    }
    .branch-dashboard-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 1.5rem;
        align-items: start;
    }
    .branch-table-card {
        min-width: 0;
    }
    @media (max-width: 1200px) {
        .branch-metric-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .branch-dashboard-grid {
            grid-template-columns: 1fr;
        }
    }
    @media (max-width: 720px) {
        .branch-dashboard-header-actions,
        .branch-dashboard-date-form {
            align-items: stretch;
            width: 100%;
            flex-direction: column;
        }
        .branch-metric-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><?php echo htmlspecialchars($branch['name']); ?></h1>
        <div class="page-subtitle">Branch employees, machine logs, and attendance for <?php echo htmlspecialchars($date_filter); ?>.</div>
    </div>
    <div class="branch-dashboard-header-actions">
        <form action="" method="GET" class="branch-dashboard-date-form">
            <input type="hidden" name="id" value="<?php echo intval($branch['id']); ?>">
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" onchange="this.form.submit()" style="padding: 0.5rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none; color-scheme: light;">
        </form>
        <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="branch-metric-grid">
    <div class="card branch-metric branch-metric-success">
        <div class="branch-metric-label">Active Employees</div>
        <div class="branch-metric-value"><?php echo count($activeEmployees); ?></div>
    </div>
    <div class="card branch-metric branch-metric-warning">
        <div class="branch-metric-label">Machine SN</div>
        <div class="branch-metric-value branch-metric-code"><?php echo htmlspecialchars($terminalSn ?: 'Not set'); ?></div>
    </div>
    <div class="card branch-metric branch-metric-success">
        <div class="branch-metric-label">Mapped Punches</div>
        <div class="branch-metric-value"><?php echo count($mappedPunches); ?></div>
    </div>
    <div class="card branch-metric branch-metric-danger">
        <div class="branch-metric-label">Unmapped Punches</div>
        <div class="branch-metric-value"><?php echo count($unmappedPunches); ?></div>
    </div>
</div>

<div class="card" style="margin-bottom: 1.5rem;">
    <h3 class="branch-section-title">Branch Employees</h3>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($employee['employee_code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($employee['department'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($employee['designation'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($employee['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?>
                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No employees assigned to this branch.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="branch-dashboard-grid">
    <div class="card branch-table-card">
        <h3 class="branch-section-title">Biometric Machine Logs</h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Machine ID</th>
                        <th>Employee</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($punches as $punch): ?>
                        <tr>
                            <td><?php echo date('H:i', strtotime($punch['punch_time'])); ?></td>
                            <td><?php echo htmlspecialchars($punch['biometric_user_id']); ?></td>
                            <td>
                                <?php if (!empty($punch['employee_code'])): ?>
                                    <?php echo htmlspecialchars($punch['first_name'] . ' ' . $punch['last_name']); ?>
                                    <div style="font-size: 0.72rem; color: var(--text-muted);"><?php echo htmlspecialchars($punch['employee_code']); ?></div>
                                <?php else: ?>
                                    <span style="color: var(--accent-danger);">Not mapped</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($formatLogStatus($punch['log_status'] ?? null)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($punches)): ?>
                        <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 1.5rem;"><?php echo $terminalSn === '' ? 'Set the branch machine serial number first.' : 'No machine logs for this date.'; ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card branch-table-card">
        <h3 class="branch-section-title">Daily Attendance</h3>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Sign-In</th>
                        <th>Sign-Out</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendanceRecords as $record): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                <div style="font-size: 0.72rem; color: var(--text-muted);"><?php echo htmlspecialchars($record['employee_code']); ?></div>
                            </td>
                            <td><?php echo $record['clock_in'] ? date('H:i', strtotime($record['clock_in'])) : '--:--'; ?></td>
                            <td><?php echo $record['clock_out'] ? date('H:i', strtotime($record['clock_out'])) : '--:--'; ?></td>
                            <td><?php echo number_format(floatval($record['total_hours']), 2); ?>h</td>
                            <td><?php echo htmlspecialchars($record['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($attendanceRecords)): ?>
                        <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">No attendance records for branch employees on this date.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
