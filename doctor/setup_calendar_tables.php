<?php
// Run this once to create the new tables
include '../config.php';

$sqls = [
    "CREATE TABLE IF NOT EXISTS doctor_settings (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id    INT NOT NULL UNIQUE,
        max_per_day  INT NOT NULL DEFAULT 10,
        slot_interval INT NOT NULL DEFAULT 30,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "CREATE TABLE IF NOT EXISTS blocked_dates (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id    INT NOT NULL,
        blocked_date DATE NOT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_block (doctor_id, blocked_date)
    )"
];

foreach ($sqls as $sql) {
    if ($conn->query($sql)) {
        echo "✅ Table created OK<br>";
    } else {
        echo "❌ Error: " . $conn->error . "<br>";
    }
}
echo "<br><strong>Done! You can delete this file now.</strong>";
?>
