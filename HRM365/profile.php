<?php 
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/audit.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $new_password = $_POST['new_password'];

    try {
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, password = ? WHERE id = ?");
            $stmt->execute([$full_name, $hashed, $currentUser['id']]);
            log_action($pdo, $currentUser['id'], 'PROFILE_UPDATE', "User updated profile and changed password");
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            $stmt->execute([$full_name, $currentUser['id']]);
            log_action($pdo, $currentUser['id'], 'PROFILE_UPDATE', "User updated profile settings");
        }
        
        $_SESSION['full_name'] = $full_name; // Update session
        $success = "Profile updated successfully!";
    } catch (\PDOException $e) {
        $error = "Failed to update profile.";
    }
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUser['id']]);
$user = $stmt->fetch();

include 'includes/header.php'; 
?>

<div class="page-header">
    <div>
        <h1 class="page-title">My Profile</h1>
        <div class="page-subtitle">Manage your account settings and preferences.</div>
    </div>
</div>

<?php if ($success): ?>
    <div style="background: rgba(16, 185, 129, 0.1); color: var(--accent-success); border: 1px solid var(--accent-success); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background: rgba(239, 68, 68, 0.1); color: var(--accent-danger); border: 1px solid var(--accent-danger); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 600px;">
    <form action="profile.php" method="POST">
        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Username / Email</label>
            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: rgba(0,0,0,0.2); color: var(--text-muted); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <div class="mb-4">
            <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Reset Password (leave blank to keep current)</label>
            <input type="password" name="new_password" placeholder="Enter new password" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-primary); outline: none;">
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save"></i> Save Profile</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
