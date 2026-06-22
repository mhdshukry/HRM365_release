<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if (!in_array($currentUser['role'], ['admin', 'HR'])) {
    die("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $start_date = $_POST['start_date'];
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $validCategories = ['National', 'Religious', 'Company-specific', 'Other'];
    
    // Toggles
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $is_half_day = isset($_POST['is_half_day']) ? 1 : 0;
    
    // Branches processing
    $selected_branches = isset($_POST['branches']) && is_array($_POST['branches']) ? $_POST['branches'] : [];
    $applies_to_all_branches = empty($selected_branches) ? 1 : 0;

    if ($end_date === null) {
        $end_date = $start_date;
    }

    if (!in_array($category, $validCategories, true)) {
        $category = 'Other';
    }

    if ($name === '' || strtotime($start_date) === false || strtotime($end_date) === false || strtotime($end_date) < strtotime($start_date)) {
        die("Invalid holiday details.");
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert master Holiday record
        $stmt = $pdo->prepare("
            INSERT INTO holidays (name, start_date, end_date, category, description, is_recurring, is_paid, is_half_day, applies_to_all_branches) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $start_date, $end_date, $category, $description, $is_recurring, $is_paid, $is_half_day, $applies_to_all_branches]);
        
        $holiday_id = $pdo->lastInsertId();

        // 2. Insert Branch Mappings (if applicable)
        if (!$applies_to_all_branches) {
            $branchStmt = $pdo->prepare("INSERT INTO holiday_branches (holiday_id, branch_id) VALUES (?, ?)");
            foreach ($selected_branches as $bid) {
                $branchStmt->execute([$holiday_id, intval($bid)]);
            }
        }

        $pdo->commit();
        log_action($pdo, $currentUser['id'], 'HOLIDAY_CREATED', "Scheduled new holiday: {$name}");
        
        header("Location: index.php?success=holiday_added");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error saving holiday: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
