<?php
require_once __DIR__ . '/../HRM365/includes/adms_intake.php';

// ZKTeco devices constantly poll this endpoint
header("Content-Type: text/plain");
adms_log_request('Root getrequest hit method=' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
echo "OK";
?>
