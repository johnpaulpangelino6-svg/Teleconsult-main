<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../login.php");
    exit();
}

$doctor_id   = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor';
$doctor_photo = !empty($_SESSION['user_photo']) ? $_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($doctor_name)."&background=0ea5e9&color=fff";

$contact_id = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;

// Handle Message Sending via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST" && $contact_id > 0) {
    $msg = trim($_POST['message'] ?? '');
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $doctor_id, $contact_id, $msg);
        $stmt->execute();
        $stmt->close();
    }
    exit();
}

// Fetch all patients ordered by conversation activity
$contacts_stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, u.photo,
        CASE WHEN EXISTS (
            SELECT 1 FROM messages m
            WHERE (m.sender_id = ? AND m.receiver_id = u.id)
               OR (m.sender_id = u.id AND m.receiver_id = ?)
        ) THEN 1 ELSE 0 END AS has_convo,
        (SELECT MAX(m2.created_at) FROM messages m2
         WHERE (m2.sender_id = ? AND m2.receiver_id = u.id)
            OR (m2.sender_id = u.id AND m2.receiver_id = ?)
        ) AS last_msg
    FROM users u
    WHERE u.role = 'patient'
    ORDER BY has_convo DESC, last_msg DESC, u.name ASC
");
$contacts_stmt->bind_param("iiii", $doctor_id, $doctor_id, $doctor_id, $doctor_id);
$contacts_stmt->execute();
$contacts_res = $contacts_stmt->get_result();

// Get contact name
$contact_name = '';
if ($contact_id > 0) {
    $ns = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'patient'");
    $ns->bind_param("i", $contact_id);
    $ns->execute();
    $nr = $ns->get_result()->fetch_assoc();
    $contact_name = $nr['name'] ?? '';
    $ns->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Community Teleconsult</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/doctor_chat.css">
</head>
<body>

<?php include '../includes/doctor_sidebar.php'; ?>

<!-- FIXED CONTACTS SIDEBAR -->
<div class="contacts-sidebar">
    <div class="contacts-header">
        <h2>Messages</h2>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search patients...">
        </div>
    </div>
    <div class="contacts-list" id="contactsList">
        <?php
        $has_contacts = false;
        while ($row = $contacts_res->fetch_assoc()):
            $has_contacts = true;
            $sel = ($contact_id == $row['id']) ? 'selected' : '';
        ?>
        <a href="chat.php?contact_id=<?php echo $row['id']; ?>"
           class="contact-item <?php echo $sel; ?>"
           data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>">
            <div class="c-avatar">
                <?php if (!empty($row['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($row['photo']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
                <?php if ($row['has_convo']): ?><div class="c-dot"></div><?php endif; ?>
            </div>
            <div class="c-info">
                <div class="c-name"><?php echo htmlspecialchars($row['name']); ?></div>
                <div class="c-sub"><?php echo $row['has_convo'] ? '💬 Active conversation' : 'Patient'; ?></div>
            </div>
        </a>
        <?php endwhile; ?>
        <?php if (!$has_contacts): ?>
        <div style="padding:30px 20px; text-align:center; color:var(--text-dim); font-size:13px;">
            <i class="fas fa-users" style="font-size:24px; display:block; margin-bottom:10px;"></i>
            No patients registered yet.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- FIXED CHAT AREA -->
<div class="chat-area">
    <?php if ($contact_id > 0 && !empty($contact_name)): ?>
        <div class="chat-header">
            <div class="ch-left">
                <div class="ch-avatar"><i class="fas fa-user" style="color:white; font-size:15px;"></i></div>
                <div>
                    <div class="ch-name"><?php echo htmlspecialchars($contact_name); ?></div>
                    <div class="ch-status">Online</div>
                </div>
            </div>
            <div class="ch-actions">
                <i class="fas fa-phone" title="Voice Call"></i>
                <i class="fas fa-video" title="Video Call"></i>
                <i class="fas fa-ellipsis-v"></i>
            </div>
        </div>

        <div class="messages-list" id="messages"></div>

        <div class="chat-input-area">
            <form id="chatForm" class="input-wrapper">
                <i class="fas fa-paperclip inp-icon" title="Attach"></i>
                <input type="text" id="message" placeholder="Type your message..." required autocomplete="off">
                <button type="submit" class="send-btn"><i class="fas fa-paper-plane" style="font-size:14px;"></i></button>
            </form>
        </div>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="far fa-comments"></i></div>
            <h3>Select a patient to chat</h3>
            <p>Choose a patient from the list to start or continue a consultation.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    const contact_id = <?php echo $contact_id; ?>;
    const msgBox = document.getElementById('messages');

    function loadMessages() {
        if (!contact_id || !msgBox) return;
        const atBottom = msgBox.scrollTop + msgBox.clientHeight >= msgBox.scrollHeight - 60;
        fetch('../load_messages.php?contact_id=' + contact_id)
            .then(r => r.text())
            .then(html => {
                msgBox.innerHTML = html;
                if (atBottom) msgBox.scrollTop = msgBox.scrollHeight;
            })
            .catch(e => console.error(e));
    }

    document.getElementById('chatForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const inp = document.getElementById('message');
        const val = inp.value.trim();
        if (!val) return;
        inp.value = '';
        fetch('chat.php?contact_id=' + contact_id, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(val)
        }).then(() => loadMessages()).catch(e => console.error(e));
    });

    document.getElementById('searchInput')?.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.contact-item').forEach(el => {
            el.style.display = (el.dataset.name || '').includes(q) ? 'flex' : 'none';
        });
    });

    if (contact_id > 0) {
        loadMessages();
        setInterval(loadMessages, 2500);
    }
</script>
</body>
</html>