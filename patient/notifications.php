<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php"); exit();
}

$user_id = $_SESSION['user_id'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header("Location: notifications.php"); exit();
}

// Mark single as read
if (isset($_GET['read'])) {
    $nid = (int)$_GET['read'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $nid AND user_id = $user_id");
    $row = $conn->query("SELECT link FROM notifications WHERE id = $nid")->fetch_assoc();
    if (!empty($row['link'])) { header("Location: " . $row['link']); exit(); }
    header("Location: notifications.php"); exit();
}

// Fetch all notifications
$notifs = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 100");
$unread = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – Community Teleconsult</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/user_dashboard.css">
    <style>
        .notif-page { max-width: 750px; margin: 0 auto; }
        .notif-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
        .notif-header h1 { font-size:1.6rem; font-weight:700; }
        .mark-all-btn { background:rgba(59,130,246,0.1); color:#3b82f6; border:1px solid rgba(59,130,246,0.3); padding:8px 16px; border-radius:8px; cursor:pointer; text-decoration:none; font-size:13px; font-weight:600; }
        .notif-item { display:flex; gap:16px; align-items:flex-start; padding:18px 20px; border-radius:14px; border:1px solid #1e293b; margin-bottom:12px; transition:all 0.2s; cursor:pointer; }
        .notif-item:hover { border-color:#3b82f6; background:rgba(59,130,246,0.04); }
        .notif-item.unread { background:rgba(59,130,246,0.06); border-color:rgba(59,130,246,0.25); }
        .notif-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
        .ni-appointment { background:rgba(59,130,246,0.15); color:#3b82f6; }
        .ni-payment { background:rgba(34,197,94,0.15); color:#22c55e; }
        .ni-prescription { background:rgba(168,85,247,0.15); color:#a855f7; }
        .ni-followup { background:rgba(234,179,8,0.15); color:#eab308; }
        .ni-system { background:rgba(100,116,139,0.15); color:#94a3b8; }
        .ni-message { background:rgba(6,182,212,0.15); color:#06b6d4; }
        .notif-body { flex:1; }
        .notif-title { font-weight:600; font-size:15px; margin-bottom:4px; }
        .notif-msg { color:#94a3b8; font-size:13px; line-height:1.5; }
        .notif-time { font-size:11px; color:#475569; margin-top:6px; }
        .unread-dot { width:8px; height:8px; background:#3b82f6; border-radius:50%; margin-top:6px; flex-shrink:0; }
        .empty-notifs { text-align:center; padding:80px 20px; color:#475569; }
        .empty-notifs i { font-size:3rem; margin-bottom:16px; display:block; }
    </style>
</head>
<body>
<?php include '../includes/patient_sidebar.php'; ?>
<main class="main">
    <div class="notif-page">
        <div class="notif-header">
            <h1><i class="far fa-bell" style="color:#3b82f6; margin-right:12px;"></i> Notifications
                <?php if ($unread > 0): ?><span style="background:#ef4444;color:white;border-radius:20px;font-size:12px;padding:2px 8px;margin-left:8px;"><?php echo $unread; ?></span><?php endif; ?>
            </h1>
            <?php if ($unread > 0): ?>
            <a href="notifications.php?mark_all=1" class="mark-all-btn"><i class="fas fa-check-double"></i> Mark all as read</a>
            <?php endif; ?>
        </div>

        <?php if ($notifs && $notifs->num_rows > 0): ?>
            <?php while ($n = $notifs->fetch_assoc()): 
                $icons = ['appointment'=>'fas fa-calendar-check ni-appointment','payment'=>'fas fa-credit-card ni-payment','prescription'=>'fas fa-file-prescription ni-prescription','followup'=>'fas fa-clock ni-followup','system'=>'fas fa-bell ni-system','message'=>'fas fa-comment-dots ni-message'];
                $icon_class = $icons[$n['type']] ?? 'fas fa-bell ni-system';
                $parts = explode(' ', $icon_class);
                $fa = $parts[0].' '.$parts[1]; $ni = $parts[2] ?? 'ni-system';
            ?>
            <a href="notifications.php?read=<?php echo $n['id']; ?>" style="text-decoration:none; color:inherit;">
            <div class="notif-item <?php echo $n['is_read'] ? '' : 'unread'; ?>">
                <div class="notif-icon <?php echo $ni; ?>"><i class="<?php echo $fa; ?>"></i></div>
                <div class="notif-body">
                    <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                    <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                    <div class="notif-time"><?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?></div>
                </div>
                <?php if (!$n['is_read']): ?><div class="unread-dot"></div><?php endif; ?>
            </div>
            </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-notifs">
                <i class="far fa-bell-slash"></i>
                <h3>No notifications yet</h3>
                <p>You'll be notified about appointments, payments, and more.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
