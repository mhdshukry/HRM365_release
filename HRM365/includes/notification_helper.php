<?php

function notification_add(array &$notifications, string $type, string $title, string $meta, string $url, string $icon): void
{
    $notifications[] = [
        'type' => $type,
        'title' => $title,
        'meta' => $meta,
        'url' => $url,
        'icon' => $icon,
    ];
}

function notification_items(PDO $pdo, array $currentUser, int $limit = 10): array
{
    $notifications = [];
    $limit = max(1, min($limit, 50));

    if (in_array($currentUser['role'], ['admin', 'HR'], true)) {
        $leaveStmt = $pdo->query("
            SELECT la.start_date, la.total_days, e.first_name, e.last_name, lt.name AS leave_type
            FROM leave_applications la
            JOIN employees e ON e.id = la.employee_id
            JOIN leave_types lt ON lt.id = la.leave_type_id
            WHERE la.status = 'Pending'
            ORDER BY la.created_at DESC
            LIMIT {$limit}
        ");
        foreach ($leaveStmt->fetchAll() as $leave) {
            notification_add(
                $notifications,
                'Leave',
                $leave['first_name'] . ' ' . $leave['last_name'] . ' requested ' . $leave['leave_type'],
                date('M d, Y', strtotime($leave['start_date'])) . ' - ' . number_format(floatval($leave['total_days']), 2) . ' day(s)',
                app_url('modules/leave_applications/index.php'),
                'fa-paper-plane'
            );
        }

        $regStmt = $pdo->query("
            SELECT ar.date, e.first_name, e.last_name, e.employee_code
            FROM attendance_regularizations ar
            JOIN employees e ON e.id = ar.employee_id
            WHERE ar.status = 'Pending'
            ORDER BY ar.created_at DESC
            LIMIT {$limit}
        ");
        foreach ($regStmt->fetchAll() as $reg) {
            notification_add(
                $notifications,
                'Regularization',
                $reg['first_name'] . ' ' . $reg['last_name'] . ' submitted attendance correction',
                $reg['employee_code'] . ' - ' . date('M d, Y', strtotime($reg['date'])),
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
            LIMIT {$limit}
        ");
        foreach ($bioStmt->fetchAll() as $bio) {
            notification_add(
                $notifications,
                'Biometric',
                intval($bio['pending_count']) . ' unsynced punch(es)',
                ($bio['terminal_sn'] ?: 'UNKNOWN_DEVICE') . ' - last ' . date('M d, H:i', strtotime($bio['last_punch'])),
                app_url('modules/biometric_records/index.php'),
                'fa-fingerprint'
            );
        }
    } elseif (!empty($currentUser['employee_id'])) {
        $stmt = $pdo->prepare("
            SELECT start_date, total_days
            FROM leave_applications
            WHERE employee_id = ? AND status = 'Pending'
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$currentUser['employee_id']]);
        foreach ($stmt->fetchAll() as $leave) {
            notification_add(
                $notifications,
                'Leave',
                'Your leave request is pending',
                date('M d, Y', strtotime($leave['start_date'])) . ' - ' . number_format(floatval($leave['total_days']), 2) . ' day(s)',
                app_url('modules/leave_applications/index.php'),
                'fa-paper-plane'
            );
        }

        $stmt = $pdo->prepare("
            SELECT date
            FROM attendance_regularizations
            WHERE employee_id = ? AND status = 'Pending'
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$currentUser['employee_id']]);
        foreach ($stmt->fetchAll() as $reg) {
            notification_add(
                $notifications,
                'Regularization',
                'Your attendance correction is pending',
                date('M d, Y', strtotime($reg['date'])),
                app_url('modules/regularizations/index.php'),
                'fa-user-clock'
            );
        }
    }

    return array_slice($notifications, 0, $limit);
}
