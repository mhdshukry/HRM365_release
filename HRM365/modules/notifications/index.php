<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

$notifications = [];

function add_notification(array &$notifications, string $type, string $title, string $meta, string $url, string $icon): void
{
    $notifications[] = [
        'type' => $type,
        'title' => $title,
        'meta' => $meta,
        'url' => $url,
        'icon' => $icon,
    ];
}

if (in_array($currentUser['role'], ['admin', 'HR'], true)) {
    $leaveStmt = $pdo->query("
        SELECT la.id, la.start_date, la.total_days, e.first_name, e.last_name, lt.name AS leave_type
        FROM leave_applications la
        JOIN employees e ON e.id = la.employee_id
        JOIN leave_types lt ON lt.id = la.leave_type_id
        WHERE la.status = 'Pending'
        ORDER BY la.created_at DESC
        LIMIT 10
    ");
    foreach ($leaveStmt->fetchAll() as $leave) {
        add_notification(
            $notifications,
            'Leave',
            $leave['first_name'] . ' ' . $leave['last_name'] . ' requested ' . $leave['leave_type'],
            date('M d, Y', strtotime($leave['start_date'])) . ' · ' . number_format(floatval($leave['total_days']), 2) . ' day(s)',
            app_url('modules/leave_applications/index.php'),
            'fa-paper-plane'
        );
    }

    $regStmt = $pdo->query("
        SELECT ar.id, ar.date, e.first_name, e.last_name, e.employee_code
        FROM attendance_regularizations ar
        JOIN employees e ON e.id = ar.employee_id
        WHERE ar.status = 'Pending'
        ORDER BY ar.created_at DESC
        LIMIT 10
    ");
    foreach ($regStmt->fetchAll() as $reg) {
        add_notification(
            $notifications,
            'Regularization',
            $reg['first_name'] . ' ' . $reg['last_name'] . ' submitted attendance correction',
            $reg['employee_code'] . ' · ' . date('M d, Y', strtotime($reg['date'])),
            app_url('modules/regularizations/index.php'),
            'fa-user-clock'
        );
    }

    $bioStmt = $pdo->query("
        SELECT terminal_sn, COUNT(*) AS pending_count, MAX(punch_time) AS last_punch
        FROM biometric_punches
        WHERE is_synced = 0
        GROUP BY terminal_sn
        ORDER BY last_punch DESC
        LIMIT 10
    ");
    foreach ($bioStmt->fetchAll() as $bio) {
        add_notification(
            $notifications,
            'Biometric',
            intval($bio['pending_count']) . ' unsynced punch(es)',
            ($bio['terminal_sn'] ?: 'UNKNOWN_DEVICE') . ' · last ' . date('M d, H:i', strtotime($bio['last_punch'])),
            app_url('modules/biometric_records/index.php'),
            'fa-fingerprint'
        );
    }
} elseif (!empty($currentUser['employee_id'])) {
    $stmt = $pdo->prepare("
        SELECT id, start_date, total_days, status
        FROM leave_applications
        WHERE employee_id = ? AND status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$currentUser['employee_id']]);
    foreach ($stmt->fetchAll() as $leave) {
        add_notification(
            $notifications,
            'Leave',
            'Your leave request is pending',
            date('M d, Y', strtotime($leave['start_date'])) . ' · ' . number_format(floatval($leave['total_days']), 2) . ' day(s)',
            app_url('modules/leave_applications/index.php'),
            'fa-paper-plane'
        );
    }

    $stmt = $pdo->prepare("
        SELECT id, date, status
        FROM attendance_regularizations
        WHERE employee_id = ? AND status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$currentUser['employee_id']]);
    foreach ($stmt->fetchAll() as $reg) {
        add_notification(
            $notifications,
            'Regularization',
            'Your attendance correction is pending',
            date('M d, Y', strtotime($reg['date'])),
            app_url('modules/regularizations/index.php'),
            'fa-user-clock'
        );
    }
}

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notifications</h1>
        <div class="page-subtitle">Pending approvals, sync items, and items needing attention.</div>
    </div>
</div>

<div class="card">
    <?php if (!empty($notifications)): ?>
        <div style="display: flex; flex-direction: column;">
            <?php foreach ($notifications as $item): ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>" style="display: flex; gap: 1rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--border-color); text-decoration: none; color: inherit;">
                    <div style="width: 42px; height: 42px; border-radius: var(--radius-md); background: var(--bg-hover); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;"><?php echo htmlspecialchars($item['type']); ?></div>
                        <div style="font-weight: 700; color: var(--text-primary); margin-top: 0.15rem;"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 0.2rem;"><?php echo htmlspecialchars($item['meta']); ?></div>
                    </div>
                    <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
            <i class="far fa-bell" style="font-size: 2.25rem; opacity: 0.35; margin-bottom: 1rem;"></i><br>
            No pending notifications.
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
