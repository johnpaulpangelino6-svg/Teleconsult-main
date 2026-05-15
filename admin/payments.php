<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

include_once '../includes/log_activity.php';
include_once '../includes/notify.php';

$admin_id = $_SESSION['user_id'];
$success = ''; $error = '';

// Verify / Reject payment
if (isset($_GET['action']) && isset($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $action = $_GET['action'];
    if (in_array($action, ['verified','rejected'])) {
        $conn->query("UPDATE payments SET status='$action', verified_by=$admin_id WHERE id=$pid");
        $payment_row = $conn->query("SELECT * FROM payments WHERE id=$pid")->fetch_assoc();
        if ($payment_row) {
            $appt_id = $payment_row['appointment_id'];
            if ($action == 'verified') {
                $conn->query("UPDATE appointments SET payment_status='paid' WHERE id=$appt_id");
            }
            notify($conn, $payment_row['patient_id'], 'Payment ' . ucfirst($action), 
                "Your payment for Appointment #$appt_id has been " . ucfirst($action) . ".", 
                'payment', 'my_appointments.php');
        }
        log_activity($conn, $admin_id, 'admin', "Payment #$pid $action");
        header("Location: payments.php?msg=$action"); exit();
    }
}

$msg = $_GET['msg'] ?? '';

// Filter
$status_filter = $_GET['status'] ?? '';
$where = $status_filter ? "WHERE p.status = '" . $conn->real_escape_string($status_filter) . "'" : "";

// Fetch payments
$payments = $conn->query("
    SELECT p.*, u.name as patient_name, a.appointment_date, d.name as doctor_name
    FROM payments p
    LEFT JOIN users u ON p.patient_id = u.id
    LEFT JOIN appointments a ON p.appointment_id = a.id
    LEFT JOIN users d ON a.doctor_id = d.id
    $where
    ORDER BY p.created_at DESC
");

// Summary counts
$total_rev = $conn->query("SELECT SUM(amount) as t FROM payments WHERE status='verified'")->fetch_assoc()['t'] ?? 0;
$pending_count = $conn->query("SELECT COUNT(*) as c FROM payments WHERE status='pending'")->fetch_assoc()['c'];
$verified_count = $conn->query("SELECT COUNT(*) as c FROM payments WHERE status='verified'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <style>
        .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .s-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 14px; padding: 20px; }
        .s-val { font-size: 2rem; font-weight: 700; }
        .s-lbl { color: #94a3b8; font-size: 13px; margin-top: 4px; }
        .filter-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-bar select { padding: 10px 14px; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; color: white; font-family: inherit; }
        .filter-bar select option { background: #0f172a; }
        .table-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 16px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(255,255,255,0.03); padding: 14px 16px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #1e293b; }
        td { padding: 14px 16px; border-bottom: 1px solid rgba(30,41,59,0.5); font-size: 14px; }
        .status-pill { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .sp-pending { background: rgba(234,179,8,0.1); color: #eab308; }
        .sp-verified { background: rgba(34,197,94,0.1); color: #22c55e; }
        .sp-rejected { background: rgba(239,68,68,0.1); color: #ef4444; }
        .act-btn { padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; font-weight: 600; margin-right: 4px; }
        .btn-verify { background: rgba(34,197,94,0.15); color: #22c55e; }
        .btn-reject { background: rgba(239,68,68,0.15); color: #ef4444; }
        .proof-link { color: #3b82f6; font-size: 12px; text-decoration: none; }
        .proof-link:hover { text-decoration: underline; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; font-weight: 500; }
        .alert-success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; }
    </style>
    <?php include '../includes/speed_insights.php'; ?>
</head>
<body>
<?php include '../includes/admin_sidebar.php'; ?>
<main class="main">
    <div class="top-bar">
        <div class="header-title"><h1>Payment Verification</h1><p>Review and verify patient consultation payments</p></div>
    </div>

    <?php if ($msg == 'verified'): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> Payment verified and patient notified!</div><?php endif; ?>
    <?php if ($msg == 'rejected'): ?><div class="alert" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#f87171;"><i class="fas fa-times-circle"></i> Payment rejected and patient notified.</div><?php endif; ?>

    <div class="stats-row">
        <div class="s-card">
            <div class="s-val" style="color:#22c55e;">₱<?php echo number_format($total_rev, 2); ?></div>
            <div class="s-lbl">Total Verified Revenue</div>
        </div>
        <div class="s-card">
            <div class="s-val" style="color:#eab308;"><?php echo $pending_count; ?></div>
            <div class="s-lbl">Pending Verification</div>
        </div>
        <div class="s-card">
            <div class="s-val" style="color:#3b82f6;"><?php echo $verified_count; ?></div>
            <div class="s-lbl">Verified Payments</div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="GET">
            <select name="status" onchange="this.form.submit()">
                <option value="">All Payments</option>
                <option value="pending" <?php echo $status_filter=='pending'?'selected':''; ?>>Pending</option>
                <option value="verified" <?php echo $status_filter=='verified'?'selected':''; ?>>Verified</option>
                <option value="rejected" <?php echo $status_filter=='rejected'?'selected':''; ?>>Rejected</option>
            </select>
        </form>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Appt Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>GCash Ref</th>
                    <th>Proof</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payments && $payments->num_rows > 0): ?>
                    <?php while ($p = $payments->fetch_assoc()): ?>
                    <tr>
                        <td style="color:#475569;">#<?php echo $p['id']; ?></td>
                        <td><?php echo htmlspecialchars($p['patient_name']); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($p['doctor_name'] ?? '—'); ?></td>
                        <td style="font-size:13px;color:#94a3b8;"><?php echo $p['appointment_date'] ? date('M d, Y', strtotime($p['appointment_date'])) : '—'; ?></td>
                        <td><strong>₱<?php echo number_format($p['amount'],2); ?></strong></td>
                        <td style="text-transform:uppercase;font-size:12px;"><?php echo $p['method']; ?></td>
                        <td style="font-size:12px;color:#94a3b8;"><?php echo htmlspecialchars($p['gcash_ref'] ?? '—'); ?></td>
                        <td>
                            <?php if (!empty($p['proof_photo'])): ?>
                                <a href="../uploads/<?php echo htmlspecialchars($p['proof_photo']); ?>" target="_blank" class="proof-link"><i class="fas fa-image"></i> View</a>
                            <?php else: ?><span style="color:#475569;font-size:12px;">None</span><?php endif; ?>
                        </td>
                        <td><span class="status-pill sp-<?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                        <td>
                            <?php if ($p['status'] == 'pending'): ?>
                                <a href="payments.php?action=verified&id=<?php echo $p['id']; ?>" class="act-btn btn-verify" onclick="return confirm('Verify this payment?')"><i class="fas fa-check"></i> Verify</a>
                                <a href="payments.php?action=rejected&id=<?php echo $p['id']; ?>" class="act-btn btn-reject" onclick="return confirm('Reject this payment?')"><i class="fas fa-times"></i> Reject</a>
                            <?php else: ?><span style="color:#475569;font-size:12px;">—</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="10" style="text-align:center;padding:40px;color:#475569;">No payments found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
