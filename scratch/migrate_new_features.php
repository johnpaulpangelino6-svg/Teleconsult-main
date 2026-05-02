<?php
/**
 * Community Teleconsult – Database Migration Script
 * Run this ONCE to add all new tables and columns.
 * Safe: uses IF NOT EXISTS / IF NOT EXISTS for columns.
 */
include '../config.php';

$migrations = [];

// 1. Add last_seen to users (safe)
$migrations[] = "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen DATETIME DEFAULT NULL";

// 2. Add specialization & bio to users (for doctors)
$migrations[] = "ALTER TABLE users ADD COLUMN IF NOT EXISTS specialization VARCHAR(150) DEFAULT NULL";
$migrations[] = "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL";
$migrations[] = "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL";

// 3. PAYMENTS table
$migrations[] = "CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    patient_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    method ENUM('gcash','cash','barangay_cash') NOT NULL DEFAULT 'cash',
    gcash_ref VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    proof_photo VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    verified_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
)";

// 4. NOTIFICATIONS table
$migrations[] = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('appointment','payment','prescription','followup','system','message') NOT NULL DEFAULT 'system',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// 5. ACTIVITY LOGS table
$migrations[] = "CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    role VARCHAR(30) DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(60) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// 6. DOCTOR SCHEDULES table
$migrations[] = "CREATE TABLE IF NOT EXISTS doctor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)";

// 7. DOCTOR SUBSCRIPTIONS table
$migrations[] = "CREATE TABLE IF NOT EXISTS doctor_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL UNIQUE,
    plan ENUM('free','basic','premium') NOT NULL DEFAULT 'free',
    monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    started_at DATE DEFAULT NULL,
    expires_at DATE DEFAULT NULL,
    status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)";

// 8. FOLLOW-UPS table
$migrations[] = "CREATE TABLE IF NOT EXISTS followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT DEFAULT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    due_date DATE NOT NULL,
    note TEXT DEFAULT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
)";

// 9. Add payment_status to appointments
$migrations[] = "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid','pending','paid') NOT NULL DEFAULT 'unpaid'";
$migrations[] = "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS doctor_notes TEXT DEFAULT NULL";

$ok = 0; $fail = 0; $errors = [];
foreach ($migrations as $sql) {
    if ($conn->query($sql)) {
        $ok++;
    } else {
        $fail++;
        $errors[] = $conn->error . " — SQL: " . substr($sql, 0, 80) . "...";
    }
}

echo "<h2>Migration Complete</h2>";
echo "<p style='color:green'>✅ $ok migration(s) succeeded.</p>";
if ($fail) {
    echo "<p style='color:red'>❌ $fail migration(s) failed:</p><ul>";
    foreach ($errors as $e) echo "<li>$e</li>";
    echo "</ul>";
}
echo "<br><a href='../admin/dashboard.php'>← Back to Admin Dashboard</a>";
?>
