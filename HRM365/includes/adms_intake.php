<?php

function adms_log_request(string $message): void
{
    $logFile = __DIR__ . '/../adms_debug.log';
    $remote = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $entry = '[' . date('Y-m-d H:i:s') . "] {$remote} {$uri} {$message}\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

function adms_direction_from_status(string $status_code): string
{
    if ($status_code === '0' || $status_code === '4') {
        return 'CHECK_IN';
    }

    if ($status_code === '1' || $status_code === '5') {
        return 'CHECK_OUT';
    }

    return 'UNKNOWN';
}

function adms_store_attlog(PDO $pdo, string $raw_data, string $terminal_sn): int
{
    $inserted = 0;
    $lines = preg_split('/\r\n|\r|\n/', trim($raw_data));

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = preg_split('/\s+/', $line);
        if (count($parts) < 3) {
            adms_log_request("Skipped malformed ATTLOG line: {$line}");
            continue;
        }

        $user_id = trim($parts[0]);
        $timestamp = trim($parts[1]) . ' ' . trim($parts[2]);
        $status_code = isset($parts[3]) ? trim($parts[3]) : '0';

        if (strtotime($timestamp) === false) {
            adms_log_request("Skipped ATTLOG line with invalid timestamp: {$line}");
            continue;
        }

        $direction = adms_direction_from_status($status_code);

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO biometric_punches
            (biometric_user_id, punch_time, punch_direction, terminal_sn)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, date('Y-m-d H:i:s', strtotime($timestamp)), $direction, $terminal_sn]);
        $inserted += $stmt->rowCount();
    }

    return $inserted;
}

