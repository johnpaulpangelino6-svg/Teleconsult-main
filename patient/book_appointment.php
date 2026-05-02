<?php
session_start();
include '../config.php';

// Check for user_id
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$patient_id   = $_SESSION['user_id'];
$patient_name = $_SESSION['user_name'] ?? 'Patient';
$patient_photo = !empty($_SESSION['user_photo']) ? $_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($patient_name)."&background=3b82f6&color=fff";

// FETCH DOCTORS
$doctors = $conn->query("SELECT id, name, role FROM users WHERE role='doctor' ORDER BY name ASC");

// HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $doctor_id = (int)$_POST['doctor_id'];
    $date = $_POST['date'];
    $reason = $conn->real_escape_string($_POST['reason']);
    
    $sql = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, status, consultation_type)
            VALUES ('$patient_id', '$doctor_id', '$date', 'Pending', '$reason')";

    if ($conn->query($sql)) {
        $success = "Appointment booked successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/book_appointment.css">
</head>
<body>

<?php include '../includes/patient_sidebar.php'; ?>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="page-header">
        <h1>Book an Appointment</h1>
        <p>Select an available doctor and pick a time that works for you.</p>
    </div>

    <form method="POST" id="appointmentForm" class="booking-container">
        
        <!-- DOCTORS LIST -->
        <div>
            <div class="section-title">Select a Doctor</div>
            <?php if($doctors && $doctors->num_rows > 0): ?>
                <?php while($doc = $doctors->fetch_assoc()): ?>
                    <div class="doctor-card" onclick="selectDoctor(<?php echo $doc['id']; ?>, this)">
                        <div class="doc-info-left">
                            <div class="doc-avatar"><i class="fas fa-user-md"></i></div>
                            <div>
                                <div class="doc-name">Dr. <?php echo htmlspecialchars($doc['name']); ?></div>
                                <div class="doc-spec">General Practitioner</div>
                            </div>
                        </div>
                        <span class="status-pill">Available</span>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="padding: 30px; text-align: center; color: var(--text-dim); background: var(--card-bg); border-radius: 12px; border: 1px dashed var(--border);">
                    No doctors available at the moment.
                </div>
            <?php endif; ?>
        </div>

        <!-- FORM PANEL -->
        <div>
            <div class="details-panel">
                <div class="section-title" style="margin-bottom: 20px;">Appointment Details</div>
                
                <?php if(isset($success)): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle" style="margin-right:6px;"></i><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if(isset($error)): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle" style="margin-right:6px;"></i><?php echo $error; ?></div>
                <?php endif; ?>

                <input type="hidden" name="doctor_id" id="selected_doctor_id" required>

                <div class="form-group">
                    <label>Select Date & Time</label>
                    <input type="datetime-local" name="date" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                </div>

                <div class="form-group">
                    <label>Reason for Visit / Consultation Type</label>
                    <textarea name="reason" rows="4" placeholder="Describe your symptoms or reason for consultation..." required></textarea>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn" disabled>
                    <i class="far fa-calendar-check"></i> Confirm Booking
                </button>
            </div>
        </div>

    </form>
</main>

<script>
    function selectDoctor(id, element) {
        document.querySelectorAll('.doctor-card').forEach(card => card.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('selected_doctor_id').value = id;
        
        const btn = document.getElementById('submitBtn');
        btn.disabled = false;
        btn.classList.add('active');
    }
</script>
</body>
</html>