<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("
        SELECT
            id,
            name AS title,
            start_date AS start,
            end_date AS end,
            category AS type,
            '#ef4444' AS color
        FROM holidays
    ");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // FullCalendar expects end dates to be exclusive. So if a holiday is 1 day, start and end shouldn't be the same if we want it to render fully, OR we just let FullCalendar handle 'allDay' flag.
    foreach ($events as &$event) {
        $event['allDay'] = true;
        // If end_date is present, we add 1 day to make it visually inclusive in FullCalendar
        if (!empty($event['end'])) {
            $event['end'] = date('Y-m-d', strtotime($event['end'] . ' +1 day'));
        }
    }
    
    echo json_encode($events);
} catch (\PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
