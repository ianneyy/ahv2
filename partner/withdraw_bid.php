<?php
require_once '../includes/session.php';
require_once '../includes/db.php';


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userid = $_SESSION['user_id'];
    $approvedid = $_POST['approvedid'];
    $winningbidprice = $_POST['winningbidprice'];
    $reason = $_POST['reason'];


  
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
    $query = "
    INSERT INTO cancel_bid (approvedid, userid, reason, created_at, updated_at)
    VALUES (?, ?, ?, NOW(), NOW())
";



    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $approvedid, $userid, $reason);
    $stmt->execute();
        if ($stmt->affected_rows > 0) {
            // echo "✅ Bid cancellation inserted successfully.";
            header("Location: won_bids.php");
            $_SESSION['toast_message'] = "Your bid cancellation has been sent. Waiting for the owner’s response.";
        } else {
            echo "⚠️ Insert failed, no rows affected.";

            // $_SESSION['toast_message'] = "Bid cancellation inserted successfully.";
        }



    } catch (Exception $e) {
        echo "❌ Error inserting bid cancellation: " . $e->getMessage();
    }



   
}
