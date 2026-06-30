<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

function redirect_meeting_error(string $message): void
{
    header('Location: index.php?error=' . urlencode($message));
    exit();
}

function parse_meeting_datetime(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d\TH:i',
        'Y-m-d\TH:i:s',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'm/d/Y h:i A',
        'm/d/Y H:i',
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime) {
            $errors = DateTime::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $date->getTimestamp();
            }
        }
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : $timestamp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $organizer_id = intval($_POST['organizer_id'] ?? 0);
    $attendeeIds = array_values(array_unique(array_filter(array_map('intval', $_POST['attendees'] ?? []), fn($id) => $id > 0)));

    if ($currentUser['role'] === 'employee') {
        $organizer_id = intval($currentUser['employee_id'] ?? 0);
    } elseif ($currentUser['role'] === 'manager') {
        $scopeStmt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id = ? AND department = ?");
        $scopeStmt->execute([$organizer_id, $currentUser['department'] ?? '']);
        if (!$scopeStmt->fetchColumn()) {
            redirect_meeting_error('Unauthorized organizer selection.');
        }
    }

    $startTimestamp = parse_meeting_datetime($start_time);
    $endTimestamp = parse_meeting_datetime($end_time);
    if ($title === '') {
        redirect_meeting_error('Meeting title is required.');
    }
    if ($startTimestamp === null || $endTimestamp === null) {
        redirect_meeting_error('Please select both start time and end time.');
    }
    if ($endTimestamp <= $startTimestamp) {
        redirect_meeting_error('End time must be after start time.');
    }

    if ($organizer_id <= 0) {
        redirect_meeting_error('Organizer profile is not linked.');
    }

    $allowedSql = "SELECT id FROM employees WHERE status = 'Active'";
    $allowedParams = [];
    if ($currentUser['role'] === 'employee') {
        $allowedSql .= " AND id = ?";
        $allowedParams[] = intval($currentUser['employee_id'] ?? 0);
    } elseif ($currentUser['role'] === 'manager') {
        $allowedSql .= " AND department = ?";
        $allowedParams[] = $currentUser['department'] ?? '';
    }
    $allowedStmt = $pdo->prepare($allowedSql);
    $allowedStmt->execute($allowedParams);
    $allowedEmployeeIds = array_map('intval', $allowedStmt->fetchAll(PDO::FETCH_COLUMN));
    $attendeeIds = array_values(array_intersect($attendeeIds, $allowedEmployeeIds));
    $attendees = implode(',', $attendeeIds);
    $start_time = date('Y-m-d H:i:s', $startTimestamp);
    $end_time = date('Y-m-d H:i:s', $endTimestamp);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO meetings (title, description, start_time, end_time, location, organizer_id, attendees)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$title, $description, $start_time, $end_time, $location, $organizer_id, $attendees]);
        
        header("Location: index.php?success=scheduled");
        exit();
    } catch (\PDOException $e) {
        redirect_meeting_error("Error scheduling meeting: " . $e->getMessage());
    }
}
header("Location: index.php");
exit();
?>
