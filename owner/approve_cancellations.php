<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id = $_POST['id'];

    // First, get the approvedid and userid from the cancel_bid
    $getCancelInfo = $conn->prepare("SELECT approvedid, userid FROM cancel_bid WHERE id = ?");
    $getCancelInfo->bind_param("i", $id);
    $getCancelInfo->execute();
    $getCancelInfo->bind_result($approvedid, $cancelUserId);
    $getCancelInfo->fetch();
    $getCancelInfo->close();

    // Get the current winner's bid amount for comparison
    $getCurrentWinner = $conn->prepare("SELECT winner_id FROM approved_submissions WHERE approvedid = ?");
    $getCurrentWinner->bind_param("i", $approvedid);
    $getCurrentWinner->execute();
    $getCurrentWinner->bind_result($currentWinnerId);
    $getCurrentWinner->fetch();
    $getCurrentWinner->close();

    // Use the same query structure as expired_transaction.php
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
    $nextHigheststmt->bind_param("iiii", $approvedid, $cancelUserId, $approvedid, $cancelUserId);
    $nextHigheststmt->execute();
    $nextHighestResult = $nextHigheststmt->get_result();

    try {
        $query = "UPDATE cancel_bid 
              SET status = 'approved', updated_at = NOW()
              WHERE id = ?";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            try {
                if ($nextHighestResult && $nextHighestResult->num_rows > 0) {
                    // ✅ Case 1: There is a next highest bidder → replace winner
                    $row = $nextHighestResult->fetch_assoc();
                    $bpartnerId = (int)$row['bpartnerid'];
                    $croptype = $row['croptype'];

                    $replaceWinnerQuery = "UPDATE approved_submissions
                     SET winner_id = ?
                      WHERE approvedid = ?
                        ";

                    $replaceWinnerstmt = $conn->prepare($replaceWinnerQuery);
                    $replaceWinnerstmt->bind_param("ii", $bpartnerId, $approvedid);
                    if ($replaceWinnerstmt->execute()) {
                        // Block the user who cancelled
                        if ($cancelUserId > 0) {
                            $blocklistQuery = "
                                INSERT INTO blocklist (userid, approvedid, created_at, updated_at)
                                VALUES (?, ?, NOW(), NOW())";
                            $blockStmt = $conn->prepare($blocklistQuery);
                            $blockStmt->bind_param("ii", $cancelUserId, $approvedid);
                            $blockStmt->execute();
                            $blockStmt->close();
                        }

                        $_SESSION['toast_message'] = "The highest bidder has been updated.";
                        $message = 'Heads up! You\'ve been updated as the highest bidder on ' . $croptype . '.';
                        notify($conn, $bpartnerId, 'businessPartner', $message);
                    } else {
                        $_SESSION['toast_error'] = "Failed to update second winner";
                    }
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


                            $_SESSION['toast_message'] = "No more bidders available. Submission reopened for 3 more days, bids cleared.";
                        } else {
                            $_SESSION['toast_error'] = "Submission reopened, but failed to clear bids.";
                        }

                        $clearStmt->close();
                        $updateStmt->close();
                    } else {
                        $_SESSION['toast_error'] = "Failed to reopen submission.";
                    }
                }
                $nextHigheststmt->close();
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            }
        } else {
            $_SESSION['toast_error'] = "Failed to reject the request.";
        }
        header("Location: bid_cancellations.php");
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
