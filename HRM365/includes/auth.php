<?php
session_start();
require_once __DIR__ . '/db.php';

// Simple auth middleware
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('login.php'));
    exit();
}

$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'] ?? 'employee',
    'employee_id' => $_SESSION['employee_id'] ?? null,
    'department' => null
];

$userStmt = $pdo->prepare("SELECT username, role, full_name, employee_id, department FROM users WHERE id = ? AND status = 'Active'");
$userStmt->execute([$currentUser['id']]);
$freshUser = $userStmt->fetch();

if (!$freshUser) {
    session_destroy();
    header('Location: ' . app_url('login.php'));
    exit();
}

$currentUser['username'] = $freshUser['username'];
$currentUser['role'] = $freshUser['role'];
$currentUser['employee_id'] = $freshUser['employee_id'];
$currentUser['department'] = $freshUser['department'];

$_SESSION['username'] = $freshUser['username'];
$_SESSION['role'] = $freshUser['role'];
$_SESSION['employee_id'] = $freshUser['employee_id'];
$_SESSION['full_name'] = $freshUser['full_name'];

if ($currentUser['employee_id']) {
    $authStmt = $pdo->prepare("SELECT department FROM employees WHERE id = ?");
    $authStmt->execute([$currentUser['employee_id']]);
    $authEmp = $authStmt->fetch();
    if ($authEmp) {
        $currentUser['department'] = $authEmp['department'];
    }
}
?>
