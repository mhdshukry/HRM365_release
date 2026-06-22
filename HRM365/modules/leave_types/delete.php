<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];

    if ($id) {
        // We should check if this leave type is used in leave_applications before deleting, 
        // but for now, we just proceed with the deletion.
        $stmt = $pdo->prepare("DELETE FROM leave_types WHERE id = ?");
        $stmt->execute([$id]);
        
        log_action($pdo, $currentUser['id'], 'DELETE_LEAVE_TYPE', "Deleted leave type ID: $id");
        header("Location: index.php?success=deleted");
        exit();
    }
}
header("Location: index.php?error=delete_failed");
exit();
?>
