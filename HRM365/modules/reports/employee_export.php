<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';
require_once '../../includes/payroll_math.php';

$format = strtolower(trim($_GET['format'] ?? 'excel'));
if (!in_array($format, ['excel', 'pdf'], true)) {
    $format = 'excel';
}

$employeeId = intval($_GET['employee_id'] ?? 0);
$startDate = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
if (strtotime($endDate) < strtotime($startDate)) {
    $endDate = $startDate;
}

if ($currentUser['role'] === 'employee') {
    $employeeId = intval($currentUser['employee_id'] ?? 0);
}

$employeeSql = "
    SELECT e.*, b.name AS branch_name, s.name AS shift_name, ap.name AS policy_name
    FROM employees e
    LEFT JOIN branches b ON b.id = e.branch_id
    LEFT JOIN shifts s ON s.id = e.shift_id
    LEFT JOIN attendance_policies ap ON ap.id = e.attendance_policy_id
    WHERE e.id = ?
";
$employeeParams = [$employeeId];
if ($currentUser['role'] === 'manager') {
    $employeeSql .= " AND e.department = ?";
    $employeeParams[] = $currentUser['department'] ?? '';
}
$employeeStmt = $pdo->prepare($employeeSql);
$employeeStmt->execute($employeeParams);
$employee = $employeeStmt->fetch();

if (!$employee || !in_array($currentUser['role'], ['admin', 'HR', 'manager', 'employee'], true)) {
    die('Unauthorized access.');
}

