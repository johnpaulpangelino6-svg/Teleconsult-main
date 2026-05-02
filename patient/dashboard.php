<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$patient_id   = $_SESSION['user_id'];
$patient_name = $_SESSION['user_name'] ?? 'Patient';
$patient_photo = !empty($_SESSION['user_photo']) ? $_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($patient_name)."&background=3b82f6&color=fff";

// STATS
$totalAppointments = $conn->query("SELECT COUNT(*) AS t FROM appointments WHERE patient_id='$patient_id'")->fetch_assoc()['t'] ?? 0;
$prescriptions     = $conn->query("SELECT COUNT(*) AS t FROM prescriptions WHERE patient_id='$patient_id'")->fetch_assoc()['t'] ?? 0;
$medicalRecords    = $conn->query("SELECT COUNT(*) AS t FROM medical_records WHERE patient_id='$patient_id'")->fetch_assoc()['t'] ?? 0;
$activeChats       = $conn->query("
    SELECT COUNT(DISTINCT CASE WHEN sender_id='$patient_id' THEN receiver_id ELSE sender_id END) AS t
    FROM messages WHERE sender_id='$patient_id' OR receiver_id='$patient_id'
")->fetch_assoc()['t'] ?? 0;

// UPCOMING APPOINTMENTS
$upcoming = $conn->query("
    SELECT a.*, u.name AS doctor_name
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id='$patient_id'
    ORDER BY a.appointment_date ASC LIMIT 3
");

// AVAILABLE DOCTORS
$doctors = $conn->query("SELECT id, name, photo FROM users WHERE role='doctor' ORDER BY name ASC LIMIT 6");
$colors = ['#06b6d4','#f43f5e','#3b82f6','#a855f7','#10b981','#f59e0b'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Community Teleconsult: Online Medical Consultation System for Local Communities</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user_dashboard.css">
</head>
<body>

<?php include '../includes/patient_sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="page-header">
        <div>
            <h1>Welcome back, <?php echo htmlspecialchars($patient_name); ?>! 👋</h1>
            <p>Here's your health overview for today</p>
        </div>
    </div>

    <!-- STATS -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,0.12); color:#3b82f6;">
                <i class="far fa-calendar-check"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $totalAppointments; ?></div>
                <div class="stat-label">Appointments</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,0.12); color:#22c55e;">
                <i class="far fa-comment-dots"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $activeChats; ?></div>
                <div class="stat-label">Active Chats</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(168,85,247,0.12); color:#a855f7;">
                <i class="far fa-file-alt"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $medicalRecords; ?></div>
                <div class="stat-label">Medical Records</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,0.12); color:#f59e0b;">
                <i class="fas fa-pills"></i>
            </div>
            <div>
                <div class="stat-val"><?php echo $prescriptions; ?></div>
                <div class="stat-label">Prescriptions</div>
            </div>
        </div>
    </div>

    <!-- GRID -->
    <div class="grid-2">
        <!-- UPCOMING APPOINTMENTS -->
        <div class="panel">
            <div class="panel-title">
                <span><i class="far fa-calendar-alt" style="color:var(--accent); margin-right:8px;"></i>Upcoming Appointments</span>
                <a href="my_appointments.php">View All →</a>
            </div>

            <?php if ($upcoming && $upcoming->num_rows > 0): ?>
                <?php while ($app = $upcoming->fetch_assoc()):
                    $s = $app['status'];
                    $badge = $s === 'Approved' ? 'badge-approved' : ($s === 'Rejected' ? 'badge-rejected' : 'badge-pending');
                ?>
                <div class="appt-item">
                    <div class="appt-left">
                        <div class="doc-avatar"><i class="fas fa-user-md" style="color:white; font-size:15px;"></i></div>
                        <div>
                            <div class="appt-name">Dr. <?php echo htmlspecialchars($app['doctor_name']); ?></div>
                            <div class="appt-time">
                                <i class="far fa-clock"></i>
                                <?php echo date('M d, Y • h:i A', strtotime($app['appointment_date'])); ?>
                            </div>
                        </div>
                    </div>
                    <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($s); ?></span>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="far fa-calendar-times"></i>
                    No upcoming appointments.
                </div>
            <?php endif; ?>

            <button class="btn-book" onclick="location.href='book_appointment.php'">
                <i class="far fa-calendar-plus"></i> Book New Appointment
            </button>
        </div>

        <!-- AVAILABLE DOCTORS -->
        <div class="panel">
            <div class="panel-title">
                <span><i class="fas fa-user-md" style="color:var(--accent-cyan); margin-right:8px;"></i>Available Doctors</span>
            </div>

            <?php if ($doctors && $doctors->num_rows > 0):
                $ci = 0;
                while ($doc = $doctors->fetch_assoc()):
                    $bg = $colors[$ci % count($colors)]; $ci++;
            ?>
            <div class="doc-item">
                <div class="doc-left">
                    <div class="doc-av" style="background:<?php echo $bg; ?>20; color:<?php echo $bg; ?>;">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div>
                        <div class="doc-name">Dr. <?php echo htmlspecialchars($doc['name']); ?></div>
                        <div class="doc-role">Medical Professional</div>
                    </div>
                </div>
                <div class="doc-right">
                    <div class="online-pill"><div class="online-dot"></div> Available</div>
                    <a href="chat.php?contact_id=<?php echo $doc['id']; ?>" class="btn-chat">
                        <i class="far fa-comment-dots"></i> Chat
                    </a>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div class="no-data">
                    <i class="fas fa-user-md"></i>
                    No doctors available at the moment.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>