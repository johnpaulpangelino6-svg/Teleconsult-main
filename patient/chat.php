<?php
session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../login.php");
    exit();
}

$user_id    = $_SESSION['user_id'];
$user_name  = $_SESSION['user_name'] ?? 'Patient';
$user_photo = !empty($_SESSION['user_photo']) ? $_SESSION['user_photo'] : "https://ui-avatars.com/api/?name=".urlencode($user_name)."&background=3b82f6&color=fff";

$contact_id = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;

// Handle Sending
if ($_SERVER["REQUEST_METHOD"] == "POST" && $contact_id > 0) {
    $msg = trim($_POST['message'] ?? '');
    if (!empty($msg)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $contact_id, $msg);
        $stmt->execute();
        $stmt->close();
    }
    exit();
}

// Fetch doctors ordered by conversation activity
$ds = $conn->prepare("
    SELECT u.id, u.name, u.photo,
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
    WHERE u.role = 'doctor'
    ORDER BY has_convo DESC, last_msg DESC, u.name ASC
");
$ds->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$ds->execute();
$doctors_res = $ds->get_result();

// Get contact (doctor) name
$contact_name = '';
if ($contact_id > 0) {
    $ns = $conn->prepare("SELECT name FROM users WHERE id = ? AND role = 'doctor'");
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
    <link rel="stylesheet" href="../assets/css/chat.css">
</head>
<body>

<?php include '../includes/patient_sidebar.php'; ?>

<!-- FIXED CONTACTS SIDEBAR -->
<div class="contacts-sidebar">
    <div class="contacts-header">
        <h2>Messages</h2>
        <div class="search-bar">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search doctors...">
        </div>
    </div>
    <div class="contacts-list" id="contactsList">
        <?php
        $has_docs = false;
        while ($row = $doctors_res->fetch_assoc()):
            $has_docs = true;
            $sel = ($contact_id == $row['id']) ? 'selected' : '';
        ?>
        <a href="chat.php?contact_id=<?php echo $row['id']; ?>"
           class="contact-item <?php echo $sel; ?>"
           data-name="<?php echo strtolower(htmlspecialchars($row['name'])); ?>">
            <div class="c-avatar">
                <?php if (!empty($row['photo'])): ?>
                    <img src="<?php echo htmlspecialchars($row['photo']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <i class="fas fa-user-md"></i>
                <?php endif; ?>
                <?php if ($row['has_convo']): ?><div class="c-dot"></div><?php endif; ?>
            </div>
            <div class="c-info">
                <div class="c-name">Dr. <?php echo htmlspecialchars($row['name']); ?></div>
                <div class="c-sub"><?php echo $row['has_convo'] ? '💬 Active conversation' : 'Doctor'; ?></div>
            </div>
        </a>
        <?php endwhile; ?>
        <?php if (!$has_docs): ?>
        <div style="padding:30px 20px; text-align:center; color:var(--text-dim); font-size:13px;">
            <i class="fas fa-user-md" style="font-size:24px; display:block; margin-bottom:10px;"></i>
            No doctors available.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- FIXED CHAT AREA -->
<div class="chat-area">
    <?php if ($contact_id > 0 && !empty($contact_name)): ?>
        <div class="chat-header">
            <div class="ch-left">
                <div class="ch-avatar"><i class="fas fa-user-md" style="color:white; font-size:15px;"></i></div>
                <div>
                    <div class="ch-name">Dr. <?php echo htmlspecialchars($contact_name); ?></div>
                    <div class="ch-status">Online</div>
                </div>
            </div>
            <div class="ch-actions">
                <i class="fas fa-phone" title="Voice Call"></i>
                <i class="fas fa-video" title="Video Call"></i>
                <i class="fas fa-ellipsis-v"></i>
            </div>
        </div>

        <div class="messages-list" id="msgList"></div>

        <div class="chat-input-area">
            <form id="chatForm" class="input-wrapper">
                <i class="fas fa-paperclip inp-icon" title="Attach"></i>
                <input type="text" id="msgInput" placeholder="Type your message..." required autocomplete="off">
                <button type="submit" class="send-btn"><i class="fas fa-paper-plane" style="font-size:14px;"></i></button>
            </form>
        </div>

    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="far fa-comments"></i></div>
            <h3>Select a doctor to chat</h3>
            <p>Choose a doctor from the list to start a conversation or request a consultation.</p>
        </div>
    <?php endif; ?>
</div>

<script>
    const contact_id = <?php echo $contact_id; ?>;
    const msgList = document.getElementById('msgList');

    function loadMessages() {
        if (!contact_id || !msgList) return;
        const atBottom = msgList.scrollTop + msgList.clientHeight >= msgList.scrollHeight - 60;
        fetch('../load_messages.php?contact_id=' + contact_id)
            .then(r => r.text())
            .then(html => {
                msgList.innerHTML = html;
                if (atBottom) msgList.scrollTop = msgList.scrollHeight;
            })
            .catch(e => console.error(e));
    }

    document.getElementById('chatForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const inp = document.getElementById('msgInput');
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