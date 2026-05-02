<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id   = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';

// ── AUTO-CREATE REQUIRED TABLES IF THEY DON'T EXIST ──────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS doctor_settings (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id     INT NOT NULL UNIQUE,
        max_per_day   INT NOT NULL DEFAULT 10,
        slot_interval INT NOT NULL DEFAULT 30,
        updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
    )
");
$conn->query("
    CREATE TABLE IF NOT EXISTS blocked_dates (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id    INT NOT NULL,
        blocked_date DATE NOT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_block (doctor_id, blocked_date)
    )
");

// ── HANDLE SETTINGS SAVE ──────────────────────────────────────────
$msg_type = '';
$msg_text = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_settings'])) {
        $max_per_day    = max(1, (int)($_POST['max_per_day'] ?? 10));
        $slot_interval  = (int)($_POST['slot_interval'] ?? 30);

        // Upsert doctor_settings
        $check = $conn->prepare("SELECT id FROM doctor_settings WHERE doctor_id = ?");
        $check->bind_param("i", $doctor_id);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $st = $conn->prepare("UPDATE doctor_settings SET max_per_day=?, slot_interval=? WHERE doctor_id=?");
            $st->bind_param("iii", $max_per_day, $slot_interval, $doctor_id);
        } else {
            $st = $conn->prepare("INSERT INTO doctor_settings (doctor_id, max_per_day, slot_interval) VALUES (?,?,?)");
            $st->bind_param("iii", $doctor_id, $max_per_day, $slot_interval);
        }
        $st->execute();
        $st->close();
        $msg_type = 'success';
        $msg_text = 'Settings saved successfully.';
    }

    if (isset($_POST['toggle_block']) && !empty($_POST['block_date'])) {
        $block_date = $_POST['block_date'];

        // Check if already blocked
        $chk = $conn->prepare("SELECT id FROM blocked_dates WHERE doctor_id=? AND blocked_date=?");
        $chk->bind_param("is", $doctor_id, $block_date);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            // Unblock it
            $del = $conn->prepare("DELETE FROM blocked_dates WHERE doctor_id=? AND blocked_date=?");
            $del->bind_param("is", $doctor_id, $block_date);
            $del->execute();
            $del->close();
            $msg_type = 'success';
            $msg_text = "Date $block_date has been unblocked.";
        } else {
            // Block it
            $ins = $conn->prepare("INSERT INTO blocked_dates (doctor_id, blocked_date) VALUES (?,?)");
            $ins->bind_param("is", $doctor_id, $block_date);
            $ins->execute();
            $ins->close();
            $msg_type = 'success';
            $msg_text = "Date $block_date has been blocked.";
        }
        $chk->close();
    }

    // Redirect to avoid re-POST on refresh
    header("Location: manage_calendar.php?msg=" . urlencode($msg_text) . "&type=" . $msg_type);
    exit();
}

// Pickup flash messages
if (isset($_GET['msg'])) {
    $msg_text = htmlspecialchars($_GET['msg']);
    $msg_type = htmlspecialchars($_GET['type'] ?? 'success');
}

// ── MONTH NAVIGATION ──────────────────────────────────────────────
$month = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
$year  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$prevM = $month - 1; $prevY = $year;
if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year;
if ($nextM > 12) { $nextM = 1; $nextY++; }

$firstDay  = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startDow  = (int)date('w', $firstDay);   // 0=Sun
$monthName = date('F', $firstDay);
$today     = date('Y-m-d');

// ── FETCH ALL APPOINTMENTS FOR THIS MONTH ────────────────────────
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$appts_raw = [];
$ast = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status, u.name AS patient_name, a.consultation_type
    FROM appointments a
    JOIN users u ON a.patient_id = u.id
    WHERE a.doctor_id = ?
      AND DATE(a.appointment_date) BETWEEN ? AND ?
    ORDER BY a.appointment_date ASC
