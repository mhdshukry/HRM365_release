<?php
require_once __DIR__ . '/../HRM365/includes/db.php';
require_once __DIR__ . '/../HRM365/includes/adms_intake.php';

header("Content-Type: text/plain");

$table = strtoupper($_GET['table'] ?? '');
$terminal_sn = $_GET['SN'] ?? $_GET['sn'] ?? 'UNKNOWN_DEVICE';
adms_log_request('Root cdata entered method=' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . ' table=' . ($table ?: 'NONE'));
$raw_data = file_get_contents('php://input') ?: '';

adms_log_request('Root cdata hit method=' . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . ' table=' . ($table ?: 'NONE') . ' bytes=' . strlen($raw_data));

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

echo "OK";
