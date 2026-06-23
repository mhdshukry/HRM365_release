<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Location: ' . app_url('modules/dashboard/index.php'));
exit();
?>
