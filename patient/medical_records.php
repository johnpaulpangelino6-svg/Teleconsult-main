<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$role       = $_SESSION['role'];
$user_name  = $_SESSION['user_name'] ?? 'User';
$user_photo = !empty($_SESSION['user_photo'])
    ? $_SESSION['user_photo']
    : "https://ui-avatars.com/api/?name=" . urlencode($user_name) . "&background=random&color=fff";

// Determine target patient ID
$target_patient_id = $user_id; // Default to self
if ($role === 'doctor' && isset($_GET['patient_id'])) {
    $target_patient_id = (int)$_GET['patient_id'];
}

// Fetch target user info if viewing someone else
$target_name = $user_name;
$target_photo = $user_photo;
if ($target_patient_id !== $user_id) {
    $t_stmt = $conn->prepare("SELECT name, photo FROM users WHERE id = ?");
    $t_stmt->bind_param("i", $target_patient_id);
    $t_stmt->execute();
    $t_res = $t_stmt->get_result()->fetch_assoc();
    if ($t_res) {
        $target_name = $t_res['name'];
        $target_photo = !empty($t_res['photo']) 
            ? "../uploads/" . $t_res['photo'] 
            : "https://ui-avatars.com/api/?name=" . urlencode($target_name) . "&background=random&color=fff";
    }
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'prescriptions';

// 1. Fetch Consultation History (from medical_records table)
$history_stmt = $conn->prepare("
    SELECT mr.*, u.name AS doctor_name
    FROM medical_records mr
    LEFT JOIN users u ON mr.doctor_id = u.id
    WHERE mr.patient_id = ?
    ORDER BY mr.created_at DESC
");
$history_stmt->bind_param("i", $target_patient_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

// 2. Fetch Prescriptions
$presc_stmt = $conn->prepare("
    SELECT p.*, u.name AS doctor_name
    FROM prescriptions p
    LEFT JOIN users u ON p.doctor_id = u.id
    WHERE p.patient_id = ?
    ORDER BY p.created_at DESC
");
$presc_stmt->bind_param("i", $target_patient_id);
$presc_stmt->execute();
$presc_result = $presc_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - Community Teleconsult</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/medical_records.css">
</head>
<body>

<?php 
if ($_SESSION['role'] === 'doctor') {
    include '../includes/doctor_sidebar.php';
} else {
    include '../includes/patient_sidebar.php';
}
?>

<!-- ═══════════════ MAIN ═══════════════ -->
<div class="main">
    <div class="page-header">
        <div>
            <h1>Medical Records: <?php echo htmlspecialchars($target_name); ?></h1>
            <p><?php echo ($target_patient_id === $user_id) ? "Your personal clinical history and prescriptions" : "Clinical history and prescriptions for " . htmlspecialchars($target_name); ?></p>
        </div>
        <div class="top-user">
            <img src="<?php echo htmlspecialchars($target_photo); ?>" alt="avatar">
            <div>
                <div class="tname"><?php echo htmlspecialchars($target_name); ?></div>
                <div class="trole">Patient</div>
            </div>
        </div>
    </div>

    <!-- TABS -->
    <div class="tabs">
        <a href="?tab=prescriptions" class="tab <?php echo $active_tab == 'prescriptions' ? 'active' : ''; ?>">
            <i class="fas fa-pills"></i> Prescriptions
        </a>
        <a href="?tab=history" class="tab <?php echo $active_tab == 'history' ? 'active' : ''; ?>">
            <i class="far fa-clipboard"></i> Consultation History
        </a>
    </div>

    <!-- ── PRESCRIPTIONS TAB ── -->
    <?php if($active_tab == 'prescriptions'): ?>

        <?php if($presc_result->num_rows > 0): ?>
            <?php while($row = $presc_result->fetch_assoc()): ?>
            <div class="record-card">
                <div class="rcard-header">
                    <div>
                        <div class="rcard-title"><?php echo htmlspecialchars($row['diagnosis']); ?></div>
                        <div class="rcard-meta">
                            <span><i class="far fa-calendar-alt"></i><?php echo date('F d, Y', strtotime($row['created_at'])); ?></span>
                            <span><i class="fas fa-user-md"></i>Dr. <?php echo htmlspecialchars($row['doctor_name'] ?? 'Unknown'); ?></span>
                        </div>
                    </div>
                    <span class="badge"><i class="fas fa-prescription-bottle-alt"></i> Prescription</span>
                </div>

                <span class="section-label">Prescribed Medications</span>
                <div class="med-list">
                    <?php
                    $p_id = $row['id'];
                    $items_stmt = $conn->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
                    $items_stmt->bind_param("i", $p_id);
                    $items_stmt->execute();
                    $items_result = $items_stmt->get_result();

                    if ($items_result->num_rows > 0):
                        while($item = $items_result->fetch_assoc()):
                    ?>
                    <div class="med-item">
                        <span class="med-name"><i class="fas fa-capsules" style="color:var(--blue); margin-right:8px;"></i><?php echo htmlspecialchars($item['medicine_name']); ?></span>
                        <span class="med-details"><?php echo htmlspecialchars($item['dosage']); ?> &nbsp;|&nbsp; <?php echo htmlspecialchars($item['duration']); ?></span>
                    </div>
                    <?php
                        endwhile;
                    else:
                    ?>
                    <div style="color:var(--dim); font-size:13px; padding:8px;">No medications recorded.</div>
                    <?php endif; ?>
                </div>

                <?php 
                $notes = $row['doctor_notes'] ?? $row['notes'] ?? '';
                if(!empty($notes)): ?>
                    <span class="section-label">Doctor's Notes</span>
                    <div class="content-box warn-box"><?php echo nl2br(htmlspecialchars($notes)); ?></div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="empty">
                <i class="fas fa-pills"></i>
                <p>No prescriptions found for your account.</p>
            </div>
        <?php endif; ?>

    <!-- ── CONSULTATION HISTORY TAB ── -->
    <?php else: ?>

        <?php if($history_result->num_rows > 0): ?>
            <?php while($row = $history_result->fetch_assoc()): ?>
            <div class="record-card">
                <div class="rcard-header">
                    <div>
                        <div class="rcard-title"><?php echo htmlspecialchars($row['diagnosis'] ?? 'Consultation'); ?></div>
                        <div class="rcard-meta">
                            <span><i class="far fa-calendar"></i><?php echo date('F d, Y', strtotime($row['created_at'])); ?></span>
                            <span><i class="fas fa-user-md"></i>Dr. <?php echo htmlspecialchars($row['doctor_name'] ?? 'Unknown'); ?></span>
                        </div>
                    </div>
                    <span class="badge" style="background:rgba(16,185,129,.15); color:var(--green);"><i class="far fa-clipboard"></i> Consultation</span>
                </div>

                <?php if(!empty($row['treatment'])): ?>
                    <span class="section-label">Clinical Treatment</span>
                    <div class="content-box"><?php echo nl2br(htmlspecialchars($row['treatment'])); ?></div>
                <?php endif; ?>

                <?php if(!empty($row['notes'])): ?>
                    <span class="section-label">Observations / Notes</span>
                    <div class="content-box warn-box"><?php echo nl2br(htmlspecialchars($row['notes'])); ?></div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>

        <?php else: ?>
            <div class="empty">
                <i class="fas fa-folder-open"></i>
                <p>No consultation history found for your account.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>