<?php 
require_once 'includes/db.php';
include 'includes/header.php'; 

// Fetch Live Metrics
$total_employees = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();
$present_today = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM attendance_records WHERE date = CURDATE()")->fetchColumn();
$on_leave = $pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'Approved' AND CURDATE() BETWEEN start_date AND COALESCE(end_date, start_date)")->fetchColumn();

// Fetch Recent Punches
$recent_punches = $pdo->query("
    SELECT a.date, a.clock_in, a.clock_out, e.employee_code, e.first_name, e.last_name 
    FROM attendance_records a
    JOIN employees e ON a.employee_id = e.id
    ORDER BY COALESCE(a.clock_out, a.clock_in) DESC LIMIT 5
")->fetchAll();

$trendStmt = $pdo->prepare("
    SELECT date,
           COUNT(DISTINCT employee_id) AS present_count,
           SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) AS late_count
    FROM attendance_records
    WHERE date BETWEEN ? AND ?
    GROUP BY date
");
$trendStart = date('Y-m-d', strtotime('-14 days'));
$trendEnd = date('Y-m-d');
$trendStmt->execute([$trendStart, $trendEnd]);
$trendRows = [];
foreach ($trendStmt->fetchAll() as $row) {
    $trendRows[$row['date']] = $row;
}

$chartLabels = [];
$chartPresent = [];
$chartLate = [];
for ($i = 14; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    if (in_array(date('N', strtotime($date)), ['6', '7'], true)) {
        continue;
    }

    $chartLabels[] = date('M j', strtotime($date));
    $chartPresent[] = isset($trendRows[$date]) ? intval($trendRows[$date]['present_count']) : 0;
    $chartLate[] = isset($trendRows[$date]) ? intval($trendRows[$date]['late_count']) : 0;
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard Overview</h1>
        <div class="page-subtitle">Welcome back! Here's what's happening today.</div>
    </div>
    <?php if (in_array($currentUser['role'], ['admin', 'HR', 'manager'])): ?>
        <a href="<?php echo app_url('modules/employees/create.php'); ?>" class="btn btn-primary" style="text-decoration: none;">
            <i class="fas fa-plus"></i> Add Employee
        </a>
    <?php endif; ?>
</div>

<div class="grid-cards">
    <!-- Card 1: Total Employees -->
    <div class="card metric-card">
        <div class="metric-info">
            <h3>Total Employees</h3>
            <div class="metric-value"><?php echo $total_employees; ?></div>
            <div class="metric-trend text-secondary">
                <i class="fas fa-users"></i> Registered Workforce
            </div>
        </div>
        <div class="metric-icon icon-blue">
            <i class="fas fa-users"></i>
        </div>
    </div>
    
    <!-- Card 2: On Leave -->
    <div class="card metric-card">
        <div class="metric-info">
            <h3>On Leave Today</h3>
            <div class="metric-value"><?php echo $on_leave; ?></div>
            <div class="metric-trend text-secondary">
                <i class="fas fa-calendar-times"></i> Approved leaves
            </div>
        </div>
        <div class="metric-icon icon-orange">
            <i class="fas fa-calendar-times"></i>
        </div>
    </div>
    
    <!-- Card 3: Present Today -->
    <div class="card metric-card">
        <div class="metric-info">
            <h3>Present Today</h3>
            <div class="metric-value"><?php echo $present_today; ?></div>
            <div class="metric-trend text-secondary">
                <i class="fas fa-check-circle"></i> Active punches today
            </div>
        </div>
        <div class="metric-icon icon-green">
            <i class="fas fa-user-check"></i>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; align-items: start;">
    <!-- Analytics Chart -->
    <div class="card" style="min-height: 350px;">
        <h3 class="mb-4">30-Day Attendance Trend</h3>
        <div style="position: relative; height: 280px; width: 100%;">
            <canvas id="attendanceChart"></canvas>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <h3 class="mb-4">Recent Activity</h3>
        <div class="table-container" style="max-height: 250px; overflow-y: auto;">
            <table class="table" style="font-size: 0.85rem;">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recent_punches) > 0): ?>
                        <?php foreach ($recent_punches as $punch): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($punch['first_name'] . ' ' . $punch['last_name']); ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo htmlspecialchars($punch['employee_code']); ?></div>
                            </td>
                            <td>
                                <?php if ($punch['clock_out']): ?>
                                    <span class="status-badge status-leave">OUT</span>
                                <?php else: ?>
                                    <span class="status-badge status-active">IN</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('h:i A', strtotime($punch['clock_out'] ?? $punch['clock_in'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 2rem;">No recent activity.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    const labels = <?php echo json_encode($chartLabels); ?>;
    const dataPresent = <?php echo json_encode($chartPresent); ?>;
    const dataLate = <?php echo json_encode($chartLate); ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'On Time',
                    data: dataPresent,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'Late Arrivals',
                    data: dataLate,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: '#94a3b8', font: { family: 'Inter' } }
                }
            },
            scales: {
                x: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94a3b8' }
                },
                y: {
                    grid: { color: 'rgba(255, 255, 255, 0.05)' },
                    ticks: { color: '#94a3b8' },
                    beginAtZero: true
                }
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
