<?php
session_start();
include '../config.php';

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

// 2. Fetch patients for the selection dropdown
// This ensures you are only picking users labeled as 'patient'
$patient_query = "SELECT id, name FROM users WHERE role = 'patient'";
$patients = $conn->query($patient_query);

// 3. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $patient_id = $_POST['patient_id'];
    $diagnosis = $_POST['diagnosis'];
    $notes = $_POST['notes'];
    
    // Get the logged-in doctor's ID
    $doctor_id = $_SESSION['user_id']; 

    // Insert main prescription record
    // We include patient_id and doctor_id to link the record correctly
    $stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, diagnosis, doctor_notes, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $patient_id, $doctor_id, $diagnosis, $notes);
    
    if ($stmt->execute()) {
        $presc_id = $conn->insert_id; // Get the ID of the prescription we just created

        // Insert medications (looping through the dynamic rows)
        if (isset($_POST['med_name']) && is_array($_POST['med_name'])) {
            for ($i = 0; $i < count($_POST['med_name']); $i++) {
                $m_name = $_POST['med_name'][$i];
                $m_dosage = $_POST['med_dosage'][$i];
                $m_duration = $_POST['med_duration'][$i];

                // Skip empty rows
                if (empty($m_name)) continue;

                $m_stmt = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_name, dosage, duration) VALUES (?, ?, ?, ?)");
                $m_stmt->bind_param("isss", $presc_id, $m_name, $m_dosage, $m_duration);
                $m_stmt->execute();
            }
        }
        // Redirect back to the dashboard or list with a success message
        header("Location: prescriptions.php?success=1");
        exit();
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Prescription - Community Teleconsult</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/new_prescription.css">
</head>
<body>

    <div class="main-content">
        <h1>Create New Prescription</h1>
        
        <div class="form-card">
            <form method="POST" id="prescriptionForm">
                <div class="input-group">
                    <label>Select Patient</label>
                    <select name="patient_id" required>
                        <option value="">-- Choose a patient --</option>
                        <?php while($p = $patients->fetch_assoc()): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="input-group">
                    <label>Diagnosis</label>
                    <input type="text" name="diagnosis" placeholder="e.g., Tension Headache" required>
                </div>

                <div id="medication-container">
                    <label>Medications</label>
                    <div class="med-row">
                        <input type="text" name="med_name[]" placeholder="Medication Name" required>
                        <input type="text" name="med_dosage[]" placeholder="Dosage (e.g. 1 tab daily)" required>
                        <input type="text" name="med_duration[]" placeholder="Duration (e.g. 7 days)" required>
                    </div>
                </div>

                <button type="button" class="btn-add-med" onclick="addMedicationRow()">
                    <i class="fas fa-plus"></i> Add Another Medication
                </button>

                <div class="input-group">
                    <label>Additional Notes</label>
                    <textarea name="notes" rows="4" placeholder="Take with food..."></textarea>
                </div>

                <div class="actions">
                    <button type="submit" class="btn-submit">Create Prescription</button>
                    <a href="prescriptions.php" class="cancel-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function addMedicationRow() {
            const container = document.getElementById('medication-container');
            const newRow = document.createElement('div');
            newRow.className = 'med-row';
            newRow.innerHTML = `
                <input type="text" name="med_name[]" placeholder="Medication Name">
                <input type="text" name="med_dosage[]" placeholder="Dosage">
                <input type="text" name="med_duration[]" placeholder="Duration">
            `;
            container.appendChild(newRow);
        }
    </script>
</body>
</html>