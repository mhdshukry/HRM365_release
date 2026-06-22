<?php
require_once '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['date']);
    
    // Grab the first employee in the system that has a biometric_user_id, or just the first employee
    $empStmt = $pdo->query("SELECT biometric_user_id FROM employees WHERE biometric_user_id IS NOT NULL LIMIT 1");
    $emp = $empStmt->fetch();
    
    $bio_id = $emp ? $emp['biometric_user_id'] : 'TEST_BIO_123';
    
    $clock_in = $date . ' 08:35:00'; // 35 mins late!
    $redundant_scan = $date . ' 09:00:00'; // redundant scan within the first hour
    $clock_out = $date . ' 18:30:00'; // 1.5 hours Overtime!
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO biometric_punches (biometric_user_id, punch_time, punch_direction, terminal_sn) VALUES (?, ?, ?, ?)");
    
    // Inject Morning Fingerprint
    $stmt->execute([$bio_id, $clock_in, 'CHECK_IN', 'SN-BRANCH-A']);
    // Inject redundant scan
    $stmt->execute([$bio_id, $redundant_scan, 'UNKNOWN', 'SN-BRANCH-A']);
    // Inject Evening Face Scan
    $stmt->execute([$bio_id, $clock_out, 'CHECK_OUT', 'SN-BRANCH-A']);
    
    header("Location: index.php?date={$date}&success=simulated");
    exit();
}
?>
