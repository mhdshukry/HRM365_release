<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

require_once '../../includes/db.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit();
}

// Extract JSON payload
$jsonStr = file_get_contents("php://input");
$data = json_decode($jsonStr, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
    exit();
}

$biometricUserId = $data['biometricUserId'] ?? null;
$timestamp = $data['timestamp'] ?? null;
$punchDirection = strtoupper(trim($data['punchDirection'] ?? 'UNKNOWN'));
$hardwareMechanism = $data['hardwareMechanism'] ?? 'UNKNOWN_HARDWARE_INPUT';
$terminalSn = $data['terminalSn'] ?? $data['terminalSN'] ?? 'ZK_MIDDLEWARE';

if (!$biometricUserId || !$timestamp) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit();
}

if (strtotime($timestamp) === false) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid timestamp"]);
    exit();
}

if (!in_array($punchDirection, ['CHECK_IN', 'CHECK_OUT', 'UNKNOWN'], true)) {
    $punchDirection = 'UNKNOWN';
}

$punchTime = date('Y-m-d H:i:s', strtotime($timestamp));

try {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO biometric_punches
        (biometric_user_id, punch_time, punch_direction, terminal_sn)
        VALUES (:b_id, :ts, :dir, :terminal_sn)
    ");
    $stmt->execute([
        ':b_id' => $biometricUserId,
        ':ts'   => $punchTime,
        ':dir'  => $punchDirection,
        ':terminal_sn' => $terminalSn
    ]);

    // Log the transaction locally for verification during development
    $logEntry = date('Y-m-d H:i:s') . " | INGEST: User=$biometricUserId | Time=$punchTime | Dir=$punchDirection | Terminal=$terminalSn | Mech=$hardwareMechanism\n";
    file_put_contents(__DIR__ . '/../../punch_logs.txt', $logEntry, FILE_APPEND);

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => $stmt->rowCount() > 0 ? "Biometric punch queued successfully" : "Duplicate biometric punch ignored",
        "data" => [
            "biometricUserId" => $biometricUserId,
            "punchTime" => $punchTime,
            "terminalSn" => $terminalSn,
            "recordedAt" => date('c')
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal Server Error: " . $e->getMessage()]);
}
?>
