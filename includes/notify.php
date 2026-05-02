<?php
/**
 * Notification Helper – include this file to send notifications
 * Usage: notify($conn, $user_id, 'Title', 'Message', 'type', '/link');
 */
function notify($conn, $user_id, $title, $message, $type = 'system', $link = null) {
    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issss", $user_id, $title, $message, $type, $link);
    $stmt->execute();
    $stmt->close();
}

/**
 * Count unread notifications for a user
 */
function count_unread($conn, $user_id) {
    $res = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = $user_id AND is_read = 0");
    return $res ? (int)$res->fetch_assoc()['c'] : 0;
}
?>
