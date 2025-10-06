<?php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';
// Sessions are not required for the websocket server; authentication is via query param

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App;

class ChatServer implements MessageComponentInterface
{
    protected $clients;
    protected $userConnections; // user_id → connection

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->userConnections = [];
        echo "Chat server started...\n";
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $query);
        $userId = $query['user_id'] ?? null;

        if ($userId) {
            $this->clients->attach($conn);
            $this->userConnections[$userId] = $conn;
            $conn->userId = $userId;
            echo "User $userId connected\n";
        } else {
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        global $conn; // from db.php
        $data = json_decode($msg, true);

        if (!isset($data['receiver_id']) || !isset($data['message']))
            return;

        $senderId = $from->userId;
        $receiverId = $data['receiver_id'];
        $message = trim($data['message']);

        // Save to DB
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, `message_read`) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("iis", $senderId, $receiverId, $message);
        $stmt->execute();
        $messageId = $conn->insert_id;

        // Prepare JSON payload
        $payload = json_encode([
            'id' => (int)$messageId,
            'sender_id' => (int)$senderId,
            'receiver_id' => (int)$receiverId,
            'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Send to receiver (if online)
        if (isset($this->userConnections[$receiverId])) {
            $this->userConnections[$receiverId]->send($payload);
        }

        // Also echo back to sender
        $from->send($payload);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
        if (isset($conn->userId)) {
            unset($this->userConnections[$conn->userId]);
            echo "User {$conn->userId} disconnected\n";
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

$app = new App('localhost', 8080);
$app->route('/chat', new ChatServer, ['*']);
$app->run();
