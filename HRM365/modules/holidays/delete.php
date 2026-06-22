<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT name FROM holidays WHERE id = ?");
            $stmt->execute([$id]);
            $holiday = $stmt->fetch();

            if ($holiday) {
                $delete = $pdo->prepare("DELETE FROM holidays WHERE id = ?");
                $delete->execute([$id]);
                log_action($pdo, $currentUser['id'], 'HOLIDAY_DELETED', "Deleted holiday: {$holiday['name']}");
            }
        } catch (\PDOException $e) {
            die("Error deleting holiday: " . $e->getMessage());
        }
    }
}

header("Location: index.php?success=holiday_deleted");
exit();
?>
