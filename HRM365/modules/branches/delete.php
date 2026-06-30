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
    $stmt = $pdo->prepare("SELECT id, name FROM branches WHERE id = ?");
    $stmt->execute([$id]);
    $branch = $stmt->fetch();

    if (!$branch) {
        header("Location: index.php?error=branch_not_found");
        exit();
    }

    $employeeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE branch_id = ?");
    $employeeStmt->execute([$id]);
    if (intval($employeeStmt->fetchColumn()) > 0) {
        header("Location: edit.php?id={$id}&error=branch_has_employees");
        exit();
    }

    $pdo->beginTransaction();

    $holidayStmt = $pdo->prepare("DELETE FROM holiday_branches WHERE branch_id = ?");
    $holidayStmt->execute([$id]);

    $deleteStmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
    $deleteStmt->execute([$id]);

    log_action($pdo, $currentUser['id'], 'BRANCH_DELETED', "Deleted branch: {$branch['name']}");

    $pdo->commit();

    header("Location: index.php?success=branch_deleted");
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header("Location: edit.php?id={$id}&error=delete_failed");
    exit();
}
?>
