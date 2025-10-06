<?php
require_once '../includes/session.php';
require_once '../includes/db.php';


if (!isset($_SESSION['user_id']) || !isset($_GET['user_id'])) {
    echo json_encode([]);
    exit;
}


$currentUserId = $_SESSION['user_id'];
$otherUserId = (int)$_GET['user_id'];

$stmt = $conn->prepare("
    SELECT id, sender_id, receiver_id, message, created_at
    FROM messages
    WHERE 
        (sender_id = ? AND receiver_id = ?)
        OR
        (sender_id = ? AND receiver_id = ?)
    ORDER BY created_at ASC
");
$stmt->bind_param("iiii", $currentUserId, $otherUserId, $otherUserId, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => (int)$row['id'],
        'text' => $row['message'],
        'sender' => $row['sender_id'] == $currentUserId ? 'me' : 'them',
        
    ];
}
$unreadCounts = [];
while ($row = $result->fetch_assoc()) {
    $unreadCounts[$row['sender_id']] = (int)$row['unread_count'];
}

echo json_encode($messages);
