<?php
// CLI script to run biometric math sync every 5 minutes via cron
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/attendance_math.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting Auto-Sync...\n";

try {
    $pdo->beginTransaction();

    $forceDate = $argv[1] ?? null;
    if ($forceDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $forceDate)) {
        echo "[" . date('Y-m-d H:i:s') . "] Invalid date argument. Use YYYY-MM-DD.\n";
        $pdo->rollBack();
        exit(1);
    }

    if ($forceDate) {
        $pendingStmt = $pdo->prepare("
            SELECT DISTINCT DATE(punch_time) AS punch_date, biometric_user_id
            FROM biometric_punches
            WHERE DATE(punch_time) = ?
        ");
        $pendingStmt->execute([$forceDate]);
    } else {
        $pendingStmt = $pdo->query("
            SELECT DISTINCT DATE(punch_time) AS punch_date, biometric_user_id
            FROM biometric_punches
            WHERE is_synced = FALSE
        ");
    }
    $pendingGroups = $pendingStmt->fetchAll();

    if (empty($pendingGroups)) {
        $message = $forceDate ? "No punches found for {$forceDate}." : "No unsynced punches found.";
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
        $pdo->rollBack();
        exit();
    }

    $whereParts = [];
    $params = [];
    foreach ($pendingGroups as $pending) {
        $whereParts[] = "(DATE(b.punch_time) = ? AND b.biometric_user_id = ?)";
        $params[] = $pending['punch_date'];
        $params[] = $pending['biometric_user_id'];
    }

    $stmt = $pdo->prepare("
        SELECT b.id, DATE(b.punch_time) as punch_date, b.biometric_user_id, b.punch_time, b.punch_direction, b.terminal_sn,
               e.id as employee_id, e.shift_id, e.attendance_policy_id, e.base_salary, e.branch_id,
               s.start_time, s.end_time,
               p.late_arrival_grace, p.early_departure_grace, p.overtime_rate_per_hour
        FROM biometric_punches b
        LEFT JOIN employees e ON b.biometric_user_id = e.biometric_user_id
        LEFT JOIN shifts s ON e.shift_id = s.id
        LEFT JOIN attendance_policies p ON e.attendance_policy_id = p.id
        WHERE " . implode(' OR ', $whereParts) . "
        ORDER BY DATE(b.punch_time), COALESCE(e.id, 0), b.biometric_user_id, b.punch_time ASC, b.id ASC
    ");
    $stmt->execute($params);
    $punches = $stmt->fetchAll();

    if (empty($punches)) {
        echo "[" . date('Y-m-d H:i:s') . "] No unsynced punches found.\n";
        $pdo->rollBack();
        exit();
    }

    $groups = [];
    foreach ($punches as $punch) {
        $date = $punch['punch_date'];
        $key = $date . '|' . $punch['biometric_user_id'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'date' => $date,
                'employee' => [
                    'employee_id' => $punch['employee_id'],
                    'shift_id' => $punch['shift_id'],
                    'attendance_policy_id' => $punch['attendance_policy_id'],
                    'base_salary' => $punch['base_salary'],
                    'branch_id' => $punch['branch_id'],
                    'start_time' => $punch['start_time'],
                    'end_time' => $punch['end_time'],
                    'late_arrival_grace' => $punch['late_arrival_grace'],
                    'early_departure_grace' => $punch['early_departure_grace'],
                    'overtime_rate_per_hour' => $punch['overtime_rate_per_hour'],
                ],
                'rows' => [],
            ];
        }
        $groups[$key]['rows'][] = $punch;
    }

    foreach ($groups as $bundle) {
        $date = $bundle['date'];
        $emp = $bundle['employee'];
        $rows = $bundle['rows'];

        $clock_in_time = $rows[0]['punch_time'];
        $clock_out_time = null;
        $clock_out_index = null;
        $branchId = $emp['branch_id'] !== null ? intval($emp['branch_id']) : null;
        $blockStatus = $emp['employee_id'] ? get_attendance_block_status($pdo, intval($emp['employee_id']), $date, $branchId) : null;

        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            if (strtotime($row['punch_time']) >= strtotime($clock_in_time . ' +1 hour')) {
                $clock_out_time = $row['punch_time'];
                $clock_out_index = $index;
                break;
            }
        }

        $statuses = [];
        foreach ($rows as $index => $row) {
            if (!$emp['employee_id']) {
                $statuses[$row['id']] = 'Unmapped';
                continue;
            }

            if ($blockStatus !== null) {
                $statuses[$row['id']] = $blockStatus;
            } elseif ($index === 0) {
                $statuses[$row['id']] = 'Clock In';
            } elseif ($clock_out_index !== null && $index === $clock_out_index) {
                $statuses[$row['id']] = 'Clock Out';
            } else {
                $statuses[$row['id']] = 'Redundant';
            }
        }

        $is_late = 0;
        $is_early_departure = 0;
        $overtime_hours = 0.00;
        $overtime_amount = 0.00;
        $total_hours = 0.00;

        if ($emp['employee_id'] && $blockStatus === null) {
            $clock_in_ts = strtotime($clock_in_time);

            if ($emp['start_time']) {
                $expected_start = strtotime($date . ' ' . $emp['start_time']);
                $late_grace = intval($emp['late_arrival_grace']) * 60;
                if ($clock_in_ts > ($expected_start + $late_grace)) {
                    $is_late = 1;
                }
            }

            if ($clock_out_time) {
                $clock_out_ts = strtotime($clock_out_time);
                $total_seconds = $clock_out_ts - $clock_in_ts;
                $total_hours = round($total_seconds / 3600, 2);

                if ($emp['end_time']) {
                    $expected_end = calculate_expected_shift_end($date, $emp['start_time'], $emp['end_time']);
                    $early_grace = intval($emp['early_departure_grace']) * 60;

                    if ($expected_end !== null && $clock_out_ts < ($expected_end - $early_grace)) {
                        $is_early_departure = 1;
                    }

                    if ($expected_end !== null && $clock_out_ts > $expected_end) {
                        $ot_seconds = $clock_out_ts - $expected_end;
                        $raw_ot_hours = round($ot_seconds / 3600, 2);
                        if ($raw_ot_hours > 0.5) {
                            $overtime_hours = $raw_ot_hours;
                            $rate = floatval($emp['overtime_rate_per_hour']) > 0 ? floatval($emp['overtime_rate_per_hour']) : 1.0;
                            $overtime_amount = calculate_overtime_amount(
                                $overtime_hours,
                                floatval($emp['base_salary']),
                                $emp['start_time'],
                                $emp['end_time'],
                                $rate
                            );
                        }
                    }
                }
            }

            $calendarFlags = get_attendance_calendar_flags($pdo, $date, $branchId);
            $upsert = $pdo->prepare("
                INSERT INTO attendance_records 
                (employee_id, shift_id, attendance_policy_id, date, clock_in, clock_out, total_hours, is_late, is_early_departure, overtime_hours, overtime_amount, is_holiday, is_weekend, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Present')
                ON DUPLICATE KEY UPDATE 
                clock_in = VALUES(clock_in), 
                clock_out = VALUES(clock_out),
                total_hours = VALUES(total_hours),
                is_late = VALUES(is_late),
                is_early_departure = VALUES(is_early_departure),
                overtime_hours = VALUES(overtime_hours),
                overtime_amount = VALUES(overtime_amount),
                is_holiday = VALUES(is_holiday),
                is_weekend = VALUES(is_weekend),
                status = 'Present'
            ");
            $upsert->execute([
                $emp['employee_id'], $emp['shift_id'], $emp['attendance_policy_id'], $date,
                $clock_in_time, $clock_out_time, $total_hours, $is_late, $is_early_departure,
                $overtime_hours, $overtime_amount, $calendarFlags['is_holiday'], $calendarFlags['is_weekend']
            ]);
        }

        $statusStmt = $pdo->prepare("UPDATE biometric_punches SET log_status = ?, is_synced = TRUE WHERE id = ?");
        foreach ($statuses as $punchId => $status) {
            $statusStmt->execute([$status, $punchId]);
        }
    }

    $pdo->commit();
    echo "[" . date('Y-m-d H:i:s') . "] Sync completed successfully. " . count($groups) . " attendance groups updated.\n";
    
} catch (\PDOException $e) {
    $pdo->rollBack();
    echo "[" . date('Y-m-d H:i:s') . "] Error syncing data: " . $e->getMessage() . "\n";
}
?>
