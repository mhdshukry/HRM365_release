<?php

function sms_settings(PDO $pdo): array
{
    $settings = [
        'sms_enabled' => '1',
        'sms_provider' => 'textlk',
        'sms_api_url' => 'https://app.text.lk/api/v3/sms/send',
        'sms_api_key' => '',
        'sms_sender_name' => 'Pos365.lk',
    ];

    try {
        $stmt = $pdo->query("
            SELECT setting_key, setting_value
            FROM system_settings
            WHERE setting_key IN ('sms_enabled', 'sms_provider', 'sms_api_url', 'sms_api_key', 'sms_sender_name')
        ");
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = (string) $row['setting_value'];
        }
    } catch (Throwable $e) {
    }

    return $settings;
}

function sms_normalize_phone(string $phone): string
{
    $phone = preg_replace('/[^\d+]/', '', trim($phone)) ?: '';
    if (strpos($phone, '00') === 0) {
        return '+' . substr($phone, 2);
    }
    if (strpos($phone, '+') === 0) {
        return $phone;
    }
    if (preg_match('/^0\d{9}$/', $phone)) {
        return '+94' . substr($phone, 1);
    }
    if (preg_match('/^7\d{8}$/', $phone)) {
        return '+94' . $phone;
    }
    return $phone;
}

function sms_debug_log(string $phone, bool $success, string $message): void
{
    $status = $success ? 'SUCCESS' : 'FAILED';
    $safeMessage = str_replace(["\r", "\n"], ' ', $message);
    $line = '[' . date('Y-m-d H:i:s') . "] {$status} phone={$phone} {$safeMessage}" . PHP_EOL;
    @file_put_contents(__DIR__ . '/../sms_debug.log', $line, FILE_APPEND);
}

function sms_send(PDO $pdo, string $phone, string $message): array
{
    $settings = sms_settings($pdo);
    if (($settings['sms_enabled'] ?? '0') !== '1') {
        sms_debug_log($phone, false, 'SMS is disabled.');
        return ['success' => false, 'message' => 'SMS is disabled.'];
    }

    $phone = sms_normalize_phone($phone);
    if ($phone === '') {
        sms_debug_log('-', false, 'Phone number is empty.');
        return ['success' => false, 'message' => 'Phone number is empty.'];
    }

    $apiUrl = trim($settings['sms_api_url'] ?? '');
    $apiKey = trim($settings['sms_api_key'] ?? '');
    $provider = strtolower(trim($settings['sms_provider'] ?? 'textlk'));
    if ($apiUrl === '' || $apiKey === '') {
        sms_debug_log($phone, false, 'SMS API URL or key is missing.');
        return ['success' => false, 'message' => 'SMS API URL or key is missing.'];
    }

    $headers = ['Content-Type: application/x-www-form-urlencoded'];
    $payload = http_build_query(sms_payload($provider, $settings, $phone, $message));
    if ($provider === 'textlk') {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $payload = json_encode(sms_payload($provider, $settings, $phone, $message));
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            $result = ['success' => false, 'message' => 'SMS request failed: ' . $error];
            sms_debug_log($phone, false, $result['message']);
            return $result;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
                'timeout' => 15,
            ],
        ]);
        $body = @file_get_contents($apiUrl, false, $context);
        if ($body === false) {
            $result = ['success' => false, 'message' => 'SMS request failed.'];
            sms_debug_log($phone, false, $result['message']);
            return $result;
        }
    }

    $decoded = json_decode((string) $body, true);
    if (is_array($decoded)) {
        $result = sms_result($provider, $decoded);
        sms_debug_log($phone, $result['success'], $result['message']);
        return $result;
    }

    sms_debug_log($phone, true, 'SMS API response received.');
    return ['success' => true, 'message' => 'SMS API response received.'];
}

function sms_payload(string $provider, array $settings, string $phone, string $message): array
{
    $sender = trim($settings['sms_sender_name'] ?? 'Pos365.lk') ?: 'Pos365.lk';

    if ($provider === 'textlk') {
        return [
            'recipient' => $phone,
            'sender_id' => $sender,
            'type' => 'plain',
            'message' => $message,
        ];
    }

    return [
        'phone' => $phone,
        'message' => $message,
        'key' => trim($settings['sms_api_key'] ?? ''),
    ];
}

function sms_result(string $provider, array $decoded): array
{
    if ($provider === 'textlk') {
        $status = strtolower((string) ($decoded['status'] ?? ''));
        $success = ($decoded['success'] ?? null) === true || in_array($status, ['success', 'sent', 'queued', 'ok'], true);
        $message = (string) (
            $decoded['message']
            ?? $decoded['error']
            ?? $decoded['data']['message']
            ?? ($success ? 'Text.lk accepted the SMS.' : 'Text.lk rejected the SMS.')
        );
        return ['success' => $success, 'message' => $message];
    }

    if (array_key_exists('success', $decoded)) {
        return [
            'success' => (bool) $decoded['success'],
            'message' => (string) ($decoded['error'] ?? $decoded['textId'] ?? 'SMS API responded.'),
        ];
    }

    return ['success' => true, 'message' => 'SMS API response received.'];
}

function sms_login_message(PDO $pdo, string $fullName, string $username, string $password, string $role): string
{
    return "Dear Sir/Madam,\n\nYour HRM365 account has been created successfully.\n\nRole: {$role}\nUsername: {$username}\nTemporary Password: {$password}\n\nPlease log in and change your password after your first login.\n\nThank you,\nHRM365";
}
?>
