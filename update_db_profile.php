<?php
include 'config.php';

$sql = "ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS phone_number VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS date_of_birth DATE DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS address TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS blood_type VARCHAR(5) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS allergies TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS emergency_contact VARCHAR(255) DEFAULT NULL";

if ($conn->query($sql)) {
    echo "✅ Users table updated successfully!";
} else {
    echo "❌ Error updating table: " . $conn->error;
}
?>
