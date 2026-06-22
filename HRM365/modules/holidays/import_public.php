<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/audit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

if (!in_array($currentUser['role'], ['admin', 'HR'], true)) {
    die("Unauthorized access.");
}

$year = intval($_POST['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = intval(date('Y'));
}

$country = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'holiday_country'")->fetchColumn();
if (!$country) {
    $timezone = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'timezone'")->fetchColumn();
    $country = $timezone === 'Asia/Colombo' ? 'LK' : 'US';
}
$country = strtoupper($country);

function fetch_public_holidays(int $year, string $country): array
{
    $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/{$country}";

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);

        if ($body !== false && $httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : [];
        }
    }

    $context = stream_context_create([
        'http' => ['timeout' => 15],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return [];
    }

    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function local_public_holidays(int $year, string $country): array
{
    if ($country !== 'LK' || $year !== 2026) {
        return [];
    }

    return [
        ['date' => '2026-01-02', 'localName' => 'Duruthu Full Moon Poya Day', 'name' => 'Duruthu Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-01-14', 'localName' => 'Thai Pongal', 'name' => 'Thai Pongal', 'category' => 'Religious'],
        ['date' => '2026-02-01', 'localName' => 'Navam Full Moon Poya Day', 'name' => 'Navam Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-02-04', 'localName' => 'Independence Day', 'name' => 'Independence Day', 'category' => 'National'],
        ['date' => '2026-03-03', 'localName' => 'Medin Full Moon Poya Day', 'name' => 'Medin Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-03-15', 'localName' => 'Maha Shivaratri Day', 'name' => 'Maha Shivaratri Day', 'category' => 'Religious'],
        ['date' => '2026-03-21', 'localName' => 'Eid al-Fitr', 'name' => 'Eid al-Fitr', 'category' => 'Religious'],
        ['date' => '2026-04-01', 'localName' => 'Bak Full Moon Poya Day', 'name' => 'Bak Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-04-03', 'localName' => 'Good Friday', 'name' => 'Good Friday', 'category' => 'Religious'],
        ['date' => '2026-04-13', 'localName' => 'Day prior to Sinhala and Tamil New Year Day', 'name' => 'Day prior to Sinhala and Tamil New Year Day', 'category' => 'National'],
        ['date' => '2026-04-14', 'localName' => 'Sinhala and Tamil New Year Day', 'name' => 'Sinhala and Tamil New Year Day', 'category' => 'National'],
        ['date' => '2026-05-01', 'localName' => 'May Day and Full Moon Poya Day', 'name' => 'May Day and Full Moon Poya Day', 'category' => 'National'],
        ['date' => '2026-05-30', 'localName' => 'Vesak Full Moon Poya Day', 'name' => 'Vesak Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-05-31', 'localName' => 'Day following Vesak Full Moon Poya Day', 'name' => 'Day following Vesak Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-06-27', 'localName' => 'Id Ul-Alha', 'name' => 'Id Ul-Alha', 'category' => 'Religious'],
        ['date' => '2026-06-29', 'localName' => 'Poson Full Moon Poya Day', 'name' => 'Poson Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-07-29', 'localName' => 'Esala Full Moon Poya Day', 'name' => 'Esala Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-08-25', 'localName' => 'Milad un-Nabi', 'name' => 'Milad un-Nabi', 'category' => 'Religious'],
        ['date' => '2026-08-27', 'localName' => 'Nikini Full Moon Poya Day', 'name' => 'Nikini Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-09-26', 'localName' => 'Binara Full Moon Poya Day', 'name' => 'Binara Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-10-25', 'localName' => 'Vap Full Moon Poya Day', 'name' => 'Vap Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-11-08', 'localName' => 'Deepavali', 'name' => 'Deepavali', 'category' => 'Religious'],
        ['date' => '2026-11-24', 'localName' => 'Il Full Moon Poya Day', 'name' => 'Il Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-12-23', 'localName' => 'Unduvap Full Moon Poya Day', 'name' => 'Unduvap Full Moon Poya Day', 'category' => 'Religious'],
        ['date' => '2026-12-25', 'localName' => 'Christmas Day', 'name' => 'Christmas Day', 'category' => 'Religious'],
    ];
}

$holidays = local_public_holidays($year, $country);
if (empty($holidays)) {
    $holidays = fetch_public_holidays($year, $country);
}

if (empty($holidays)) {
    die("Could not fetch public holidays. Please check internet access and the holiday country setting.");
}

$existsStmt = $pdo->prepare("
    SELECT id
    FROM holidays
    WHERE start_date = ? AND name = ?
    LIMIT 1
");
$insertStmt = $pdo->prepare("
    INSERT INTO holidays
        (name, start_date, end_date, category, description, is_recurring, is_paid, is_half_day, applies_to_all_branches)
    VALUES (?, ?, ?, ?, ?, 0, 1, 0, 1)
");

$inserted = 0;
$skipped = 0;

foreach ($holidays as $holiday) {
    $date = $holiday['date'] ?? null;
    $name = trim($holiday['localName'] ?? $holiday['name'] ?? '');
    if (!$date || $name === '' || strtotime($date) === false) {
        $skipped++;
        continue;
    }

    $category = $holiday['category'] ?? 'National';
    if (!in_array($category, ['National', 'Religious', 'Company-specific', 'Other'], true)) {
        $category = 'National';
    }

    $existsStmt->execute([$date, $name]);
    if ($existsStmt->fetchColumn()) {
        $skipped++;
        continue;
    }

    $description = 'Imported public holiday for ' . $country . '.';
    if (!empty($holiday['name']) && $holiday['name'] !== $name) {
        $description .= ' English name: ' . $holiday['name'];
    }

    $insertStmt->execute([$name, $date, $date, $category, $description]);
    $inserted++;
}

log_action($pdo, $currentUser['id'], 'PUBLIC_HOLIDAYS_IMPORTED', "Imported {$inserted} public holidays for {$country} {$year}; skipped {$skipped}");

header('Location: index.php?success=public_holidays_imported&year=' . urlencode((string)$year));
exit();
