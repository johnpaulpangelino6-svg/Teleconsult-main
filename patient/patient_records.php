<?php
session_start();
include '../config.php';

// Assuming the logged-in patient's ID is stored in the session
$patient_id = $_SESSION['user_id']; 

$sql = "SELECT p.*, u.name as doctor_name 
        FROM prescriptions p 
        JOIN users u ON p.doctor_id = u.id 
        WHERE p.patient_id = ? 
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="main-content">
    <h1>My Prescriptions</h1>
    
    <?php if ($result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
            <div class="presc-card" style="background:#111827; padding:20px; border-radius:12px; margin-bottom:20px; border:1px solid #1e293b;">
                <div style="display:flex; justify-content:space-between;">
                    <h3>Prescription #<?= $row['id'] ?></h3>
                    <span style="color:#94a3b8;"><?= $row['created_at'] ?></span>
                </div>
                <p><strong>Doctor:</strong> <?= htmlspecialchars($row['doctor_name']) ?></p>
                <p style="background:#1e293b; display:inline-block; padding:5px 10px; border-radius:4px;">
                    Diagnosis: <?= htmlspecialchars($row['diagnosis']) ?>
                </p>

                <h4>Medications:</h4>
                <?php
                $p_id = $row['id'];
                $items = $conn->query("SELECT * FROM prescription_items WHERE prescription_id = $p_id");
                while($item = $items->fetch_assoc()):
                ?>
                    <div style="background:#1e293b; padding:10px; border-radius:8px; margin-bottom:5px;">
                        <strong><?= htmlspecialchars($item['medicine_name']) ?></strong><br>
                        <small><?= htmlspecialchars($item['dosage']) ?> • <?= htmlspecialchars($item['duration']) ?></small>
                    </div>
                <?php endwhile; ?>
                
                <div style="color:#f59e0b; margin-top:10px;">
                    <strong>Notes:</strong> <?= htmlspecialchars($row['notes']) ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div style="text-align:center; padding:50px; color:#94a3b8;">
            <i class="fas fa-pills" style="font-size:3rem; margin-bottom:10px;"></i>
            <p>You have no active or past prescriptions recorded.</p>
        </div>
    <?php endif; ?>
</div>