<?php
session_start();
include 'config.php';

// Support both doctor and patient sessions (both use 'user_id' after login)
if (!isset($_SESSION['user_id'])) {
    exit();
}

$current_user_id = $_SESSION['user_id'];
$contact_id = isset($_GET['contact_id']) ? (int)$_GET['contact_id'] : 0;

if ($contact_id === 0) {
    exit();
}

// Fetch messages between the current user and the contact
$stmt = $conn->prepare("
    SELECT * FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?) 
    ORDER BY created_at ASC
");
$stmt->bind_param("iiii", $current_user_id, $contact_id, $contact_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $isMe = ($row['sender_id'] == $current_user_id);
        $bubbleClass = $isMe ? 'me' : 'other';
        $time = date('h:i A', strtotime($row['created_at']));
        $message = htmlspecialchars($row['message']);

        $bg     = $isMe ? 'var(--accent-blue, #3b82f6)' : '#1e293b';
        $align  = $isMe ? 'flex-end' : 'flex-start';
        $color  = $isMe ? 'white' : '#cbd5e1';
        $radius = $isMe ? '12px 12px 2px 12px' : '12px 12px 12px 2px';

        echo "<div class='msg-row $bubbleClass' style='display: flex; flex-direction: column; width: 100%; margin-bottom: 12px;'>";
        echo "<div class='bubble' style='max-width: 60%; padding: 12px 18px; border-radius: $radius; font-size: 14px; background: $bg; align-self: $align; color: $color; line-height: 1.5; word-break: break-word;'>";
        echo $message;
        echo "</div>";
        echo "<div class='msg-time' style='font-size: 10px; color: #94a3b8; margin-top: 4px; align-self: $align;'>$time</div>";
        echo "</div>";
    }
} else {
    echo "<div style='text-align: center; color: #94a3b8; padding: 40px 20px; font-size: 14px;'>
            <i class='far fa-comment-dots' style='font-size: 28px; margin-bottom: 10px; display: block; color: #334155;'></i>
            No messages yet. Start the conversation!
          </div>";
}
$stmt->close();
?>
