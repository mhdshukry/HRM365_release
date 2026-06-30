<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

if (!in_array($currentUser['role'], ['admin', 'HR'], true)) {
    die("Unauthorized access.");
}

$id = intval($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: index.php");
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM shifts WHERE id = ?");
    $stmt->execute([$id]);
    $shift = $stmt->fetch();

    if (!$shift) {
        header("Location: index.php?error=shift_not_found");
        exit();
    }

    $employeeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE shift_id = ?");
    $employeeStmt->execute([$id]);
    if (intval($employeeStmt->fetchColumn()) > 0) {
        header("Location: edit.php?id={$id}&error=shift_has_employees");
        exit();
    }

    $overrideStmt = $pdo->prepare("SELECT COUNT(*) FROM employee_shift_overrides WHERE shift_id = ?");
    $overrideStmt->execute([$id]);
    if (intval($overrideStmt->fetchColumn()) > 0) {
        header("Location: edit.php?id={$id}&error=shift_has_overrides");
        exit();
    }

    $pdo->beginTransaction();

    $scheduleStmt = $pdo->prepare("DELETE FROM shift_weekly_schedules WHERE shift_id = ?");
    $scheduleStmt->execute([$id]);

    $deleteStmt = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
    $deleteStmt->execute([$id]);

    log_action($pdo, $currentUser['id'], 'SHIFT_DELETED', "Deleted shift: {$shift['name']}");

    $pdo->commit();

    header("Location: index.php?success=shift_deleted");
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: edit.php?id={$id}&error=delete_failed");
    exit();
}
?>
