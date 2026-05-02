<?php
include 'config.php';
$sql = "ALTER TABLE users ADD COLUMN last_seen DATETIME NULL DEFAULT NULL;";
if ($conn->query($sql) === TRUE) {
    echo "Column last_seen added successfully.";
} else {
    echo "Error adding column: " . $conn->error;
}
?>
