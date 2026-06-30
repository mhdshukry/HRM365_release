        <?php if (!function_exists('app_url')) { require_once __DIR__ . '/db.php'; } ?>
        </main>
        <div style="margin-top: auto; padding: 1.25rem 2rem; font-size: 0.85rem; color: #6b7280; border-top: 1px solid var(--border-color); background: #ffffff; width: 100%;">
            &copy; <?php echo date('Y'); ?> <strong style="color: #ea580c;">HRM365</strong>. All rights reserved. <span style="margin: 0 0.75rem; color: #d1d5db;">|</span> Developed by &lt;/&gt; <a href="https://lushanth.com/" target="_blank" style="color: #ea580c; text-decoration: none; font-weight: bold;">Lushanth Pvt Ltd</a>
        </div>
    </div>

    <?php
    $scriptPath = __DIR__ . '/../js/app.js';
    $scriptVersion = is_file($scriptPath) ? filemtime($scriptPath) : time();
    ?>
    <script src="<?php echo app_url('js/app.js'); ?>?v=<?php echo $scriptVersion; ?>"></script>
    <script>
        // Auto-sync Biometric data in the background every 5 minutes (300,000 ms)
        setInterval(function() {
            fetch('<?php echo app_url('cli_sync.php'); ?>').catch(e => console.error('Auto-sync failed:', e));
        }, 300000);
        
        // Also run once immediately on page load, quietly in the background
        setTimeout(function() {
            fetch('<?php echo app_url('cli_sync.php'); ?>').catch(e => {});
        }, 5000);
    </script>
</body>
</html>
