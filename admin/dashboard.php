<?php
session_start();
include '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// 1. FETCH TOTAL PATIENTS
$totalPatients = $conn->query("SELECT COUNT(*) AS t FROM users WHERE role='patient'")->fetch_assoc()['t'] ?? 0;

// 2. FETCH ACTIVE DOCTORS
$activeDoctors = $conn->query("SELECT COUNT(*) AS t FROM users WHERE role='doctor'")->fetch_assoc()['t'] ?? 0;

// 3. FETCH TOTAL APPOINTMENTS
$totalAppts = $conn->query("SELECT COUNT(*) AS t FROM appointments")->fetch_assoc()['t'] ?? 0;

// 4. FETCH TOTAL REVENUE (Assuming some simple calculation, or placeholder if table not present)
// Let's assume a fixed fee per appointment for demonstration or fetch from a 'payments' table if it exists.
$revenue = $totalAppts * 500; // Placeholder calculation: 500 PHP per appointment

// 5. APPOINTMENT STATUSES FOR PIE CHART
$statusCounts = $conn->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
$statuses = ['Completed' => 0, 'Pending' => 0, 'Cancelled' => 0, 'Rejected' => 0];
while($row = $statusCounts->fetch_assoc()) {
    $statuses[$row['status']] = (int)$row['count'];
}

// Combine Cancelled and Rejected for simplicity in the pie chart if needed
$statusData = [
    'Completed' => $statuses['Completed'] ?? 0,
    'Pending' => $statuses['Pending'] ?? 0,
    'Cancelled' => ($statuses['Cancelled'] ?? 0) + ($statuses['Rejected'] ?? 0)
];

// 6. REVENUE TREND (MONTHLY)
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$revenueTrend = [32000, 35000, 38000, 45678, 0, 0]; // Static for current demo as we don't have historical payment data
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>

<main class="main">
    <div class="top-bar">
        <div class="header-title">
            <h1>Admin Dashboard</h1>
            <p>System overview and analytics</p>
        </div>
        <div style="display:flex; gap:1.5rem; align-items:center;">
            <i class="far fa-bell" style="font-size:1.25rem; color:var(--text-muted);"></i>
            <i class="far fa-moon" style="font-size:1.25rem; color:var(--text-muted);"></i>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <img src="https://ui-avatars.com/api/?name=Admin&background=020617&color=fff" style="width:32px; height:32px; border-radius:50%;">
                <div style="font-size:0.875rem;">
                    <div style="font-weight:600;">Admin User</div>
                    <div style="font-size:0.75rem; color:var(--text-muted);">System Admin</div>
                </div>
            </div>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Patients</div>
            <div class="stat-value"><?php echo number_format($totalPatients); ?></div>
            <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> 12.5% <span>from last month</span></div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Active Doctors</div>
            <div class="stat-value"><?php echo number_format($activeDoctors); ?></div>
            <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> 8.3% <span>from last month</span></div>
            <div class="stat-icon" style="color:var(--success); background:rgba(34,197,94,0.1);"><i class="fas fa-user-md"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Appointments</div>
            <div class="stat-value"><?php echo number_format($totalAppts); ?></div>
            <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> 15.7% <span>from last month</span></div>
            <div class="stat-icon" style="color:var(--warning); background:rgba(234,179,8,0.1);"><i class="far fa-calendar-check"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Revenue</div>
            <div class="stat-value">₱<?php echo number_format($revenue); ?></div>
            <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> 22.4% <span>from last month</span></div>
            <div class="stat-icon" style="color:#f59e0b; background:rgba(245,158,11,0.1);"><i class="fas fa-dollar-sign"></i></div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-line"></i> Revenue Trend</h3>
                <i class="fas fa-ellipsis-v" style="color:var(--text-muted);"></i>
            </div>
            <canvas id="revenueChart" height="200"></canvas>
        </div>
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-pie"></i> Appointment Status</h3>
                <i class="fas fa-ellipsis-v" style="color:var(--text-muted);"></i>
            </div>
            <canvas id="statusChart" height="200"></canvas>
        </div>
    </div>

    <!-- BOTTOM STATS -->
    <div class="bottom-stats">
        <div class="mini-card">
            <div>
                <div class="mini-label">Avg. Consultation Time</div>
                <div class="mini-value">24 mins</div>
            </div>
            <div class="mini-icon"><i class="far fa-clock"></i></div>
        </div>
        <div class="mini-card">
            <div>
                <div class="mini-label">Patient Satisfaction</div>
                <div class="mini-value">4.8/5.0</div>
            </div>
            <div class="mini-icon" style="color:var(--success);"><i class="far fa-smile"></i></div>
        </div>
        <div class="mini-card">
            <div>
                <div class="mini-label">System Uptime</div>
                <div class="mini-value">99.9%</div>
            </div>
            <div class="mini-icon" style="color:var(--accent);"><i class="fas fa-signal"></i></div>
        </div>
    </div>
</main>

<script>
    // Revenue Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?php echo json_encode($revenueTrend); ?>,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });

    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Completed', 'Pending', 'Cancelled'],
            datasets: [{
                data: [
                    <?php echo $statusData['Completed']; ?>, 
                    <?php echo $statusData['Pending']; ?>, 
                    <?php echo $statusData['Cancelled']; ?>
                ],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { 
                    position: 'bottom',
                    labels: { color: '#94a3b8', padding: 20, usePointStyle: true }
                }
            },
            cutout: '70%'
        }
    });
</script>

</body>
</html>
