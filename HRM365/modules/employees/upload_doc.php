<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id = intval($_POST['employee_id'] ?? 0);
    $title = trim($_POST['title'] ?? 'Untitled');
    
    if (!$emp_id || !isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        die("Invalid upload request.");
    }

    $empStmt = $pdo->prepare("SELECT id, department FROM employees WHERE id = ?");
    $empStmt->execute([$emp_id]);
    $employee = $empStmt->fetch();
    if (!$employee) {
        die("Employee not found.");
    }

    if ($currentUser['role'] === 'employee') {
        $emp_id = intval($currentUser['employee_id'] ?? 0);
        if ($emp_id !== intval($employee['id'])) {
            die("Unauthorized upload target.");
        }
    } elseif ($currentUser['role'] === 'manager' && $employee['department'] !== ($currentUser['department'] ?? null)) {
        die("Unauthorized upload target.");
    }

    if ($title === '') {
        die("Document title is required.");
    }

    $maxBytes = 5 * 1024 * 1024;
    if (filesize($_FILES['document']['tmp_name']) > $maxBytes) {
        die("Security Violation: Maximum file size is 5 MB.");
    }
    
    // Strict MIME type validation
    $allowed_mimes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $file_mime = mime_content_type($_FILES['document']['tmp_name']);
    
    if (!isset($allowed_mimes[$file_mime])) {
        die("Security Violation: Only PDF, JPG, and PNG files are allowed.");
    }
    
    // Generate secure unique filename
    $ext = $allowed_mimes[$file_mime];
    $filename = 'doc_' . bin2hex(random_bytes(12)) . '_' . time() . '.' . $ext;
    
    $upload_dir = '../../uploads/documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($_FILES['document']['tmp_name'], $destination)) {
        // Insert into DB
        $db_path = 'uploads/documents/' . $filename;
        $stmt = $pdo->prepare("INSERT INTO documents (employee_id, title, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$emp_id, $title, $db_path, $currentUser['id']]);
        
        // Audit log
        log_action($pdo, $currentUser['id'], 'DOCUMENT_UPLOAD', "Uploaded document '{$title}' for Employee #{$emp_id}");
        
        header("Location: documents.php?id=" . $emp_id . "&success=upload_complete");
        exit();
    } else {
        die("Failed to move uploaded file.");
    }
}
?>