");
$ast->bind_param("iss", $doctor_id, $monthStart, $monthEnd);
$ast->execute();
$res = $ast->get_result();
while ($row = $res->fetch_assoc()) {
    $d = date('Y-m-d', strtotime($row['appointment_date']));
    $appts_raw[$d][] = $row;
}
$ast->close();

// ── FETCH BLOCKED DATES ───────────────────────────────────────────
$blocked_dates_list = [];
$bst = $conn->prepare("SELECT blocked_date FROM blocked_dates WHERE doctor_id=? AND blocked_date BETWEEN ? AND ?");
$bst->bind_param("iss", $doctor_id, $monthStart, $monthEnd);
$bst->execute();
$br = $bst->get_result();
while ($brow = $br->fetch_assoc()) {
    $blocked_dates_list[] = $brow['blocked_date'];
}
$bst->close();

// ── FETCH SETTINGS ────────────────────────────────────────────────
$settings = ['max_per_day' => 10, 'slot_interval' => 30];
$sst = $conn->prepare("SELECT max_per_day, slot_interval FROM doctor_settings WHERE doctor_id=?");
$sst->bind_param("i", $doctor_id);
$sst->execute();
$sr = $sst->get_result();
if ($sr->num_rows > 0) { $settings = $sr->fetch_assoc(); }
$sst->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Calendar - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/manage_calendar.css">
</head>
<body>

<?php include '../includes/doctor_sidebar.php'; ?>

