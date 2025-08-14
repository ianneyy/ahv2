<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $bpartnerid = $_SESSION['user_id'];
    $approvedid = $_POST['approvedid'];
    $winningbidprice = $_POST['winningbidprice'];

    // Check if a transaction already exists for this approvedid and bpartnerid
    $check = "SELECT * FROM transactions WHERE approvedid = ? AND bpartnerid = ?";
    $check_stmt = $conn->prepare($check);
    $check_stmt->bind_param("ii", $approvedid, $bpartnerid);
    $check_stmt->execute();
    $existing = $check_stmt->get_result();

    if ($existing->num_rows > 0) {
        echo "Transaction already exists.";
        exit();
    }

    // Get crop details from approved_submissions
    $sql = "SELECT farmerid, quantity FROM approved_submissions WHERE approvedid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $approvedid);
    $stmt->execute();
    $result = $stmt->get_result();
    $approved = $result->fetch_assoc();

    if ($approved) {
        $farmerid = $approved['farmerid'];
        $quantity = $approved['quantity'];

        // Insert into transactions
        $insert = "INSERT INTO transactions (approvedid, bpartnerid, farmerid, quantity, winningbidprice)
                   VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert);
        $insert_stmt->bind_param("iiiid", $approvedid, $bpartnerid, $farmerid, $quantity, $winningbidprice);

        if ($insert_stmt->execute()) {
            // Step 1: Get base price from approved_submissions - farmer
            $base_stmt = $conn->prepare("SELECT baseprice FROM approved_submissions WHERE approvedid = ?");
            $base_stmt->bind_param("i", $approvedid);
            $base_stmt->execute();
            $base_result = $base_stmt->get_result();
            $base_data = $base_result->fetch_assoc();
            $baseprice = $base_data ? $base_data['baseprice'] : 0;
            $base_stmt->close();

            // Step 2: Get Business Partner's Name
            $bp_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $bp_stmt->bind_param("i", $bpartnerid);
            $bp_stmt->execute();
            $bp_result = $bp_stmt->get_result();
            $bp_data = $bp_result->fetch_assoc();
            $bp_name = $bp_data ? $bp_data['name'] : 'a business partner';
            $bp_stmt->close();

            // Step 3: Format message for farmer
            $message = "Your crop has received a winning bid from {$bp_name}.\n";
            $message .= "Base Price: ₱" . number_format($baseprice, 2) . "\n";
            $message .= "Winning Bid: ₱" . number_format($winningbidprice, 2);

            // Step 4: Notify Farmer
            $notify_stmt = $conn->prepare("INSERT INTO notifications (userid, user_type, message) VALUES (?, 'farmer', ?)");
            $notify_stmt->bind_param("is", $farmerid, $message);
            $notify_stmt->execute();
            $notify_stmt->close();

            // Step 5: Get Crop Type - business partner
            $crop_stmt = $conn->prepare("SELECT croptype FROM approved_submissions WHERE approvedid = ?");
            $crop_stmt->bind_param("i", $approvedid);
            $crop_stmt->execute();
            $crop_result = $crop_stmt->get_result();
            $crop_data = $crop_result->fetch_assoc();
            $croptype = $crop_data ? $crop_data['croptype'] : 'a crop';
            $crop_stmt->close();

            // Step 6: Notify Business Partner
            $bp_message = "🎉 You’ve officially won the bid for {$croptype}!\nPlease proceed to submit your payment proof.";
            $bp_notify = $conn->prepare("INSERT INTO notifications (userid, user_type, message) VALUES (?, 'businessPartner', ?)");
            $bp_notify->bind_param("is", $bpartnerid, $bp_message);
            $bp_notify->execute();
            $bp_notify->close();

            $_SESSION['toast_message'] = "This crop is now yours. Please proceed by uploading your proof of payment";
            // $message = "🧾 A payment proof for Transaction #$transactionId was submitted. ($dateNow)";

            // Redirect after successful transaction and notification
            header("Location: wond_bids.php");
            exit();
        } else {
            echo "Failed to insert transaction: " . $insert_stmt->error;
        }
    } else {
        echo "Invalid approved submission.";
    }
}
?>
