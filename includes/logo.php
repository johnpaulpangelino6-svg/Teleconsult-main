<?php
$base_path = (dirname($_SERVER['PHP_SELF']) == '/Teleconsult-main' || dirname($_SERVER['PHP_SELF']) == '/' || dirname($_SERVER['PHP_SELF']) == '\\Teleconsult-main' || dirname($_SERVER['PHP_SELF']) == '\\') ? '' : '../';
?>
<div class="logo">
    <img src="<?php echo $base_path; ?>uploads/1777630001_logo%20system.png" alt="Logo" class="system-logo" style="width: 42px !important; height: 42px !important; object-fit: contain; border-radius: 10px; flex-shrink: 0;">
    <div class="logo-text">
        <b style="font-size: 16px; color: white; display: block; line-height: 1.2;">Community Teleconsult</b>
        <span style="font-size: 11px; color: #94a3b8; display: block; line-height: 1.3; max-width: 150px;">Healthcare for All</span>
    </div>
</div>
