<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

$id = intval($_GET['id'] ?? 0);
$mode = $_GET['mode'] ?? 'view';

$stmt = $pdo->prepare("
    SELECT d.*, e.department
    FROM documents d
    JOIN employees e ON d.employee_id = e.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    die("Document not found.");
}

if ($currentUser['role'] === 'employee' && intval($doc['employee_id']) !== intval($currentUser['employee_id'] ?? 0)) {
    http_response_code(403);
    die("Unauthorized access.");
}

if ($currentUser['role'] === 'manager' && $doc['department'] !== ($currentUser['department'] ?? null)) {
    http_response_code(403);
    die("Unauthorized access.");
}

$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $doc['file_path']);
$absolutePath = realpath(__DIR__ . '/../../' . $relativePath);
$uploadRoot = realpath(__DIR__ . '/../../uploads/documents');

if (!$absolutePath || !$uploadRoot || strpos($absolutePath, $uploadRoot) !== 0 || !is_file($absolutePath)) {
    http_response_code(404);
    die("File not found.");
}

$mime = mime_content_type($absolutePath) ?: 'application/octet-stream';
$downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', $doc['title']);
$extension = pathinfo($absolutePath, PATHINFO_EXTENSION);
if ($extension) {
    $downloadName .= '.' . $extension;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: ' . ($mode === 'download' ? 'attachment' : 'inline') . '; filename="' . $downloadName . '"');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit();
?>
