<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php"); exit();
}

$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$where = "WHERE 1=1";
if (!empty($search)) { $s = $conn->real_escape_string($search); $where .= " AND (l.action LIKE '%$s%' OR u.name LIKE '%$s%')"; }
if (!empty($role_filter)) { $where .= " AND l.role = '" . $conn->real_escape_string($role_filter) . "'"; }

$logs = $conn->query("
    SELECT l.*, u.name as user_name
    FROM activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    $where
    ORDER BY l.created_at DESC
    LIMIT 500
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs – Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <style>
        .filter-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-bar input, .filter-bar select { padding: 10px 14px; background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; color: white; font-family: inherit; font-size: 14px; }
        .filter-bar input { flex: 1; min-width: 200px; }
        .filter-bar button { background: #3b82f6; color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .table-card { background: #0f172a; border: 1px solid #1e293b; border-radius: 16px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: rgba(255,255,255,0.03); padding: 12px 16px; text-align: left; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #1e293b; }
        td { padding: 12px 16px; border-bottom: 1px solid rgba(30,41,59,0.5); font-size: 13px; }
        .role-pill { padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
        .rp-admin { background: rgba(168,85,247,0.15); color: #a855f7; }
        .rp-doctor { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .rp-patient { background: rgba(34,197,94,0.15); color: #22c55e; }
    </style>
    <?php include '../includes/speed_insights.php'; ?>
</head>
<body>
<?php include '../includes/admin_sidebar.php'; ?>
<main class="main">
    <div class="top-bar">
        <div class="header-title"><h1>Activity Logs</h1><p>Complete audit trail of all system actions</p></div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="🔍 Search actions or user name..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="role">
            <option value="">All Roles</option>
            <option value="admin" <?php echo $role_filter=='admin'?'selected':''; ?>>Admin</option>
            <option value="doctor" <?php echo $role_filter=='doctor'?'selected':''; ?>>Doctor</option>
            <option value="patient" <?php echo $role_filter=='patient'?'selected':''; ?>>Patient</option>
        </select>
        <button type="submit"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($search || $role_filter): ?><a href="activity_logs.php" style="padding:10px 16px;border:1px solid #1e293b;border-radius:8px;color:#94a3b8;text-decoration:none;font-size:13px;">Clear</a><?php endif; ?>
    </form>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs && $logs->num_rows > 0): ?>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td style="color:#475569;"><?php echo $log['id']; ?></td>
                        <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                        <td><span class="role-pill rp-<?php echo $log['role']; ?>"><?php echo htmlspecialchars($log['role'] ?? '—'); ?></span></td>
                        <td><?php echo htmlspecialchars($log['action']); ?></td>
                        <td style="color:#94a3b8;font-size:12px;"><?php echo htmlspecialchars($log['details'] ?? '—'); ?></td>
                        <td style="font-size:12px;color:#64748b;font-family:monospace;"><?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?></td>
                        <td style="color:#94a3b8;font-size:12px;"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;padding:40px;color:#475569;">No activity logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
