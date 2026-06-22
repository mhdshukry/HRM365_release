<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT d.*, e.department
        FROM documents d
        JOIN employees e ON d.employee_id = e.id
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        die("Document not found.");
    }

    if ($currentUser['role'] === 'employee' && intval($doc['employee_id']) !== intval($currentUser['employee_id'] ?? 0)) {
        die("Unauthorized access.");
    }

    if ($currentUser['role'] === 'manager' && $doc['department'] !== ($currentUser['department'] ?? null)) {
        die("Unauthorized access.");
    }

    if (!in_array($currentUser['role'], ['admin', 'HR', 'manager'], true)) {
        die("Unauthorized access.");
    }

    try {
        $delete = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $delete->execute([$id]);

        $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $doc['file_path']);
        $absolutePath = realpath(__DIR__ . '/../../' . $relativePath);
        $uploadRoot = realpath(__DIR__ . '/../../uploads/documents');

        if ($absolutePath && $uploadRoot && strpos($absolutePath, $uploadRoot) === 0 && is_file($absolutePath)) {
            unlink($absolutePath);
        }

        log_action($pdo, $currentUser['id'], 'DOCUMENT_DELETED', "Deleted document '{$doc['title']}' for Employee #{$doc['employee_id']}");

        header("Location: documents.php?id={$doc['employee_id']}&success=document_deleted");
        exit();
    } catch (\PDOException $e) {
        die("Error deleting document: " . $e->getMessage());
    }
}

header("Location: index.php");
exit();
?>
