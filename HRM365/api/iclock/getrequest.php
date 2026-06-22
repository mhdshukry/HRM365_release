<?php
require_once '../../includes/adms_intake.php';

// ZKTeco devices constantly poll this endpoint asking if the server has any commands for them.
// A simple "OK" response tells the device there are no pending commands.
header("Content-Type: text/plain");
adms_log_request('API getrequest hit method=' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
echo "OK";
?>
