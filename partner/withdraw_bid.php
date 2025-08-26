<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userid = $_SESSION['user_id'];
    $approvedid = $_POST['approvedid'];
    $winningbidprice = $_POST['winningbidprice'];
    $reason = $_POST['reason'];

    $username = "SELECT name FROM users WHERE id = ?";
    $usernameStmt = $conn->prepare($username);
    $usernameStmt->bind_param("i", $userid);
    $usernameStmt->execute();


    $usernameResult = $usernameStmt->get_result();
    if ($row = $usernameResult->fetch_assoc()) {
        $name = $row['name'];  // this is the username
    } else {
        $name = null; // no user found
    }

    $croptypeQuery = "SELECT croptype FROM approved_submissions
    WHERE approvedid = ?
    ";
    $croptypeStmt = $conn->prepare($croptypeQuery);
    $croptypeStmt->bind_param("i", $approvedid);
    $croptypeStmt->execute();

    $croptypeResult = $croptypeStmt->get_result();
    if ($croptypeRow = $croptypeResult->fetch_assoc()) {
        $croptype = $croptypeRow['croptype'];  // this is the username
    } else {
        $croptype = null; // no user found
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        $query = "
    INSERT INTO cancel_bid (approvedid, userid, reason, created_at, updated_at)
    VALUES (?, ?, ?, NOW(), NOW())
";



        $stmt = $conn->prepare($query);
        $stmt->bind_param("iis", $approvedid, $userid, $reason);
        $stmt->execute();
        // $croptypeResult = $stmt->get_result();


        if ($stmt->affected_rows > 0) {
            // echo "✅ Bid cancellation inserted successfully.";
            $message = $name . ' has cancelled their offer for ' . $croptype . '. Please review and take action.';
            sendNotificationToUserType($conn, 'businessOwner', $message);
            $_SESSION['toast_message'] = "Your bid cancellation has been sent. Waiting for the owner’s response.";
        } else {
            echo "⚠️ Insert failed, no rows affected.";

            // $_SESSION['toast_message'] = "Bid cancellation inserted successfully.";
        }
        header("Location: won_bids.php");



    } catch (Exception $e) {
        echo "❌ Error inserting bid cancellation: " . $e->getMessage();
    }




}
