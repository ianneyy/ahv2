<?php
require_once '../includes/db.php';
require_once '../includes/notify.php'; // Add this line if not already
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionId = $_POST['transactionid'];
    $file = $_FILES['payment_proof'];

    // Validate file
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/payment_proofs/';
        $filename = uniqid('proof_', true) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $targetPath = $uploadDir . $filename;

        // Move file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // ✅ Update DB with payment proof + status
            $stmt = $conn->prepare("UPDATE transactions SET payment_proof = ?, status = 'awaiting_verification' WHERE transactionid = ?");
            $stmt->bind_param("si", $filename, $transactionId);
            $stmt->execute();

            // ✅ Send Notification to Business Owner(s)
            date_default_timezone_set('Asia/Manila');
            $dateNow = date('M j, Y g:iA');
            $_SESSION['toast_message'] = "Payment proof submitted successfully!";
            $message = "🧾 A payment proof for Transaction #$transactionId was submitted. ($dateNow)";
            sendNotificationToUserType($conn, 'businessOwner', $message);

            header("Location: won_bids.php?success=1");
            exit;
        } else {
            die("Error uploading file.");
        }
    } else {
        die("Upload error: " . $file['error']);
    }
}
?>