<!-- MAIN -->
<main class="main">
    <div class="page-header">
        <div>
            <h1><i class="far fa-calendar-alt" style="color:var(--accent); margin-right:10px;"></i>Manage Calendar</h1>
            <p>View patient appointments and configure your availability</p>
        </div>
    </div>

    <?php if ($msg_text): ?>
    <div class="alert alert-<?php echo $msg_type; ?>">
        <i class="fas fa-<?php echo $msg_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $msg_text; ?>
    </div>
    <?php endif; ?>

    <div class="cal-layout">

        <!-- ── CALENDAR ── -->
        <div class="cal-card">
            <div class="cal-header">
                <h2><?php echo "$monthName $year"; ?></h2>
                <div class="cal-nav">
                    <button onclick="location.href='?m=<?php echo $prevM; ?>&y=<?php echo $prevY; ?>'">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button onclick="location.href='?m=<?php echo $nextM; ?>&y=<?php echo $nextY; ?>'">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="cal-dow">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                    <span><?php echo $d; ?></span>
                <?php endforeach; ?>
            </div>

            <div class="cal-grid">
                <?php
                // Leading blanks from previous month
                $prevMonthDays = (int)date('t', mktime(0,0,0,$prevM,1,$prevY));
                for ($i = 0; $i < $startDow; $i++) {
                    $d = $prevMonthDays - $startDow + $i + 1;
                    echo "<div class='cal-day other-month'><div class='day-num'>$d</div></div>";
                }

                // Current month days
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateStr   = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday   = ($dateStr === $today);
                    $isBlocked = in_array($dateStr, $blocked_dates_list);
                    $dayAppts  = $appts_raw[$dateStr] ?? [];
                    $cls = '';
                    if ($isToday)   $cls .= ' today';
                    if ($isBlocked) $cls .= ' blocked';
                    ?>
                    <div class="cal-day<?php echo $cls; ?>" onclick="selectDate('<?php echo $dateStr; ?>')" id="day-<?php echo $dateStr; ?>">
                        <div class="day-num">
                            <?php echo $day; ?>
                            <?php if ($isBlocked): ?><i class="fas fa-ban" style="color:#ef4444; font-size:10px; margin-left:4px;"></i><?php endif; ?>
                        </div>
                        <?php
                        // Show up to 2 appointments, then +N more
                        $shown = 0;
                        foreach ($dayAppts as $ap) {
                            if ($shown >= 2) break;
                            $pClass = 'status-' . strtolower($ap['status']);
                            $time   = date('h:iA', strtotime($ap['appointment_date']));
                            echo "<div class='appt-pill $pClass'><i class='fas fa-user-circle'></i> {$time} &nbsp;{$ap['patient_name']}</div>";
                            $shown++;
                        }
                        $remaining = count($dayAppts) - $shown;
                        if ($remaining > 0):
                        ?>
                            <div style="font-size:11px; color:var(--text-dim); padding:2px 0;">+<?php echo $remaining; ?> more</div>
                        <?php endif; ?>
                    </div>
                    <?php
                }

                // Trailing blanks
                $totalCells = $startDow + $daysInMonth;
                $trailingDays = (7 - ($totalCells % 7)) % 7;
                for ($i = 1; $i <= $trailingDays; $i++) {
                    echo "<div class='cal-day other-month'><div class='day-num'>$i</div></div>";
                }
                ?>
            </div>
        </div>

        <!-- ── RIGHT PANEL ── -->
        <div class="right-panel">

            <!-- Selected Date / Block Tool -->
            <div class="panel-card">
                <div class="panel-card-head"><i class="fas fa-tools"></i> Availability Tool</div>
                <div class="panel-card-body">
                    <div class="selected-date-display" id="selectedDateDisplay">
                        <i class="far fa-calendar"></i>
                        <span id="selectedDateText">No date selected — click a day</span>
                    </div>
                    <form method="POST" id="blockForm">
                        <input type="hidden" name="toggle_block" value="1">
                        <input type="hidden" name="block_date" id="blockDateInput" value="">
                        <input type="hidden" name="m" value="<?php echo $month; ?>">
                        <input type="hidden" name="y" value="<?php echo $year; ?>">
                        <button type="submit" class="btn-block" id="blockBtn" disabled>
                            <i class="fas fa-ban"></i> Block Selected Date
                        </button>
                    </form>
                    <div class="availability-hint">
                        Select a date on the calendar to block it for holidays or unavailability.
                        Blocked dates will prevent new bookings.
                        Click again to unblock.
                    </div>
                </div>
            </div>

            <!-- Slot Configuration -->
            <div class="panel-card">
                <div class="panel-card-head"><i class="fas fa-sliders-h"></i> Slot Configuration</div>
                <div class="panel-card-body">
                    <form method="POST">
                        <input type="hidden" name="save_settings" value="1">
                        <div class="form-group">
                            <label>Max Appointments per Day</label>
                            <input type="number" name="max_per_day" min="1" max="50"
                                   value="<?php echo (int)$settings['max_per_day']; ?>">
                        </div>
                        <div class="form-group">
                            <label>Consultation Slot Interval</label>
                            <select name="slot_interval">
                                <?php foreach ([15,20,30,45,60,90,120] as $min): ?>
                                    <option value="<?php echo $min; ?>" <?php echo $settings['slot_interval'] == $min ? 'selected' : ''; ?>>
                                        <?php echo $min >= 60 ? ($min/60).' Hour'.($min>60?'s':'') : "$min Minutes"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Quick stats for this month -->
            <div class="panel-card">
                <div class="panel-card-head"><i class="far fa-chart-bar"></i> This Month</div>
                <div class="panel-card-body">
                    <?php
                    $totalAppts    = array_sum(array_map('count', $appts_raw));
                    $approvedCount = 0; $pendingCount = 0;
                    foreach ($appts_raw as $dayArr) {
                        foreach ($dayArr as $a) {
                            if ($a['status'] === 'Approved') $approvedCount++;
                            if ($a['status'] === 'Pending')  $pendingCount++;
                        }
                    }
                    ?>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div style="background:rgba(59,130,246,0.08); border:1px solid rgba(59,130,246,0.2); border-radius:10px; padding:14px; text-align:center;">
                            <div style="font-size:26px; font-weight:700; color:var(--accent);"><?php echo $totalAppts; ?></div>
                            <div style="font-size:11px; color:var(--text-dim); margin-top:4px;">Total</div>
                        </div>
                        <div style="background:rgba(34,197,94,0.08); border:1px solid rgba(34,197,94,0.2); border-radius:10px; padding:14px; text-align:center;">
                            <div style="font-size:26px; font-weight:700; color:#22c55e;"><?php echo $approvedCount; ?></div>
                            <div style="font-size:11px; color:var(--text-dim); margin-top:4px;">Approved</div>
                        </div>
                        <div style="background:rgba(234,179,8,0.08); border:1px solid rgba(234,179,8,0.2); border-radius:10px; padding:14px; text-align:center;">
                            <div style="font-size:26px; font-weight:700; color:#eab308;"><?php echo $pendingCount; ?></div>
                            <div style="font-size:11px; color:var(--text-dim); margin-top:4px;">Pending</div>
                        </div>
                        <div style="background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); border-radius:10px; padding:14px; text-align:center;">
                            <div style="font-size:26px; font-weight:700; color:#ef4444;"><?php echo count($blocked_dates_list); ?></div>
                            <div style="font-size:11px; color:var(--text-dim); margin-top:4px;">Blocked</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /.cal-layout -->
