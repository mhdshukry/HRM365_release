<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $name = trim($_POST['title'] ?? $_POST['name'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $category = trim($_POST['type'] ?? $_POST['category'] ?? 'Statutory');
    $validCategories = ['National', 'Religious', 'Company-specific', 'Other'];

    // Default end_date to start_date if not provided
    if (empty($end_date)) {
        $end_date = $start_date;
    }

    if (!in_array($category, $validCategories, true)) {
        $category = 'Other';
    }

    if ($name === '' || strtotime($start_date) === false || strtotime($end_date) === false || strtotime($end_date) < strtotime($start_date)) {
        die("Invalid holiday details.");
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO holidays (name, start_date, end_date, category, is_recurring, is_paid, applies_to_all_branches)
            VALUES (?, ?, ?, ?, 0, 1, 1)
        ");
        $stmt->execute([$name, $start_date, $end_date, $category]);
        header("Location: index.php?success=1");
        exit();
    } catch (\PDOException $e) {
        die("Error saving holiday: " . $e->getMessage());
    }
}
?>
