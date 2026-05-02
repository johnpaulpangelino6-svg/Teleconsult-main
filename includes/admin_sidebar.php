<?php
$current_page = basename($_SERVER['PHP_SELF']);
$sb_admin_name = $_SESSION['user_name'] ?? 'Admin';
$sb_admin_photo = !empty($_SESSION['user_photo']) ? "../uploads/".$_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($sb_admin_name)."&background=020617&color=fff";
?>
<aside class="sidebar">
    <?php include 'logo.php'; ?>

    <div class="nav-section">
        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="manage_users.php" class="nav-link <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i> Manage Users
        </a>
        <a href="appointments.php" class="nav-link <?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>">
            <i class="far fa-calendar-check"></i> Appointments
        </a>
        <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="far fa-user"></i> Profile
        </a>
    </div>

    <div class="sidebar-spacer"></div>
    
    <div class="user-card">
        <img src="<?php echo htmlspecialchars($sb_admin_photo); ?>" alt="avatar">
        <div>
            <div class="uname"><?php echo htmlspecialchars($sb_admin_name); ?></div>
            <div class="urole">Administrator</div>
        </div>
    </div>

    <a href="../login.php" class="nav-link logout">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</aside>
