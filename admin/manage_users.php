<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$role_view = $_GET['role'] ?? 'patient';

// FETCH PATIENTS COUNT
$patient_count = $conn->query("SELECT COUNT(*) as t FROM users WHERE role='patient'")->fetch_assoc()['t'] ?? 0;
// FETCH DOCTORS COUNT
$doctor_count = $conn->query("SELECT COUNT(*) as t FROM users WHERE role='doctor'")->fetch_assoc()['t'] ?? 0;

// SEARCH LOGIC
$search = $_GET['q'] ?? '';
$search_query = "";
if (!empty($search)) {
    $search_query = " AND (name LIKE '%$search%' OR email LIKE '%$search%') ";
}

// FETCH USERS
$users_res = $conn->query("SELECT * FROM users WHERE role='$role_view' $search_query ORDER BY name ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin_manage_users.css">
</head>
<body>

<?php include '../includes/admin_sidebar.php'; ?>

<main class="main">
    <div class="page-header">
        <h1>Manage Users</h1>
        <p>Add, edit, or remove users from the system</p>
    </div>

    <!-- TABS -->
    <div class="tabs-container">
        <button onclick="location.href='?role=patient'" class="tab-btn <?php echo $role_view == 'patient' ? 'active' : ''; ?>">
            <i class="fas fa-user-injured"></i> Patients (<?php echo $patient_count; ?>)
        </button>
        <button onclick="location.href='?role=doctor'" class="tab-btn <?php echo $role_view == 'doctor' ? 'active' : ''; ?>">
            <i class="fas fa-user-md"></i> Doctors (<?php echo $doctor_count; ?>)
        </button>
    </div>

    <!-- SEARCH & ACTION -->
    <div class="search-container">
        <form class="search-input-wrapper">
            <i class="fas fa-search"></i>
            <input type="hidden" name="role" value="<?php echo $role_view; ?>">
            <input type="text" name="q" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
        </form>
        <?php if ($role_view == 'doctor'): ?>
            <button class="btn-add"><i class="fas fa-plus"></i> Add New Doctor</button>
        <?php endif; ?>
    </div>

    <!-- USER LIST -->
    <div class="user-list-container">
        <div class="list-title">All <?php echo ucfirst($role_view); ?>s</div>
        
        <?php if ($users_res && $users_res->num_rows > 0): ?>
            <?php while($user = $users_res->fetch_assoc()): 
                $photo = !empty($user['photo']) ? "../uploads/".$user['photo'] : "https://ui-avatars.com/api/?name=".urlencode($user['name'])."&background=random&color=fff";
            ?>
            <div class="user-item">
                <div class="user-info">
                    <div class="user-avatar">
                        <img src="<?php echo $photo; ?>" alt="avatar">
                    </div>
                    <div class="user-details">
                        <div class="name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <?php if ($role_view == 'patient'): ?>
                            <div class="meta">
                                <?php echo $user['age'] ?? 'N/A'; ?> years • <?php echo $user['gender'] ?? 'N/A'; ?> • 
                                Last visit: <?php echo date('Y-m-d'); // Placeholder logic ?>
                            </div>
                        <?php else: ?>
                            <div class="meta">General Practitioner • 8 years • ⭐ 4.9</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="user-actions">
                    <span class="status-pill">Active</span>
                    <i class="far fa-edit action-btn" title="Edit"></i>
                    <i class="far fa-trash-alt action-btn btn-delete" title="Delete"></i>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:3rem; color:var(--text-muted);">
                <i class="fas fa-user-slash" style="font-size:2.5rem; display:block; margin-bottom:1rem;"></i>
                No users found matching your search.
            </div>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