$attendanceStmt = $pdo->prepare("
    SELECT *
    FROM attendance_records
    WHERE employee_id = ? AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$attendanceStmt->execute([$employeeId, $startDate, $endDate]);
$attendanceRows = $attendanceStmt->fetchAll();

$leaveStmt = $pdo->prepare("
    SELECT la.*, lt.name AS leave_type, lt.is_paid
    FROM leave_applications la
    JOIN leave_types lt ON lt.id = la.leave_type_id
    WHERE la.employee_id = ?
      AND la.start_date <= ?
      AND COALESCE(la.end_date, la.start_date) >= ?
    ORDER BY la.start_date DESC
");
$leaveStmt->execute([$employeeId, $endDate, $startDate]);
$leaveRows = $leaveStmt->fetchAll();

$payrollStartMonth = date('Y-m', strtotime($startDate));
$payrollEndMonth = date('Y-m', strtotime($endDate));
$payrollStmt = $pdo->prepare("
    SELECT *
    FROM payroll_records
    WHERE employee_id = ? AND payroll_month BETWEEN ? AND ?
    ORDER BY payroll_month DESC
");
$payrollStmt->execute([$employeeId, $payrollStartMonth, $payrollEndMonth]);
$payrollRows = $payrollStmt->fetchAll();
$payrollFeatures = payroll_feature_settings($pdo);
$showOvertime = $payrollFeatures['payroll_enable_overtime'] ?? true;
$showEpf = $payrollFeatures['payroll_enable_epf'] ?? true;
$showEtf = $payrollFeatures['payroll_enable_etf'] ?? true;

$summary = [
    'present' => 0,
    'absent' => 0,
    'leave' => 0,
    'hours' => 0.00,
    'overtime' => 0.00,
    'unpaid_days' => 0.00,
    'epf_employee' => 0.00,
    'epf_employer' => 0.00,
    'etf_employer' => 0.00,
    'net_salary' => 0.00,
];
foreach ($attendanceRows as $row) {
    if ($row['status'] === 'Present') {
        $summary['present']++;
    } elseif ($row['status'] === 'Absent') {
        $summary['absent']++;
    } elseif ($row['status'] === 'On Leave') {
        $summary['leave']++;
    }
    $summary['hours'] += floatval($row['total_hours']);
    $summary['overtime'] += floatval($row['overtime_hours']);
}
foreach ($payrollRows as $row) {
    $summary['unpaid_days'] += floatval($row['unpaid_days'] ?? 0);
    $summary['epf_employee'] += floatval($row['epf_employee_amount'] ?? 0);
    $summary['epf_employer'] += floatval($row['epf_employer_amount'] ?? 0);
    $summary['etf_employer'] += floatval($row['etf_employer_amount'] ?? 0);
    $summary['net_salary'] += floatval($row['net_salary']);
}

$currency = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'currency'")->fetchColumn() ?: 'LKR';
$employeeName = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
$periodLabel = date('M d, Y', strtotime($startDate)) . ' to ' . date('M d, Y', strtotime($endDate));
$fileBase = report_employee_clean_filename('hrm365_employee_report_' . ($employee['employee_code'] ?? $employeeId) . '_' . $startDate . '_to_' . $endDate);

function report_employee_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function report_employee_clean_filename(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
    return trim($value ?: 'employee_report', '_');
}

function report_employee_time_range(array $row): string
{
    $in = !empty($row['clock_in']) ? date('H:i', strtotime($row['clock_in'])) : '--:--';
    $out = !empty($row['clock_out']) ? date('H:i', strtotime($row['clock_out'])) : '--:--';
    return $in . ' to ' . $out;
}

function report_employee_flags(array $row): string
{
    $flags = [];
    if (!empty($row['is_late'])) $flags[] = 'Late';
    if (!empty($row['is_early_departure'])) $flags[] = 'Early out';
    if (!empty($row['is_absent'])) $flags[] = 'No punch';
    if (!empty($row['is_holiday'])) $flags[] = 'Holiday';
    if (!empty($row['is_weekend'])) $flags[] = 'Weekend';
    return $flags ? implode(', ', $flags) : 'Clear';
}

function employee_pdf_escape(string $value): string
{
    $value = preg_replace('/[^\x20-\x7E]/', ' ', $value);
    return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $value);
}

function employee_pdf_color(array $rgb): string
{
    return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
}

function employee_pdf_truncate(string $value, float $width, int $fontSize): string
{
    $value = preg_replace('/\s+/', ' ', trim($value));
    $maxChars = max(4, (int) floor($width / ($fontSize * 0.52)));
    return strlen($value) <= $maxChars ? $value : substr($value, 0, max(1, $maxChars - 3)) . '...';
}

function employee_pdf_stream(array $pages, string $filename): void
{
    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $pageObjectNumbers = [];
    $contentObjectNumbers = [];
    $nextObject = 3;
    foreach ($pages as $_) {
        $pageObjectNumbers[] = $nextObject++;
        $contentObjectNumbers[] = $nextObject++;
    }
    $kids = implode(' ', array_map(fn($num) => $num . ' 0 R', $pageObjectNumbers));
    $objects[] = "<< /Type /Pages /Kids [{$kids}] /Count " . count($pages) . " >>";
    foreach ($pages as $index => $content) {
        $contentObject = $contentObjectNumbers[$index];
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> /F2 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> >> >> /Contents {$contentObject} 0 R >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";
    }
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('X-Content-Type-Options: nosniff');
    echo $pdf;
    exit;
}

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.xls"');
    header('X-Content-Type-Options: nosniff');
    echo "\xEF\xBB\xBF";
    ?>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; }
            h1, h2 { margin: 12px 0 8px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
            th { background: #111827; color: #fff; font-weight: bold; }
            th, td { border: 1px solid #94a3b8; padding: 7px; vertical-align: top; }
            .num { text-align: right; }
        </style>
    </head>
    <body>
        <h1>Employee Report</h1>
        <table>
            <tr><th>Employee</th><td><?php echo report_employee_h($employeeName); ?></td><th>Employee Code</th><td><?php echo report_employee_h($employee['employee_code']); ?></td></tr>
            <tr><th>Period</th><td><?php echo report_employee_h($periodLabel); ?></td><th>Status</th><td><?php echo report_employee_h($employee['status']); ?></td></tr>
            <tr><th>Branch</th><td><?php echo report_employee_h($employee['branch_name'] ?? 'No Branch'); ?></td><th>Department</th><td><?php echo report_employee_h($employee['department'] ?: 'Not assigned'); ?></td></tr>
            <tr><th>Shift</th><td><?php echo report_employee_h($employee['shift_name'] ?: 'No shift'); ?></td><th>Policy</th><td><?php echo report_employee_h($employee['policy_name'] ?: 'No policy'); ?></td></tr>
        </table>
        <h2>Summary</h2>
        <table>
            <tr><th>Present</th><th>Absent</th><th>On Leave</th><th>Total Hours</th><?php if ($showOvertime): ?><th>Overtime</th><?php endif; ?><th>Unpaid Days</th><?php if ($showEpf): ?><th>Employee EPF</th><th>Employer EPF</th><?php endif; ?><?php if ($showEtf): ?><th>ETF</th><?php endif; ?><th>Net Payroll</th></tr>
            <tr>
                <td class="num"><?php echo intval($summary['present']); ?></td>
                <td class="num"><?php echo intval($summary['absent']); ?></td>
                <td class="num"><?php echo intval($summary['leave']); ?></td>
                <td class="num"><?php echo number_format($summary['hours'], 2); ?></td>
                <?php if ($showOvertime): ?><td class="num"><?php echo number_format($summary['overtime'], 2); ?></td><?php endif; ?>
                <td class="num"><?php echo number_format($summary['unpaid_days'], 2); ?></td>
                <?php if ($showEpf): ?>
                    <td class="num"><?php echo number_format($summary['epf_employee'], 2); ?></td>
                    <td class="num"><?php echo number_format($summary['epf_employer'], 2); ?></td>
                <?php endif; ?>
                <?php if ($showEtf): ?><td class="num"><?php echo number_format($summary['etf_employer'], 2); ?></td><?php endif; ?>
                <td class="num"><?php echo number_format($summary['net_salary'], 2); ?></td>
            </tr>
        </table>
        <h2>Attendance Records</h2>
        <table>
            <tr><th>Date</th><th>In - Out</th><th>Total Hours</th><?php if ($showOvertime): ?><th>OT Hours</th><?php endif; ?><th>Status</th><th>Flags</th></tr>
            <?php foreach ($attendanceRows as $row): ?>
                <tr>
                    <td><?php echo report_employee_h($row['date']); ?></td>
                    <td><?php echo report_employee_h(report_employee_time_range($row)); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['total_hours']), 2); ?></td>
                    <?php if ($showOvertime): ?><td class="num"><?php echo number_format(floatval($row['overtime_hours']), 2); ?></td><?php endif; ?>
                    <td><?php echo report_employee_h($row['status']); ?></td>
                    <td><?php echo report_employee_h(report_employee_flags($row)); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <h2>Leave Applications</h2>
        <table>
            <tr><th>Type</th><th>Date Range</th><th>Days</th><th>Paid</th><th>Status</th><th>Reason</th></tr>
            <?php foreach ($leaveRows as $row): ?>
                <tr>
                    <td><?php echo report_employee_h($row['leave_type']); ?></td>
                    <td><?php echo report_employee_h($row['start_date'] . ' to ' . ($row['end_date'] ?: $row['start_date'])); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['total_days']), 2); ?></td>
                    <td><?php echo intval($row['is_paid']) === 1 ? 'Paid' : 'Unpaid'; ?></td>
                    <td><?php echo report_employee_h($row['status']); ?></td>
                    <td><?php echo report_employee_h($row['reason'] ?? '-'); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <h2>Payroll</h2>
        <table>
            <tr><th>Month</th><th>Base</th><?php if ($showOvertime): ?><th>OT Amount</th><?php endif; ?><th>Unpaid Days</th><th>Advance</th><?php if ($showEpf): ?><th>Employee EPF</th><th>Employer EPF</th><?php endif; ?><?php if ($showEtf): ?><th>ETF</th><?php endif; ?><th>Net</th><th>Status</th></tr>
            <?php foreach ($payrollRows as $row): ?>
                <tr>
                    <td><?php echo report_employee_h($row['payroll_month']); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['base_salary']), 2); ?></td>
                    <?php if ($showOvertime): ?><td class="num"><?php echo number_format(floatval($row['overtime_amount']), 2); ?></td><?php endif; ?>
                    <td class="num"><?php echo number_format(floatval($row['unpaid_days'] ?? 0), 2); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['advance_amount'] ?? 0), 2); ?></td>
                    <?php if ($showEpf): ?>
                        <td class="num"><?php echo number_format(floatval($row['epf_employee_amount'] ?? 0), 2); ?></td>
                        <td class="num"><?php echo number_format(floatval($row['epf_employer_amount'] ?? 0), 2); ?></td>
                    <?php endif; ?>
                    <?php if ($showEtf): ?><td class="num"><?php echo number_format(floatval($row['etf_employer_amount'] ?? 0), 2); ?></td><?php endif; ?>
                    <td class="num"><?php echo number_format(floatval($row['net_salary']), 2); ?></td>
                    <td><?php echo report_employee_h($row['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </body>
    </html>
    <?php
    exit;
}

$colors = [
    'ink' => [17, 24, 39],
    'muted' => [100, 116, 139],
    'line' => [226, 232, 240],
    'paper' => [238, 242, 247],
    'brand' => [15, 23, 42],
    'blue' => [37, 99, 235],
    'green' => [16, 185, 129],
    'red' => [220, 38, 38],
    'amber' => [245, 158, 11],
    'white' => [255, 255, 255],
    'soft' => [241, 245, 249],
    'softBlue' => [239, 246, 255],
    'darkMuted' => [203, 213, 225],
];

$left = 36;
$contentWidth = 770;
$bottom = 36;
$pages = [];
$content = '';
$y = 0;
$pageNo = 0;

$rect = function (float $x, float $yPos, float $w, float $h, array $fill, ?array $stroke = null) use (&$content) {
    $content .= employee_pdf_color($fill) . " rg\n";
    if ($stroke !== null) {
        $content .= employee_pdf_color($stroke) . " RG\n";
        $content .= sprintf("%.2F %.2F %.2F %.2F re B\n", $x, $yPos, $w, $h);
        return;
    }
    $content .= sprintf("%.2F %.2F %.2F %.2F re f\n", $x, $yPos, $w, $h);
};
$text = function (float $x, float $yPos, string $value, int $size = 8, bool $bold = false, ?array $color = null) use (&$content, $colors) {
    $font = $bold ? 'F2' : 'F1';
    $color = $color ?: $colors['ink'];
    $content .= employee_pdf_color($color) . " rg\n";
    $content .= "BT /{$font} {$size} Tf {$x} {$yPos} Td (" . employee_pdf_escape($value) . ") Tj ET\n";
};
$textRight = function (float $rightX, float $yPos, string $value, int $size = 8, bool $bold = false, ?array $color = null) use ($text) {
    $text(max(0, $rightX - (strlen($value) * $size * 0.48)), $yPos, $value, $size, $bold, $color);
};
$finishPage = function () use (&$pages, &$content) {
    if ($content !== '') $pages[] = $content;
};
$startPage = function () use (&$content, &$y, &$pageNo, $rect, $text, $textRight, $colors, $left, $contentWidth, $employeeName, $employee, $periodLabel) {
    $pageNo++;
    $content = '';
    $rect(0, 0, 842, 595, $colors['paper']);
    $rect(24, 28, 794, 540, $colors['white'], $colors['line']);
    $rect(24, 492, 794, 76, $colors['brand']);
    $rect(24, 492, 794, 5, $colors['blue']);
    $rect($left, 522, 50, 34, $colors['blue']);
    $text($left + 12, 535, strtoupper(substr($employee['first_name'] ?? 'E', 0, 1) . substr($employee['last_name'] ?? '', 0, 1)), 15, true, $colors['white']);
    $text($left + 62, 547, 'EMPLOYEE REPORT', 18, true, $colors['white']);
    $text($left + 62, 529, employee_pdf_truncate($employeeName . ' / ' . ($employee['employee_code'] ?? ''), 330, 10), 10, false, $colors['darkMuted']);
    $textRight(806, 547, $periodLabel, 10, true, $colors['white']);
    $textRight(806, 529, 'Generated ' . date('Y-m-d H:i'), 8, false, $colors['darkMuted']);

    $metaY = 455;
    $cardW = ($contentWidth - 20) / 3;
    $rect($left, $metaY - 44, $cardW, 44, $colors['softBlue'], $colors['line']);
    $text($left + 12, $metaY - 16, 'BRANCH', 7, true, $colors['muted']);
    $text($left + 12, $metaY - 32, employee_pdf_truncate($employee['branch_name'] ?? 'No Branch', $cardW - 22, 9), 9, true, $colors['ink']);
    $rect($left + $cardW + 10, $metaY - 44, $cardW, 44, $colors['softBlue'], $colors['line']);
    $text($left + $cardW + 22, $metaY - 16, 'DEPARTMENT', 7, true, $colors['muted']);
    $text($left + $cardW + 22, $metaY - 32, employee_pdf_truncate($employee['department'] ?: 'Not assigned', $cardW - 22, 9), 9, true, $colors['ink']);
    $rect($left + (($cardW + 10) * 2), $metaY - 44, $cardW, 44, $colors['softBlue'], $colors['line']);
    $text($left + (($cardW + 10) * 2) + 12, $metaY - 16, 'STATUS', 7, true, $colors['muted']);
    $text($left + (($cardW + 10) * 2) + 12, $metaY - 32, employee_pdf_truncate($employee['status'] ?? '-', $cardW - 22, 9), 9, true, $colors['ink']);

    $text($left, 38, 'HRM365 Human Resource Management System', 7, false, $colors['muted']);
    $textRight(806, 38, 'Page ' . $pageNo, 7, false, $colors['muted']);
    $y = 392;
};
$ensureSpace = function (float $height) use (&$y, $bottom, $finishPage, $startPage) {
    if (($y - $height) < $bottom) {
        $finishPage();
        $startPage();
    }
};
$kpi = function (float $x, float $w, string $label, string $value, string $note, array $accent) use (&$y, $rect, $text, $colors) {
    $rect($x, $y - 66, $w, 66, $colors['white'], $colors['line']);
    $rect($x, $y - 66, 5, 66, $accent);
    $text($x + 13, $y - 18, $label, 7, true, $colors['muted']);
    $text($x + 13, $y - 41, employee_pdf_truncate($value, $w - 24, 14), 14, true, $colors['ink']);
    $text($x + 13, $y - 56, employee_pdf_truncate($note, $w - 24, 7), 7, false, $colors['muted']);
};
$section = function (string $title, int $count) use (&$y, $ensureSpace, $rect, $text, $textRight, $colors, $left, $contentWidth) {
    $ensureSpace(34);
    $rect($left, $y - 30, $contentWidth, 30, $colors['white'], $colors['line']);
    $rect($left, $y - 30, 6, 30, $colors['blue']);
    $text($left + 15, $y - 14, $title, 11, true, $colors['ink']);
    $text($left + 15, $y - 25, $count . ' row(s)', 7, false, $colors['muted']);
    $y -= 38;
};
$table = function (string $title, array $columns, array $rows) use (&$y, $section, $ensureSpace, $rect, $text, $textRight, $colors, $left, $contentWidth, $bottom, $finishPage, $startPage) {
    $section($title, count($rows));
    $headerHeight = 23;
    $rowHeight = 22;
    $drawHeader = function () use (&$y, $columns, $rect, $text, $textRight, $colors, $left, $contentWidth, $headerHeight) {
        $rect($left, $y - $headerHeight, $contentWidth, $headerHeight, $colors['soft'], $colors['line']);
        $x = $left + 7;
        foreach ($columns as $column) {
            $label = employee_pdf_truncate($column['label'], $column['width'] - 8, 7);
            if (($column['align'] ?? '') === 'right') $textRight($x + $column['width'] - 8, $y - 15, $label, 7, true, $colors['ink']);
            else $text($x, $y - 15, $label, 7, true, $colors['ink']);
            $x += $column['width'];
        }
        $y -= $headerHeight;
    };
    $ensureSpace($headerHeight + $rowHeight);
    $drawHeader();
    if (empty($rows)) {
        $rect($left, $y - 24, $contentWidth, 24, $colors['white'], $colors['line']);
        $text($left + 10, $y - 16, 'No records for this filter.', 8, false, $colors['muted']);
        $y -= 34;
        return;
    }
    foreach ($rows as $index => $row) {
        if (($y - $rowHeight) < $bottom) {
            $finishPage();
            $startPage();
            $drawHeader();
        }
        $rect($left, $y - $rowHeight, $contentWidth, $rowHeight, $index % 2 === 0 ? $colors['white'] : $colors['soft'], $colors['line']);
        $x = $left + 7;
        foreach ($columns as $idx => $column) {
            $fontSize = $column['size'] ?? 7;
            $value = employee_pdf_truncate((string) ($row[$idx] ?? ''), $column['width'] - 8, $fontSize);
            if (($column['align'] ?? '') === 'right') $textRight($x + $column['width'] - 8, $y - 14, $value, $fontSize, false, $colors['ink']);
            else $text($x, $y - 14, $value, $fontSize, false, $colors['ink']);
            $x += $column['width'];
        }
        $y -= $rowHeight;
    }
    $y -= 14;
};

$startPage();
$rect($left, $y - 30, $contentWidth, 30, $colors['white'], $colors['line']);
$text($left + 12, $y - 12, 'Executive Summary', 12, true, $colors['ink']);
$text($left + 12, $y - 24, 'Attendance, leave, and payroll overview for the selected employee.', 8, false, $colors['muted']);
$y -= 36;

$gap = 10;
$cardCount = $showOvertime ? 5 : 4;
$cardWidth = ($contentWidth - ($gap * ($cardCount - 1))) / $cardCount;
$kpi($left, $cardWidth, 'PRESENT', (string) intval($summary['present']), count($attendanceRows) . ' attendance rows', $colors['green']);
$kpi($left + ($cardWidth + $gap), $cardWidth, 'ABSENT', (string) intval($summary['absent']), 'No punch days', $colors['red']);
$kpi($left + (($cardWidth + $gap) * 2), $cardWidth, 'TOTAL HOURS', number_format($summary['hours'], 2) . 'h', 'Selected period', $colors['blue']);
if ($showOvertime) {
    $kpi($left + (($cardWidth + $gap) * 3), $cardWidth, 'OVERTIME', number_format($summary['overtime'], 2) . 'h', 'Payroll basis', $colors['amber']);
    $kpi($left + (($cardWidth + $gap) * 4), $cardWidth, 'NET PAYROLL', $currency . ' ' . number_format($summary['net_salary'], 2), count($payrollRows) . ' payroll rows', $colors['green']);
} else {
    $kpi($left + (($cardWidth + $gap) * 3), $cardWidth, 'NET PAYROLL', $currency . ' ' . number_format($summary['net_salary'], 2), count($payrollRows) . ' payroll rows', $colors['green']);
}
$y -= 82;

$rect($left, $y - 30, $contentWidth, 30, $colors['white'], $colors['line']);
$text($left + 12, $y - 13, 'Base Salary', 7, true, $colors['muted']);
$text($left + 12, $y - 24, $currency . ' ' . number_format(floatval($employee['base_salary']), 2), 8, true, $colors['ink']);
$text($left + 190, $y - 13, 'Shift', 7, true, $colors['muted']);
$text($left + 190, $y - 24, employee_pdf_truncate($employee['shift_name'] ?: 'No shift', 155, 8), 8, true, $colors['ink']);
$text($left + 370, $y - 13, 'Policy', 7, true, $colors['muted']);
$text($left + 370, $y - 24, employee_pdf_truncate($employee['policy_name'] ?: 'No policy', 155, 8), 8, true, $colors['ink']);
if ($showEpf || $showEtf) {
    $text($left + 550, $y - 13, 'Statutory Total', 7, true, $colors['muted']);
    $text($left + 550, $y - 24, $currency . ' ' . number_format(($showEpf ? ($summary['epf_employee'] + $summary['epf_employer']) : 0) + ($showEtf ? $summary['etf_employer'] : 0), 2), 8, true, $colors['ink']);
}
$y -= 45;

$attendanceTableRows = [];
foreach ($attendanceRows as $row) {
    $record = [
        $row['date'],
        report_employee_time_range($row),
        number_format(floatval($row['total_hours']), 2),
    ];
    if ($showOvertime) {
        $record[] = number_format(floatval($row['overtime_hours']), 2);
    }
    $record[] = $row['status'];
    $record[] = report_employee_flags($row);
    $attendanceTableRows[] = $record;
}
$attendanceColumns = [
    ['label' => 'Date', 'width' => 78],
    ['label' => 'In - Out', 'width' => 110],
    ['label' => 'Total', 'width' => 58, 'align' => 'right'],
];
if ($showOvertime) {
    $attendanceColumns[] = ['label' => 'OT', 'width' => 58, 'align' => 'right'];
}
$attendanceColumns = array_merge($attendanceColumns, [
    ['label' => 'Status', 'width' => 96],
    ['label' => 'Flags', 'width' => $showOvertime ? 356 : 414],
]);
$table('Attendance Records', $attendanceColumns, $attendanceTableRows);

$leaveTableRows = [];
foreach ($leaveRows as $row) {
    $leaveTableRows[] = [
        $row['leave_type'],
        $row['start_date'] . ' to ' . ($row['end_date'] ?: $row['start_date']),
        number_format(floatval($row['total_days']), 2),
        intval($row['is_paid']) === 1 ? 'Paid' : 'Unpaid',
        $row['status'],
        $row['reason'] ?? '-',
    ];
}
$table('Leave Applications', [
    ['label' => 'Type', 'width' => 130],
    ['label' => 'Date Range', 'width' => 150],
    ['label' => 'Days', 'width' => 55, 'align' => 'right'],
    ['label' => 'Paid', 'width' => 60],
    ['label' => 'Status', 'width' => 90],
    ['label' => 'Reason', 'width' => 271],
], $leaveTableRows);

$payrollTableRows = [];
foreach ($payrollRows as $row) {
    $record = [
        date('M Y', strtotime($row['payroll_month'] . '-01')),
        number_format(floatval($row['base_salary']), 2),
    ];
    if ($showOvertime) {
        $record[] = number_format(floatval($row['overtime_amount']), 2);
    }
    $record[] = number_format(floatval($row['unpaid_days'] ?? 0), 2);
    $record[] = number_format(floatval($row['advance_amount'] ?? 0), 2);
    if ($showEpf) {
        $record[] = number_format(floatval($row['epf_employee_amount'] ?? 0), 2);
        $record[] = number_format(floatval($row['epf_employer_amount'] ?? 0), 2);
    }
    if ($showEtf) {
        $record[] = number_format(floatval($row['etf_employer_amount'] ?? 0), 2);
    }
    $record[] = number_format(floatval($row['net_salary']), 2);
    $record[] = $row['status'];
    $payrollTableRows[] = $record;
}
$payrollColumns = [
    ['label' => 'Month', 'width' => 70],
    ['label' => 'Base', 'width' => 78, 'align' => 'right'],
];
if ($showOvertime) {
    $payrollColumns[] = ['label' => 'OT Amount', 'width' => 82, 'align' => 'right'];
}
$payrollColumns[] = ['label' => 'Unpaid', 'width' => 62, 'align' => 'right'];
$payrollColumns[] = ['label' => 'Advance', 'width' => 68, 'align' => 'right'];
if ($showEpf) {
    $payrollColumns[] = ['label' => 'EPF EE', 'width' => 70, 'align' => 'right'];
    $payrollColumns[] = ['label' => 'EPF ER', 'width' => 70, 'align' => 'right'];
}
if ($showEtf) {
    $payrollColumns[] = ['label' => 'ETF', 'width' => 70, 'align' => 'right'];
}
$payrollColumns = array_merge($payrollColumns, [
    ['label' => 'Net', 'width' => 88, 'align' => 'right'],
    ['label' => 'Status', 'width' => 166],
]);
$table('Payroll', $payrollColumns, $payrollTableRows);

$finishPage();
employee_pdf_stream($pages, $fileBase . '.pdf');
