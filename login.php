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

<link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

<div class="container">

<?php include 'includes/logo.php'; ?>

<?php if ($success) echo "<p class='success'>$success</p>"; ?>
<?php if ($error) echo "<p class='error'>$error</p>"; ?>

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

</body>
</html>