<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $location = trim($_POST['location']);
    $organizer_id = intval($_POST['organizer_id']);

    if ($currentUser['role'] === 'employee') {
        $organizer_id = intval($currentUser['employee_id'] ?? 0);
    } elseif ($currentUser['role'] === 'manager') {
        $scopeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND department = ?");
        $scopeStmt->execute([$organizer_id, $currentUser['department'] ?? '']);
        if (!$scopeStmt->fetchColumn()) {
            die("Unauthorized organizer selection.");
        }
    }

    if ($title === '' || strtotime($start_time) === false || strtotime($end_time) === false || strtotime($end_time) <= strtotime($start_time)) {
        die("Invalid meeting details.");
    }

    if ($organizer_id <= 0) {
        die("Organizer profile is not linked.");
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO meetings (title, description, start_time, end_time, location, organizer_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $start_time, $end_time, $location, $organizer_id]);
        
        header("Location: index.php?success=scheduled");
        exit();
    } catch (\PDOException $e) {
        die("Error scheduling meeting: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
