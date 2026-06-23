<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';

/** @var array<string, mixed> $currentUser */

$isHR = in_array($currentUser['role'], ['admin', 'HR'], true);
$isManager = $currentUser['role'] === 'manager';
$canViewTeamDashboard = $isHR || $isManager;
$department = $currentUser['department'] ?? '';

$metrics = [];
$chartLabels = [];
$chartPresent = [];
$chartLate = [];

if ($canViewTeamDashboard) {
    $scopeJoin = '';
    $scopeWhere = '';
    $scopeParams = [];

    if ($isManager) {
        $scopeJoin = ' JOIN employees e ON e.id = a.employee_id';
        $scopeWhere = ' AND e.department = ?';
        $scopeParams[] = $department;

        $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE status = 'Active' AND department = ?");
        $totalStmt->execute([$department]);
        $metrics['total_emps'] = intval($totalStmt->fetchColumn());

        $presentStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT a.employee_id)
            FROM attendance_records a
            JOIN employees e ON e.id = a.employee_id
            WHERE a.date = CURDATE() AND a.clock_in IS NOT NULL AND e.department = ?
        ");
        $presentStmt->execute([$department]);
        $metrics['present_today'] = intval($presentStmt->fetchColumn());

        $leaveStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM leave_applications la
            JOIN employees e ON e.id = la.employee_id
            WHERE la.status = 'Approved'
              AND CURDATE() BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
              AND e.department = ?
        ");
        $leaveStmt->execute([$department]);
        $metrics['on_leave_today'] = intval($leaveStmt->fetchColumn());

        $pendingStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM leave_applications la
            JOIN employees e ON e.id = la.employee_id
            WHERE la.status = 'Pending' AND e.department = ?
        ");
        $pendingStmt->execute([$department]);
        $metrics['pending_leaves'] = intval($pendingStmt->fetchColumn());
    } else {
        $metrics['total_emps'] = intval($pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn());
        $metrics['present_today'] = intval($pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_records WHERE date = CURDATE() AND clock_in IS NOT NULL")->fetchColumn());
        $metrics['on_leave_today'] = intval($pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND COALESCE(end_date, start_date)")->fetchColumn());
        $metrics['pending_leaves'] = intval($pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Pending'")->fetchColumn());
    }

    $trendStmt = $pdo->prepare("
        SELECT a.date,
               COUNT(DISTINCT a.employee_id) AS present_count,
               SUM(CASE WHEN a.is_late = 1 THEN 1 ELSE 0 END) AS late_count
        FROM attendance_records a
        {$scopeJoin}
        WHERE a.date BETWEEN ? AND ?
          {$scopeWhere}
        GROUP BY a.date
    ");
    $trendStart = date('Y-m-d', strtotime('-14 days'));
    $trendEnd = date('Y-m-d');
    $trendStmt->execute(array_merge([$trendStart, $trendEnd], $scopeParams));
    $trendRows = [];
    foreach ($trendStmt->fetchAll() as $row) {
        $trendRows[$row['date']] = $row;
    }

    for ($i = 14; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        if (in_array(date('N', strtotime($date)), ['6', '7'], true)) {
            continue;
        }

        $chartLabels[] = date('M j', strtotime($date));
        $chartPresent[] = isset($trendRows[$date]) ? intval($trendRows[$date]['present_count']) : 0;
        $chartLate[] = isset($trendRows[$date]) ? intval($trendRows[$date]['late_count']) : 0;
    }
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
} elseif ($isManager) {
    $activityStmt = $pdo->prepare("
        SELECT a.date, a.clock_in, a.clock_out, a.status, e.employee_code, e.first_name, e.last_name
        FROM attendance_records a
        JOIN employees e ON a.employee_id = e.id
        WHERE e.department = ?
        ORDER BY COALESCE(a.clock_out, a.clock_in, a.created_at) DESC
        LIMIT 5
    ");
    $activityStmt->execute([$department]);
    $recentActivity = $activityStmt->fetchAll();
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

$todayLeaveRows = [];
$pendingApprovalRows = [];

if ($isHR) {
    $todayLeaveRows = $pdo->query("
        SELECT la.id, la.start_date, la.end_date, la.total_days, lt.name AS leave_type,
               e.first_name, e.last_name, e.employee_code
        FROM leave_applications la
        JOIN employees e ON e.id = la.employee_id
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.status = 'Approved'
          AND CURDATE() BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
        ORDER BY la.start_date ASC, e.first_name ASC
        LIMIT 5
    ")->fetchAll();

    $pendingStmt = $pdo->query("
        SELECT 'Leave' AS item_type, la.id, la.created_at, la.start_date AS item_date,
               lt.name AS item_title, e.first_name, e.last_name, e.employee_code
        FROM leave_applications la
        JOIN employees e ON e.id = la.employee_id
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.status = 'Pending'
        UNION ALL
        SELECT 'Regularization' AS item_type, ar.id, ar.created_at, ar.date AS item_date,
               ar.reason AS item_title, e.first_name, e.last_name, e.employee_code
        FROM attendance_regularizations ar
        JOIN employees e ON e.id = ar.employee_id
        WHERE ar.status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $pendingApprovalRows = $pendingStmt->fetchAll();
} elseif ($isManager) {
    $leaveStmt = $pdo->prepare("
        SELECT la.id, la.start_date, la.end_date, la.total_days, lt.name AS leave_type,
               e.first_name, e.last_name, e.employee_code
        FROM leave_applications la
        JOIN employees e ON e.id = la.employee_id
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.status = 'Approved'
          AND CURDATE() BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
          AND e.department = ?
        ORDER BY la.start_date ASC, e.first_name ASC
        LIMIT 5
    ");
    $leaveStmt->execute([$department]);
    $todayLeaveRows = $leaveStmt->fetchAll();

    $pendingStmt = $pdo->prepare("
        SELECT 'Leave' AS item_type, la.id, la.created_at, la.start_date AS item_date,
               lt.name AS item_title, e.first_name, e.last_name, e.employee_code
        FROM leave_applications la
        JOIN employees e ON e.id = la.employee_id
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.status = 'Pending' AND e.department = ?
        UNION ALL
        SELECT 'Regularization' AS item_type, ar.id, ar.created_at, ar.date AS item_date,
               ar.reason AS item_title, e.first_name, e.last_name, e.employee_code
        FROM attendance_regularizations ar
        JOIN employees e ON e.id = ar.employee_id
        WHERE ar.status = 'Pending' AND e.department = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $pendingStmt->execute([$department, $department]);
    $pendingApprovalRows = $pendingStmt->fetchAll();
} elseif ($employeeId) {
    $leaveStmt = $pdo->prepare("
        SELECT la.id, la.start_date, la.end_date, la.total_days, lt.name AS leave_type,
               e.first_name, e.last_name, e.employee_code
        FROM leave_applications la
        JOIN employees e ON e.id = la.employee_id
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.status = 'Approved'
          AND CURDATE() BETWEEN la.start_date AND COALESCE(la.end_date, la.start_date)
          AND la.employee_id = ?
        ORDER BY la.start_date ASC
        LIMIT 5
    ");
    $leaveStmt->execute([$employeeId]);
    $todayLeaveRows = $leaveStmt->fetchAll();

    $pendingStmt = $pdo->prepare("
        SELECT 'Leave' AS item_type, la.id, la.created_at, la.start_date AS item_date,
               lt.name AS item_title, e.first_name, e.last_name, e.employee_code
        FROM leave_applications la
        JOIN employees e ON e.id = la.employee_id
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.status = 'Pending' AND la.employee_id = ?
        UNION ALL
        SELECT 'Regularization' AS item_type, ar.id, ar.created_at, ar.date AS item_date,
               ar.reason AS item_title, e.first_name, e.last_name, e.employee_code
        FROM attendance_regularizations ar
        JOIN employees e ON e.id = ar.employee_id
        WHERE ar.status = 'Pending' AND ar.employee_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $pendingStmt->execute([$employeeId, $employeeId]);
    $pendingApprovalRows = $pendingStmt->fetchAll();
}

$holidayStmt = $pdo->query("
    SELECT name, start_date, end_date, category, is_half_day
    FROM holidays
    WHERE COALESCE(end_date, start_date) >= CURDATE()
    ORDER BY start_date ASC
    LIMIT 5
");
$upcomingHolidayRows = $holidayStmt->fetchAll();

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

<?php if ($canViewTeamDashboard): ?>
<div class="dashboard-metrics">
    <div class="dashboard-metric dashboard-metric-blue">
        <div class="dashboard-metric-icon"><i class="fas fa-users"></i></div>
        <div>
            <div class="dashboard-metric-label"><?php echo $isManager ? 'Active Team' : 'Active Employees'; ?></div>
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

    <div class="dashboard-metric dashboard-metric-cyan">
        <div class="dashboard-metric-icon"><i class="fas fa-calendar-times"></i></div>
        <div>
            <div class="dashboard-metric-label">On Leave Today</div>
            <div class="dashboard-metric-value"><?php echo $metrics['on_leave_today']; ?></div>
        </div>
    </div>

    <a href="<?php echo app_url('modules/leave_applications/index.php'); ?>" class="dashboard-metric dashboard-metric-amber">
        <div class="dashboard-metric-icon"><i class="fas fa-paper-plane"></i></div>
        <div>
            <div class="dashboard-metric-label">Pending Leaves</div>
            <div class="dashboard-metric-value"><?php echo $metrics['pending_leaves']; ?></div>
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
                <?php if ($record && intval($record['is_absent']) && empty($record['clock_in'])): ?><span class="dashboard-flag dashboard-flag-danger">No Punch</span><?php endif; ?>
                <?php if ($record && intval($record['is_holiday'])): ?><span class="dashboard-flag dashboard-flag-info">Holiday</span><?php endif; ?>
                <?php if ($record && intval($record['is_weekend'])): ?><span class="dashboard-flag">Weekend</span><?php endif; ?>
                <?php if (!$record || (!intval($record['is_late']) && !intval($record['is_early_departure']) && !(intval($record['is_absent']) && empty($record['clock_in'])) && !intval($record['is_holiday']) && !intval($record['is_weekend']))): ?>
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

<section class="dashboard-insights-grid">
    <div class="dashboard-widget">
        <div class="dashboard-widget-title dashboard-widget-title-danger">
            <span><i class="fas fa-clipboard-check"></i> <?php echo $currentUser['role'] === 'employee' ? 'Pending Requests' : 'Pending Approvals'; ?></span>
            <a href="<?php echo app_url('modules/leave_applications/index.php'); ?>" class="dashboard-widget-link">View</a>
        </div>
        <?php if (!empty($pendingApprovalRows)): ?>
            <div class="dashboard-list">
                <?php foreach ($pendingApprovalRows as $item): ?>
                    <div class="dashboard-list-row">
                        <div>
                            <div class="dashboard-list-title"><?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></div>
                            <div class="dashboard-list-meta"><?php echo htmlspecialchars($item['employee_code']); ?> &middot; <?php echo htmlspecialchars($item['item_type']); ?></div>
                            <div class="dashboard-list-meta"><?php echo htmlspecialchars($item['item_title']); ?></div>
                        </div>
                        <div class="dashboard-list-time">
                            <?php echo date('M d', strtotime($item['item_date'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-empty dashboard-empty-compact">
                <i class="fas fa-check-circle"></i>
                <span>No pending items.</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-widget">
        <div class="dashboard-widget-title dashboard-widget-title-info">
            <span><i class="fas fa-user-clock"></i> Today's Leave</span>
            <a href="<?php echo app_url('modules/leave_applications/index.php'); ?>" class="dashboard-widget-link">View</a>
        </div>
        <?php if (!empty($todayLeaveRows)): ?>
            <div class="dashboard-list">
                <?php foreach ($todayLeaveRows as $leave): ?>
                    <div class="dashboard-list-row">
                        <div>
                            <div class="dashboard-list-title"><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></div>
                            <div class="dashboard-list-meta"><?php echo htmlspecialchars($leave['employee_code']); ?> &middot; <?php echo htmlspecialchars($leave['leave_type']); ?></div>
                            <div class="dashboard-list-meta"><?php echo number_format(floatval($leave['total_days']), 2); ?> day(s)</div>
                        </div>
                        <div class="dashboard-list-time">
                            <?php echo date('M d', strtotime($leave['start_date'])); ?>
                            <?php if ($leave['end_date'] && $leave['end_date'] !== $leave['start_date']): ?>
                                <br>to <?php echo date('M d', strtotime($leave['end_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-empty dashboard-empty-compact">
                <i class="fas fa-calendar-check"></i>
                <span>No active leave today.</span>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-widget">
        <div class="dashboard-widget-title dashboard-widget-title-success">
            <span><i class="fas fa-umbrella-beach"></i> Upcoming Holidays</span>
            <?php if (in_array($currentUser['role'], ['admin', 'HR', 'manager'], true)): ?>
            <a href="<?php echo app_url('modules/holidays/index.php'); ?>" class="dashboard-widget-link">View</a>
            <?php endif; ?>
        </div>
        <?php if (!empty($upcomingHolidayRows)): ?>
            <div class="dashboard-list">
                <?php foreach ($upcomingHolidayRows as $holiday): ?>
                    <div class="dashboard-list-row">
                        <div>
                            <div class="dashboard-list-title"><?php echo htmlspecialchars($holiday['name']); ?></div>
                            <div class="dashboard-list-meta">
                                <?php echo htmlspecialchars($holiday['category']); ?>
                                <?php if (intval($holiday['is_half_day'])): ?>
                                    &middot; Half Day
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="dashboard-list-time">
                            <?php echo date('M d', strtotime($holiday['start_date'])); ?>
                            <?php if ($holiday['end_date'] && $holiday['end_date'] !== $holiday['start_date']): ?>
                                <br>to <?php echo date('M d', strtotime($holiday['end_date'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dashboard-empty dashboard-empty-compact">
                <i class="fas fa-calendar-times"></i>
                <span>No upcoming holidays.</span>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($canViewTeamDashboard): ?>
<section class="dashboard-trend-card">
    <div class="dashboard-widget-title dashboard-widget-title-success">
        <span><i class="fas fa-chart-line"></i> Attendance Trend</span>
    </div>
    <div class="dashboard-chart-wrap">
        <canvas id="attendanceTrendChart"></canvas>
    </div>
</section>
<?php endif; ?>

<script>
    setInterval(() => {
        const d = new Date();
        const clock = document.getElementById('liveClock');
        if (clock) {
            clock.innerText = d.toLocaleTimeString('en-GB', { hour12: false });
        }
    }, 1000);
</script>

<?php if ($canViewTeamDashboard): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const attendanceTrendCanvas = document.getElementById('attendanceTrendChart');
    if (attendanceTrendCanvas && window.Chart) {
        new Chart(attendanceTrendCanvas.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [
                    {
                        label: 'Present',
                        data: <?php echo json_encode($chartPresent); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.35,
                        fill: true
                    },
                    {
                        label: 'Late Arrivals',
                        data: <?php echo json_encode($chartLate); ?>,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.08)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.35,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            color: '#475569',
                            font: { family: 'Inter' }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(148, 163, 184, 0.14)' },
                        ticks: { color: '#64748b' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(148, 163, 184, 0.14)' },
                        ticks: { color: '#64748b', precision: 0 }
                    }
                }
            }
        });
    }
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
