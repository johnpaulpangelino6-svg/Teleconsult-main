<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

include_once '../includes/notify.php';
include_once '../includes/log_activity.php';

$admin_id = $_SESSION['user_id'];

// Handle status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $aid = (int)$_GET['id'];
    $action = $_GET['action'];
    $valid_statuses = ['Approved','Rejected','Completed','Pending'];
    if (in_array($action, $valid_statuses)) {
        $conn->query("UPDATE appointments SET status='$action' WHERE id=$aid");
        $appt_row = $conn->query("SELECT * FROM appointments WHERE id=$aid")->fetch_assoc();
        if ($appt_row) {
            notify($conn, $appt_row['patient_id'], 'Appointment ' . $action, "Your appointment (#{$aid}) has been {$action} by the administrator.", 'appointment', 'my_appointments.php');
        }
        log_activity($conn, $admin_id, 'admin', "Appointment #$aid status set to $action");
        header("Location: appointments.php?msg=$action"); exit();
    }
}

// Filters
$status_f = $_GET['status'] ?? '';
$search_f = $_GET['search'] ?? '';
$date_f = $_GET['date'] ?? '';

$where = "WHERE 1=1";
if ($status_f) $where .= " AND a.status = '" . $conn->real_escape_string($status_f) . "'";
if ($search_f) { $s = $conn->real_escape_string($search_f); $where .= " AND (p.name LIKE '%$s%' OR d.name LIKE '%$s%')"; }
if ($date_f) $where .= " AND DATE(a.appointment_date) = '" . $conn->real_escape_string($date_f) . "'";

