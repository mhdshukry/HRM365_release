<?php 
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/attendance_math.php';
require_once '../../includes/payroll_math.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$stmt = $pdo->prepare("
    SELECT p.*, e.first_name, e.last_name, e.employee_code, e.department, e.designation, e.hire_date
    FROM payroll_records p
    JOIN employees e ON p.employee_id = e.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payroll = $stmt->fetch();

if (!$payroll) {
    die("Payslip not found.");
}

if ($currentUser['role'] === 'employee' && intval($payroll['employee_id']) !== intval($currentUser['employee_id'] ?? 0)) {
    die("Unauthorized access.");
}

if (!in_array($currentUser['role'], ['admin', 'HR', 'employee'])) {
    die("Unauthorized access.");
}

$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('currency', 'company_name')");
$settings = [];
foreach ($settingsStmt->fetchAll() as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

$currency = $settings['currency'] ?? 'LKR';
$companyName = $settings['company_name'] ?? 'HRM365 Enterprise';
$payrollFeatures = payroll_feature_settings($pdo);
$total_overtime_hours = floatval($payroll['overtime_hours'] ?? 0);
$unpaid_days = floatval($payroll['unpaid_days'] ?? 0);
$advance_amount = floatval($payroll['advance_amount'] ?? 0);
$epf_employee_amount = floatval($payroll['epf_employee_amount'] ?? 0);
$epf_employer_amount = floatval($payroll['epf_employer_amount'] ?? 0);
$etf_employer_amount = floatval($payroll['etf_employer_amount'] ?? 0);
$total_employee_deductions = floatval($payroll['deductions']) + $advance_amount + ($payrollFeatures['payroll_enable_epf'] ? $epf_employee_amount : 0.00);
$base_salary = floatval($payroll['base_salary']);
$epf_employee_rate = $base_salary > 0 ? ($epf_employee_amount / $base_salary) * 100 : 0;
$epf_employer_rate = $base_salary > 0 ? ($epf_employer_amount / $base_salary) * 100 : 0;
$etf_employer_rate = $base_salary > 0 ? ($etf_employer_amount / $base_salary) * 100 : 0;
$gross_earnings = $base_salary + ($payrollFeatures['payroll_enable_overtime'] ? floatval($payroll['overtime_amount']) : 0.00);
$total_employer_contributions = ($payrollFeatures['payroll_enable_epf'] ? $epf_employer_amount : 0.00) + ($payrollFeatures['payroll_enable_etf'] ? $etf_employer_amount : 0.00);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></title>
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --soft: #f8fafc;
            --panel: #ffffff;
            --brand: #1f2937;
            --brand-2: #2563eb;
            --good: #047857;
            --bad: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #eef2f7;
            color: var(--ink);
            padding: 28px;
            font-size: 13px;
            line-height: 1.45;
        }
        .payslip-container {
            max-width: 860px;
            margin: 0 auto;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .document-top {
            background: var(--brand);
            color: #fff;
            padding: 30px 34px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
        }
        .company-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.2px;
        }
        .doc-subtitle {
            color: #cbd5e1;
            margin-top: 6px;
            font-size: 12px;
        }
        .payslip-title {
            text-align: right;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 1.4px;
        }
        .period-pill {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: #e5e7eb;
            font-size: 12px;
            font-weight: 700;
        }
        .document-body { padding: 30px 34px 34px; }
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .meta-card {
            border: 1px solid var(--line);
            background: var(--soft);
            border-radius: 8px;
            padding: 13px 14px;
            min-height: 72px;
        }
        .meta-label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .meta-value {
            font-weight: 800;
            font-size: 13px;
            color: var(--ink);
        }
        .status-paid {
            color: var(--good);
        }
        .section-title {
            margin: 0 0 10px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.7px;
            text-transform: uppercase;
            color: #374151;
        }
        .amount-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            align-items: start;
        }
        .amount-card {
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .salary-table th {
            background: #f1f5f9;
            color: #475569;
            font-size: 11px;
            font-weight: 800;
            text-align: left;
            padding: 11px 14px;
            border-bottom: 1px solid var(--line);
        }
        .salary-table th:last-child,
        .salary-table td.amount {
            text-align: right;
        }
        .salary-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }
        .salary-table tr:last-child td {
            border-bottom: 0;
        }
        .amount {
            font-family: Consolas, "Courier New", monospace;
            white-space: nowrap;
            font-size: 13px;
            font-weight: 700;
        }
        .amount-positive { color: var(--good); }
        .amount-negative { color: var(--bad); }
        .line-note {
            display: block;
            color: var(--muted);
            font-size: 11px;
            margin-top: 3px;
        }
        .total-row td {
            background: #fbfdff;
            font-weight: 800;
        }
        .contribution-card {
            margin-top: 18px;
        }
        .net-pay {
            margin-top: 24px;
            background: var(--brand);
            color: #fff;
            border-radius: 8px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .net-label {
            font-size: 12px;
            color: #cbd5e1;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            font-weight: 800;
        }
        .net-amount {
            font-family: Consolas, "Courier New", monospace;
            font-size: 24px;
            font-weight: 900;
            white-space: nowrap;
        }
        .document-footer {
            margin-top: 26px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 20px;
            color: var(--muted);
            font-size: 11px;
        }
        .print-btn {
            display: block;
            margin: 22px auto 0;
            padding: 11px 22px;
            background: var(--brand-2);
            color: white;
            border: none;
            border-radius: 7px;
            font-weight: 800;
            cursor: pointer;
            text-align: center;
            width: max-content;
        }
        
        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: white; padding: 0; }
            .payslip-container { box-shadow: none; border-radius: 0; border: 0; }
            .print-btn { display: none; }
        }

        @media (max-width: 720px) {
            body { padding: 14px; }
            .document-top,
            .net-pay,
            .document-footer {
                flex-direction: column;
                gap: 1rem;
                text-align: left;
            }
            .payslip-title {
                text-align: left !important;
            }
            .document-body {
                padding: 22px;
            }
            .meta-grid,
            .amount-grid {
                grid-template-columns: 1fr;
            }
            .salary-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .print-btn { width: 100%; }
        }
    </style>
