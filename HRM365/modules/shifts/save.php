<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $working_days = $_POST['working_days'] ?? [];
    $day_start_time = $_POST['day_start_time'] ?? [];
    $day_end_time = $_POST['day_end_time'] ?? [];
    
    $grace_period = max(0, intval($_POST['grace_period'] ?? 0));
    $is_night_shift = isset($_POST['is_night_shift']) ? 1 : 0;
    $status = isset($_POST['status']) && $_POST['status'] === 'Active' ? 'Active' : 'Inactive';

    if ($name === '') {
        die("Shift name is required.");
    }

    $weeklySchedule = [];
    $start_time = null;
    $end_time = null;
    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $isWorking = isset($working_days[$weekday]) ? 1 : 0;
        $dayStart = trim($day_start_time[$weekday] ?? '');
        $dayEnd = trim($day_end_time[$weekday] ?? '');

        if ($isWorking && (!preg_match('/^\d{2}:\d{2}$/', $dayStart) || !preg_match('/^\d{2}:\d{2}$/', $dayEnd))) {
            die("Please enter valid start and end times for each working day.");
        }

        if ($isWorking && $start_time === null) {
            $start_time = $dayStart;
            $end_time = $dayEnd;
        }

        $weeklySchedule[$weekday] = [
            'is_working' => $isWorking,
            'start_time' => $isWorking ? $dayStart : null,
            'end_time' => $isWorking ? $dayEnd : null,
        ];
    }

    if ($start_time === null || $end_time === null) {
        die("Please select at least one working day for this shift.");
    }

    try {
        $pdo->beginTransaction();

        if ($id > 0) {
            $existsStmt = $pdo->prepare("SELECT id FROM shifts WHERE id = ?");
            $existsStmt->execute([$id]);
            if (!$existsStmt->fetchColumn()) {
                die("Shift not found.");
            }

            $stmt = $pdo->prepare("
                UPDATE shifts
                SET name = ?, description = ?, start_time = ?, end_time = ?, grace_period = ?, is_night_shift = ?, status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name, $description, $start_time, $end_time,
                $grace_period, $is_night_shift, $status, $id
            ]);

            log_action($pdo, $currentUser['id'], 'SHIFT_UPDATED', "Updated Time & Attendance Shift: {$name}");

            $shiftId = $id;
            header("Location: index.php?success=shift_updated");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO shifts
                (name, description, start_time, end_time, grace_period, is_night_shift, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name, $description, $start_time, $end_time,
                $grace_period, $is_night_shift, $status
            ]);

            $shiftId = intval($pdo->lastInsertId());
            log_action($pdo, $currentUser['id'], 'SHIFT_CREATED', "Defined new Time & Attendance Shift: {$name}");

            header("Location: index.php?success=shift_created");
        }

        $scheduleStmt = $pdo->prepare("
            INSERT INTO shift_weekly_schedules (shift_id, weekday, is_working, start_time, end_time, is_night_shift)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_working = VALUES(is_working),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                is_night_shift = VALUES(is_night_shift)
        ");

        foreach ($weeklySchedule as $weekday => $day) {
            $scheduleStmt->execute([
                $shiftId,
                $weekday,
                $day['is_working'],
                $day['start_time'],
                $day['end_time'],
                $is_night_shift,
            ]);
        }

        $pdo->commit();
        exit();
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Error saving shift: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
