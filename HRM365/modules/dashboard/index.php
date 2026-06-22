<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

/** @var array<string, mixed> $currentUser */

$isHR = in_array($currentUser['role'], ['admin', 'HR']);

$metrics = [];
if ($isHR) {
    $metrics['total_emps'] = intval($pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn());
    $metrics['present_today'] = intval($pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_records WHERE date = CURDATE() AND clock_in IS NOT NULL")->fetchColumn());
    $metrics['pending_leaves'] = intval($pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Pending'")->fetchColumn());
    $metrics['unsynced_bio'] = intval($pdo->query("SELECT COUNT(*) FROM biometric_punches WHERE is_synced = 0")->fetchColumn());
}

$today = date('Y-m-d');
$employeeId = $currentUser['employee_id'] ?? null;
$stmt = $pdo->prepare("SELECT * FROM attendance_records WHERE employee_id = ? AND date = ?");
$stmt->execute([$employeeId, $today]);
$record = $stmt->fetch();

$isClockedIn = false;
$isClockedOut = false;

if ($record) {
    if (!empty($record['clock_in']) && empty($record['clock_out'])) {
        $isClockedIn = true;
    } elseif (!empty($record['clock_in']) && !empty($record['clock_out'])) {
        $isClockedOut = true;
    }
}

$empStmt = $pdo->prepare("SELECT id, first_name, shift_id, branch_id FROM employees WHERE id = ?");
$empStmt->execute([$employeeId]);
$emp = $empStmt->fetch();
$blockStatus = null;
if ($emp) {
    $branchId = $emp['branch_id'] !== null ? intval($emp['branch_id']) : null;
    $blockStatus = get_attendance_block_status($pdo, intval($emp['id']), $today, $branchId);
}

$recentActivity = [];
if ($isHR) {
    $recentActivity = $pdo->query("
        SELECT a.date, a.clock_in, a.clock_out, a.status, e.employee_code, e.first_name, e.last_name
        FROM attendance_records a
        JOIN employees e ON a.employee_id = e.id
        ORDER BY COALESCE(a.clock_out, a.clock_in, a.created_at) DESC
        LIMIT 5
    ")->fetchAll();
} elseif ($employeeId) {
    $activityStmt = $pdo->prepare("
        SELECT a.date, a.clock_in, a.clock_out, a.status, e.employee_code, e.first_name, e.last_name
        FROM attendance_records a
        JOIN employees e ON a.employee_id = e.id
        WHERE a.employee_id = ?
        ORDER BY COALESCE(a.clock_out, a.clock_in, a.created_at) DESC
        LIMIT 5
    ");
    $activityStmt->execute([$employeeId]);
    $recentActivity = $activityStmt->fetchAll();
}

$eventParams = [];
$eventScope = '';
if ($currentUser['role'] === 'employee') {
    $eventScope = ' AND m.organizer_id = ?';
    $eventParams[] = $employeeId ?? 0;
} elseif ($currentUser['role'] === 'manager') {
    $eventScope = ' AND e.department = ?';
    $eventParams[] = $currentUser['department'] ?? '';
}
$eventStmt = $pdo->prepare("
    SELECT m.title, m.start_time, m.location, e.first_name, e.last_name
    FROM meetings m
    JOIN employees e ON m.organizer_id = e.id
    WHERE m.status = 'Scheduled'
      AND m.start_time >= NOW()
      {$eventScope}
    ORDER BY m.start_time ASC
    LIMIT 5
");
$eventStmt->execute($eventParams);
$upcomingEvents = $eventStmt->fetchAll();

include '../../includes/header.php';
?>

<div class="dashboard-header">
    <div>
        <h1 class="page-title">Welcome back, <?php echo htmlspecialchars($emp ? $emp['first_name'] : $currentUser['username']); ?></h1>
        <div class="page-subtitle"><?php echo date('l, F j, Y'); ?></div>
    </div>
</div>

<?php if (($_GET['error'] ?? '') === 'blocked'): ?>
<div class="alert alert-warning" style="margin-bottom: 1rem;">
    <i class="fas fa-ban"></i>
    Punch blocked: <?php echo htmlspecialchars($_GET['reason'] ?? 'Not allowed today'); ?>.
</div>
<?php endif; ?>

<?php if ($isHR): ?>
<div class="dashboard-metrics">
    <div class="dashboard-metric dashboard-metric-blue">
        <div class="dashboard-metric-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="dashboard-metric-label">Active Employees</div>
            <div class="dashboard-metric-value"><?php echo $metrics['total_emps']; ?></div>
        </div>
    </div>

    <div class="dashboard-metric dashboard-metric-green">
        <div class="dashboard-metric-icon"><i class="fas fa-user-check"></i></div>
        <div>
            <div class="dashboard-metric-label">Present Today</div>
            <div class="dashboard-metric-value"><?php echo $metrics['present_today']; ?> <span>/ <?php echo $metrics['total_emps']; ?></span></div>
        </div>
    </div>

    <a href="<?php echo app_url('modules/leave_applications/index.php'); ?>" class="dashboard-metric dashboard-metric-amber">
        <div class="dashboard-metric-icon"><i class="fas fa-paper-plane"></i></div>
        <div>
            <div class="dashboard-metric-label">Pending Leaves</div>
            <div class="dashboard-metric-value"><?php echo $metrics['pending_leaves']; ?></div>
        </div>
    </a>

    <a href="<?php echo app_url('modules/biometric_records/index.php'); ?>" class="dashboard-metric dashboard-metric-violet">
        <div class="dashboard-metric-icon"><i class="fas fa-fingerprint"></i></div>
        <div>
            <div class="dashboard-metric-label">Unsynced Punches</div>
            <div class="dashboard-metric-value"><?php echo $metrics['unsynced_bio']; ?></div>
        </div>
    </a>
</div>
<?php endif; ?>

<div class="dashboard-layout">
    <section class="dashboard-clock-card">
        <div class="dashboard-card-title">
            <i class="fas fa-clock"></i>
            <span>Time & Attendance</span>
        </div>

        <div id="liveClock" class="dashboard-clock"><?php echo date('H:i:s'); ?></div>

        <div class="dashboard-clock-status">
            <?php if (!$employeeId || !$emp): ?>
                <span class="dashboard-state"><i class="fas fa-user-slash"></i> Employee Profile Not Linked</span>
            <?php elseif ($blockStatus !== null): ?>
                <span class="dashboard-state dashboard-state-info"><i class="fas fa-ban"></i> Punch Blocked: <?php echo htmlspecialchars($blockStatus); ?></span>
            <?php elseif ($record && $record['status'] === 'Absent'): ?>
                <span class="dashboard-state dashboard-state-danger"><i class="fas fa-user-times"></i> Absent</span>
            <?php elseif ($isClockedOut): ?>
                <span class="dashboard-state dashboard-state-success"><i class="fas fa-check-circle"></i> Shift Completed</span>
            <?php elseif ($isClockedIn): ?>
                <span class="dashboard-state dashboard-state-info"><i class="fas fa-spinner fa-spin"></i> Shift in Progress</span>
            <?php elseif ($record): ?>
                <span class="dashboard-state"><i class="fas fa-clock"></i> <?php echo htmlspecialchars($record['status']); ?></span>
            <?php else: ?>
                <span class="dashboard-state"><i class="fas fa-fingerprint"></i> No punch recorded</span>
            <?php endif; ?>
        </div>

        <div class="dashboard-attendance-panel">
            <div class="dashboard-attendance-row">
                <span>Clock In</span>
                <strong><?php echo !empty($record['clock_in']) ? date('h:i A', strtotime($record['clock_in'])) : '--:--'; ?></strong>
            </div>
            <div class="dashboard-attendance-row">
                <span>Clock Out</span>
                <strong><?php echo !empty($record['clock_out']) ? date('h:i A', strtotime($record['clock_out'])) : '--:--'; ?></strong>
            </div>
            <div class="dashboard-attendance-row">
                <span>Total Time</span>
                <strong><?php echo $record ? number_format(floatval($record['total_hours']), 2) . 'h' : '0.00h'; ?></strong>
            </div>
            <div class="dashboard-attendance-row">
                <span>Status</span>
                <strong><?php echo htmlspecialchars($record['status'] ?? 'Not Recorded'); ?></strong>
            </div>
            <div class="dashboard-attendance-flags">
                <?php if ($record && intval($record['is_late'])): ?><span class="dashboard-flag dashboard-flag-danger">Late</span><?php endif; ?>
                <?php if ($record && intval($record['is_early_departure'])): ?><span class="dashboard-flag dashboard-flag-warning">Early Out</span><?php endif; ?>
                <?php if ($record && intval($record['is_absent'])): ?><span class="dashboard-flag dashboard-flag-danger">No Punch</span><?php endif; ?>
                <?php if ($record && intval($record['is_holiday'])): ?><span class="dashboard-flag dashboard-flag-info">Holiday</span><?php endif; ?>
                <?php if ($record && intval($record['is_weekend'])): ?><span class="dashboard-flag">Weekend</span><?php endif; ?>
                <?php if (!$record || (!intval($record['is_late']) && !intval($record['is_early_departure']) && !intval($record['is_absent']) && !intval($record['is_holiday']) && !intval($record['is_weekend']))): ?>
                    <span class="dashboard-flag">No Flags</span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="dashboard-main-column">
        <div class="dashboard-leave-banner">
            <div class="dashboard-leave-copy">
                <i class="fas fa-umbrella-beach"></i>
                <div>
                    <h3>Need a Break?</h3>
                    <p>Request time off through the Leave Portal. Balances are tracked automatically.</p>
                </div>
            </div>
            <a href="<?php echo app_url('modules/leave_applications/request_leave.php'); ?>" class="dashboard-banner-action">Request Leave</a>
        </div>

        <div class="dashboard-widgets">
            <div class="dashboard-widget">
                <div class="dashboard-widget-title">
                    <span><i class="fas fa-history"></i> Recent Attendance</span>
                </div>
                <?php if (!empty($recentActivity)): ?>
                    <div class="dashboard-list">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="dashboard-list-row">
                                <div>
                                    <div class="dashboard-list-title"><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></div>
                                    <div class="dashboard-list-meta"><?php echo htmlspecialchars($activity['employee_code']); ?> &middot; <?php echo date('M d, Y', strtotime($activity['date'])); ?></div>
                                </div>
                                <div class="dashboard-list-time">
                                    <div><?php echo $activity['clock_in'] ? date('H:i', strtotime($activity['clock_in'])) : '--:--'; ?> &rarr; <?php echo $activity['clock_out'] ? date('H:i', strtotime($activity['clock_out'])) : '--:--'; ?></div>
                                    <span class="status-badge status-active"><?php echo htmlspecialchars($activity['status']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty">
                        <i class="fas fa-calendar-times"></i>
                        <span>No attendance activity yet.</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-widget">
                <div class="dashboard-widget-title dashboard-widget-title-info">
                    <span><i class="fas fa-calendar-alt"></i> Upcoming Events</span>
                </div>
                <?php if (!empty($upcomingEvents)): ?>
                    <div class="dashboard-list">
                        <?php foreach ($upcomingEvents as $event): ?>
                            <div class="dashboard-list-row">
                                <div>
                                    <div class="dashboard-list-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                    <div class="dashboard-list-meta"><?php echo date('M d, Y h:i A', strtotime($event['start_time'])); ?></div>
                                    <div class="dashboard-list-meta"><?php echo htmlspecialchars($event['location'] ?: 'No location'); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="dashboard-empty">
                        <i class="fas fa-calendar-times"></i>
                        <span>No upcoming events scheduled.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
    setInterval(() => {
        const d = new Date();
        const clock = document.getElementById('liveClock');
        if (clock) {
            clock.innerText = d.toLocaleTimeString('en-GB', { hour12: false });
        }
    }, 1000);
</script>

<?php include '../../includes/footer.php'; ?>
