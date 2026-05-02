<?php
/**
 * Activity Logger – include and call log_activity() anywhere
 * Usage: log_activity($conn, $user_id, 'role', 'Did something', 'details');
 */
function log_activity($conn, $user_id, $role, $action, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $conn->prepare(
        "INSERT INTO activity_logs (user_id, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issss", $user_id, $role, $action, $details, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
