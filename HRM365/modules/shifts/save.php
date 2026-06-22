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
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    
    $grace_period = max(0, intval($_POST['grace_period'] ?? 0));
    $is_night_shift = isset($_POST['is_night_shift']) ? 1 : 0;
    $status = isset($_POST['status']) && $_POST['status'] === 'Active' ? 'Active' : 'Inactive';

    if ($name === '') {
        die("Shift name is required.");
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
        die("Please enter valid shift start and end times.");
    }

    try {
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

            log_action($pdo, $currentUser['id'], 'SHIFT_CREATED', "Defined new Time & Attendance Shift: {$name}");

            header("Location: index.php?success=shift_created");
        }
        exit();
    } catch (\PDOException $e) {
        die("Error saving shift: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
