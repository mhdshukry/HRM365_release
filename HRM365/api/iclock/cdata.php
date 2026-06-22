<?php
require_once '../../includes/db.php';
require_once '../../includes/adms_intake.php';

// ZKTeco expects an explicit plain text "OK" response
header("Content-Type: text/plain");

// Only process if it's an attendance log push
$table = strtoupper($_GET['table'] ?? '');

// Read the raw plain text payload
$raw_data = file_get_contents('php://input') ?: '';

// Extract the device serial number to handle multi-branch setups
$terminal_sn = $_GET['SN'] ?? $_GET['sn'] ?? 'UNKNOWN_DEVICE';

adms_log_request('API cdata hit method=' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . ' table=' . ($table ?: 'NONE') . ' bytes=' . strlen($raw_data));

if (strtolower($_GET['options'] ?? '') === 'all') {
    echo "GET OPTION FROM: {$terminal_sn}\n";
    echo "Stamp=9999\n";
    echo "OpStamp=9999\n";
    echo "ErrorDelay=60\n";
    echo "Delay=30\n";
    echo "TransTimes=00:00;14:00\n";
    echo "TransInterval=1\n";
    echo "TransFlag=1111000000\n";
    echo "Realtime=1\n";
    echo "Encrypt=0\n";
    exit;
}

if ($table === 'ATTLOG' && trim($raw_data) !== '') {
    try {
        $inserted = adms_store_attlog($pdo, $raw_data, $terminal_sn);
        adms_log_request("Stored {$inserted} ATTLOG punch row(s) from {$terminal_sn}");
    } catch (\PDOException $e) {
        adms_log_request('ATTLOG database error: ' . $e->getMessage());
    }
}

// Important: Return "OK" so the device knows the records were received and can delete them from its internal memory
echo "OK";
?>

