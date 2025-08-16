<?php
require_once '../includes/session.php';
require_once '../includes/db.php';


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userid = $_SESSION['user_id'];
    $approvedid = $_POST['approvedid'];
    $winningbidprice = $_POST['winningbidprice'];
    $reason = $_POST['reason'];


    // echo "<pre>";
    // print_r([
    //     'userid'      => $userid,
    //     'approvedid'      => $approvedid,
    //     'winningbidprice' => $winningbidprice,
    //     'reason' => $reason,
    //     // 'query_result'    => $data,
    // ]);
    // echo "</pre>";
    // var_dump($bpartnerid, $approvedid, $winningbidprice);
    // exit;

    // $query = "SELECT * FROM approved_submissions
    //  JOIN crop_bids ON approved_submissions.approvedid = crop_bids.approvedid
    //     WHERE approved_submissions.approvedid = ? 
    //     GROUP BY crop_bids.bidamount DESC
    //  ";
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
