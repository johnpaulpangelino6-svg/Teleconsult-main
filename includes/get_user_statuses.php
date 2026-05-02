<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Fetch the last_seen for all users
// Define a threshold for "Active" (e.g., 2 minutes)
$threshold = date('Y-m-d H:i:s', strtotime('-2 minutes'));

$stmt = $conn->prepare("SELECT id, last_seen FROM users");
$stmt->execute();
$result = $stmt->get_result();

$statuses = [];
while ($row = $result->fetch_assoc()) {
    $isActive = ($row['last_seen'] !== null && $row['last_seen'] >= $threshold);
    $statuses[$row['id']] = $isActive ? 'Active' : 'Inactive';
}

echo json_encode(['status' => 'success', 'data' => $statuses]);
?>
