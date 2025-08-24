<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';




if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $approvedid = $_POST['approvedid'];
    $userid = $_SESSION['user_id'];


    $getOldWinner = $conn->prepare("SELECT winner_id FROM approved_submissions WHERE approvedid = ?");
    $getOldWinner->bind_param("i", $approvedid);
    $getOldWinner->execute();
    $getOldWinner->bind_result($removedUserId);
    $getOldWinner->fetch();
    $getOldWinner->close();
    $removedUserId = (int)($removedUserId ?? 0); // may be 0 if none
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

    $nextHigheststmt->bind_param("iiii", $approvedid, $userid, $approvedid, $userid);

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
            // ✅ Case 1: There is a next highest bidder → replace winner
            $row         = $nextHighestResult->fetch_assoc();
            $bpartnerId  = (int)$row['bpartnerid'];
            $croptype    = $row['croptype'];

            $expired_at = (new DateTime())->modify('+1 hour')->format('Y-m-d H:i:s');

            // a) Set new winner & expiry
            $replaceWinnerQuery = "
                UPDATE approved_submissions
                   SET winner_id = ?, expired_at = ?
                 WHERE approvedid = ?";
            $replaceWinnerstmt = $conn->prepare($replaceWinnerQuery);
            $replaceWinnerstmt->bind_param("isi", $bpartnerId, $expired_at, $approvedid);
            $replaceWinnerstmt->execute();

            // b) Blocklist the removed winner (if any)
            if ($removedUserId > 0) {
                $blocklistQuery = "
                    INSERT INTO blocklist (userid, approvedid, created_at, updated_at)
                    VALUES (?, ?, NOW(), NOW())";
                $blockStmt = $conn->prepare($blocklistQuery);
                $blockStmt->bind_param("ii", $userid, $approvedid);
                $blockStmt->execute();
                $blockStmt->close();
            }

            // c) Notify the new winner
            $_SESSION['toast_message'] = "The highest bidder has been updated.";
            $message = 'Heads up! You’ve been updated as the highest bidder on ' . $croptype . '.';
            notify($conn, $bpartnerId, 'businessPartner', $message);

            $replaceWinnerstmt->close();
        } else {
            // ❌ Case 2: No next highest bidder → reopen transaction
            $newSellingDate = date('Y-m-d H:i:s', strtotime('+3 days'));
            $updateNoBidderQuery = "UPDATE approved_submissions
                                    SET winner_id = 0,
                                        status = 'open',
                                        sellingdate = ?
                                    WHERE approvedid = ?";

            $updateStmt = $conn->prepare($updateNoBidderQuery);
            $updateStmt->bind_param("si", $newSellingDate, $approvedid);
            if ($updateStmt->execute()) {

                $clearCropBidsQuery = "DELETE FROM crop_bids WHERE approvedid = ?";
                $clearStmt = $conn->prepare($clearCropBidsQuery);
                $clearStmt->bind_param("i", $approvedid);
                if ($clearStmt->execute()) {
                    $blocklistQuery = "
                    INSERT INTO blocklist (userid, approvedid, created_at, updated_at)
                    VALUES (?, ?, NOW(), NOW())";
                    $blockStmt = $conn->prepare($blocklistQuery);
                    $blockStmt->bind_param("ii", $userid, $approvedid);
                    $blockStmt->execute();
                    $blockStmt->close();

                    $_SESSION['toast_message'] = "No more bidders available. Submission reopened for 3 more days, bids cleared.";
                } else {
                    $_SESSION['toast_error'] = "Submission reopened, but failed to clear bids.";
                }

                $clearStmt->close();
            } else {
                $_SESSION['toast_error'] = "Failed to reopen submission.";
            }
        }

        header("Location: won_bids.php");
        exit;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
