<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

// Fetch real data from the database using PDO
$query = "SELECT * FROM employees ";
$params = [];
if ($currentUser['role'] === 'manager') {
    $query .= "WHERE department = ? ";
    $params[] = $currentUser['department'];
}
$query .= "ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll();

include '../../includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Employees</h1>
        <div class="page-subtitle">Manage your organization's workforce and biometric ID mappings.</div>
    </div>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-user-plus"></i> Add New Employee
    </a>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Designation</th>
                    <th>Biometric ID</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($emp['employee_code']); ?></strong></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div class="avatar" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                <?php echo substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1); ?>
                            </div>
                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($emp['department']); ?></td>
                    <td><span class="text-secondary"><?php echo htmlspecialchars($emp['designation']); ?></span></td>
                    <td><code style="background: var(--bg-hover); padding: 0.2rem 0.4rem; border-radius: 4px; color: var(--accent-warning);"><?php echo htmlspecialchars($emp['biometric_user_id'] ?? 'Not Mapped'); ?></code></td>
                    <td>
                        <?php if ($emp['status'] === 'Active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-leave"><?php echo htmlspecialchars($emp['status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="documents.php?id=<?php echo $emp['id']; ?>" class="action-btn" style="color: var(--accent-primary);" title="Document Vault"><i class="fas fa-folder-open"></i></a>
                            <a href="edit.php?id=<?php echo $emp['id']; ?>" class="action-btn" style="font-size: 1rem;" title="Edit Profile"><i class="fas fa-edit"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
