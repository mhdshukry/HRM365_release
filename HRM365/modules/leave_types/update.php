<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $max_days_per_year = max(0, intval($_POST['max_days_per_year'] ?? 0));
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $status = $_POST['status'] ?? 'Active';
    $color = trim($_POST['color'] ?? '#3b82f6');

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        die("Invalid leave type status.");
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        die("Invalid calendar color.");
    }

    if ($id > 0 && $name !== '') {
        $existsStmt = $pdo->prepare("SELECT id FROM leave_types WHERE id = ?");
        $existsStmt->execute([$id]);
        if (!$existsStmt->fetchColumn()) {
            die("Leave type not found.");
        }

        $stmt = $pdo->prepare("
            UPDATE leave_types 
            SET name = ?, description = ?, max_days_per_year = ?, is_paid = ?, status = ?, color = ?
            WHERE id = ?
        ");
        $stmt->execute([$name, $description, $max_days_per_year, $is_paid, $status, $color, $id]);
        
        log_action($pdo, $currentUser['id'], 'UPDATE_LEAVE_TYPE', "Updated leave type: $name");
        header("Location: index.php?success=updated");
        exit();
    }
}
header("Location: index.php?error=update_failed");
exit();
?>
