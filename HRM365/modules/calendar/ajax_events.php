<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$events = [];

$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$dept_filter = isset($_GET['department']) ? $_GET['department'] : 'all';
$emp_filter = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

if (!in_array($type_filter, ['all', 'meeting', 'holiday', 'leave'], true)) {
    $type_filter = 'all';
}

if ($currentUser['role'] === 'employee') {
    $emp_filter = intval($currentUser['employee_id'] ?? 0);
    $dept_filter = 'all';
} elseif ($currentUser['role'] === 'manager') {
    $dept_filter = $currentUser['department'] ?? '';
}

// 1. Fetch Holidays (Red)
if ($type_filter === 'all' || $type_filter === 'holiday') {
    $holidays = $pdo->query("SELECT * FROM holidays")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($holidays as $h) {
        $end_date = $h['end_date'] ? $h['end_date'] : $h['start_date'];
        
        $end_dt = new DateTime($end_date);
        if (!$h['is_half_day']) {
            $end_dt->modify('+1 day');
        }
        
        $events[] = [
            'id' => 'hol_' . $h['id'],
            'title' => $h['name'],
            'start' => $h['start_date'],
            'end' => $end_dt->format('Y-m-d'),
            'allDay' => !$h['is_half_day'],
            'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
            'borderColor' => 'var(--accent-danger)',
            'textColor' => 'var(--accent-danger)',
            'extendedProps' => [
                'type' => 'Holiday',
                'description' => $h['description'],
                'category' => $h['category'],
                'is_paid' => $h['is_paid'] ? 'Paid' : 'Unpaid',
                'applies_to_all_branches' => $h['applies_to_all_branches'] ? 'All Branches' : 'Specific Branches',
                'icon' => 'fa-calendar-star'
            ]
        ];
    }
}

// 2. Fetch Approved Leaves (Green)
if ($type_filter === 'all' || $type_filter === 'leave') {
    $leave_sql = "
        SELECT la.id, la.start_date, la.end_date, la.total_days, la.reason, la.updated_at,
               e.id as emp_id, e.first_name, e.last_name, e.department, lt.name as leave_name 
        FROM leave_applications la
        JOIN employees e ON la.employee_id = e.id
        JOIN leave_types lt ON la.leave_type_id = lt.id
        WHERE la.status = 'Approved'
    ";
    
    $params = [];
    if ($dept_filter !== 'all' && $dept_filter !== '') {
        $leave_sql .= " AND e.department = ?";
        $params[] = $dept_filter;
    }
    if ($emp_filter > 0) {
        $leave_sql .= " AND e.id = ?";
        $params[] = $emp_filter;
    }

    $stmt = $pdo->prepare($leave_sql);
    $stmt->execute($params);
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($leaves as $l) {
        $is_all_day = floatval($l['total_days']) >= 1;
        
        $end_date = $l['end_date'] ? $l['end_date'] : $l['start_date'];
        $end_dt = new DateTime($end_date);
        if ($is_all_day) {
            $end_dt->modify('+1 day');
        }

        $events[] = [
            'id' => 'lv_' . $l['id'],
            'title' => $l['first_name'] . ' ' . substr($l['last_name'], 0, 1) . '. (' . $l['leave_name'] . ')',
            'start' => $l['start_date'],
            'end' => $end_dt->format('Y-m-d'),
            'allDay' => $is_all_day,
            'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
            'borderColor' => 'var(--accent-success)',
            'textColor' => 'var(--accent-success)',
            'extendedProps' => [
                'type' => 'Leave',
                'employee' => $l['first_name'] . ' ' . $l['last_name'],
                'department' => $l['department'],
                'leave_type' => $l['leave_name'],
                'reason' => $l['reason'],
                'total_days' => floatval($l['total_days']),
                'approval_date' => date('M d, Y', strtotime($l['updated_at'])),
                'icon' => 'fa-plane-departure'
            ]
        ];
    }
}

// 3. Fetch Meetings (Blue)
if ($type_filter === 'all' || $type_filter === 'meeting') {
    $meet_sql = "
        SELECT m.*, e.id as emp_id, e.first_name, e.last_name, e.department,
               (
                    SELECT GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) ORDER BY a.first_name ASC SEPARATOR ', ')
                    FROM employees a
                    WHERE FIND_IN_SET(a.id, COALESCE(m.attendees, ''))
               ) AS attendee_names
        FROM meetings m
        JOIN employees e ON m.organizer_id = e.id
        WHERE m.status != 'Cancelled'
    ";
    
    $params = [];
    if ($dept_filter !== 'all' && $dept_filter !== '') {
        $meet_sql .= " AND (e.department = ? OR EXISTS (
            SELECT 1 FROM employees ae
            WHERE ae.department = ? AND FIND_IN_SET(ae.id, COALESCE(m.attendees, ''))
        ))";
        $params[] = $dept_filter;
        $params[] = $dept_filter;
    }
    if ($emp_filter > 0) {
        $meet_sql .= " AND (e.id = ? OR FIND_IN_SET(?, COALESCE(m.attendees, '')))";
        $params[] = $emp_filter;
        $params[] = $emp_filter;
    }

    $stmt = $pdo->prepare($meet_sql);
    $stmt->execute($params);
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($meetings as $m) {
        $events[] = [
            'id' => 'mtg_' . $m['id'],
            'title' => $m['title'],
            'start' => $m['start_time'],
            'end' => $m['end_time'],
            'allDay' => false,
            'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
            'borderColor' => 'var(--accent-primary)',
            'textColor' => 'var(--accent-primary)',
            'extendedProps' => [
                'type' => 'Meeting',
                'description' => $m['description'],
                'location' => $m['location'],
                'organizer' => $m['first_name'] . ' ' . $m['last_name'],
                'attendees' => $m['attendee_names'] ?: 'No attendees selected',
                'status' => $m['status'],
                'icon' => 'fa-video'
            ]
        ];
    }
}

echo json_encode($events);
?>
