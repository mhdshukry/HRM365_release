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
    $late_arrival_grace = max(0, intval($_POST['late_arrival_grace'] ?? 0));
    $early_departure_grace = max(0, intval($_POST['early_departure_grace'] ?? 0));
    $overtime_rate_per_hour = max(0, floatval($_POST['overtime_rate_per_hour'] ?? 0));
    
    $status = $_POST['status'] ?? 'Active';

    if ($name === '') {
        die("Policy name is required.");
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        die("Invalid policy status.");
    }

    try {
        if ($id > 0) {
            $existsStmt = $pdo->prepare("SELECT id FROM attendance_policies WHERE id = ?");
            $existsStmt->execute([$id]);
            if (!$existsStmt->fetchColumn()) {
                die("Attendance policy not found.");
            }

            $stmt = $pdo->prepare("
                UPDATE attendance_policies
                SET name = ?, description = ?, late_arrival_grace = ?, early_departure_grace = ?, overtime_rate_per_hour = ?, status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $name, $description, $late_arrival_grace, $early_departure_grace,
                $overtime_rate_per_hour, $status, $id
            ]);

            log_action($pdo, $currentUser['id'], 'ATTENDANCE_POLICY_UPDATED', "Updated Attendance Policy: {$name}");

            header("Location: index.php?success=policy_updated");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance_policies
                (name, description, late_arrival_grace, early_departure_grace, overtime_rate_per_hour, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $name, $description, $late_arrival_grace, $early_departure_grace,
                $overtime_rate_per_hour, $status
            ]);

            log_action($pdo, $currentUser['id'], 'ATTENDANCE_POLICY_CREATED', "Defined new Attendance Policy: {$name}");

            header("Location: index.php?success=policy_created");
        }
        exit();
    } catch (\PDOException $e) {
        die("Error saving policy: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
