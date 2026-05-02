<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
</head>
<body>
<?php include '../includes/admin_sidebar.php'; ?>
<main class="main">
    <div class="page-header">
        <h1>System Reports</h1>
        <p>Deep dive into analytics and system performance</p>
    </div>
    <div class="user-list-container" style="text-align:center; padding:100px;">
        <i class="fas fa-chart-bar" style="font-size:4rem; color:var(--text-muted); margin-bottom:1.5rem;"></i>
        <h2>Advanced Analytics module is coming soon!</h2>
        <p style="color:var(--text-muted);">We are working on generating detailed PDF and CSV exportable reports.</p>
    </div>
</main>
</body>
</html>
