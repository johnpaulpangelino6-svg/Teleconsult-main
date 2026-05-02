<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$id = $_SESSION['user_id'];

// --- DB SELF-HEALING ---
$cols_to_check = [
    'phone_number' => 'VARCHAR(20)',
    'date_of_birth' => 'DATE',
    'address' => 'TEXT',
    'blood_type' => 'VARCHAR(5)',
    'allergies' => 'TEXT',
    'emergency_contact' => 'VARCHAR(255)'
];
foreach($cols_to_check as $col => $type) {
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS $col $type DEFAULT NULL");
}

/* =========================
   HANDLE UPDATE
========================= */
$success_msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone_number']);
    $dob = mysqli_real_escape_string($conn, $_POST['date_of_birth']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $blood = mysqli_real_escape_string($conn, $_POST['blood_type']);
    $allergies = mysqli_real_escape_string($conn, $_POST['allergies']);
    $emergency = mysqli_real_escape_string($conn, $_POST['emergency_contact']);

    if (!empty($_FILES['photo']['name'])) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . "_" . basename($_FILES["photo"]["name"]);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
            $conn->query("UPDATE users SET photo='$fileName' WHERE id='$id'");
            $_SESSION['user_photo'] = $fileName;
        }
    }

    $sql = "UPDATE users SET name='$name', email='$email', phone_number='$phone', date_of_birth='$dob', address='$address', blood_type='$blood', allergies='$allergies', emergency_contact='$emergency' WHERE id='$id'";
    if ($conn->query($sql)) $success_msg = "Profile updated successfully!";
}

$user = $conn->query("SELECT * FROM users WHERE id='$id'")->fetch_assoc();
$photo_path = !empty($user['photo']) ? "../uploads/".$user['photo'] : "https://ui-avatars.com/api/?name=".urlencode($user['name'])."&background=random&color=fff";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/profile.css">
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>Administrator Profile</h1>
        <p>Manage your system and personal information</p>
    </div>

    <?php if(!empty($success_msg)): ?>
        <div class="success-alert">
            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="profileForm" class="profile-container">
        <div class="profile-card-left">
            <div class="avatar-wrapper">
                <img src="<?php echo $photo_path; ?>" alt="avatar" class="avatar-img" id="avatarPreview">
                <label for="photoInput" class="cam-icon"><i class="fas fa-camera"></i></label>
                <input type="file" name="photo" id="photoInput" class="hidden-file" onchange="previewImage(this)">
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
            <span class="profile-role">System Administrator</span>
            <div class="contact-info-list">
                <div class="contact-item"><i class="far fa-envelope"></i><span><?php echo htmlspecialchars($user['email']); ?></span></div>
                <div class="contact-item"><i class="fas fa-phone-alt"></i><span><?php echo htmlspecialchars($user['phone_number'] ?? '+63 000 000 0000'); ?></span></div>
                <div class="contact-item"><i class="fas fa-map-marker-alt"></i><span><?php echo htmlspecialchars($user['address'] ?? 'No address provided'); ?></span></div>
            </div>
        </div>

        <div class="profile-content-right">
            <div class="info-section">
                <div class="section-header"><h3>Personal Information</h3><button type="button" class="btn-edit" onclick="toggleEdit()">Edit Profile</button></div>
                <div class="grid-fields">
                    <div class="field-group"><label>Full Name</label><input type="text" name="name" class="field-value" value="<?php echo htmlspecialchars($user['name']); ?>" readonly></div>
                    <div class="field-group"><label>Email</label><input type="email" name="email" class="field-value" value="<?php echo htmlspecialchars($user['email']); ?>" readonly></div>
                    <div class="field-group"><label>Phone Number</label><input type="text" name="phone_number" class="field-value" value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>" readonly></div>
                    <div class="field-group"><label>Date of Birth</label><input type="date" name="date_of_birth" class="field-value" value="<?php echo $user['date_of_birth'] ?? ''; ?>" readonly></div>
                    <div class="field-group full-width"><label>Home Address</label><input type="text" name="address" class="field-value" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" readonly></div>
                </div>
            </div>
            <div class="info-section">
                <div class="section-header"><h3>Security & Emergency</h3></div>
                <div class="grid-fields">
                    <div class="field-group"><label>Blood Type</label><input type="text" name="blood_type" class="field-value" value="<?php echo htmlspecialchars($user['blood_type'] ?? ''); ?>" readonly></div>
                    <div class="field-group"><label>Allergies</label><input type="text" name="allergies" class="field-value" value="<?php echo htmlspecialchars($user['allergies'] ?? ''); ?>" readonly></div>
                    <div class="field-group full-width"><label>Emergency Contact</label><input type="text" name="emergency_contact" class="field-value" value="<?php echo htmlspecialchars($user['emergency_contact'] ?? ''); ?>" readonly></div>
                </div>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </div>
    </form>
</main>

<script>
    function toggleEdit() {
        const form = document.getElementById('profileForm');
        const inputs = form.querySelectorAll('.field-value');
        const btnEdit = document.querySelector('.btn-edit');
        const isEditing = form.classList.toggle('edit-mode');
        inputs.forEach(input => input.readOnly = !isEditing);
        btnEdit.textContent = isEditing ? 'Cancel' : 'Edit Profile';
    }
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatarPreview').src = e.target.result;
                document.getElementById('profileForm').submit();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
</body>
</html>
