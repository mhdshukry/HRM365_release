<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php'; 
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($currentUser['role'], ['admin', 'HR'])) {
        die("Unauthorized access.");
    }
    
    // We expect these fields based on the form
    $settings = [
        'company_name' => trim($_POST['company_name'] ?? ''),
        'timezone' => trim($_POST['timezone'] ?? ''),
        'currency' => trim($_POST['currency'] ?? ''),
        'holiday_country' => strtoupper(trim($_POST['holiday_country'] ?? 'LK')),
        'payroll_enable_overtime' => isset($_POST['payroll_enable_overtime']) ? '1' : '0',
        'payroll_enable_epf' => isset($_POST['payroll_enable_epf']) ? '1' : '0',
        'payroll_enable_etf' => isset($_POST['payroll_enable_etf']) ? '1' : '0',
        'sms_enabled' => isset($_POST['sms_enabled']) ? '1' : '0',
        'sms_provider' => 'textlk',
        'sms_api_url' => 'https://app.text.lk/api/v3/sms/send',
        'sms_sender_name' => trim($_POST['sms_sender_name'] ?? 'Pos365.lk'),
        'epf_employee_rate' => number_format(max(0, min(100, floatval($_POST['epf_employee_rate'] ?? 8))), 2, '.', ''),
        'epf_employer_rate' => number_format(max(0, min(100, floatval($_POST['epf_employer_rate'] ?? 12))), 2, '.', ''),
        'etf_employer_rate' => number_format(max(0, min(100, floatval($_POST['etf_employer_rate'] ?? 3))), 2, '.', '')
    ];

    $allowedTimezones = ['Asia/Colombo', 'Asia/Kolkata', 'UTC', 'America/New_York'];
    $allowedHolidayCountries = ['LK', 'IN', 'US', 'GB'];
    if ($settings['company_name'] === '') {
        die("Company name is required.");
    }

    if (!in_array($settings['timezone'], $allowedTimezones, true)) {
        die("Invalid timezone.");
    }

    if ($settings['currency'] === '') {
        $settings['currency'] = 'LKR';
    }

    if (!in_array($settings['holiday_country'], $allowedHolidayCountries, true)) {
        $settings['holiday_country'] = $settings['timezone'] === 'Asia/Colombo' ? 'LK' : 'US';
    }

    if ($settings['sms_sender_name'] === '') {
        $settings['sms_sender_name'] = 'Pos365.lk';
    }

    $newSmsApiKey = trim($_POST['sms_api_key'] ?? '');
    if ($newSmsApiKey !== '') {
        $settings['sms_api_key'] = $newSmsApiKey;
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        
        foreach ($settings as $key => $val) {
            $stmt->execute([$key, $val]);
        }
        
        $pdo->commit();

        log_action($pdo, $currentUser['id'], 'SETTINGS_UPDATED', "Updated company settings: {$settings['company_name']}, {$settings['timezone']}, {$settings['currency']}, holidays {$settings['holiday_country']}");

        header("Location: index.php?success=1");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        die("Error saving settings: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}
?>
