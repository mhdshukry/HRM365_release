<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    if ($name === '') {
        die("Branch name is required.");
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Please enter a valid branch email address.");
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        die("Invalid branch status.");
    }

    try {
        if ($id > 0) {
            $existsStmt = $pdo->prepare("SELECT id FROM branches WHERE id = ?");
            $existsStmt->execute([$id]);
            if (!$existsStmt->fetchColumn()) {
                die("Branch not found.");
            }

            $stmt = $pdo->prepare("
                UPDATE branches
                SET name = ?, address = ?, phone = ?, email = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $address, $phone, $email, $status, $id]);

            log_action($pdo, $currentUser['id'], 'BRANCH_UPDATED', "Updated branch: {$name}");

            header("Location: index.php?success=branch_updated");
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO branches (name, address, phone, email, status)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $address, $phone, $email, $status]);

            log_action($pdo, $currentUser['id'], 'BRANCH_CREATED', "Created new branch: {$name}");

            header("Location: index.php?success=branch_added");
        }
        exit();
    } catch (\PDOException $e) {
        die("Error saving branch: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
