<?php
require_once __DIR__ . '/avatar.php';
/** @var array<string, mixed> $currentUser */
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo app_url('modules/dashboard/index.php'); ?>" class="sidebar-logo" style="justify-content: center; padding: 1rem 0;">
            <div class="logo-icon" style="display: flex; align-items: center; justify-content: center;">
                <img src="<?php echo app_url('LOGO.png'); ?>" alt="Logo" style="max-width: 140px; height: auto;">
            </div>
        </a>
    </div>
    
    <nav class="sidebar-nav" id="sidebarNav">
        <?php $uri = $_SERVER['REQUEST_URI']; ?>

        <!-- MAIN -->
        <div class="nav-section-label">Main</div>
        <a href="<?php echo app_url('modules/dashboard/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/dashboard') !== false ? 'active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo app_url('modules/calendar/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/calendar') !== false ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span>Calendar Overview</span>
        </a>
        <a href="<?php echo app_url('modules/meetings/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/meetings') !== false ? 'active' : ''; ?>">
            <i class="fas fa-video nav-icon"></i>
            <span>Meetings</span>
        </a>

        <?php if (in_array($currentUser['role'], ['admin', 'manager', 'HR'])): ?>
        <!-- ORGANIZATION -->
        <div class="nav-section-label">Organization</div>
        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
        <a href="<?php echo app_url('modules/branches/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/branches') !== false ? 'active' : ''; ?>">
            <i class="fas fa-building nav-icon"></i>
            <span>Branches</span>
        </a>
        <?php endif; ?>
        <a href="<?php echo app_url('modules/employees/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/employees') !== false ? 'active' : ''; ?>">
            <i class="fas fa-users nav-icon"></i>
            <span>Employees</span>
        </a>
        <a href="<?php echo app_url('modules/holidays/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/holidays') !== false ? 'active' : ''; ?>">
            <i class="fas fa-umbrella-beach nav-icon"></i>
            <span>Holidays</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
        <?php if (!in_array($currentUser['role'], ['admin', 'manager', 'HR'])): ?>
        <div class="nav-section-label">Organization</div>
        <?php endif; ?>
        <a href="<?php echo app_url('modules/users/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/users') !== false ? 'active' : ''; ?>">
            <i class="fas fa-user-shield nav-icon"></i>
            <span>Users & Roles</span>
        </a>
        <?php endif; ?>

        <!-- LEAVE MANAGEMENT -->
        <div class="nav-section-label">Leave Management</div>
        <a href="<?php echo app_url('modules/leave_applications/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/leave_applications') !== false ? 'active' : ''; ?>">
            <i class="fas fa-paper-plane nav-icon"></i>
            <span>Applications</span>
        </a>
        <a href="<?php echo app_url('modules/leave_types/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/leave_types') !== false ? 'active' : ''; ?>">
            <i class="fas fa-tags nav-icon"></i>
            <span>Leave Types</span>
        </a>
        <a href="<?php echo app_url('modules/leave_policies/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/leave_policies') !== false ? 'active' : ''; ?>">
            <i class="fas fa-file-contract nav-icon"></i>
            <span>Leave Policies</span>
        </a>
        <a href="<?php echo app_url('modules/leave_balances/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/leave_balances') !== false ? 'active' : ''; ?>">
            <i class="fas fa-balance-scale nav-icon"></i>
            <span>Leave Balances</span>
        </a>

        <!-- ATTENDANCE MANAGEMENT -->
        <div class="nav-section-label">Attendance</div>
        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
        <a href="<?php echo app_url('modules/shifts/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/shifts') !== false ? 'active' : ''; ?>">
            <i class="fas fa-clock nav-icon"></i>
            <span>Shifts</span>
        </a>
        <a href="<?php echo app_url('modules/attendance_assignments/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/attendance_assignments') !== false ? 'active' : ''; ?>">
            <i class="fas fa-user-clock nav-icon"></i>
            <span>Assignments</span>
        </a>
        <a href="<?php echo app_url('modules/attendance_policies/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/attendance_policies') !== false ? 'active' : ''; ?>">
            <i class="fas fa-gavel nav-icon"></i>
            <span>Policies</span>
        </a>
        <?php endif; ?>
        <a href="<?php echo app_url('modules/attendance_records/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/attendance_records') !== false ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list nav-icon"></i>
            <span>Records</span>
        </a>
        <a href="<?php echo app_url('modules/regularizations/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/regularizations') !== false ? 'active' : ''; ?>">
            <i class="fas fa-edit nav-icon"></i>
            <span>Regularizations</span>
        </a>
        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
        <a href="<?php echo app_url('modules/biometric_records/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/biometric_records') !== false ? 'active' : ''; ?>">
            <i class="fas fa-fingerprint nav-icon"></i>
            <span>Biometric Sync</span>
        </a>
        <?php endif; ?>

        <!-- REPORTS -->
        <div class="nav-section-label">Reports</div>
        <a href="<?php echo app_url('modules/reports/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/reports') !== false ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar nav-icon"></i>
            <span>Reports</span>
        </a>

        <!-- PAYROLL & FINANCE -->
        <?php if (in_array($currentUser['role'], ['admin', 'HR', 'employee'])): ?>
        <div class="nav-section-label">Payroll & Finance</div>
        <a href="<?php echo app_url('modules/payroll/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/payroll') !== false ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar nav-icon"></i>
            <span>Payroll Engine</span>
        </a>
        <a href="<?php echo app_url('modules/advance_payments/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/advance_payments') !== false ? 'active' : ''; ?>">
            <i class="fas fa-hand-holding-usd nav-icon"></i>
            <span>Advance Payments</span>
        </a>
        <?php endif; ?>

        <!-- SYSTEM -->
        <?php if (in_array($currentUser['role'], ['admin', 'HR'])): ?>
        <div class="nav-section-label">System</div>
        <a href="<?php echo app_url('modules/settings/index.php'); ?>" class="nav-item <?php echo strpos($uri, '/modules/settings') !== false ? 'active' : ''; ?>">
            <i class="fas fa-cog nav-icon"></i>
            <span>Settings</span>
        </a>
        <?php endif; ?>
    </nav>

    <script>
        (function() {
            const nav = document.getElementById('sidebarNav');
            if (!nav) return;

            const key = 'hrm365.sidebar.scrollTop';
            const activeItem = nav.querySelector('.nav-item.active');

            function restoreSidebarScroll() {
                const saved = parseInt(localStorage.getItem(key) || '0', 10);
                if (saved > 0) {
                    nav.scrollTop = saved;
                } else if (activeItem) {
                    activeItem.scrollIntoView({ block: 'center' });
                }
            }

            function saveSidebarScroll() {
                localStorage.setItem(key, String(nav.scrollTop));
            }

            restoreSidebarScroll();
            requestAnimationFrame(restoreSidebarScroll);
            setTimeout(restoreSidebarScroll, 100);

            nav.addEventListener('scroll', saveSidebarScroll, { passive: true });
            nav.addEventListener('click', function(event) {
                if (event.target.closest('a.nav-item')) {
                    saveSidebarScroll();
                }
            }, true);
            window.addEventListener('beforeunload', saveSidebarScroll);
        })();
    </script>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <?php echo render_avatar($currentUser['first_name'] ?? null, $currentUser['last_name'] ?? null, $currentUser['profile_photo'] ?? null, $currentUser['username']); ?>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $currentUser['username']); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars($currentUser['role']); ?></div>
            </div>
            <a href="<?php echo app_url('logout.php'); ?>" class="sidebar-logout" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>
