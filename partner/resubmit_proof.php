<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transactionid']) && isset($_FILES['payment_proof'])) {
    $transactionId = $_POST['transactionid'];

    // File validation
    $file = $_FILES['payment_proof'];
    if ($file['error'] !== 0) {
        echo 'Error uploading file.';
        exit;
    }

    // Get old filename (if any)
    $stmt = $conn->prepare("SELECT payment_proof FROM transactions WHERE transactionid = ?");
    $stmt->bind_param("i", $transactionId);
    $stmt->execute();
    $stmt->bind_result($oldFile);
    $stmt->fetch();
    $stmt->close();

    // Remove old file if it exists
    if ($oldFile && file_exists("../assets/payment_proofs/$oldFile")) {
        unlink("../assets/payment_proofs/$oldFile");
    }

    // Rename new file using transaction ID
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFileName = 'proof_' . $transactionId . '_' . uniqid() . '.' . $ext;
    $uploadPath = '../assets/payment_proofs/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo 'Failed to move uploaded file.';
        exit;
    }

    // Update DB
    $stmt = $conn->prepare("UPDATE transactions SET payment_proof = ?, status = 'awaiting_verification', verifiedat = NULL, rejectionreason = NULL WHERE transactionid = ?");
    $stmt->bind_param("si", $newFileName, $transactionId);

    if ($stmt->execute()) {
        header("Location: won_bids.php?success=1");
        exit;
    } else {
        echo 'Database update failed.';
        exit;
    }
} else {
    echo 'Invalid request.';
    exit;
}
