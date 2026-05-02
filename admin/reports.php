<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$admin_name = $_SESSION['user_name'] ?? 'Administrator';

// Filters
$report_type = $_GET['report_type'] ?? 'appointments';
$date_filter = $_GET['date_filter'] ?? 'monthly';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Date logic
$date_cond = "1=1";
$date_field = ($report_type == 'appointments') ? 'a.appointment_date' : 'a.created_at';
if ($report_type == 'users') $date_field = 'u.created_at';

if ($date_filter == 'daily') {
    $date_cond = "DATE($date_field) = CURDATE()";
} elseif ($date_filter == 'weekly') {
    $date_cond = "YEARWEEK($date_field, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($date_filter == 'monthly') {
    $date_cond = "MONTH($date_field) = MONTH(CURDATE()) AND YEAR($date_field) = YEAR(CURDATE())";
} elseif ($date_filter == 'yearly') {
    $date_cond = "YEAR($date_field) = YEAR(CURDATE())";
} elseif ($date_filter == 'custom' && $start_date && $end_date) {
    $date_cond = "DATE($date_field) BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'";
}

// Search Logic
$search_cond = "1=1";
if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    if ($report_type == 'appointments' || $report_type == 'prescriptions') {
        $search_cond = "(p.name LIKE '%$s%' OR d.name LIKE '%$s%')";
    } else {
        $search_cond = "(u.name LIKE '%$s%' OR u.email LIKE '%$s%')";
    }
}

// Status logic
$status_cond = "1=1";
if (!empty($status_filter) && $report_type == 'appointments') {
    $status_cond = "a.status = '" . $conn->real_escape_string($status_filter) . "'";
}

// Fetch Data Based on Report Type
$data = [];
$summary = [];

