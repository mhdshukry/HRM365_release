<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

/** @var array<string, mixed> $currentUser */

// Employees can see their own docs. Admins and Managers can see all or specific department.
$emp_id = intval($_GET['id'] ?? ($currentUser['role'] === 'employee' ? $currentUser['employee_id'] : 0));

if (!$emp_id) {
    die("Employee ID required.");
}

// Fetch employee details
$empStmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
$empStmt->execute([$emp_id]);
$employee = $empStmt->fetch();

if (!$employee) {
    die("Employee not found.");
}

// Security: If manager, ensure employee is in their department
if ($currentUser['role'] === 'manager' && $employee['department'] !== $currentUser['department']) {
    die("Unauthorized access to this employee's vault.");
}

// Security: If employee, ensure it's their own ID
if ($currentUser['role'] === 'employee' && $emp_id != $currentUser['employee_id']) {
    die("Unauthorized access.");
}

// Fetch documents
$docStmt = $pdo->prepare("
    SELECT d.*, u.username as uploader_name 
    FROM documents d
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE d.employee_id = ?
    ORDER BY d.created_at DESC
");
$docStmt->execute([$emp_id]);
$documents = $docStmt->fetchAll();

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Document Vault: <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h1>
        <div class="page-subtitle">Secure storage for KYC, Tax Forms, and Employment Contracts.</div>
    </div>
    <div style="display: flex; gap: 1rem;">
        <a href="index.php" class="btn" style="background: var(--bg-hover); color: var(--text-primary);">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button class="btn btn-primary" onclick="document.getElementById('uploadModal').style.display='block'">
            <i class="fas fa-cloud-upload-alt"></i> Upload Document
        </button>
    </div>
</div>

<div class="grid-cards" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">
    <?php foreach ($documents as $doc): ?>
    <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div style="display: flex; align-items: flex-start; gap: 1rem; margin-bottom: 1rem;">
            <div class="metric-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); width: 48px; height: 48px; font-size: 1.5rem; flex-shrink: 0;">
                <i class="fas fa-file-pdf"></i>
            </div>
            <div>
                <h4 style="margin: 0; color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($doc['title']); ?></h4>
                <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                    Uploaded by: <?php echo htmlspecialchars($doc['uploader_name'] ?? 'Unknown'); ?><br>
                    Date: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="serve_doc.php?id=<?php echo $doc['id']; ?>&mode=view" target="_blank" class="btn" style="flex: 1; text-align: center; background: var(--bg-hover); color: var(--text-primary); padding: 0.5rem; font-size: 0.85rem;">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="serve_doc.php?id=<?php echo $doc['id']; ?>&mode=download" class="btn btn-primary" style="flex: 1; text-align: center; padding: 0.5rem; font-size: 0.85rem;">
                <i class="fas fa-download"></i> Download
            </a>
            <?php if (in_array($currentUser['role'], ['admin', 'HR', 'manager'])): ?>
                <form action="delete_doc.php" method="POST" style="margin: 0;" onsubmit="return confirm('Delete this document?');">
                    <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                    <button type="submit" class="btn" style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); padding: 0.5rem; font-size: 0.85rem;">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($documents)): ?>
    <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: var(--bg-secondary); border-radius: var(--radius-lg); border: 1px dashed var(--border-color);">
        <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--text-primary);">Vault is Empty</h3>
        <p style="color: var(--text-secondary);">No documents have been uploaded for this employee yet.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div id="uploadModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); backdrop-filter:blur(5px); z-index:1000; align-items:center; justify-content:center;">
    <div class="card" style="width: 100%; max-width: 500px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
            <h3 style="font-size: 1.25rem;">Upload Document</h3>
            <button class="action-btn" onclick="document.getElementById('uploadModal').style.display='none'"><i class="fas fa-times"></i></button>
        </div>
        <form action="upload_doc.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="employee_id" value="<?php echo $emp_id; ?>">
            <div class="mb-4">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Document Title</label>
                <input type="text" name="title" required placeholder="e.g. W-2 Tax Form 2026" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
            </div>
            <div class="mb-4">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Select File (PDF, JPG, PNG)</label>
                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px dashed var(--accent-primary); background: rgba(59, 130, 246, 0.05); color: var(--text-primary); outline: none;">
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">Maximum file size: 5 MB.</small>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn" onclick="document.getElementById('uploadModal').style.display='none'" style="background: var(--bg-hover); color: var(--text-primary);">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-upload-alt"></i> Upload Securely</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
