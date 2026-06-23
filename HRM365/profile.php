<?php 
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/audit.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $new_password = $_POST['new_password'];
    $profile_photo_path = null;

    try {
        if (!empty($currentUser['employee_id']) && isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $maxPhotoBytes = 2 * 1024 * 1024;
            if (filesize($_FILES['profile_photo']['tmp_name']) > $maxPhotoBytes) {
                throw new RuntimeException("Maximum profile photo size is 2 MB.");
            }

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
            ];
            $fileMime = mime_content_type($_FILES['profile_photo']['tmp_name']);
            if (!isset($allowedMimes[$fileMime])) {
                throw new RuntimeException("Only JPG and PNG profile photos are allowed.");
            }

            $filename = 'profile_' . bin2hex(random_bytes(12)) . '_' . time() . '.' . $allowedMimes[$fileMime];
            $uploadDir = __DIR__ . '/uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $uploadDir . $filename)) {
                throw new RuntimeException("Failed to upload profile photo.");
            }

            $profile_photo_path = 'uploads/profiles/' . $filename;
        }

        if ($profile_photo_path !== null) {
            $photoStmt = $pdo->prepare("SELECT profile_photo FROM employees WHERE id = ?");
            $photoStmt->execute([$currentUser['employee_id']]);
            $oldPhoto = $photoStmt->fetchColumn();

            $updatePhoto = $pdo->prepare("UPDATE employees SET profile_photo = ? WHERE id = ?");
            $updatePhoto->execute([$profile_photo_path, $currentUser['employee_id']]);
            $currentUser['profile_photo'] = $profile_photo_path;

            if (!empty($oldPhoto)) {
                $relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldPhoto);
                $absolutePath = realpath(__DIR__ . '/' . $relativePath);
                $profileRoot = realpath(__DIR__ . '/uploads/profiles');
                if ($absolutePath && $profileRoot && strpos($absolutePath, $profileRoot) === 0 && is_file($absolutePath)) {
                    unlink($absolutePath);
                }
            }
        }

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
    } catch (\Throwable $e) {
        $error = $e instanceof RuntimeException ? $e->getMessage() : "Failed to update profile.";
    }
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$currentUser['id']]);
$user = $stmt->fetch();

$profileEmployee = null;
if (!empty($currentUser['employee_id'])) {
    $empStmt = $pdo->prepare("SELECT first_name, last_name, employee_code, profile_photo FROM employees WHERE id = ?");
    $empStmt->execute([$currentUser['employee_id']]);
    $profileEmployee = $empStmt->fetch();
}

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
    <form action="profile.php" method="POST" enctype="multipart/form-data">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
            <?php echo render_avatar($profileEmployee['first_name'] ?? ($currentUser['first_name'] ?? null), $profileEmployee['last_name'] ?? ($currentUser['last_name'] ?? null), $profileEmployee['profile_photo'] ?? ($currentUser['profile_photo'] ?? null), $currentUser['username'], 'avatar', 'width: 72px; height: 72px; border-radius: 14px; font-size: 1.35rem;'); ?>
            <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 800; color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($user['full_name'] ?? $currentUser['username']); ?></div>
                <div style="color: var(--text-muted); font-size: 0.82rem; margin-top: 0.2rem;"><?php echo htmlspecialchars($profileEmployee['employee_code'] ?? 'No linked employee profile'); ?></div>
            </div>
        </div>

        <?php if (!empty($currentUser['employee_id'])): ?>
            <div class="mb-4">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.9rem;">Profile Picture</label>
                <input type="file" name="profile_photo" accept="image/jpeg,image/png" style="width: 100%; padding: 0.75rem; border-radius: var(--radius-md); border: 1px dashed var(--accent-primary); background: rgba(59, 130, 246, 0.05); color: var(--text-primary); outline: none;">
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">JPG or PNG only, maximum 2 MB.</small>
            </div>
        <?php endif; ?>

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