if ($report_type == 'appointments') {
    $sql = "SELECT a.*, p.name as patient_name, d.name as doctor_name 
            FROM appointments a 
            LEFT JOIN users p ON a.patient_id = p.id 
            LEFT JOIN users d ON a.doctor_id = d.id 
            WHERE $date_cond AND $search_cond AND $status_cond
            ORDER BY a.appointment_date DESC";
    $res = $conn->query($sql);
    $summary['Total'] = $res->num_rows;
    $summary['Completed'] = 0;
    $summary['Pending'] = 0;
    $summary['Cancelled/Rejected'] = 0;
    
    if($res) {
        while($row = $res->fetch_assoc()) {
            $data[] = $row;
            if($row['status'] == 'Completed') $summary['Completed']++;
            elseif($row['status'] == 'Pending') $summary['Pending']++;
            else $summary['Cancelled/Rejected']++;
        }
    }
} elseif ($report_type == 'prescriptions') {
    $sql = "SELECT a.*, p.name as patient_name, d.name as doctor_name 
            FROM prescriptions a 
            LEFT JOIN users p ON a.patient_id = p.id 
            LEFT JOIN users d ON a.doctor_id = d.id 
            WHERE $date_cond AND $search_cond
            ORDER BY a.created_at DESC";
    $res = $conn->query($sql);
    $summary['Total Prescriptions'] = $res ? $res->num_rows : 0;
    if($res) while($row = $res->fetch_assoc()) $data[] = $row;
} elseif ($report_type == 'users') {
    $sql = "SELECT u.* FROM users u WHERE $date_cond AND $search_cond ORDER BY u.created_at DESC";
    $res = $conn->query($sql);
    $summary['Total Users'] = $res ? $res->num_rows : 0;
    $summary['Active Now'] = 0;
    
    $threshold = strtotime('-2 minutes');
    if($res) {
        while($row = $res->fetch_assoc()) {
            $last_seen = $row['last_seen'] ? strtotime($row['last_seen']) : 0;
            if ($last_seen >= $threshold) $summary['Active Now']++;
            $data[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Reports - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    
    <!-- html2pdf for downloading PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .filters-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-group label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
        }
        .filter-group select, .filter-group input {
            padding: 10px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            color: white;
            border-radius: 8px;
            outline: none;
        }
        .filter-group select option { background: var(--bg-main); color: white; }
        
        .actions-row {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 16px;
        }
        .btn-apply { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-outline { background: transparent; color: white; border: 1px solid var(--border); padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;}
        .btn-outline:hover { background: rgba(255,255,255,0.05); }
        .btn-pdf { background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;}
        
        /* REPORT PAPER DESIGN */
        .report-preview-container {
            background: white;
            color: black;
            padding: 40px;
            border-radius: 8px;
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            font-family: Arial, sans-serif;
            position: relative;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 24px;
        }
        .rh-left { display: flex; align-items: center; gap: 16px; }
        .rh-logo { width: 64px; height: 64px; object-fit: contain; }
        .rh-title { font-size: 24px; font-weight: bold; color: #1e293b; margin-bottom: 4px; }
        .rh-subtitle { font-size: 13px; color: #64748b; }
        .rh-right { text-align: right; font-size: 13px; color: #475569; }
        .rh-right strong { color: #0f172a; }
        
        .report-title-bar {
            text-align: center;
            margin-bottom: 24px;
        }
        .report-title-bar h2 { font-size: 20px; text-transform: uppercase; letter-spacing: 1px; color: #0f172a; }
        
        .summary-boxes {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        .s-box {
            flex: 1;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        .s-box-val { font-size: 24px; font-weight: bold; color: #2563eb; }
        .s-box-label { font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600; margin-top: 4px; }
        
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        table.report-table th { background: #f1f5f9; color: #334155; padding: 12px; text-align: left; border-bottom: 2px solid #cbd5e1; }
        table.report-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; color: #0f172a; }
        table.report-table tr:nth-child(even) td { background: #f8fafc; }
        
        .report-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #94a3b8;
        }

        /* Print Specific Overrides */
        @media print {
            body * { visibility: hidden; }
            .report-preview-container, .report-preview-container * { visibility: visible; }
            .report-preview-container { position: absolute; left: 0; top: 0; width: 100%; margin: 0; box-shadow: none; padding: 0; }
            @page { margin: 1cm; }
        }
    </style>
</head>
<body>
<?php include '../includes/admin_sidebar.php'; ?>
<main class="main">
    <div class="page-header">
        <h1>System Reports</h1>
        <p>Generate professional PDF reports and analytics</p>
    </div>

    <!-- FILTERS -->
    <form method="GET" action="" class="filters-card">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Report Type</label>
                <select name="report_type">
                    <option value="appointments" <?php echo $report_type=='appointments'?'selected':'';?>>Appointments & Consultations</option>
                    <option value="prescriptions" <?php echo $report_type=='prescriptions'?'selected':'';?>>Prescriptions Issued</option>
                    <option value="users" <?php echo $report_type=='users'?'selected':'';?>>System Users (Doctors & Patients)</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Date Filter</label>
                <select name="date_filter" id="date_filter" onchange="toggleCustomDate()">
                    <option value="daily" <?php echo $date_filter=='daily'?'selected':'';?>>Today (Daily)</option>
                    <option value="weekly" <?php echo $date_filter=='weekly'?'selected':'';?>>This Week</option>
                    <option value="monthly" <?php echo $date_filter=='monthly'?'selected':'';?>>This Month</option>
                    <option value="yearly" <?php echo $date_filter=='yearly'?'selected':'';?>>This Year</option>
                    <option value="custom" <?php echo $date_filter=='custom'?'selected':'';?>>Custom Date Range</option>
                    <option value="all" <?php echo $date_filter=='all'?'selected':'';?>>All Time</option>
                </select>
            </div>
            <div class="filter-group" id="custom_start" style="display: <?php echo $date_filter=='custom'?'flex':'none';?>;">
                <label>Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="filter-group" id="custom_end" style="display: <?php echo $date_filter=='custom'?'flex':'none';?>;">
                <label>End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status_filter=='Pending'?'selected':'';?>>Pending</option>
                    <option value="Approved" <?php echo $status_filter=='Approved'?'selected':'';?>>Approved</option>
                    <option value="Completed" <?php echo $status_filter=='Completed'?'selected':'';?>>Completed</option>
                    <option value="Rejected" <?php echo $status_filter=='Rejected'?'selected':'';?>>Rejected</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Search Keyword</label>
                <input type="text" name="search" placeholder="Name, Doctor, Email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </div>
        <div class="actions-row">
            <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Generate Report</button>
            <button type="button" class="btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            <button type="button" class="btn-pdf" onclick="downloadPDF()"><i class="fas fa-file-pdf"></i> Download PDF</button>
        </div>
    </form>

    <!-- REPORT PREVIEW -->
    <div style="overflow-x: auto;">
        <div class="report-preview-container" id="printable-report">
            
            <div class="report-header">
                <div class="rh-left">
                    <?php 
                        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                        $root_path = dirname($_SERVER['PHP_SELF']) == '/admin' ? '/Teleconsult-main' : '';
                        $logo_url = $base_url . $root_path . "/uploads/1777630001_logo%20system.png";
                    ?>
                    <!-- Use absolute URL for html2pdf compatibility -->
                    <img src="<?php echo $logo_url; ?>" class="rh-logo" alt="Logo" crossorigin="anonymous">
                    <div>
                        <div class="rh-title">Community Teleconsult</div>
                        <div class="rh-subtitle">Healthcare for All • Official System Report</div>
                    </div>
                </div>
                <div class="rh-right">
                    <div>Generated: <strong><?php echo date('M d, Y h:i A'); ?></strong></div>
                    <div>Prepared By: <strong><?php echo htmlspecialchars($admin_name); ?></strong></div>
                    <div>Filter: <strong><?php echo ucfirst($date_filter); ?></strong></div>
                </div>
            </div>

            <div class="report-title-bar">
                <h2><?php echo strtoupper($report_type); ?> REPORT</h2>
            </div>

            <!-- SUMMARY BOXES -->
            <?php if(!empty($summary)): ?>
            <div class="summary-boxes">
                <?php foreach($summary as $label => $val): ?>
                <div class="s-box">
                    <div class="s-box-val"><?php echo $val; ?></div>
                    <div class="s-box-label"><?php echo htmlspecialchars($label); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- DATA TABLE -->
            <table class="report-table">
                <thead>
                    <?php if ($report_type == 'appointments'): ?>
                        <tr>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    <?php elseif ($report_type == 'prescriptions'): ?>
                        <tr>
                            <th>Date Issued</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Diagnosis</th>
                        </tr>
                    <?php elseif ($report_type == 'users'): ?>
                        <tr>
                            <th>Joined</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Current Status</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if(empty($data)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 30px;">No records found for the selected filters.</td></tr>
                    <?php else: ?>
                        <?php foreach($data as $row): ?>
                            <?php if ($report_type == 'appointments'): ?>
                                <tr>
                                    <td><?php echo date('M d, Y h:i A', strtotime($row['appointment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                    <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['consultation_type']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['status']); ?></strong></td>
                                </tr>
                            <?php elseif ($report_type == 'prescriptions'): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                    <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['diagnosis']); ?></td>
                                </tr>
                            <?php elseif ($report_type == 'users'): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td style="text-transform: capitalize;"><?php echo htmlspecialchars($row['role']); ?></td>
                                    <td>
                                        <?php 
                                            $threshold = strtotime('-2 minutes');
                                            $last_seen = $row['last_seen'] ? strtotime($row['last_seen']) : 0;
                                            echo ($last_seen >= $threshold) ? '<span style="color:#10b981;">Active</span>' : '<span style="color:#94a3b8;">Inactive</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="report-footer">
                <div>© <?php echo date('Y'); ?> Community Teleconsult. All rights reserved.</div>
                <div>System Generated Report</div>
            </div>
        </div>
    </div>
</main>

<script>
    function toggleCustomDate() {
        const filter = document.getElementById('date_filter').value;
        const customStart = document.getElementById('custom_start');
        const customEnd = document.getElementById('custom_end');
        if (filter === 'custom') {
            customStart.style.display = 'flex';
            customEnd.style.display = 'flex';
        } else {
            customStart.style.display = 'none';
            customEnd.style.display = 'none';
        }
    }

    function downloadPDF() {
        const element = document.getElementById('printable-report');
        const opt = {
            margin:       0.5,
            filename:     '<?php echo ucfirst($report_type); ?>_Report_<?php echo date("Ymd"); ?>.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        
        // Generate PDF
        html2pdf().set(opt).from(element).save();
    }
</script>

</body>
</html>
