<?php
$current_page = basename($_SERVER['PHP_SELF']);
$sb_doctor_name = $_SESSION['user_name'] ?? 'Doctor';
$sb_doctor_photo = !empty($_SESSION['user_photo']) ? "../uploads/".$_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($sb_doctor_name)."&background=0ea5e9&color=fff";
?>
<!-- FIXED SIDEBAR -->
<aside class="sidebar">
    <?php include 'logo.php'; ?>

    <div class="user-card">
        <img src="<?php echo htmlspecialchars($sb_doctor_photo); ?>" alt="avatar">
        <div>
            <div class="uname"><?php echo htmlspecialchars($sb_doctor_name); ?></div>
            <div class="urole">Doctor</div>
        </div>
    </div>

    <a href="dashboard.php"        class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="chat.php"             class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>"><i class="far fa-comment-dots"></i> Messages</a>
    <a href="manage_patients.php"  class="nav-link <?php echo $current_page == 'manage_patients.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Patients</a>
    <a href="prescriptions.php"    class="nav-link <?php echo $current_page == 'prescriptions.php' ? 'active' : ''; ?>"><i class="fas fa-file-prescription"></i> Prescriptions</a>
    <a href="manage_calendar.php"  class="nav-link <?php echo $current_page == 'manage_calendar.php' ? 'active' : ''; ?>"><i class="far fa-calendar-alt"></i> Manage Calendar</a>
    <a href="profile.php"          class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"><i class="far fa-user"></i> Profile</a>

    <div class="sidebar-spacer"></div>
    <a href="../login.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>