</head>
<body>

    <main class="payslip-container">
        <header class="document-top">
            <div>
                <div class="company-name"><?php echo htmlspecialchars($companyName); ?></div>
                <div class="doc-subtitle">Payroll generated by HRM365</div>
            </div>
            <div>
                <div class="payslip-title">SALARY SLIP</div>
                <div class="period-pill"><?php echo date('F Y', strtotime($payroll['payroll_month'] . '-01')); ?></div>
            </div>
        </header>

        <section class="document-body">
            <div class="meta-grid">
                <div class="meta-card">
                    <div class="meta-label">Employee</div>
                    <div class="meta-value"><?php echo htmlspecialchars($payroll['first_name'] . ' ' . $payroll['last_name']); ?></div>
                    <div class="line-note"><?php echo htmlspecialchars($payroll['employee_code']); ?></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Department</div>
                    <div class="meta-value"><?php echo htmlspecialchars($payroll['department'] ?: 'Not assigned'); ?></div>
                    <div class="line-note"><?php echo htmlspecialchars($payroll['designation'] ?: 'No designation'); ?></div>
                </div>
                <div class="meta-card">
                    <div class="meta-label">Payroll Status</div>
                    <div class="meta-value status-paid"><?php echo htmlspecialchars($payroll['status']); ?></div>
                    <div class="line-note">Joined <?php echo date('M d, Y', strtotime($payroll['hire_date'])); ?></div>
                </div>
            </div>

            <div class="amount-grid">
                <div>
                    <h2 class="section-title">Earnings</h2>
                    <div class="amount-card">
                        <table class="salary-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Basic Salary</td>
                                    <td class="amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($base_salary, 2); ?></td>
                                </tr>
                                <?php if ($payrollFeatures['payroll_enable_overtime']): ?>
                                    <tr>
                                        <td>Overtime Allowance <span class="line-note"><?php echo htmlspecialchars(format_hours_minutes($total_overtime_hours)); ?> based on 240 monthly hours</span></td>
                                        <td class="amount amount-positive">+<?php echo htmlspecialchars($currency); ?> <?php echo number_format($payroll['overtime_amount'], 2); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="total-row">
                                    <td>Total Earnings</td>
                                    <td class="amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($gross_earnings, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h2 class="section-title">Employee Deductions</h2>
                    <div class="amount-card">
                        <table class="salary-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Unpaid Leave / Absence <span class="line-note"><?php echo number_format($unpaid_days, 2); ?> day(s), based on 30 salary days</span></td>
                                    <td class="amount amount-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format($payroll['deductions'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Salary Advance <span class="line-note">Paid advances assigned to this payroll month</span></td>
                                    <td class="amount amount-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format($advance_amount, 2); ?></td>
                                </tr>
                                <?php if ($payrollFeatures['payroll_enable_epf']): ?>
                                    <tr>
                                        <td>Employee EPF <span class="line-note"><?php echo number_format($epf_employee_rate, 2); ?>% of basic salary</span></td>
                                        <td class="amount amount-negative">-<?php echo htmlspecialchars($currency); ?> <?php echo number_format($epf_employee_amount, 2); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="total-row">
                                    <td>Total Deductions</td>
                                    <td class="amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_employee_deductions, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($payrollFeatures['payroll_enable_epf'] || $payrollFeatures['payroll_enable_etf']): ?>
                <div class="contribution-card">
                    <h2 class="section-title">Employer Contributions</h2>
                    <div class="amount-card">
                        <table class="salary-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Rate</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($payrollFeatures['payroll_enable_epf']): ?>
                                    <tr>
                                        <td>Employer EPF</td>
                                        <td><?php echo number_format($epf_employer_rate, 2); ?>%</td>
                                        <td class="amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($epf_employer_amount, 2); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php if ($payrollFeatures['payroll_enable_etf']): ?>
                                    <tr>
                                        <td>ETF</td>
                                        <td><?php echo number_format($etf_employer_rate, 2); ?>%</td>
                                        <td class="amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($etf_employer_amount, 2); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr class="total-row">
                                    <td colspan="2">Total Employer Contributions</td>
                                    <td class="amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($total_employer_contributions, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="net-pay">
                <div>
                    <div class="net-label">Net Salary Payable</div>
                    <div class="line-note" style="color: #cbd5e1;">Gross earnings minus employee deductions</div>
                </div>
                <div class="net-amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($payroll['net_salary'], 2); ?></div>
            </div>

            <footer class="document-footer">
                <span>This is a computer-generated salary slip. No signature is required.</span>
                <span>Generated <?php echo date('Y-m-d H:i'); ?></span>
            </footer>
        </section>
    </main>

    <button onclick="window.print()" class="print-btn">Print / Save as PDF</button>

</body>
</html>
