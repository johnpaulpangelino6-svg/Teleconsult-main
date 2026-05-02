<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php"); exit();
}

include_once '../includes/notify.php';
include_once '../includes/log_activity.php';

$doctor_id = $_SESSION['user_id'];
$success = ''; $error = '';

// Add new follow-up
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_followup'])) {
    $patient_id = (int)$_POST['patient_id'];
    $due_date = $conn->real_escape_string($_POST['due_date']);
    $note = $conn->real_escape_string(trim($_POST['note']));
    $appt_id = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 'NULL';

    $sql = "INSERT INTO followups (appointment_id, patient_id, doctor_id, due_date, note) VALUES (" . ($appt_id === 'NULL' ? 'NULL' : $appt_id) . ", $patient_id, $doctor_id, '$due_date', '$note')";
    if ($conn->query($sql)) {
        $notif_msg = "Dr. {$_SESSION['user_name']} has scheduled a follow-up for you on " . date('M d, Y', strtotime($due_date)) . ".";
        if (!empty($note)) {
            $notif_msg .= "\nDoctor's Note: " . $note;
        }
        notify($conn, $patient_id, 'Follow-up Reminder', $notif_msg, 'followup', '../patient/my_appointments.php');
        log_activity($conn, $doctor_id, 'doctor', 'Added follow-up for patient ID ' . $patient_id);
        $success = "Follow-up added and patient notified!";
    } else {
        $error = "Failed to add follow-up.";
    }
}

// Mark as done
if (isset($_GET['done'])) {
    $fid = (int)$_GET['done'];
    $conn->query("UPDATE followups SET is_done = 1 WHERE id = $fid AND doctor_id = $doctor_id");
    header("Location: followups.php"); exit();
}

// Fetch all follow-ups for this doctor
$followups = $conn->query("
    SELECT f.*, p.name as patient_name, p.photo as patient_photo
    FROM followups f
    LEFT JOIN users p ON f.patient_id = p.id
    WHERE f.doctor_id = $doctor_id
    ORDER BY f.is_done ASC, f.due_date ASC
");

// Fetch patients list for add form
$patients = $conn->query("
    SELECT DISTINCT u.id, u.name FROM users u
    WHERE u.role = 'patient'
    AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = u.id AND a.doctor_id = $doctor_id)
    ORDER BY u.name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Follow-up Reminders – Community Teleconsult</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/doctor_dashboard.css">
    <style>
        .fu-layout { display: grid; grid-template-columns: 1fr 350px; gap: 24px; }
        .fu-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 18px; padding: 24px; }
        .fu-card h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; }
        .fu-item { display: flex; gap: 16px; align-items: flex-start; padding: 16px; background: rgba(255,255,255,0.02); border: 1px solid #1e293b; border-radius: 12px; margin-bottom: 12px; }
        .fu-item.done { opacity: 0.45; }
        .fu-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg,#3b82f6,#06b6d4); display: flex; align-items: center; justify-content: center; font-size: 18px; color: white; flex-shrink: 0; overflow: hidden; }
        .fu-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .fu-body { flex: 1; }
        .fu-patient { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .fu-due { font-size: 13px; margin-bottom: 4px; }
        .fu-due.overdue { color: #ef4444; font-weight: 600; }
        .fu-due.today { color: #eab308; font-weight: 600; }
        .fu-due.upcoming { color: #22c55e; }
        .fu-note { color: #94a3b8; font-size: 13px; }
        .btn-done { background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: none; white-space: nowrap; }
        .done-badge { background: rgba(100,116,139,0.1); color: #64748b; border: 1px solid rgba(100,116,139,0.2); padding: 4px 10px; border-radius: 6px; font-size: 11px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; background: rgba(255,255,255,0.05); border: 1px solid #1e293b; border-radius: 8px; color: white; font-family: inherit; box-sizing: border-box; }
        .form-group select option { background: #0f172a; }
        .btn-add { width: 100%; background: #3b82f6; color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #f87171; }
        @media (max-width: 900px) { .fu-layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php include '../includes/doctor_sidebar.php'; ?>
<main class="main">
    <div style="margin-bottom:24px;">
        <h1 style="font-size:1.6rem;font-weight:700;"><i class="fas fa-bell" style="color:#3b82f6;margin-right:12px;"></i>Follow-up Reminders</h1>
        <p style="color:#94a3b8;font-size:14px;">Track and manage patient follow-up schedules</p>
    </div>

    <div class="fu-layout">
        <!-- FOLLOW-UPS LIST -->
        <div class="fu-card">
            <h3>All Follow-ups</h3>
            <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
            
            <?php if ($followups->num_rows == 0): ?>
                <div style="text-align:center;padding:40px;color:#475569;">
                    <i class="fas fa-bell-slash" style="font-size:2.5rem;display:block;margin-bottom:12px;"></i>
                    No follow-ups scheduled yet.
                </div>
            <?php else: ?>
                <?php while ($fu = $followups->fetch_assoc()): 
                    $due = strtotime($fu['due_date']);
                    $today = strtotime('today');
                    $due_class = ($fu['is_done']) ? '' : ($due < $today ? 'overdue' : ($due == $today ? 'today' : 'upcoming'));
                    $due_label = ($fu['is_done']) ? '✓ Done' : ($due < $today ? '⚠ Overdue: ' : ($due == $today ? '⏰ Due Today: ' : '📅 '));
                ?>
                <div class="fu-item <?php echo $fu['is_done'] ? 'done' : ''; ?>">
                    <div class="fu-avatar">
                        <?php if (!empty($fu['patient_photo'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($fu['patient_photo']); ?>">
                        <?php else: ?><i class="fas fa-user"></i><?php endif; ?>
                    </div>
                    <div class="fu-body">
                        <div class="fu-patient"><?php echo htmlspecialchars($fu['patient_name']); ?></div>
                        <div class="fu-due <?php echo $due_class; ?>"><?php echo $due_label . date('M d, Y', $due); ?></div>
                        <?php if (!empty($fu['note'])): ?>
                            <div class="fu-note"><?php echo htmlspecialchars($fu['note']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$fu['is_done']): ?>
                        <a href="followups.php?done=<?php echo $fu['id']; ?>" class="btn-done"><i class="fas fa-check"></i> Done</a>
                    <?php else: ?>
                        <span class="done-badge">Completed</span>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- ADD FOLLOW-UP FORM -->
        <div>
            <div class="fu-card">
                <h3><i class="fas fa-plus" style="color:#3b82f6;margin-right:8px;"></i>Add Follow-up</h3>
                <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Patient</label>
                        <select name="patient_id" required>
                            <option value="">Select Patient...</option>
                            <?php 
                            if ($patients) {
                                while ($p = $patients->fetch_assoc()) {
                                    echo "<option value='{$p['id']}'>" . htmlspecialchars($p['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Follow-up Date</label>
                        <input type="date" name="due_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Notes / Instructions</label>
                        <textarea name="note" rows="3" placeholder="e.g. Check blood pressure, bring lab results..."></textarea>
                    </div>
                    <button type="submit" name="add_followup" class="btn-add"><i class="fas fa-bell"></i> Schedule Follow-up</button>
                </form>
            </div>
        </div>
    </div>
</main>
</body>
</html>
