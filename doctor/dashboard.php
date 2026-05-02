<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id   = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';
$doctor_photo = !empty($_SESSION['user_photo']) ? $_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($doctor_name)."&background=0ea5e9&color=fff";

// UPDATE STATUS (before any query)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id     = (int)$_GET['id'];
    $action = $_GET['action'];
    if ($action === 'approve') {
        $conn->query("UPDATE appointments SET status='Approved' WHERE id=$id AND doctor_id=$doctor_id");
    } elseif ($action === 'reject') {
        $conn->query("UPDATE appointments SET status='Rejected' WHERE id=$id AND doctor_id=$doctor_id");
    }
    header("Location: dashboard.php");
    exit();
}

// STATS
$totalPatients      = $conn->query("SELECT COUNT(DISTINCT patient_id) AS t FROM appointments WHERE doctor_id=$doctor_id")->fetch_assoc()['t'] ?? 0;
$totalAppointments  = $conn->query("SELECT COUNT(*) AS t FROM appointments WHERE doctor_id=$doctor_id")->fetch_assoc()['t'] ?? 0;
$pendingCount       = $conn->query("SELECT COUNT(*) AS t FROM appointments WHERE doctor_id=$doctor_id AND status='Pending'")->fetch_assoc()['t'] ?? 0;
$approvedCount      = $conn->query("SELECT COUNT(*) AS t FROM appointments WHERE doctor_id=$doctor_id AND status='Approved'")->fetch_assoc()['t'] ?? 0;

// APPOINTMENTS
$appointments = $conn->query("
    SELECT a.*, u.name AS patient_name
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = $doctor_id
    ORDER BY a.appointment_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/doctor_dashboard.css">
</head>
<body>

<?php include '../includes/doctor_sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p>Welcome back, Dr. <?php echo htmlspecialchars($doctor_name); ?>!</p>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,0.12); color:#3b82f6;">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $totalPatients; ?></div>
                <div class="stat-label">Total Patients</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(6,182,212,0.12); color:#06b6d4;">
                <i class="far fa-calendar-check"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $totalAppointments; ?></div>
                <div class="stat-label">Appointments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,0.12); color:#eab308;">
                <i class="far fa-clock"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $pendingCount; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,0.12); color:#22c55e;">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $approvedCount; ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
    </div>

    <!-- APPOINTMENTS TABLE -->
    <div class="panel">
        <div class="panel-head">
            <h2><i class="far fa-calendar-alt" style="color:var(--accent); margin-right:8px;"></i>Appointments</h2>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Date & Time</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($appointments && $appointments->num_rows > 0): ?>
                    <?php while ($row = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="patient-cell">
                                <div class="p-avatar"><i class="fas fa-user"></i></div>
                                <div class="p-name"><?php echo htmlspecialchars($row['patient_name']); ?></div>
                            </div>
                        </td>
                        <td><?php echo date('M d, Y • h:i A', strtotime($row['appointment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['consultation_type'] ?? 'N/A'); ?></td>
                        <td>
                            <?php
                                $s = $row['status'];
                                $cls = $s === 'Approved' ? 'badge-approved' : ($s === 'Rejected' ? 'badge-rejected' : 'badge-pending');
                            ?>
                            <span class="badge <?php echo $cls; ?>">
                                <?php echo htmlspecialchars($s); ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <a href="?action=approve&id=<?php echo $row['id']; ?>" class="btn btn-approve">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="?action=reject&id=<?php echo $row['id']; ?>" class="btn btn-reject">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                <?php endif; ?>
                                <a href="chat.php?contact_id=<?php echo $row['patient_id']; ?>" class="btn btn-chat">
                                    <i class="far fa-comment-dots"></i> Chat
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="empty-row">
                        <td colspan="5">
                            <i class="far fa-calendar-times" style="font-size:28px; display:block; margin-bottom:10px; color:#334155;"></i>
                            No appointments yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

</body>
</html>