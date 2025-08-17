<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST['id'];
    // var_dump($id);

    $nextHighest = "
    SELECT crop_bids.*, crop_bids.bpartnerid , users.name, approved_submissions.croptype
    FROM crop_bids
    JOIN approved_submissions ON crop_bids.approvedid = approved_submissions.approvedid
    JOIN cancel_bid ON crop_bids.approvedid = cancel_bid.approvedid
    JOIN users ON crop_bids.bpartnerid = users.id
    WHERE cancel_bid.id = ?
    AND crop_bids.bpartnerid != cancel_bid.userid
    ORDER BY crop_bids.bidamount DESC
    LIMIT 1
    ";

    $nextHigheststmt = $conn->prepare($nextHighest);

    $nextHigheststmt->bind_param("i", $id);

    $nextHigheststmt->execute();

    $nextHighestResult = $nextHigheststmt->get_result();

    // $row = $nextHighestResult->fetch_assoc();

    if ($nextHighestResult && $row = $nextHighestResult->fetch_assoc()) {
        $bpartnerId = $row['bpartnerid'];
        // use $bpartnerId here
        var_dump($bpartnerId);

    }
    $croptype = $row['croptype'];
    $approvedid = $row['approvedid'];

    // $data = $nextHighestResult->fetch_all(MYSQLI_ASSOC);


    // echo "<pre>";
    // var_dump($approvedid); // Dumps the actual rows
    // echo "</pre>";
    // $nextHigheststmt->close();
    // die;



    try {
        $query = "UPDATE cancel_bid 
              SET status = 'approved', updated_at = NOW()
              WHERE id = ?";


        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);


        if ($stmt->execute()) {
            // echo "Cancel request rejected successfully.";
            try {
                $replaceWinnerQuery = "UPDATE approved_submissions
                 SET winner_id = ?
                  WHERE approvedid = ?
                    ";

                $replaceWinnerstmt = $conn->prepare($replaceWinnerQuery);
                $replaceWinnerstmt->bind_param("ii", $bpartnerId, $approvedid);
                if ($replaceWinnerstmt->execute()) {
                    $_SESSION['toast_message'] = "The highest bidder has been updated.";
                    $message = 'Heads up! You’ve been updated as the highest bidder on '. $croptype . '.';
                    notify($conn, $bpartnerId , 'businessPartner', $message);
                    
                } else {
                    $_SESSION['toast_error'] = "Failed to update second winner";
                }

            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
                // $_SESSION['toast_error'] = "Error:" . $e->getMessage();
            }



        } else {
            $_SESSION['toast_error'] = "Failed to reject the request.";
        }
        header("Location: bid_cancellations.php");


    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>