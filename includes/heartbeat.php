<?php
session_start();
include '../config.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // Update last_seen to current time
    $stmt = $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
}
?>
