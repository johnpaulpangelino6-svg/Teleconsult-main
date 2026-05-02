<?php
session_start();
include 'config.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $role = $_POST['role']; // New: capture role
    $pass = $_POST['password'];
    $conf_pass = $_POST['confirm_password'];

    // Basic Validation
    if ($pass !== $conf_pass) {
        $error = "Passwords do not match!";
    } else {
        $password_hashed = password_hash($pass, PASSWORD_DEFAULT);

        // CHECK EMAIL
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $error = "Email already exists!";
        } else {
            // INSERT INTO USERS TABLE (Now including the dynamic role)
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $password_hashed, $role);

            if ($stmt->execute()) {
                $_SESSION['success'] = "You are now registered. Please login.";
                header("Location: login.php");
                exit();
            } else {
                $error = "Registration failed!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>

<div class="card">
    <div class="logo-container">
        <div class="logo-circle">
            <i class="fas fa-hospital-user"></i>
        </div>
    </div>

    <h2>Create Account</h2>
    <p class="subtitle">Join our healthcare community today</p>

    <?php if (!empty($error)): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Full Name</label>
            <div class="input-wrapper">
                <i class="far fa-user"></i>
                <input type="text" name="fullname" placeholder="Juan Dela Cruz" required>
            </div>
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <div class="input-wrapper">
                <i class="far fa-envelope"></i>
                <input type="email" name="email" placeholder="you@example.com" required>
            </div>
        </div>

        <div class="form-group">
            <label>Register As</label>
            <div class="input-wrapper">
                <i class="fas fa-user-tag"></i>
                <select name="role" required>
                    <option value="" disabled selected>Select your role</option>
                    <option value="patient">Patient</option>
                    <option value="doctor">Doctor</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Create a strong password" required>
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <div class="input-wrapper">
                <i class="fas fa-lock"></i>
                <input type="password" name="confirm_password" placeholder="Re-enter your password" required>
            </div>
        </div>

        <button type="submit">Create Account</button>
    </form>

    <div class="footer-text">
        Already have an account? <a href="login.php">Sign in here</a>
    </div>

    <div class="note-box">
        <p style="margin: 0; color: #94a3b8;">
            <span>Note:</span> Registration creates a patient account. Healthcare providers should contact the administrator for verification.
        </p>
    </div>
</div>

</body>
</html>