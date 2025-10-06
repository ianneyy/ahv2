<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$currentUserId = $_SESSION['user_id'] ?? null;
if (!$currentUserId) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    return;
}

$input = json_decode(file_get_contents('php://input'), true);
$otherId = isset($input['other_id']) ? (int)$input['other_id'] : 0;
if ($otherId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_other_id']);
    return;
}

$stmt = $conn->prepare("UPDATE messages SET `message_read` = 1 WHERE sender_id = ? AND receiver_id = ? AND `message_read` = 0");
$stmt->bind_param('ii', $otherId, $currentUserId);
$stmt->execute();

echo json_encode(['ok' => true, 'updated' => $stmt->affected_rows]);
