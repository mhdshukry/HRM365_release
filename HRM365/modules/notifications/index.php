<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/notification_helper.php';

$notifications = notification_items($pdo, $currentUser, 50);

include '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Notifications</h1>
        <div class="page-subtitle">Pending approvals, sync items, and items needing attention.</div>
    </div>
</div>

<div class="card">
    <?php if (!empty($notifications)): ?>
        <div style="display: flex; flex-direction: column;">
            <?php foreach ($notifications as $item): ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>" style="display: flex; gap: 1rem; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--border-color); text-decoration: none; color: inherit;">
                    <div style="width: 42px; height: 42px; border-radius: var(--radius-md); background: var(--bg-hover); color: var(--accent-primary); display: flex; align-items: center; justify-content: center;">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;"><?php echo htmlspecialchars($item['type']); ?></div>
                        <div style="font-weight: 700; color: var(--text-primary); margin-top: 0.15rem;"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div style="font-size: 0.82rem; color: var(--text-secondary); margin-top: 0.2rem;"><?php echo htmlspecialchars($item['meta']); ?></div>
                    </div>
                    <i class="fas fa-chevron-right" style="color: var(--text-muted);"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="padding: 3rem; text-align: center; color: var(--text-muted);">
            <i class="far fa-bell" style="font-size: 2.25rem; opacity: 0.35; margin-bottom: 1rem;"></i><br>
            No pending notifications.
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
