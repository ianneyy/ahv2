<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$senderId = $_SESSION['user_id'];
$receiverId = $data['receiver_id'] ?? null;
$message = trim($data['message'] ?? '');