$appointments = $conn->query("
    SELECT a.*, p.name as patient_name, p.photo as patient_photo, d.name as doctor_name, d.specialization
    FROM appointments a
    LEFT JOIN users p ON a.patient_id = p.id
    LEFT JOIN users d ON a.doctor_id = d.id
    $where
    ORDER BY a.appointment_date DESC
");

// Summary
$total    = $conn->query("SELECT COUNT(*) as c FROM appointments")->fetch_assoc()['c'];
$pending  = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];
$completed= $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='Completed'")->fetch_assoc()['c'];
$today    = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE DATE(appointment_date)=CURDATE()")->fetch_assoc()['c'];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <?php include '../includes/speed_insights.php'; ?>
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .s-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; }
        .s-val { font-size: 2rem; font-weight: 700; }
        .s-lbl { color: #94a3b8; font-size: 13px; margin-top: 4px; }
        .filter-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-bar input, .filter-bar select { padding: 10px 14px; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; color: white; font-family: inherit; font-size: 13px; }
        .filter-bar input { flex: 1; min-width: 180px; }
        .filter-bar select option { background: #0f172a; }
        .filter-btn { background: #3b82f6; color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; }
        .table-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 16px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(255,255,255,0.03); padding: 13px 16px; text-align: left; font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #1e293b; }
        td { padding: 13px 16px; border-bottom: 1px solid rgba(30,41,59,0.5); font-size: 13px; }
        tr:hover td { background: rgba(255,255,255,0.01); }
        .avatar-sm { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
        .pat-cell { display: flex; align-items: center; gap: 10px; }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .sp-Pending { background: rgba(234,179,8,0.1); color: #eab308; }
        .sp-Approved { background: rgba(59,130,246,0.1); color: #3b82f6; }
        .sp-Completed { background: rgba(34,197,94,0.1); color: #22c55e; }
        .sp-Rejected { background: rgba(239,68,68,0.1); color: #ef4444; }
        .act-btn { padding: 5px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 11px; font-weight: 600; margin-right: 3px; text-decoration: none; display: inline-block; }
        .btn-approve { background: rgba(34,197,94,0.15); color: #22c55e; }
        .btn-reject { background: rgba(239,68,68,0.15); color: #ef4444; }
        .btn-complete { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; font-weight: 500; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; }
    </style>
</head>
<body>
<?php include '../includes/admin_sidebar.php'; ?>
<main class="main">
    <div class="top-bar">
        <div class="header-title"><h1>Appointments</h1><p>Monitor and manage all consultation appointments</p></div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Appointment status updated to <strong><?php echo htmlspecialchars($msg); ?></strong> and patient notified.</div><?php endif; ?>

    <div class="stats-row">
        <div class="s-card"><div class="s-val"><?php echo $total; ?></div><div class="s-lbl">Total Appointments</div></div>
        <div class="s-card"><div class="s-val" style="color:#eab308;"><?php echo $pending; ?></div><div class="s-lbl">Pending</div></div>
        <div class="s-card"><div class="s-val" style="color:#22c55e;"><?php echo $completed; ?></div><div class="s-lbl">Completed</div></div>
        <div class="s-card"><div class="s-val" style="color:#3b82f6;"><?php echo $today; ?></div><div class="s-lbl">Today</div></div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="🔍 Search patient or doctor..." value="<?php echo htmlspecialchars($search_f); ?>">
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach(['Pending','Approved','Completed','Rejected'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status_f==$s?'selected':''; ?>><?php echo $s; ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date" value="<?php echo htmlspecialchars($date_f); ?>">
        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($search_f || $status_f || $date_f): ?><a href="appointments.php" style="padding:10px 14px;border:1px solid #1e293b;border-radius:8px;color:#94a3b8;text-decoration:none;font-size:12px;">Clear</a><?php endif; ?>
    </form>

    <div class="table-card">
        <table>
            <thead>
                <tr><th>#</th><th>Patient</th><th>Doctor</th><th>Date & Time</th><th>Type</th><th>Status</th><th>Payment</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if ($appointments && $appointments->num_rows > 0): ?>
                    <?php while ($a = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td style="color:#475569;">#<?php echo $a['id']; ?></td>
                        <td>
                            <div class="pat-cell">
                                <?php if (!empty($a['patient_photo'])): ?>
                                    <img src="../uploads/<?php echo htmlspecialchars($a['patient_photo']); ?>" class="avatar-sm">
                                <?php else: ?>
                                    <div style="width:32px;height:32px;border-radius:50%;background:#1e293b;display:flex;align-items:center;justify-content:center;"><i class="fas fa-user" style="font-size:12px;color:#64748b;"></i></div>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($a['patient_name']); ?>
                            </div>
                        </td>
                        <td>
                            <div>Dr. <?php echo htmlspecialchars($a['doctor_name']); ?></div>
                            <div style="font-size:11px;color:#64748b;"><?php echo htmlspecialchars($a['specialization'] ?? ''); ?></div>
                        </td>
                        <td style="font-size:12px;color:#94a3b8;"><?php echo date('M d, Y<\b\r>h:i A', strtotime($a['appointment_date'])); ?></td>
                        <td style="font-size:12px;"><?php echo htmlspecialchars($a['consultation_type']); ?></td>
                        <td><span class="status-pill sp-<?php echo $a['status']; ?>"><?php echo $a['status']; ?></span></td>
                        <td>
                            <?php 
                                $pstyle = ['unpaid'=>'color:#ef4444','pending'=>'color:#eab308','paid'=>'color:#22c55e'];
                                $ps = $a['payment_status'] ?? 'unpaid';
                                echo "<span style='font-size:12px;font-weight:600;" . ($pstyle[$ps] ?? '') . ";text-transform:capitalize;'>$ps</span>";
                            ?>
                        </td>
                        <td>
                            <?php if ($a['status'] == 'Pending'): ?>
                                <a href="appointments.php?action=Approved&id=<?php echo $a['id']; ?>" class="act-btn btn-approve" onclick="return confirm('Approve this appointment?')"><i class="fas fa-check"></i> Approve</a>
                                <a href="appointments.php?action=Rejected&id=<?php echo $a['id']; ?>" class="act-btn btn-reject" onclick="return confirm('Reject?')"><i class="fas fa-times"></i> Reject</a>
                            <?php elseif ($a['status'] == 'Approved'): ?>
                                <a href="appointments.php?action=Completed&id=<?php echo $a['id']; ?>" class="act-btn btn-complete" onclick="return confirm('Mark as Completed?')"><i class="fas fa-check-double"></i> Complete</a>
                            <?php else: ?>
                                <span style="color:#475569;font-size:11px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:#475569;">No appointments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
