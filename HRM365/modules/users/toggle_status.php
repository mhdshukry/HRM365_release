<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id']);
    $status = $_POST['status'];

    if ($id > 0 && in_array($status, ['Active', 'Inactive'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            
            log_action($pdo, $currentUser['id'], 'USER_STATUS_CHANGE', "Changed user #{$id} status to {$status}");
            
            header("Location: index.php?success=status_updated");
            exit();
        } catch (\PDOException $e) {
            die("Error updating status: " . $e->getMessage());
        }
    }
}
header("Location: index.php");
exit();
?>
