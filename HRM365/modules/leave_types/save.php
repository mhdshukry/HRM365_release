<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $max_days_per_year = intval($_POST['max_days_per_year']);
    $color = trim($_POST['color']);
    $status = $_POST['status'];
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO leave_types (name, description, max_days_per_year, is_paid, color, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $max_days_per_year, $is_paid, $color, $status]);
        
        log_action($pdo, $currentUser['id'], 'LEAVE_TYPE_CREATED', "Configured new Leave Policy: {$name}");
        
        header("Location: index.php?success=policy_added");
        exit();
    } catch (\PDOException $e) {
        die("Error saving leave policy: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
