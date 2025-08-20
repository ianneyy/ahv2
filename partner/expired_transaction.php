<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';




if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $approvedid = $_POST['approvedid'];
    $userid = $_SESSION['user_id'];
    // var_dump($id);
    $nextHighest = "
    SELECT approved_submissions.*, crop_bids.*, users.name
    FROM approved_submissions
    JOIN crop_bids ON approved_submissions.approvedid = crop_bids.approvedid
     JOIN users ON crop_bids.bpartnerid = users.id
    WHERE approved_submissions.approvedid = ?
    AND crop_bids.bpartnerid != ? 
     AND crop_bids.bidamount < (
          SELECT bidamount 
          FROM crop_bids 
          WHERE approvedid = ? 
            AND bpartnerid = ?
      )
     ORDER BY crop_bids.bidamount DESC
    LIMIT 1
    
    ";

    $nextHigheststmt = $conn->prepare($nextHighest);

    $nextHigheststmt->bind_param("iiii", $approvedid, $userid,  $approvedid, $userid);

    $nextHigheststmt->execute();

    $nextHighestResult = $nextHigheststmt->get_result();


    // $data = $nextHighestResult->fetch_all(MYSQLI_ASSOC);
    // echo "<pre>";
    // var_dump($data); // Dumps the actual rows
    // echo "</pre>";
    // $nextHigheststmt->close();
    // die;
    // mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    try {
        if ($nextHighestResult && $nextHighestResult->num_rows > 0) {
            $row = $nextHighestResult->fetch_assoc();
            $bpartnerId = $row['bpartnerid'];
            $croptype   = $row['croptype'];
            $approvedid = $row['approvedid'] ?? 0;

            $expired_at = new DateTime(); // fresh time, not old one
            $expired_at->modify('+1 hour');
            $expiredAtFormatted = $expired_at->format('Y-m-d H:i:s');

            if ($approvedid != 0) {
                $replaceWinnerQuery = "UPDATE approved_submissions
                                    SET winner_id = ?, expired_at = ?
                                    WHERE approvedid = ?";
                $replaceWinnerstmt = $conn->prepare($replaceWinnerQuery);
                $replaceWinnerstmt->bind_param("isi", $bpartnerId, $expiredAtFormatted, $approvedid);
                if ($replaceWinnerstmt->execute()) {
                    $_SESSION['toast_message'] = "The highest bidder has been updated.";
                    $message = 'Heads up! You’ve been updated as the highest bidder on ' . $croptype . '.';
                    notify($conn, $bpartnerId, 'businessPartner', $message);
                } else {
                    $_SESSION['toast_error'] = "Failed to update second winner";
                }
            }
            header("Location: won_bids.php");
            exit;
        } else {
            // no next highest bidder
            $_SESSION['toast_error'] = "No more bidders available. Transaction closed.";
            header("Location: won_bids.php");
            exit;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
