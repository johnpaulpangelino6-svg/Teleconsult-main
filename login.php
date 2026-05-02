<?php
ob_start(); // IMPORTANT: prevents header errors
session_start();
include 'config.php';

$error = "";
$success = "";

// Show success message (from register)
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// LOGIN PROCESS
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Prepare query
    $stmt = $conn->prepare("SELECT id, name, password, role, photo FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        // VERIFY PASSWORD
        if (password_verify($password, $user['password'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_photo'] = $user['photo'];

            // REDIRECT BASED ON ROLE
            if ($user['role'] === "patient") {
                header("Location: patient/dashboard.php");
                exit();
            } 
            elseif ($user['role'] === "doctor") {
                header("Location: doctor/dashboard.php");
                exit();
            } 
            elseif ($user['role'] === "admin") {
                header("Location: admin/dashboard.php");
                exit();
            } 
            else {
                $error = "User role not defined!";
            }

        } else {
            $error = "Invalid email or password!";
        }

    } else {
        $error = "Invalid email or password!";
    }
}

ob_end_flush(); // flush output
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>

<link rel="stylesheet" href="assets/css/login.css?v=<?php echo time(); ?>">
</head>

<body>

<div class="login-page-wrapper">
    
    <!-- LEFT SIDE: GUIDE & FEATURES -->
    <div class="login-info-side">
        <div class="badge-serve">
            <span class="dot"></span> Now serving local communities nationwide
        </div>
        
        <h1 class="hero-title">Community Teleconsult</h1>
        <p class="hero-subtitle">Accessible healthcare for everyone. Connect with qualified doctors from the comfort of your home. Serving local communities with quality medical consultation.</p>

        <!-- Features Grid -->
        <h3 class="section-title">Platform Features</h3>
        <p class="section-desc">Everything you need for quality healthcare at your fingertips</p>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="f-icon chat"><i class="fas fa-comment-dots"></i></div>
                <h4>Real-time Chat</h4>
                <p>Connect with doctors instantly through our secure messaging system</p>
            </div>
            <div class="feature-card">
                <div class="f-icon calendar"><i class="far fa-calendar-alt"></i></div>
                <h4>Easy Appointments</h4>
                <p>Book and manage appointments with healthcare professionals</p>
            </div>
            <div class="feature-card">
                <div class="f-icon presc"><i class="fas fa-file-prescription"></i></div>
                <h4>E-Prescriptions</h4>
                <p>Receive and store digital prescriptions securely</p>
            </div>
            <div class="feature-card">
                <div class="f-icon secure"><i class="fas fa-shield-alt"></i></div>
                <h4>Secure & Private</h4>
                <p>Your medical data is encrypted and protected</p>
            </div>
            <div class="feature-card">
                <div class="f-icon time"><i class="far fa-clock"></i></div>
                <h4>24/7 Availability</h4>
                <p>Access healthcare services anytime, anywhere</p>
            </div>
            <div class="feature-card">
                <div class="f-icon comm"><i class="fas fa-user-friends"></i></div>
                <h4>Community-Focused</h4>
                <p>Designed for local barangay communities</p>
            </div>
        </div>

        <!-- How it works -->
        <div class="process-container">
            <div class="section-label" style="color: #10b981;">PROCESS</div>
            <h2 class="section-title" style="margin-top:0;">How It Works</h2>
            
            <div class="process-steps">
                <div class="step">
                    <div class="step-num" style="background:#3b82f6;">1</div>
                    <h4>Create Account</h4>
                    <p>Sign up as a patient and complete your profile</p>
                </div>
                <div class="step-line" style="background: linear-gradient(to right, #3b82f6, #10b981);"></div>
                <div class="step">
                    <div class="step-num" style="background:#10b981;">2</div>
                    <h4>Find a Doctor</h4>
                    <p>Browse doctors by specialization and book an appointment</p>
                </div>
                <div class="step-line" style="background: linear-gradient(to right, #10b981, #a855f7);"></div>
                <div class="step">
                    <div class="step-num" style="background:#a855f7;">3</div>
                    <h4>Get Treatment</h4>
                    <p>Consult via chat or video and receive e-prescriptions</p>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE: LOGIN FORM -->
    <div class="login-form-side">
        <div class="container">
            <div class="logo-wrapper">
                <img src="uploads/1777630001_logo%20system.png" alt="Logo" style="width: 300px; height: 300px; object-fit: contain; border-radius: 50%; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05);">
                <h2>Welcome Back</h2>
                <p class="subtitle">Please sign in to your account</p>
            </div>

            <?php if ($success) echo "<div class='success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='error'>$error</div>"; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>

                <button type="submit">Sign In</button>
            </form>

            <div class="register">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </div>

</div>

<!-- Include FontAwesome for icons -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html>