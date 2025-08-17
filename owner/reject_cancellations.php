<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST['id'];
    $reason = $_POST['reason'];

    // var_dump($reason);
    try {
        $query = "UPDATE cancel_bid 
              SET status = 'rejected', updated_at = NOW(), rej_reason = ?
              WHERE id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $reason,$id);

        if ($stmt->execute()) {
            // echo "Cancel request rejected successfully.";
            $_SESSION['toast_message'] = "You’ve rejected the cancel request. The bidder has been notified.";
            $message = 'Your cancellation request has been rejected. You are still responsible for completing your winning bid.';
            sendNotificationToUserType($conn, 'businessPartner', $message);
            header("Location: bid_cancellations.php");

        } else {
            echo "❌ Failed to reject the request.";
        }

        $stmt->close();
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }


}

?>