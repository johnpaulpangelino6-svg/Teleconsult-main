<?php
$current_page = basename($_SERVER['PHP_SELF']);
$sb_patient_name = $_SESSION['user_name'] ?? 'Patient';
$sb_patient_photo = !empty($_SESSION['user_photo']) ? "../uploads/".$_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($sb_patient_name)."&background=3b82f6&color=fff";
?>
<!-- FIXED SIDEBAR -->
<aside class="sidebar">
    <?php include 'logo.php'; ?>

    <div class="user-card">
        <img src="<?php echo htmlspecialchars($sb_patient_photo); ?>" alt="avatar">
        <div>
            <div class="uname"><?php echo htmlspecialchars($sb_patient_name); ?></div>
            <div class="urole">Patient</div>
        </div>
    </div>

    <a href="dashboard.php"        class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="book_appointment.php" class="nav-link <?php echo $current_page == 'book_appointment.php' ? 'active' : ''; ?>"><i class="far fa-calendar-plus"></i> Book Appointment</a>
    <a href="my_appointments.php"  class="nav-link <?php echo $current_page == 'my_appointments.php' ? 'active' : ''; ?>"><i class="far fa-calendar-alt"></i> My Appointments</a>
    <a href="chat.php"             class="nav-link <?php echo $current_page == 'chat.php' ? 'active' : ''; ?>"><i class="far fa-comment-dots"></i> Messages</a>
    <a href="medical_records.php"  class="nav-link <?php echo $current_page == 'medical_records.php' ? 'active' : ''; ?>"><i class="far fa-file-medical"></i> Medical Records</a>
    <a href="profile.php"          class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"><i class="far fa-user"></i> Profile</a>

    <div class="sidebar-spacer"></div>
    <a href="../login.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</aside>