</main>

<!-- ── APPOINTMENT DETAIL MODAL ── -->
<div class="modal-overlay" id="apptModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalDateLabel">Appointments</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<script>
// Embedded appointment data per day
const apptData = <?php
    $jsData = [];
    foreach ($appts_raw as $date => $arr) {
        foreach ($arr as $a) {
            $jsData[$date][] = [
                'id'           => $a['id'],
                'patient'      => htmlspecialchars($a['patient_name'], ENT_QUOTES),
                'time'         => date('h:i A', strtotime($a['appointment_date'])),
                'type'         => htmlspecialchars($a['consultation_type'] ?? 'General', ENT_QUOTES),
                'status'       => $a['status'],
            ];
        }
    }
    echo json_encode($jsData);
?>;

const blockedDates = <?php echo json_encode($blocked_dates_list); ?>;

let selectedDate = null;

function selectDate(dateStr) {
    // Deselect old
    document.querySelectorAll('.cal-day.selected').forEach(el => el.classList.remove('selected'));
    const cell = document.getElementById('day-' + dateStr);
    if (cell) cell.classList.add('selected');

    selectedDate = dateStr;

    // Update right panel
    const label = new Date(dateStr + 'T00:00:00');
    const opts  = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('selectedDateText').textContent = label.toLocaleDateString('en-US', opts);

    // Update block button label
    const btn = document.getElementById('blockBtn');
    btn.disabled = false;
    document.getElementById('blockDateInput').value = dateStr;
    if (blockedDates.includes(dateStr)) {
        btn.innerHTML = '<i class="fas fa-unlock"></i> Unblock This Date';
        btn.classList.add('blocked-active');
    } else {
        btn.innerHTML = '<i class="fas fa-ban"></i> Block This Date';
        btn.classList.remove('blocked-active');
    }

    // Show modal if there are appointments
    if (apptData[dateStr] && apptData[dateStr].length > 0) {
        openModal(dateStr);
    }
}

function openModal(dateStr) {
    const label = new Date(dateStr + 'T00:00:00');
    const opts  = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
    document.getElementById('modalDateLabel').textContent =
        label.toLocaleDateString('en-US', opts);

    const appts = apptData[dateStr] || [];
    let html = '';
    if (appts.length === 0) {
        html = "<div class='modal-empty'><i class='far fa-calendar-times'></i>No appointments on this day.</div>";
    } else {
        appts.forEach(a => {
            const statusClass = 'badge-' + a.status.toLowerCase();
            html += `
            <div class="modal-appt-item">
                <div class="appt-avatar"><i class="fas fa-user" style="color:white;font-size:15px;"></i></div>
                <div class="appt-info">
                    <div class="appt-patient">${a.patient}</div>
                    <div class="appt-meta"><i class="far fa-clock" style="color:var(--accent);margin-right:4px;"></i>${a.time} &nbsp;·&nbsp; ${a.type}</div>
                </div>
                <span class="badge ${statusClass}">${a.status}</span>
            </div>`;
        });
    }
    document.getElementById('modalBody').innerHTML = html;
    document.getElementById('apptModal').classList.add('open');
}

function closeModal() {
    document.getElementById('apptModal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('apptModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Keyboard ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>
