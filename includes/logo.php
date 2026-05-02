<?php
/**
 * Shared Logo Component
 * This file contains the logo HTML and CSS.
 * It dynamically detects the directory level to set the correct image path.
 */

// Detect if we are in a subdirectory (doctor, patient, admin)
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$is_subdir = in_array($current_dir, ['doctor', 'patient', 'admin']);
$logo_src = ($is_subdir ? '../' : '') . 'uploads/1777630001_logo system.png';
?>
<style>
    .system-logo-container {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 0 8px;
        margin-bottom: 30px;
        text-decoration: none;
    }
    .system-logo-img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 50%;
        flex-shrink: 0;
        background: white;
        padding: 2px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .system-logo-text {
        display: flex;
        flex-direction: column;
        line-height: 1.2;
    }
    .system-logo-text b {
        font-size: 15px;
        font-weight: 700;
        color: #FFFFFF !important;
        display: block;
    }
    .system-logo-text span {
        font-size: 10.5px;
        color: #94a3b8 !important;
        display: block;
        margin-top: 2px;
    }

    /* Adjustments for Login Page */
    .login-logo-override {
        flex-direction: column;
        justify-content: center;
        margin-bottom: 20px;
        text-align: center;
    }
    .login-logo-override .system-logo-img {
        width: 100px;
        height: 100px;
        margin-bottom: 15px;
    }
    .login-logo-override .system-logo-text b {
        font-size: 22px;
    }
</style>

<div class="system-logo-container <?php echo (!$is_subdir && basename($_SERVER['PHP_SELF']) == 'login.php') ? 'login-logo-override' : ''; ?>">
    <img src="<?php echo htmlspecialchars($logo_src); ?>" alt="Logo" class="system-logo-img">
    <div class="system-logo-text">
        <b>Community Teleconsult</b>
        <span>Healthcare for All</span>
    </div>
</div>
