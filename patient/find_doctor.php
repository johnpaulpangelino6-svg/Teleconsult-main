<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php"); exit();
}

// Fetch all doctors
$search = $_GET['search'] ?? '';
$spec_filter = $_GET['specialization'] ?? '';

$sql = "SELECT u.id, u.name, u.photo, u.specialization, u.bio, u.email,
        (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id = u.id AND a.status='Completed') as completed_appts,
        CASE WHEN u.last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'Active' ELSE 'Inactive' END as online_status
        FROM users u
        WHERE u.role = 'doctor'";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.specialization LIKE ?)";
    $s = "%$search%";
    $params[] = $s; $params[] = $s; $types .= 'ss';
}
if (!empty($spec_filter)) {
    $sql .= " AND u.specialization = ?";
    $params[] = $spec_filter; $types .= 's';
}
$sql .= " ORDER BY online_status ASC, u.name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$doctors = $stmt->get_result();

// Fetch distinct specializations
$specs = $conn->query("SELECT DISTINCT specialization FROM users WHERE role='doctor' AND specialization IS NOT NULL ORDER BY specialization");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Doctor – Community Teleconsult</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user_dashboard.css">
    <style>
        .find-header { margin-bottom: 28px; }
        .find-header h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 6px; }
        .find-header p { color: #94a3b8; font-size: 14px; }
        .filter-row { display: flex; gap: 14px; margin-bottom: 28px; flex-wrap: wrap; }
        .filter-row input, .filter-row select { padding: 12px 16px; background: #0f172a; border: 1px solid #1e293b; border-radius: 10px; color: white; font-family: inherit; font-size: 14px; }
        .filter-row input { flex: 1; min-width: 200px; }
        .filter-row input:focus, .filter-row select:focus { outline: none; border-color: #3b82f6; }
        .filter-row select option { background: #0f172a; }
        .filter-btn { background: #3b82f6; color: white; border: none; padding: 12px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; }
        .doctors-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .doctor-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 18px; padding: 24px; transition: all 0.2s; }
        .doctor-card:hover { border-color: #3b82f6; transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .doc-avatar { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid #1e293b; margin-bottom: 16px; }
        .doc-avatar-placeholder { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg,#3b82f6,#06b6d4); display: flex; align-items: center; justify-content: center; font-size: 28px; color: white; margin-bottom: 16px; }
        .doc-status { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; margin-bottom: 10px; }
        .status-active { color: #22c55e; } .status-inactive { color: #64748b; }
        .doc-name { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .doc-spec { color: #3b82f6; font-size: 13px; font-weight: 600; margin-bottom: 8px; }
        .doc-bio { color: #94a3b8; font-size: 13px; line-height: 1.5; margin-bottom: 16px; }
        .doc-stats { display: flex; gap: 16px; margin-bottom: 16px; font-size: 12px; color: #64748b; }
        .doc-stats span { display: flex; align-items: center; gap: 4px; }
        .btn-book { width: 100%; background: #3b82f6; color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; display: block; text-align: center; transition: background 0.2s; }
        .btn-book:hover { background: #2563eb; }
        .no-results { text-align: center; padding: 80px 20px; color: #475569; grid-column: 1/-1; }
    </style>
    <?php include '../includes/speed_insights.php'; ?>
</head>
<body>
<?php include '../includes/patient_sidebar.php'; ?>
<main class="main-content">
    <div class="find-header">
        <h1><i class="fas fa-user-md" style="color:#3b82f6;margin-right:12px;"></i>Find a Doctor</h1>
        <p>Browse qualified healthcare professionals available for online consultation</p>
    </div>

    <form method="GET" class="filter-row">
        <input type="text" name="search" placeholder="🔍 Search by name or specialization..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="specialization">
            <option value="">All Specializations</option>
            <?php while ($sp = $specs->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($sp['specialization']); ?>" <?php echo $spec_filter == $sp['specialization'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($sp['specialization']); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($search || $spec_filter): ?>
            <a href="find_doctor.php" style="padding:12px 16px;border:1px solid #1e293b;border-radius:10px;color:#94a3b8;text-decoration:none;font-size:13px;">Clear</a>
        <?php endif; ?>
    </form>

    <div class="doctors-grid">
        <?php if ($doctors->num_rows == 0): ?>
            <div class="no-results">
                <i class="fas fa-user-md" style="font-size:3rem;display:block;margin-bottom:16px;color:#1e293b;"></i>
                <h3>No doctors found</h3>
                <p>Try a different search or check back later.</p>
            </div>
        <?php else: ?>
            <?php while ($doc = $doctors->fetch_assoc()): ?>
            <div class="doctor-card">
                <?php if (!empty($doc['photo'])): ?>
                    <img src="../uploads/<?php echo htmlspecialchars($doc['photo']); ?>" class="doc-avatar" alt="Doctor">
                <?php else: ?>
                    <div class="doc-avatar-placeholder"><i class="fas fa-user-md"></i></div>
                <?php endif; ?>

                <div class="doc-status <?php echo $doc['online_status']=='Active' ? 'status-active' : 'status-inactive'; ?>">
                    <span style="width:7px;height:7px;border-radius:50%;background:currentColor;display:inline-block;"></span>
                    <?php echo $doc['online_status']; ?>
                </div>

                <div class="doc-name">Dr. <?php echo htmlspecialchars($doc['name']); ?></div>
                <div class="doc-spec"><?php echo htmlspecialchars($doc['specialization'] ?? 'General Practice'); ?></div>
                
                <?php if (!empty($doc['bio'])): ?>
                    <div class="doc-bio"><?php echo htmlspecialchars(substr($doc['bio'], 0, 100)) . (strlen($doc['bio']) > 100 ? '...' : ''); ?></div>
                <?php else: ?>
                    <div class="doc-bio">Qualified healthcare professional available for online consultations.</div>
                <?php endif; ?>

                <div class="doc-stats">
                    <span><i class="fas fa-check-circle" style="color:#22c55e;"></i> <?php echo $doc['completed_appts']; ?> Consultations</span>
                </div>

                <a href="book_appointment.php?doctor_id=<?php echo $doc['id']; ?>" class="btn-book">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
