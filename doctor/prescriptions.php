<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id   = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';
$doctor_photo = !empty($_SESSION['user_photo']) ? $_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($doctor_name)."&background=0ea5e9&color=fff";

$result = $conn->query("
    SELECT p.*, u.name AS patient_name, d.name AS doctor_name
    FROM prescriptions p
    LEFT JOIN users u ON p.patient_id = u.id
    LEFT JOIN users d ON p.doctor_id  = d.id
    WHERE p.doctor_id = $doctor_id
    ORDER BY p.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/doctor_prescriptions.css">
</head>
<body>

<?php include '../includes/doctor_sidebar.php'; ?>

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-file-prescription" style="color:var(--accent); margin-right:10px;"></i>Prescriptions</h1>
            <p>Create and manage patient e-prescriptions</p>
        </div>
        <a href="new_prescription.php" class="btn-new">
            <i class="fas fa-plus"></i> New Prescription
        </a>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle" style="font-size:16px;"></i>
            Prescription created successfully!
        </div>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <div class="presc-card">
            <div class="card-top">
                <div>
                    <div class="presc-id">Prescription #<?php echo $row['id']; ?></div>
                    <div class="presc-title"><?php echo htmlspecialchars($row['diagnosis']); ?></div>
                    <div class="presc-meta">
                        <i class="far fa-calendar-alt"></i>
                        <?php echo date('F d, Y', strtotime($row['created_at'])); ?>
                        &nbsp;·&nbsp;
                        Dr. <?php echo htmlspecialchars($row['doctor_name'] ?? 'Unknown'); ?>
                    </div>
                </div>
                <button class="btn-pdf" onclick="window.print()">
                    <i class="fas fa-file-pdf"></i> View PDF
                </button>
            </div>

            <div class="patient-row">
                <i class="fas fa-user"></i>
                <span style="color:var(--text-dim);">Issued to:</span>
                <strong><?php echo htmlspecialchars($row['patient_name'] ?? 'Unknown Patient'); ?></strong>
            </div>

            <div class="diagnosis-pill">
                <i class="fas fa-stethoscope"></i>
                <?php echo htmlspecialchars($row['diagnosis']); ?>
            </div>

            <div class="meds-label">Medications</div>
            <?php
                $p_id = $row['id'];
                $items = $conn->query("SELECT * FROM prescription_items WHERE prescription_id=$p_id");
                if ($items && $items->num_rows > 0):
                    while ($item = $items->fetch_assoc()):
            ?>
            <div class="med-item">
                <div class="med-icon"><i class="fas fa-pills"></i></div>
                <div>
                    <div class="med-name"><?php echo htmlspecialchars($item['medicine_name']); ?></div>
                    <div class="med-info">
                        <?php echo htmlspecialchars($item['dosage']); ?> &nbsp;·&nbsp;
                        Duration: <?php echo htmlspecialchars($item['duration']); ?>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div style="color:var(--text-dim); font-size:13px; padding:10px 0;">No medications listed.</div>
            <?php endif; ?>

            <?php $notes = $row['notes'] ?? $row['doctor_notes'] ?? ''; ?>
            <?php if (!empty($notes)): ?>
            <div class="notes-box">
                <strong><i class="fas fa-sticky-note"></i> Doctor's Notes:</strong>
                <?php echo htmlspecialchars($notes); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-prescription"></i>
            <h3>No prescriptions yet</h3>
            <p>Create your first prescription using the button above.</p>
        </div>
    <?php endif; ?>
</main>

</body>
</html>