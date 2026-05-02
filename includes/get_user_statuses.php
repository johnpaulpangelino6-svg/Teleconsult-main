<?php
include '../config.php';

$sql = "SELECT id, last_activity, is_online FROM users WHERE role = 'patient'";
$result = $conn->query($sql);

$statuses = [];
$current_time = time();

while ($row = $result->fetch_assoc()) {
    $last_time = strtotime($row['last_activity']);
    $is_active = ($row['is_online'] && ($current_time - $last_time < 120));
    $statuses[$row['id']] = $is_active ? 'Active' : 'Inactive';
}

header('Content-Type: application/json');
echo json_encode($statuses);
?>
