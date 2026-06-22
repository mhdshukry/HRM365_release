        <?php if (!function_exists('app_url')) { require_once __DIR__ . '/db.php'; } ?>
        </main>
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
