<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once __DIR__ . '/report_data.php';

$format = strtolower(trim($_GET['format'] ?? 'excel'));
if (!in_array($format, ['excel', 'pdf'], true)) {
    $format = 'excel';
}

function report_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function report_clean_filename(string $value): string
{
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
    return trim($value ?: 'reports', '_');
}

function report_time_range(array $row): string
{
    $in = !empty($row['clock_in']) ? date('H:i', strtotime($row['clock_in'])) : '--:--';
    $out = !empty($row['clock_out']) ? date('H:i', strtotime($row['clock_out'])) : '--:--';
    return $in . ' to ' . $out;
}

function report_attendance_flags(array $row): string
{
    $flags = [];
    if (!empty($row['is_late'])) {
        $flags[] = 'Late';
    }
    if (!empty($row['is_early_departure'])) {
        $flags[] = 'Early';
    }
    if (!empty($row['is_absent'])) {
        $flags[] = 'No punch';
    }
    if (!empty($row['is_holiday'])) {
        $flags[] = 'Holiday';
    }
    if (!empty($row['is_weekend'])) {
        $flags[] = 'Weekend';
    }
    return $flags ? implode(', ', $flags) : 'Clear';
}

function report_pdf_escape(string $value): string
{
    $value = preg_replace('/[^\x20-\x7E]/', ' ', $value);
    return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $value);
}

