<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php"); exit();
}

include_once '../includes/log_activity.php';

$doctor_id = $_SESSION['user_id'];
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$success = ''; $error = '';

// Handle save schedule
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Delete all existing for this doctor and re-insert
    $conn->query("DELETE FROM doctor_schedules WHERE doctor_id = $doctor_id");
    foreach ($days as $day) {
        $key = strtolower($day);
        if (!empty($_POST["start_$key"]) && !empty($_POST["end_$key"])) {
            $start = $conn->real_escape_string($_POST["start_$key"]);
            $end   = $conn->real_escape_string($_POST["end_$key"]);
            $avail = isset($_POST["avail_$key"]) ? 1 : 0;
            $conn->query("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, is_available) VALUES ($doctor_id, '$day', '$start', '$end', $avail)");
        }
    }
    log_activity($conn, $doctor_id, 'doctor', 'Updated availability schedule');
    $success = "Schedule updated successfully!";
}

// Fetch existing schedule
$sched_data = [];
$res = $conn->query("SELECT * FROM doctor_schedules WHERE doctor_id = $doctor_id");
while ($row = $res->fetch_assoc()) $sched_data[$row['day_of_week']] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule – Community Teleconsult</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/doctor_dashboard.css">
    <style>
        .sched-page { max-width: 800px; margin: 0 auto; }
        .sched-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 20px; padding: 28px; margin-bottom: 20px; }
        .sched-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 6px; }
        .sched-desc { color: #94a3b8; font-size: 14px; margin-bottom: 24px; }
        .day-row { display: grid; grid-template-columns: 140px 1fr 1fr 80px; gap: 14px; align-items: center; margin-bottom: 14px; }
        .day-label { font-weight: 600; font-size: 14px; }
        .day-input { padding: 10px 14px; background: rgba(255,255,255,0.05); border: 1px solid #1e293b; border-radius: 8px; color: white; font-family: inherit; width: 100%; box-sizing: border-box; }
        .day-input:focus { outline: none; border-color: #3b82f6; }
        .avail-toggle { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .toggle-inp { width: 40px; height: 22px; appearance: none; background: #1e293b; border-radius: 20px; cursor: pointer; position: relative; transition: background 0.2s; }
        .toggle-inp:checked { background: #3b82f6; }
        .toggle-inp::after { content:''; position: absolute; width: 16px; height: 16px; background: white; border-radius: 50%; top: 3px; left: 3px; transition: left 0.2s; }
        .toggle-inp:checked::after { left: 21px; }
        .btn-save { background: #3b82f6; color: white; border: none; padding: 14px 32px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; margin-top: 10px; }
        .btn-save:hover { background: #2563eb; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; padding: 14px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        @media (max-width: 640px) { .day-row { grid-template-columns: 1fr 1fr; } .day-label { grid-column: 1/-1; } }
    </style>
    <?php include '../includes/speed_insights.php'; ?>
</head>
<body>
<?php include '../includes/doctor_sidebar.php'; ?>
<main class="main">
    <div class="sched-page">
        <div class="sched-card">
            <div class="sched-title"><i class="far fa-clock" style="color:#3b82f6;margin-right:10px;"></i>My Availability Schedule</div>
            <div class="sched-desc">Set your weekly consultation hours. Patients will see your availability when booking appointments.</div>

            <?php if ($success): ?><div class="alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>

            <form method="POST">
                <div style="display:grid;grid-template-columns:140px 1fr 1fr auto;gap:12px;margin-bottom:10px;">
                    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;">Day</div>
                    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;">Start Time</div>
                    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;">End Time</div>
                    <div style="font-size:12px;color:#64748b;font-weight:600;text-transform:uppercase;">Active</div>
                </div>

                <?php foreach ($days as $day): 
                    $key = strtolower($day);
                    $existing = $sched_data[$day] ?? null;
                ?>
                <div class="day-row">
                    <div class="day-label"><?php echo $day; ?></div>
                    <input type="time" name="start_<?php echo $key; ?>" class="day-input" value="<?php echo htmlspecialchars($existing['start_time'] ?? '08:00'); ?>">
                    <input type="time" name="end_<?php echo $key; ?>"   class="day-input" value="<?php echo htmlspecialchars($existing['end_time'] ?? '17:00'); ?>">
                    <div class="avail-toggle">
                        <input type="checkbox" name="avail_<?php echo $key; ?>" class="toggle-inp" id="avail_<?php echo $key; ?>" <?php echo (!$existing || $existing['is_available']) ? 'checked' : ''; ?>>
                    </div>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Schedule</button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
