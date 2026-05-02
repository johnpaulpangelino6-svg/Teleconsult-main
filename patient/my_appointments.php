<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$patient_id   = $_SESSION['user_id'];
$patient_name = $_SESSION['user_name'] ?? "Patient";
$patient_photo = !empty($_SESSION['user_photo']) ? $_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($patient_name)."&background=3b82f6&color=fff";

// FETCH APPOINTMENTS WITH DOCTOR NAME + PAYMENT STATUS
$sql = "SELECT a.*, u.name AS doctor_name, u.specialization,
        p.status AS pay_status, p.method AS pay_method, p.id AS pay_id
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN payments p ON p.appointment_id = a.id AND p.patient_id = a.patient_id
        WHERE a.patient_id = '$patient_id'
        ORDER BY a.appointment_date DESC";
$result = $conn->query($sql);

// Counts
$count_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM appointments WHERE patient_id = '$patient_id'";
$counts = $conn->query($count_sql)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/my_appointments.css">
</head>
<body>

<?php include '../includes/patient_sidebar.php'; ?>

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <div>
            <h1>My Appointments</h1>
            <p>Manage your scheduled video consultations</p>
        </div>
        <a href="book_appointment.php" class="btn-book">
            <i class="fas fa-plus"></i> Book New
        </a>
    </div>

    <div class="filter-tabs">
        <button class="tab active">All (<?php echo $counts['total'] ?? 0; ?>)</button>
        <button class="tab">Pending (<?php echo $counts['pending'] ?? 0; ?>)</button>
        <button class="tab">Approved (<?php echo $counts['approved'] ?? 0; ?>)</button>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="cards-grid">
            <?php while($row = $result->fetch_assoc()): 
                $s = $row['status'];
                $b_cls = $s === 'Approved' ? 'badge-approved' : ($s === 'Rejected' ? 'badge-rejected' : ($s === 'Completed' ? 'badge-completed' : 'badge-pending'));
                $pay_status = $row['pay_status'] ?? null;
                $pay_method = $row['pay_method'] ?? null;
            ?>
                <div class="appt-card">
                    <div class="card-top">
                        <div class="doc-info">
                            <div class="doc-icon"><i class="fas fa-video"></i></div>
                            <div>
                                <div class="doc-name">Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></div>
                                <div class="consult-type"><?php echo htmlspecialchars($row['consultation_type'] ?? 'General Checkup'); ?></div>
                            </div>
                        </div>
                        <span class="badge <?php echo $b_cls; ?>"><?php echo htmlspecialchars($s); ?></span>
                    </div>

                    <div class="time-box">
                        <div class="time-item">
                            <i class="far fa-calendar"></i> <?php echo date("M d, Y", strtotime($row['appointment_date'])); ?>
                        </div>
                        <div class="time-item">
                            <i class="far fa-clock"></i> <?php echo date("h:i A", strtotime($row['appointment_date'])); ?>
                        </div>
                    </div>

                    <!-- PAYMENT STATUS SECTION -->
                    <div class="payment-status-row">
                        <?php if (!$pay_status): ?>
                            <span class="pay-badge pay-unpaid"><i class="fas fa-times-circle"></i> Unpaid</span>
                        <?php elseif ($pay_status == 'pending'): ?>
                            <span class="pay-badge pay-pending"><i class="fas fa-clock"></i> Payment Pending Verification</span>
                        <?php elseif ($pay_status == 'verified'): ?>
                            <span class="pay-badge pay-verified"><i class="fas fa-check-circle"></i> Paid via <?php echo strtoupper($pay_method); ?></span>
                        <?php elseif ($pay_status == 'rejected'): ?>
                            <span class="pay-badge pay-rejected"><i class="fas fa-times-circle"></i> Payment Rejected</span>
                        <?php endif; ?>
                    </div>

                    <div class="action-bar">
                        <?php if ($s == 'Pending'): ?>
                            <form method="POST" action="cancel_appointment.php" style="flex:1; display:flex;">
                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn-action btn-cancel" style="width:100%;">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </form>
                        <?php elseif ($s == 'Approved'): ?>
                            <a href="chat.php?contact_id=<?php echo $row['doctor_id']; ?>" class="btn-action btn-join">
                                <i class="fas fa-comment-medical"></i> Consult
                            </a>
                            <?php if (!$pay_status || $pay_status == 'rejected'): ?>
                            <a href="payment.php?appointment_id=<?php echo $row['id']; ?>" class="btn-action btn-pay">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </a>
                            <?php endif; ?>
                        <?php elseif ($s == 'Completed'): ?>
                            <?php if (!$pay_status || $pay_status == 'rejected'): ?>
                            <a href="payment.php?appointment_id=<?php echo $row['id']; ?>" class="btn-action btn-pay" style="flex:1;">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </a>
                            <?php else: ?>
                            <button class="btn-action btn-details" disabled style="opacity:0.5; cursor:not-allowed; flex:1;">Completed</button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn-action btn-details" disabled style="opacity:0.5; cursor:not-allowed;">View Details</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="far fa-calendar-times"></i>
            <h3>No appointments found</h3>
            <p>You haven't booked any consultations yet. Click the button above to book one.</p>
        </div>
    <?php endif; ?>

</main>

</body>
</html>