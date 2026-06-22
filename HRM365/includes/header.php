<?php
require_once __DIR__ . '/auth.php';

$notificationCount = 0;
try {
    if (in_array($currentUser['role'], ['admin', 'HR'])) {
        $notificationCount += intval($pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Pending'")->fetchColumn());
        $notificationCount += intval($pdo->query("SELECT COUNT(*) FROM attendance_regularizations WHERE status = 'Pending'")->fetchColumn());
        $notificationCount += intval($pdo->query("SELECT COUNT(*) FROM biometric_punches WHERE is_synced = 0")->fetchColumn());
    } elseif (!empty($currentUser['employee_id'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE employee_id = ? AND status = 'Pending'");
        $stmt->execute([$currentUser['employee_id']]);
        $notificationCount += intval($stmt->fetchColumn());

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_regularizations WHERE employee_id = ? AND status = 'Pending'");
        $stmt->execute([$currentUser['employee_id']]);
        $notificationCount += intval($stmt->fetchColumn());
    }
} catch (\PDOException $e) {
    $notificationCount = 0;
}

$stylesheetPath = __DIR__ . '/../css/styles.css';
$stylesheetVersion = is_file($stylesheetPath) ? filemtime($stylesheetPath) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRM365 - Enterprise HR System</title>
    <link rel="stylesheet" href="<?php echo app_url('css/styles.css'); ?>?v=<?php echo $stylesheetVersion; ?>">
    <!-- FontAwesome for icons (using a CDN for quick prototyping) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-wrapper">
        <header class="top-header">
            <div class="header-search">
                <i class="fas fa-search text-secondary"></i>
                <input type="text" id="globalSearchInput" placeholder="Search employees, leaves..." autocomplete="off">
                <div id="globalSearchResults" class="global-search-results"></div>
            </div>
            
            <div class="header-actions">
                <a href="<?php echo app_url('modules/notifications/index.php'); ?>" class="action-btn" title="Notifications">
                    <i class="far fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </a>
                <div class="user-profile" style="display: flex; align-items: center; gap: 1rem;">
                    <a href="<?php echo app_url('profile.php'); ?>" style="display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: inherit;">
                        <div class="avatar"><?php echo strtoupper(substr($currentUser['username'], 0, 2)); ?></div>
                        <div class="user-info">
                            <div style="font-size: 0.9rem; font-weight: 500; color: var(--text-primary);">
                                <?php echo htmlspecialchars($_SESSION['full_name'] ?? $currentUser['username']); ?>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">
                                <?php echo htmlspecialchars($currentUser['role']); ?>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo app_url('logout.php'); ?>" style="color: var(--accent-danger);" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            </div>
        </header>
        
        <main class="content-area">
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const input = document.getElementById('globalSearchInput');
                    const results = document.getElementById('globalSearchResults');
                    let timer = null;

                    if (!input || !results) return;

                    function hideResults() {
                        results.style.display = 'none';
                        results.innerHTML = '';
                    }

                    function escapeHtml(value) {
                        return String(value).replace(/[&<>"']/g, function(char) {
                            return {
                                '&': '&amp;',
                                '<': '&lt;',
                                '>': '&gt;',
                                '"': '&quot;',
                                "'": '&#039;'
                            }[char];
                        });
                    }

                    input.addEventListener('input', function() {
                        const q = input.value.trim();
                        clearTimeout(timer);

                        if (q.length < 2) {
                            hideResults();
                            return;
                        }

                        timer = setTimeout(function() {
                            fetch('<?php echo app_url('api/search.php'); ?>?q=' + encodeURIComponent(q))
                                .then(response => response.json())
                                .then(data => {
                                    const items = data.results || [];
                                    if (!items.length) {
                                        results.innerHTML = '<div class="global-search-empty">No results found</div>';
                                        results.style.display = 'block';
                                        return;
                                    }

                                    results.innerHTML = items.map(item => `
                                        <a href="${escapeHtml(item.url)}" class="global-search-item">
                                            <span class="global-search-type">${escapeHtml(item.type)}</span>
                                            <span class="global-search-title">${escapeHtml(item.title)}</span>
                                            <span class="global-search-meta">${escapeHtml(item.meta)}</span>
                                        </a>
                                    `).join('');
                                    results.style.display = 'block';
                                })
                                .catch(hideResults);
                        }, 200);
                    });

                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            hideResults();
                            input.blur();
                        }
                    });

                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.header-search')) {
                            hideResults();
                        }
                    });
                });
            </script>
