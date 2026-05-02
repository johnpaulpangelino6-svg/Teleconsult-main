<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

// Fetch appointment details
$appt = null;
if ($appointment_id > 0) {
    $stmt = $conn->prepare("SELECT a.*, d.name as doctor_name, d.specialization FROM appointments a LEFT JOIN users d ON a.doctor_id = d.id WHERE a.id = ? AND a.patient_id = ?");
    $stmt->bind_param("ii", $appointment_id, $user_id);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();
}

// Check if payment already exists
$existing_payment = null;
if ($appt) {
    $ps = $conn->prepare("SELECT * FROM payments WHERE appointment_id = ? AND patient_id = ?");
    $ps->bind_param("ii", $appointment_id, $user_id);
    $ps->execute();
    $existing_payment = $ps->get_result()->fetch_assoc();
}

$success = ''; $error = '';

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $appt && !$existing_payment) {
    $method = $_POST['method'] ?? 'cash';
    $amount = (float)($_POST['amount'] ?? 500);
    $gcash_ref = trim($_POST['gcash_ref'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $proof_path = null;

    // Handle proof photo upload
    if (!empty($_FILES['proof']['name'])) {
        $ext = pathinfo($_FILES['proof']['name'], PATHINFO_EXTENSION);
        $filename = 'payment_' . time() . '_' . $user_id . '.' . $ext;
        $dest = '../uploads/' . $filename;
        if (move_uploaded_file($_FILES['proof']['tmp_name'], $dest)) {
            $proof_path = $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO payments (appointment_id, patient_id, amount, method, gcash_ref, notes, proof_photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidsss", $appointment_id, $user_id, $amount, $method, $gcash_ref, $notes, $proof_path);
    if ($stmt->execute()) {
        // Update appointment payment status
        $conn->query("UPDATE appointments SET payment_status = 'pending' WHERE id = $appointment_id");
        
        // Notify admin
        include_once '../includes/notify.php';
        $admin = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
        if ($admin) notify($conn, $admin['id'], 'New Payment Submitted', "Patient submitted a $method payment for Appointment #$appointment_id.", 'payment', '../admin/payments.php');

        $success = "Payment submitted successfully! An administrator will verify it shortly.";
        $existing_payment = $conn->query("SELECT * FROM payments WHERE appointment_id = $appointment_id AND patient_id = $user_id")->fetch_assoc();
    } else {
        $error = "Failed to submit payment. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment – Community Teleconsult</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user_dashboard.css">
    <style>
        .payment-page { max-width: 700px; margin: 0 auto; }
        .pay-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 20px; padding: 32px; margin-bottom: 24px; }
        .pay-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 6px; }
        .pay-subtitle { color: #94a3b8; font-size: 14px; margin-bottom: 28px; }
        .appt-info { background: rgba(59,130,246,0.06); border: 1px solid rgba(59,130,246,0.2); border-radius: 12px; padding: 16px 20px; margin-bottom: 28px; font-size: 14px; }
        .appt-info strong { color: white; }
        .method-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
        .method-option { border: 2px solid #1e293b; border-radius: 12px; padding: 16px; text-align: center; cursor: pointer; transition: all 0.2s; }
        .method-option:hover { border-color: #3b82f6; }
        .method-option.selected { border-color: #3b82f6; background: rgba(59,130,246,0.1); }
        .method-option input { display: none; }
        .method-icon { font-size: 28px; margin-bottom: 8px; }
        .method-label { font-size: 13px; font-weight: 600; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 14px; background: rgba(255,255,255,0.05); border: 1px solid #1e293b; border-radius: 10px; color: white; font-family: inherit; box-sizing: border-box; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: #3b82f6; }
        .btn-pay { width: 100%; background: #3b82f6; color: white; border: none; padding: 14px; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: background 0.2s; }
        .btn-pay:hover { background: #2563eb; }
        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600; }
        .status-pending { background: rgba(234,179,8,0.1); color: #eab308; border: 1px solid rgba(234,179,8,0.3); }
        .status-verified { background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
        .status-rejected { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; }
        .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #f87171; }
        #gcash-fields { display: none; }
    </style>
</head>
<body>
<?php include '../includes/patient_sidebar.php'; ?>
<main class="main-content">
    <div class="payment-page">
        <div class="pay-card">
            <div class="pay-title"><i class="fas fa-credit-card" style="color:#3b82f6;margin-right:10px;"></i>Consultation Payment</div>
            <div class="pay-subtitle">Pay for your appointment to confirm your consultation.</div>

            <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>

            <?php if (!$appt): ?>
                <p style="color:#94a3b8;">Invalid appointment. <a href="my_appointments.php" style="color:#3b82f6;">View your appointments →</a></p>
            <?php elseif ($existing_payment): ?>
                <div class="appt-info">
                    <div>Appointment with <strong>Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></strong></div>
                    <div>Date: <strong><?php echo date('M d, Y h:i A', strtotime($appt['appointment_date'])); ?></strong></div>
                </div>
                <p style="margin-bottom:16px;color:#94a3b8;">Your payment has been submitted.</p>
                <div>Status: 
                    <span class="status-badge status-<?php echo $existing_payment['status']; ?>">
                        <i class="fas fa-<?php echo $existing_payment['status']=='verified'?'check-circle':($existing_payment['status']=='rejected'?'times-circle':'clock'); ?>"></i>
                        <?php echo ucfirst($existing_payment['status']); ?>
                    </span>
                </div>
                <p style="margin-top:16px;font-size:13px;color:#64748b;">Amount: <strong style="color:white;">₱<?php echo number_format($existing_payment['amount'],2); ?></strong> via <?php echo strtoupper($existing_payment['method']); ?></p>
            <?php else: ?>
                <div class="appt-info">
                    <div>Appointment with <strong>Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?></strong></div>
                    <div>Specialization: <strong><?php echo htmlspecialchars($appt['specialization'] ?? 'General Practice'); ?></strong></div>
                    <div>Date: <strong><?php echo date('M d, Y h:i A', strtotime($appt['appointment_date'])); ?></strong></div>
                    <div>Consultation Type: <strong><?php echo htmlspecialchars($appt['consultation_type']); ?></strong></div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Select Payment Method</label>
                        <div class="method-grid">
                            <label class="method-option" onclick="selectMethod(this,'gcash')">
                                <input type="radio" name="method" value="gcash">
                                <div class="method-icon" style="color:#0070ba;">💙</div>
                                <div class="method-label">GCash</div>
                            </label>
                            <label class="method-option" onclick="selectMethod(this,'cash')">
                                <input type="radio" name="method" value="cash" checked>
                                <div class="method-icon">💵</div>
                                <div class="method-label">Cash</div>
                            </label>
                            <label class="method-option selected" onclick="selectMethod(this,'barangay_cash')">
                                <input type="radio" name="method" value="barangay_cash">
                                <div class="method-icon">🏘️</div>
                                <div class="method-label">Barangay Cash</div>
                            </label>
                        </div>
                    </div>

                    <div id="gcash-fields">
                        <div class="form-group">
                            <label>GCash Reference Number</label>
                            <input type="text" name="gcash_ref" placeholder="e.g. 1234567890">
                        </div>
                        <div class="form-group">
                            <label>Upload Payment Screenshot (optional)</label>
                            <input type="file" name="proof" accept="image/*">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Consultation Fee (₱)</label>
                        <input type="number" name="amount" value="500" min="0" step="0.01">
                    </div>

                    <div class="form-group">
                        <label>Notes (optional)</label>
                        <textarea name="notes" rows="3" placeholder="Any notes about your payment..."></textarea>
                    </div>

                    <button type="submit" class="btn-pay"><i class="fas fa-paper-plane"></i> Submit Payment</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>
<script>
function selectMethod(el, method) {
    document.querySelectorAll('.method-option').forEach(m => m.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input').checked = true;
    document.getElementById('gcash-fields').style.display = method === 'gcash' ? 'block' : 'none';
}
</script>
</body>
</html>