function report_pdf_color(array $rgb): string
{
    return sprintf('%.3F %.3F %.3F', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
}

function report_pdf_truncate(string $value, float $width, int $fontSize): string
{
    $value = preg_replace('/\s+/', ' ', trim($value));
    $maxChars = max(4, (int) floor($width / ($fontSize * 0.52)));
    if (strlen($value) <= $maxChars) {
        return $value;
    }
    return substr($value, 0, max(1, $maxChars - 3)) . '...';
}

function report_pdf_stream(array $pages, string $filename): void
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
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
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

function report_download_pdf(
    array $summary,
    array $attendanceRows,
    array $leaveRows,
    array $payrollRows,
    string $currency,
    string $periodLabel,
    string $selectedEmployeeLabel,
    string $filename
): void {
    $left = 36;
    $contentWidth = 770;
    $bottom = 36;
    $colors = [
        'ink' => [17, 24, 39],
        'muted' => [107, 114, 128],
        'line' => [226, 232, 240],
        'paper' => [248, 250, 252],
        'navy' => [15, 23, 42],
        'header' => [30, 41, 59],
        'blue' => [37, 99, 235],
        'green' => [16, 185, 129],
        'red' => [239, 68, 68],
        'amber' => [245, 158, 11],
        'stripe' => [241, 245, 249],
        'white' => [255, 255, 255],
    ];

    $pages = [];
    $content = '';
    $y = 0;
    $pageNo = 0;

    $rect = function (float $x, float $yPos, float $w, float $h, array $fill, ?array $stroke = null) use (&$content) {
        $content .= report_pdf_color($fill) . " rg\n";
        if ($stroke !== null) {
            $content .= report_pdf_color($stroke) . " RG\n";
            $content .= sprintf("%.2F %.2F %.2F %.2F re B\n", $x, $yPos, $w, $h);
            return;
        }
        $content .= sprintf("%.2F %.2F %.2F %.2F re f\n", $x, $yPos, $w, $h);
    };

    $text = function (float $x, float $yPos, string $value, int $size = 8, bool $bold = false, ?array $color = null) use (&$content, $colors) {
        $font = $bold ? 'F2' : 'F1';
        $color = $color ?: $colors['ink'];
        $content .= report_pdf_color($color) . " rg\n";
        $content .= "BT /{$font} {$size} Tf {$x} {$yPos} Td (" . report_pdf_escape($value) . ") Tj ET\n";
    };

    $line = function (float $x1, float $y1, float $x2, float $y2, array $color) use (&$content) {
        $content .= report_pdf_color($color) . " RG\n";
        $content .= sprintf("0.8 w %.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
    };

    $finishPage = function () use (&$pages, &$content) {
        if ($content !== '') {
            $pages[] = $content;
        }
    };

    $startPage = function () use (&$content, &$y, &$pageNo, $rect, $text, $line, $colors, $left, $contentWidth, $periodLabel, $selectedEmployeeLabel) {
        $pageNo++;
        $content = '';
        $rect(0, 0, 842, 595, $colors['paper']);
        $rect(0, 515, 842, 80, $colors['navy']);
        $rect(0, 515, 842, 5, $colors['blue']);
        $text(36, 558, 'HRM365', 20, true, $colors['white']);
        $text(36, 538, 'Operational Report', 10, false, [203, 213, 225]);
        $text(640, 558, 'Period', 8, true, [203, 213, 225]);
        $text(640, 544, report_pdf_truncate($periodLabel, 160, 9), 9, false, $colors['white']);
        $text(640, 528, 'Generated ' . date('Y-m-d H:i'), 8, false, [203, 213, 225]);
        $rect($left, 482, $contentWidth, 25, $colors['white'], $colors['line']);
        $text($left + 10, 491, 'Filter: ' . report_pdf_truncate($selectedEmployeeLabel, 675, 9), 9, true, $colors['ink']);
        $line($left, 30, $left + $contentWidth, 30, $colors['line']);
        $text($left, 18, 'HRM365 Human Resource Management System', 7, false, $colors['muted']);
        $text(760, 18, 'Page ' . $pageNo, 7, false, $colors['muted']);
        $y = 455;
    };

    $ensureSpace = function (float $height) use (&$y, $bottom, $finishPage, $startPage) {
        if (($y - $height) < $bottom) {
            $finishPage();
            $startPage();
        }
    };

    $section = function (string $title, int $count) use (&$y, $ensureSpace, $rect, $text, $colors, $left, $contentWidth) {
        $ensureSpace(34);
        $rect($left, $y - 23, $contentWidth, 23, $colors['navy']);
        $text($left + 10, $y - 15, $title, 10, true, $colors['white']);
        $text($left + $contentWidth - 74, $y - 15, $count . ' row(s)', 8, false, [203, 213, 225]);
        $y -= 31;
    };

    $kpi = function (float $x, float $w, string $label, string $value, string $sub, array $accent) use (&$y, $rect, $text, $colors) {
        $rect($x, $y - 66, $w, 66, $colors['white'], $colors['line']);
        $rect($x, $y - 66, 4, 66, $accent);
        $text($x + 12, $y - 19, $label, 7, true, $colors['muted']);
        $text($x + 12, $y - 41, report_pdf_truncate($value, $w - 22, 15), 15, true, $colors['ink']);
        $text($x + 12, $y - 55, report_pdf_truncate($sub, $w - 22, 8), 8, false, $colors['muted']);
    };

    $table = function (string $title, array $columns, array $rows) use (&$y, $section, $ensureSpace, $rect, $text, $line, $colors, $left, $contentWidth, $bottom, $finishPage, $startPage) {
        $section($title, count($rows));
        $headerHeight = 21;
        $rowHeight = 20;
        $drawHeader = function () use (&$y, $columns, $rect, $text, $colors, $left, $contentWidth, $headerHeight) {
            $rect($left, $y - $headerHeight, $contentWidth, $headerHeight, $colors['header']);
            $x = $left + 7;
            foreach ($columns as $column) {
                $text($x, $y - 14, report_pdf_truncate($column['label'], $column['width'] - 8, 7), 7, true, $colors['white']);
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
            $fill = $index % 2 === 0 ? $colors['white'] : $colors['stripe'];
            $rect($left, $y - $rowHeight, $contentWidth, $rowHeight, $fill);
            $line($left, $y - $rowHeight, $left + $contentWidth, $y - $rowHeight, $colors['line']);
            $x = $left + 7;
            foreach ($columns as $idx => $column) {
                $fontSize = $column['size'] ?? 7;
                $text($x, $y - 13, report_pdf_truncate((string) ($row[$idx] ?? ''), $column['width'] - 8, $fontSize), $fontSize, false, $colors['ink']);
                $x += $column['width'];
            }
            $y -= $rowHeight;
        }
        $y -= 14;
    };

    $startPage();
    $text($left, $y, 'Executive Summary', 12, true, $colors['ink']);
    $text($left, $y - 15, 'Attendance, leave, and payroll performance for the selected filter.', 8, false, $colors['muted']);
    $y -= 34;

    $cardGap = 10;
    $cardWidth = ($contentWidth - ($cardGap * 4)) / 5;
    $kpi($left, $cardWidth, 'PRESENT', (string) intval($summary['present']), number_format($summary['hours'], 2) . ' total hours', $colors['green']);
    $kpi($left + ($cardWidth + $cardGap), $cardWidth, 'ABSENT', (string) intval($summary['absent']), number_format($summary['unpaid_days'], 2) . ' unpaid days', $colors['red']);
    $kpi($left + (($cardWidth + $cardGap) * 2), $cardWidth, 'ON LEAVE', (string) intval($summary['leave']), count($leaveRows) . ' leave records', $colors['blue']);
    $kpi($left + (($cardWidth + $cardGap) * 3), $cardWidth, 'OVERTIME', number_format($summary['overtime'], 2) . 'h', 'Attendance records', $colors['amber']);
    $kpi($left + (($cardWidth + $cardGap) * 4), $cardWidth, 'NET SALARY', $currency . ' ' . number_format($summary['net_salary'], 2), count($payrollRows) . ' payroll rows', $colors['green']);
    $y -= 88;

    $attendanceTableRows = [];
    foreach ($attendanceRows as $row) {
        $attendanceTableRows[] = [
            $row['date'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['employee_code'],
            report_time_range($row),
            number_format(floatval($row['total_hours']), 2),
            number_format(floatval($row['overtime_hours']), 2),
            $row['status'],
            report_attendance_flags($row),
        ];
    }
    $table('Attendance Records', [
        ['label' => 'Date', 'width' => 62],
        ['label' => 'Employee', 'width' => 162],
        ['label' => 'Code', 'width' => 70],
        ['label' => 'Timeline', 'width' => 82],
        ['label' => 'Total', 'width' => 54],
        ['label' => 'OT', 'width' => 48],
        ['label' => 'Status', 'width' => 78],
        ['label' => 'Flags', 'width' => 214],
    ], $attendanceTableRows);

    $leaveTableRows = [];
    foreach ($leaveRows as $row) {
        $leaveTableRows[] = [
            $row['first_name'] . ' ' . $row['last_name'],
            $row['employee_code'],
            $row['leave_type'],
            $row['start_date'],
            $row['end_date'] ?: $row['start_date'],
            number_format(floatval($row['total_days']), 2),
            intval($row['is_paid']) === 1 ? 'Paid' : 'Unpaid',
            $row['status'],
        ];
    }
    $table('Leave Applications', [
        ['label' => 'Employee', 'width' => 170],
        ['label' => 'Code', 'width' => 72],
        ['label' => 'Type', 'width' => 124],
        ['label' => 'Start', 'width' => 76],
        ['label' => 'End', 'width' => 76],
        ['label' => 'Days', 'width' => 54],
        ['label' => 'Paid', 'width' => 62],
        ['label' => 'Status', 'width' => 136],
    ], $leaveTableRows);

    $payrollTableRows = [];
    foreach ($payrollRows as $row) {
        $payrollTableRows[] = [
            $row['payroll_month'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['employee_code'],
            number_format(floatval($row['base_salary']), 2),
            number_format(floatval($row['overtime_hours'] ?? 0), 2),
            number_format(floatval($row['overtime_amount'] ?? 0), 2),
            number_format(floatval($row['unpaid_days'] ?? 0), 2),
            number_format(floatval($row['net_salary']), 2),
            $row['status'],
        ];
    }
    $table('Payroll Records', [
        ['label' => 'Month', 'width' => 62],
        ['label' => 'Employee', 'width' => 150],
        ['label' => 'Code', 'width' => 66],
        ['label' => 'Base', 'width' => 86],
        ['label' => 'OT Hrs', 'width' => 52],
        ['label' => 'OT Amount', 'width' => 84],
        ['label' => 'Unpaid', 'width' => 58],
        ['label' => 'Net Salary', 'width' => 92],
        ['label' => 'Status', 'width' => 120],
    ], $payrollTableRows);

    $finishPage();
    report_pdf_stream($pages, $filename);
}

$baseName = report_clean_filename('hrm365_report_' . $start_date . '_to_' . $end_date);

if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $baseName . '.xls"');
    header('X-Content-Type-Options: nosniff');
    echo "\xEF\xBB\xBF";
    ?>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; }
            h1, h2 { margin: 12px 0 8px; }
            table { border-collapse: collapse; margin-bottom: 18px; width: 100%; }
            th { background: #1f2937; color: #ffffff; font-weight: bold; }
            th, td { border: 1px solid #9ca3af; padding: 6px; vertical-align: top; }
            .num { text-align: right; }
        </style>
    </head>
    <body>
        <h1>HRM365 Report</h1>
        <table>
            <tr><th>Period</th><td><?php echo report_h($periodLabel); ?></td></tr>
            <tr><th>Employee Filter</th><td><?php echo report_h($selectedEmployeeLabel); ?></td></tr>
            <tr><th>Generated At</th><td><?php echo report_h(date('Y-m-d H:i:s')); ?></td></tr>
        </table>

        <h2>Summary</h2>
        <table>
            <tr>
                <th>Present</th><th>Absent</th><th>On Leave</th><th>Total Hours</th><th>Overtime Hours</th><th>Unpaid Days</th><th>Net Salary</th>
            </tr>
            <tr>
                <td class="num"><?php echo intval($summary['present']); ?></td>
                <td class="num"><?php echo intval($summary['absent']); ?></td>
                <td class="num"><?php echo intval($summary['leave']); ?></td>
                <td class="num"><?php echo number_format($summary['hours'], 2); ?></td>
                <td class="num"><?php echo number_format($summary['overtime'], 2); ?></td>
                <td class="num"><?php echo number_format($summary['unpaid_days'], 2); ?></td>
                <td class="num"><?php echo report_h($currency); ?> <?php echo number_format($summary['net_salary'], 2); ?></td>
            </tr>
        </table>

        <h2>Attendance Records</h2>
        <table>
            <tr><th>Date</th><th>Employee</th><th>Code</th><th>Timeline</th><th>Total Hours</th><th>OT Hours</th><th>Status</th><th>Flags</th></tr>
            <?php foreach ($attendanceRows as $row): ?>
                <tr>
                    <td><?php echo report_h($row['date']); ?></td>
                    <td><?php echo report_h($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?php echo report_h($row['employee_code']); ?></td>
                    <td><?php echo report_h(report_time_range($row)); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['total_hours']), 2); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['overtime_hours']), 2); ?></td>
                    <td><?php echo report_h($row['status']); ?></td>
                    <td><?php echo report_h(report_attendance_flags($row)); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h2>Leave Applications</h2>
        <table>
            <tr><th>Employee</th><th>Code</th><th>Leave Type</th><th>Start</th><th>End</th><th>Days</th><th>Paid</th><th>Status</th></tr>
            <?php foreach ($leaveRows as $row): ?>
                <tr>
                    <td><?php echo report_h($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?php echo report_h($row['employee_code']); ?></td>
                    <td><?php echo report_h($row['leave_type']); ?></td>
                    <td><?php echo report_h($row['start_date']); ?></td>
                    <td><?php echo report_h($row['end_date'] ?: $row['start_date']); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['total_days']), 2); ?></td>
                    <td><?php echo intval($row['is_paid']) === 1 ? 'Paid' : 'Unpaid'; ?></td>
                    <td><?php echo report_h($row['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <h2>Payroll Records</h2>
        <table>
            <tr><th>Month</th><th>Employee</th><th>Code</th><th>Base Salary</th><th>OT Hours</th><th>OT Amount</th><th>Unpaid Days</th><th>Deductions</th><th>Net Salary</th><th>Status</th></tr>
            <?php foreach ($payrollRows as $row): ?>
                <tr>
                    <td><?php echo report_h($row['payroll_month']); ?></td>
                    <td><?php echo report_h($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?php echo report_h($row['employee_code']); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['base_salary']), 2); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['overtime_hours'] ?? 0), 2); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['overtime_amount'] ?? 0), 2); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['unpaid_days'] ?? 0), 2); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['deductions'] ?? 0), 2); ?></td>
                    <td class="num"><?php echo number_format(floatval($row['net_salary']), 2); ?></td>
                    <td><?php echo report_h($row['status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </body>
    </html>
    <?php
    exit;
}

report_download_pdf(
    $summary,
    $attendanceRows,
    $leaveRows,
    $payrollRows,
    $currency,
    $periodLabel,
    $selectedEmployeeLabel,
    $baseName . '.pdf'
);
