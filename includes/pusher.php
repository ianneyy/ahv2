<?php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use Pusher\Pusher;

// Pusher config
$options = [
    'cluster' => 'ap1', // e.g., 'ap1'
    'useTLS' => true
];

$pusher = new Pusher(
    '4fc0a68218e1989eb11f',      // App Key
    'e20a43cdbbe88989edd4',   // App Secret
    '2085797',       // App ID
    $options
);

// Get POST request from client
// Expected JSON: { "sender_id": 1, "receiver_id": 2, "message": "Hello" }
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['sender_id'], $data['receiver_id'], $data['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$senderId = (int) $data['sender_id'];
$receiverId = (int) $data['receiver_id'];
$message = trim($data['message']);

// Save to DB
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, `message_read`) VALUES (?, ?, ?, 0)");
$stmt->bind_param("iis", $senderId, $receiverId, $message);
$stmt->execute();
$messageId = $conn->insert_id;

// Prepare payload
$payload = [
    'id' => (int) $messageId,
    'sender_id' => $senderId,
    'receiver_id' => $receiverId,
    'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
    'created_at' => date('Y-m-d H:i:s')
];

// Trigger event on Pusher
$channel = "chat_{$receiverId}";  // Each user has their own channel
$event = "new_message";
$pusher->trigger($channel, $event, $payload);

// Optionally, respond back to sender with the same payload
echo json_encode($payload);
