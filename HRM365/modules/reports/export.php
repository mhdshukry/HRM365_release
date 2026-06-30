<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once __DIR__ . '/report_data.php';

$format = strtolower(trim($_GET['format'] ?? 'excel'));
if (!in_array($format, ['excel', 'pdf'], true)) {
    $format = 'excel';
}
$reportView = $_GET['view'] ?? 'attendance';
if (!in_array($reportView, ['attendance', 'leave_payroll'], true)) {
    $reportView = 'attendance';
}
$reportTitle = $reportView === 'leave_payroll' ? 'Leave & Payroll Report' : 'Attendance Records Report';
$companyName = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'company_name'")->fetchColumn() ?: 'HRM365 Enterprise';

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

function report_leave_summary(array $leaveRows): string
{
    if (empty($leaveRows)) {
        return 'No leave';
    }
    $items = [];
    foreach ($leaveRows as $row) {
        $items[] = $row['leave_type']
            . ' (' . $row['status'] . ') '
            . date('M d', strtotime($row['start_date']))
            . ' to '
            . date('M d, Y', strtotime($row['end_date'] ?: $row['start_date']))
            . ' - '
            . number_format(floatval($row['total_days']), 2)
            . ' day(s)';
    }
    return implode('; ', $items);
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
    array $leavePayrollRows,
    string $currency,
    string $periodLabel,
    string $selectedEmployeeLabel,
    string $selectedBranchLabel,
    string $reportView,
    string $reportTitle,
    string $companyName,
    array $payrollFeatures,
    string $filename
): void {
    $showOvertime = $payrollFeatures['payroll_enable_overtime'] ?? true;
    $showEpf = $payrollFeatures['payroll_enable_epf'] ?? true;
    $showEtf = $payrollFeatures['payroll_enable_etf'] ?? true;
    $showStatutory = $showEpf || $showEtf;
    $left = 60;
    $contentWidth = 722;
    $bottom = 36;
    $colors = [
        'ink' => [17, 24, 39],
        'muted' => [107, 114, 128],
        'line' => [229, 231, 235],
        'paper' => [238, 242, 247],
        'navy' => [31, 41, 55],
        'slate' => [51, 65, 85],
        'header' => [241, 245, 249],
        'blue' => [37, 99, 235],
        'green' => [4, 120, 87],
        'red' => [220, 38, 38],
        'amber' => [245, 158, 11],
        'stripe' => [248, 250, 252],
        'softBlue' => [248, 250, 252],
        'panel' => [255, 255, 255],
        'darkMuted' => [203, 213, 225],
        'white' => [255, 255, 255],
        'pill' => [75, 85, 99],
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

    $textRight = function (float $rightX, float $yPos, string $value, int $size = 8, bool $bold = false, ?array $color = null) use ($text) {
        $approxWidth = strlen($value) * $size * 0.48;
        $text(max(0, $rightX - $approxWidth), $yPos, $value, $size, $bold, $color);
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

    $startPage = function () use (&$content, &$y, &$pageNo, $rect, $text, $textRight, $line, $colors, $left, $contentWidth, $periodLabel, $selectedEmployeeLabel, $selectedBranchLabel, $reportTitle, $companyName) {
        $pageNo++;
        $content = '';
        $rect(0, 0, 842, 595, $colors['paper']);
        $rect(36, 28, 770, 540, $colors['panel'], $colors['line']);
        $rect(36, 480, 770, 88, $colors['navy']);
        $text($left, 540, report_pdf_truncate($companyName, 310, 18), 18, true, $colors['white']);
        $text($left, 518, 'Report generated by HRM365', 10, false, $colors['darkMuted']);
        $textRight(752, 540, report_pdf_truncate(strtoupper($reportTitle), 180, 14), 14, true, $colors['white']);
        $rect(596, 502, 154, 22, $colors['pill']);
        $text(608, 509, report_pdf_truncate($periodLabel, 132, 7), 7, true, $colors['white']);

        $metaY = 450;
        $cardW = ($contentWidth - 20) / 3;
        $rect($left, $metaY - 44, $cardW, 44, $colors['softBlue'], $colors['line']);
        $text($left + 12, $metaY - 16, 'BRANCH FILTER', 7, true, $colors['muted']);
        $text($left + 12, $metaY - 32, report_pdf_truncate($selectedBranchLabel, $cardW - 22, 9), 9, true, $colors['ink']);
        $rect($left + $cardW + 10, $metaY - 44, $cardW, 44, $colors['softBlue'], $colors['line']);
        $text($left + $cardW + 22, $metaY - 16, 'EMPLOYEE FILTER', 7, true, $colors['muted']);
        $text($left + $cardW + 22, $metaY - 32, report_pdf_truncate($selectedEmployeeLabel, $cardW - 22, 9), 9, true, $colors['ink']);
        $rect($left + (($cardW + 10) * 2), $metaY - 44, $cardW, 44, $colors['softBlue'], $colors['line']);
        $text($left + (($cardW + 10) * 2) + 12, $metaY - 16, 'PERIOD', 7, true, $colors['muted']);
        $text($left + (($cardW + 10) * 2) + 12, $metaY - 32, report_pdf_truncate($periodLabel, $cardW - 22, 9), 9, true, $colors['ink']);

        $line($left, 50, $left + $contentWidth, 50, $colors['line']);
        $text($left, 38, 'This is a computer-generated HRM365 report.', 7, false, $colors['muted']);
        $textRight(782, 38, 'Generated ' . date('Y-m-d H:i') . '   Page ' . $pageNo, 7, false, $colors['muted']);
        $y = 388;
    };

    $ensureSpace = function (float $height) use (&$y, $bottom, $finishPage, $startPage) {
        if (($y - $height) < $bottom) {
            $finishPage();
            $startPage();
        }
    };

    $section = function (string $title, int $count) use (&$y, $ensureSpace, $rect, $text, $textRight, $colors, $left, $contentWidth) {
        $ensureSpace(34);
        $text($left, $y - 14, strtoupper($title), 11, true, $colors['slate']);
        $textRight($left + $contentWidth, $y - 14, $count . ' row(s)', 8, true, $colors['muted']);
        $y -= 28;
    };

    $kpi = function (float $x, float $w, string $label, string $value, string $sub, array $accent) use (&$y, $rect, $text, $colors) {
        $rect($x, $y - 58, $w, 58, $colors['softBlue'], $colors['line']);
        $text($x + 11, $y - 16, $label, 7, true, $colors['muted']);
        $text($x + 11, $y - 37, report_pdf_truncate($value, $w - 22, 13), 13, true, $accent);
        $text($x + 11, $y - 50, report_pdf_truncate($sub, $w - 22, 7), 7, false, $colors['muted']);
    };

    $table = function (string $title, array $columns, array $rows) use (&$y, $section, $ensureSpace, $rect, $text, $textRight, $line, $colors, $left, $contentWidth, $bottom, $finishPage, $startPage) {
        $section($title, count($rows));
        $headerHeight = 23;
        $rowHeight = 22;
        $drawHeader = function () use (&$y, $columns, $rect, $text, $textRight, $colors, $left, $contentWidth, $headerHeight) {
            $rect($left, $y - $headerHeight, $contentWidth, $headerHeight, $colors['header'], $colors['line']);
            $x = $left + 10;
            foreach ($columns as $column) {
                $label = report_pdf_truncate($column['label'], $column['width'] - 8, 7);
                if (($column['align'] ?? '') === 'right') {
                    $textRight($x + $column['width'] - 10, $y - 15, $label, 7, true, $colors['slate']);
                } else {
                    $text($x, $y - 15, $label, 7, true, $colors['slate']);
                }
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
            $rect($left, $y - $rowHeight, $contentWidth, $rowHeight, $fill, $colors['line']);
            $line($left, $y - $rowHeight, $left + $contentWidth, $y - $rowHeight, $colors['line']);
            $x = $left + 10;
            foreach ($columns as $idx => $column) {
                $fontSize = $column['size'] ?? 7;
                $value = report_pdf_truncate((string) ($row[$idx] ?? ''), $column['width'] - 8, $fontSize);
                if (($column['align'] ?? '') === 'right') {
                    $textRight($x + $column['width'] - 10, $y - 14, $value, $fontSize, false, $colors['ink']);
                } else {
                    $text($x, $y - 14, $value, $fontSize, false, $colors['ink']);
                }
                $x += $column['width'];
            }
            $y -= $rowHeight;
        }
        $y -= 14;
    };

    $startPage();
    $text($left, $y - 12, 'EXECUTIVE SUMMARY', 12, true, $colors['slate']);
    $text($left, $y - 26, $reportView === 'attendance' ? 'Attendance performance for the selected filter.' : 'Leave and payroll performance for the selected filter.', 8, false, $colors['muted']);
    $y -= 42;

    $cardGap = 10;
    $cardCount = $showOvertime ? 5 : 4;
    $cardWidth = ($contentWidth - ($cardGap * ($cardCount - 1))) / $cardCount;
    if ($reportView === 'attendance') {
        $kpi($left, $cardWidth, 'PRESENT', (string) intval($summary['present']), number_format($summary['hours'], 2) . ' total hours', $colors['green']);
        $kpi($left + ($cardWidth + $cardGap), $cardWidth, 'ABSENT', (string) intval($summary['absent']), 'No punch days', $colors['red']);
        $kpi($left + (($cardWidth + $cardGap) * 2), $cardWidth, 'ON LEAVE', (string) intval($summary['leave']), 'Attendance status', $colors['blue']);
        if ($showOvertime) {
            $kpi($left + (($cardWidth + $cardGap) * 3), $cardWidth, 'OVERTIME', number_format($summary['overtime'], 2) . 'h', 'From attendance', $colors['amber']);
            $kpi($left + (($cardWidth + $cardGap) * 4), $cardWidth, 'ROWS', (string) count($attendanceRows), 'Attendance records', $colors['green']);
        } else {
            $kpi($left + (($cardWidth + $cardGap) * 3), $cardWidth, 'ROWS', (string) count($attendanceRows), 'Attendance records', $colors['green']);
        }
    } else {
        $kpi($left, $cardWidth, 'LEAVE ROWS', (string) count($leaveRows), 'Applications in period', $colors['blue']);
        $kpi($left + ($cardWidth + $cardGap), $cardWidth, 'PAYROLL ROWS', (string) count($payrollRows), 'Generated records', $colors['green']);
        $kpi($left + (($cardWidth + $cardGap) * 2), $cardWidth, 'UNPAID', number_format($summary['unpaid_days'], 2), 'No-pay day(s)', $colors['red']);
        if ($showOvertime) {
            $kpi($left + (($cardWidth + $cardGap) * 3), $cardWidth, 'OVERTIME', number_format($summary['overtime'], 2) . 'h', 'Payroll basis', $colors['amber']);
            $kpi($left + (($cardWidth + $cardGap) * 4), $cardWidth, 'NET SALARY', $currency . ' ' . number_format($summary['net_salary'], 2), count($payrollRows) . ' payroll rows', $colors['green']);
        } else {
            $kpi($left + (($cardWidth + $cardGap) * 3), $cardWidth, 'NET SALARY', $currency . ' ' . number_format($summary['net_salary'], 2), count($payrollRows) . ' payroll rows', $colors['green']);
        }
    }
    $y -= 76;
    $ensureSpace(52);
    $rect($left, $y - 42, $contentWidth, 42, $colors['navy']);
    $text($left + 18, $y - 17, $reportView === 'attendance' ? 'TOTAL HOURS RECORDED' : 'NET SALARY TOTAL', 10, true, $colors['darkMuted']);
    $text($left + 18, $y - 30, $reportView === 'attendance' ? 'Selected period attendance total' : 'Gross earnings minus deductions across payroll rows', 7, false, $colors['darkMuted']);
    $totalBarValue = $reportView === 'attendance'
        ? number_format($summary['hours'], 2) . 'h'
        : $currency . ' ' . number_format($summary['net_salary'], 2);
    $textRight($left + $contentWidth - 18, $y - 27, $totalBarValue, 18, true, $colors['white']);
    $y -= 66;
    if ($reportView === 'leave_payroll' && $showStatutory) {
        $rect($left, $y - 28, $contentWidth, 28, $colors['white'], $colors['line']);
        $statX = $left + 12;
        if ($showEpf) {
            $text($statX, $y - 12, 'Employee EPF', 7, true, $colors['muted']);
            $text($statX, $y - 23, $currency . ' ' . number_format($summary['epf_employee'], 2), 8, true, $colors['ink']);
            $statX += 175;
            $text($statX, $y - 12, 'Employer EPF', 7, true, $colors['muted']);
            $text($statX, $y - 23, $currency . ' ' . number_format($summary['epf_employer'], 2), 8, true, $colors['ink']);
            $statX += 175;
        }
        if ($showEtf) {
            $text($statX, $y - 12, 'ETF', 7, true, $colors['muted']);
            $text($statX, $y - 23, $currency . ' ' . number_format($summary['etf_employer'], 2), 8, true, $colors['ink']);
        }
        $text($left + 535, $y - 12, 'Statutory Total', 7, true, $colors['muted']);
        $text($left + 535, $y - 23, $currency . ' ' . number_format(($showEpf ? ($summary['epf_employee'] + $summary['epf_employer']) : 0) + ($showEtf ? $summary['etf_employer'] : 0), 2), 8, true, $colors['ink']);
        $y -= 42;
    }

    if ($reportView === 'attendance') {
        $attendanceTableRows = [];
        foreach ($attendanceRows as $row) {
            $record = [
                $row['date'],
                $row['first_name'] . ' ' . $row['last_name'],
                $row['employee_code'],
                report_time_range($row),
                number_format(floatval($row['total_hours']), 2),
            ];
            if ($showOvertime) {
                $record[] = number_format(floatval($row['overtime_hours']), 2);
            }
            $record[] = $row['status'];
            $record[] = report_attendance_flags($row);
            $attendanceTableRows[] = $record;
        }
        $attendanceColumns = [
            ['label' => 'Date', 'width' => 58],
            ['label' => 'Employee', 'width' => $showOvertime ? 140 : 158],
            ['label' => 'Code', 'width' => 58],
            ['label' => 'Sign-In / Sign-Out', 'width' => 78],
            ['label' => 'Total', 'width' => 52, 'align' => 'right'],
        ];
        if ($showOvertime) {
            $attendanceColumns[] = ['label' => 'OT', 'width' => 42, 'align' => 'right'];
        }
        $attendanceColumns = array_merge($attendanceColumns, [
            ['label' => 'Status', 'width' => 68],
            ['label' => 'Flags', 'width' => $showOvertime ? 226 : 250],
        ]);
        $table('Attendance Records', $attendanceColumns, $attendanceTableRows);
    } else {
        $leavePayrollTableRows = [];
        foreach ($leavePayrollRows as $row) {
            $payroll = $row['payroll'];
            $record = [
                $row['employee_name'],
                $row['employee_code'],
                report_leave_summary($row['leave_rows']),
                $payroll ? date('M Y', strtotime($payroll['payroll_month'] . '-01')) : 'No payroll',
            ];
            if ($showOvertime) {
                $record[] = $payroll ? number_format(floatval($payroll['overtime_hours'] ?? 0), 2) : '0.00';
            }
            $record[] = $payroll ? number_format(floatval($payroll['unpaid_days'] ?? 0), 2) : '0.00';
            $record[] = $payroll ? number_format(floatval($payroll['advance_amount'] ?? 0), 2) : '0.00';
            if ($showEpf) {
                $record[] = $payroll ? number_format(floatval($payroll['epf_employee_amount'] ?? 0), 2) : '0.00';
                $record[] = $payroll ? number_format(floatval($payroll['epf_employer_amount'] ?? 0), 2) : '0.00';
            }
            if ($showEtf) {
                $record[] = $payroll ? number_format(floatval($payroll['etf_employer_amount'] ?? 0), 2) : '0.00';
            }
            $record[] = $payroll ? number_format(floatval($payroll['net_salary']), 2) : '0.00';
            $record[] = $payroll ? $payroll['status'] : '-';
            $leavePayrollTableRows[] = $record;
        }
        $leavePayrollColumns = [
            ['label' => 'Employee', 'width' => 96],
            ['label' => 'Code', 'width' => 50],
            ['label' => 'Leave in Period', 'width' => 142],
            ['label' => 'Payroll', 'width' => 54],
        ];
        if ($showOvertime) {
            $leavePayrollColumns[] = ['label' => 'OT Hrs', 'width' => 40, 'align' => 'right'];
        }
        $leavePayrollColumns[] = ['label' => 'Unpaid', 'width' => 42, 'align' => 'right'];
        $leavePayrollColumns[] = ['label' => 'Advance', 'width' => 48, 'align' => 'right'];
        if ($showEpf) {
            $leavePayrollColumns[] = ['label' => 'EPF EE', 'width' => 48, 'align' => 'right'];
            $leavePayrollColumns[] = ['label' => 'EPF ER', 'width' => 48, 'align' => 'right'];
        }
        if ($showEtf) {
            $leavePayrollColumns[] = ['label' => 'ETF', 'width' => 40, 'align' => 'right'];
        }
        $leavePayrollColumns = array_merge($leavePayrollColumns, [
            ['label' => 'Net', 'width' => 58, 'align' => 'right'],
            ['label' => 'Status', 'width' => 100],
        ]);
        $table('Leave & Payroll', $leavePayrollColumns, $leavePayrollTableRows);
    }

    $finishPage();
    report_pdf_stream($pages, $filename);
}

$baseName = report_clean_filename('hrm365_' . $reportView . '_report_' . $start_date . '_to_' . $end_date);

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
        <h1><?php echo report_h($reportTitle); ?></h1>
        <table>
            <tr><th>Period</th><td><?php echo report_h($periodLabel); ?></td></tr>
            <tr><th>Branch Filter</th><td><?php echo report_h($selectedBranchLabel); ?></td></tr>
            <tr><th>Employee Filter</th><td><?php echo report_h($selectedEmployeeLabel); ?></td></tr>
            <tr><th>Generated At</th><td><?php echo report_h(date('Y-m-d H:i:s')); ?></td></tr>
        </table>

        <h2>Summary</h2>
        <table>
            <?php if ($reportView === 'attendance'): ?>
                <tr><th>Present</th><th>Absent</th><th>On Leave</th><th>Total Hours</th><?php if ($payrollFeatures['payroll_enable_overtime']): ?><th>Overtime Hours</th><?php endif; ?></tr>
                <tr>
                    <td class="num"><?php echo intval($summary['present']); ?></td>
                    <td class="num"><?php echo intval($summary['absent']); ?></td>
                    <td class="num"><?php echo intval($summary['leave']); ?></td>
                    <td class="num"><?php echo number_format($summary['hours'], 2); ?></td>
                    <?php if ($payrollFeatures['payroll_enable_overtime']): ?><td class="num"><?php echo number_format($summary['overtime'], 2); ?></td><?php endif; ?>
                </tr>
            <?php else: ?>
                <tr><th>Leave Records</th><th>Payroll Rows</th><th>Unpaid Days</th><?php if ($payrollFeatures['payroll_enable_epf']): ?><th>Employee EPF</th><th>Employer EPF</th><?php endif; ?><?php if ($payrollFeatures['payroll_enable_etf']): ?><th>ETF</th><?php endif; ?><th>Net Salary</th></tr>
                <tr>
                    <td class="num"><?php echo count($leaveRows); ?></td>
                    <td class="num"><?php echo count($payrollRows); ?></td>
                    <td class="num"><?php echo number_format($summary['unpaid_days'], 2); ?></td>
                    <?php if ($payrollFeatures['payroll_enable_epf']): ?>
                        <td class="num"><?php echo report_h($currency); ?> <?php echo number_format($summary['epf_employee'], 2); ?></td>
                        <td class="num"><?php echo report_h($currency); ?> <?php echo number_format($summary['epf_employer'], 2); ?></td>
                    <?php endif; ?>
                    <?php if ($payrollFeatures['payroll_enable_etf']): ?><td class="num"><?php echo report_h($currency); ?> <?php echo number_format($summary['etf_employer'], 2); ?></td><?php endif; ?>
                    <td class="num"><?php echo report_h($currency); ?> <?php echo number_format($summary['net_salary'], 2); ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <?php if ($reportView === 'attendance'): ?>
            <h2>Attendance Records</h2>
            <table>
                <tr><th>Date</th><th>Employee</th><th>Code</th><th>Sign-In / Sign-Out</th><th>Total Hours</th><?php if ($payrollFeatures['payroll_enable_overtime']): ?><th>OT Hours</th><?php endif; ?><th>Status</th><th>Flags</th></tr>
                <?php foreach ($attendanceRows as $row): ?>
                    <tr>
                        <td><?php echo report_h($row['date']); ?></td>
                        <td><?php echo report_h($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><?php echo report_h($row['employee_code']); ?></td>
                        <td><?php echo report_h(report_time_range($row)); ?></td>
                        <td class="num"><?php echo number_format(floatval($row['total_hours']), 2); ?></td>
                        <?php if ($payrollFeatures['payroll_enable_overtime']): ?><td class="num"><?php echo number_format(floatval($row['overtime_hours']), 2); ?></td><?php endif; ?>
                        <td><?php echo report_h($row['status']); ?></td>
                        <td><?php echo report_h(report_attendance_flags($row)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <h2>Leave & Payroll</h2>
            <table>
                <tr><th>Employee</th><th>Code</th><th>Leave in Period</th><th>Payroll Month</th><?php if ($payrollFeatures['payroll_enable_overtime']): ?><th>OT Hours</th><?php endif; ?><th>Unpaid Days</th><th>Advance</th><?php if ($payrollFeatures['payroll_enable_epf']): ?><th>Employee EPF</th><th>Employer EPF</th><?php endif; ?><?php if ($payrollFeatures['payroll_enable_etf']): ?><th>ETF</th><?php endif; ?><th>Net Salary</th><th>Status</th></tr>
                <?php foreach ($leavePayrollRows as $row): ?>
                    <?php $payroll = $row['payroll']; ?>
                    <tr>
                        <td><?php echo report_h($row['employee_name']); ?></td>
                        <td><?php echo report_h($row['employee_code']); ?></td>
                        <td><?php echo report_h(report_leave_summary($row['leave_rows'])); ?></td>
                        <td><?php echo $payroll ? report_h(date('M Y', strtotime($payroll['payroll_month'] . '-01'))) : 'No payroll'; ?></td>
                        <?php if ($payrollFeatures['payroll_enable_overtime']): ?><td class="num"><?php echo $payroll ? number_format(floatval($payroll['overtime_hours'] ?? 0), 2) : '0.00'; ?></td><?php endif; ?>
                        <td class="num"><?php echo $payroll ? number_format(floatval($payroll['unpaid_days'] ?? 0), 2) : '0.00'; ?></td>
                        <td class="num"><?php echo $payroll ? number_format(floatval($payroll['advance_amount'] ?? 0), 2) : '0.00'; ?></td>
                        <?php if ($payrollFeatures['payroll_enable_epf']): ?>
                            <td class="num"><?php echo $payroll ? number_format(floatval($payroll['epf_employee_amount'] ?? 0), 2) : '0.00'; ?></td>
                            <td class="num"><?php echo $payroll ? number_format(floatval($payroll['epf_employer_amount'] ?? 0), 2) : '0.00'; ?></td>
                        <?php endif; ?>
                        <?php if ($payrollFeatures['payroll_enable_etf']): ?><td class="num"><?php echo $payroll ? number_format(floatval($payroll['etf_employer_amount'] ?? 0), 2) : '0.00'; ?></td><?php endif; ?>
                        <td class="num"><?php echo $payroll ? number_format(floatval($payroll['net_salary']), 2) : '0.00'; ?></td>
                        <td><?php echo $payroll ? report_h($payroll['status']) : '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
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
    $leavePayrollRows,
    $currency,
    $periodLabel,
    $selectedEmployeeLabel,
    $selectedBranchLabel,
    $reportView,
    $reportTitle,
    $companyName,
    $payrollFeatures,
    $baseName . '.pdf'
);
