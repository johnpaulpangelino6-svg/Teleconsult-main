<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id = $_SESSION['user_id'];
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Fetch all patients
$query = "
    SELECT 
        u.id, 
        u.name, 
        u.age, 
        u.gender, 
        u.photo,
        (SELECT MAX(created_at) FROM medical_records WHERE patient_id = u.id) as last_medical_record,
        (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = u.id AND status = 'Approved') as last_appointment
    FROM users u
    WHERE u.role = 'patient'
";

if (!empty($search)) {
    $search_safe = $conn->real_escape_string($search);
    $query .= " AND u.name LIKE '%$search_safe%'";
}

$query .= " ORDER BY u.name ASC";
$result = $conn->query($query);

function getLastVisit($medical, $appointment) {
    if (!$medical && !$appointment) return 'Never';
    
    $med_time = $medical ? strtotime($medical) : 0;
    $app_time = $appointment ? strtotime($appointment) : 0;
    
    $max_time = max($med_time, $app_time);
    return date('Y-m-d', $max_time);
}

function getAvatar($name, $photo) {
    if ($photo) {
        return "../uploads/" . htmlspecialchars($photo);
    }
    return "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patients - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/doctor_dashboard.css"> <!-- Reusing sidebar and core layout -->
    <link rel="stylesheet" href="../assets/css/manage_patients.css">
</head>
<body>

<?php include '../includes/doctor_sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>Patients</h1>
        <p>Manage your patient records</p>
    </div>

    <div class="search-container">
        <form method="GET" action="">
            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search patients by name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
        </form>
    </div>

    <div class="patients-grid">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="patient-card">
                    <div class="card-header">
                        <div class="avatar">
                            <img src="<?php echo getAvatar($row['name'], $row['photo']); ?>" alt="avatar">
                        </div>
                        <div class="info">
                            <h2><?php echo htmlspecialchars($row['name']); ?></h2>
                            <p>
                                <?php echo $row['age'] ? $row['age'] . ' years' : 'Age N/A'; ?> 
                                • 
                                <?php echo htmlspecialchars($row['gender'] ?? 'Gender N/A'); ?>
                            </p>
                            <span class="badge">Active</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="visit">
                            <i class="far fa-calendar"></i>
                            <span>Last visit: <?php echo getLastVisit($row['last_medical_record'], $row['last_appointment']); ?></span>
                        </div>
                    </div>

                    <div class="card-footer">
                        <a href="../patient/medical_records.php?patient_id=<?php echo $row['id']; ?>" class="btn btn-records">
                            <i class="far fa-file-alt"></i> Records
                        </a>
                        <a href="chat.php?contact_id=<?php echo $row['id']; ?>" class="btn btn-chat">
                            <i class="far fa-comment-dots"></i> Chat
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted);">
                <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>No patients found.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    // Simple live search (optional enhancement)
    const searchInput = document.querySelector('input[name="search"]');
    let timeout = null;

    searchInput.addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            this.form.submit();
        }, 500);
    });
</script>

</body>
</html>
