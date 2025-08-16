<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';


if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessPartner') {
    header("Location: ../auth/login.php");
    exit();
}

$partnerId = $_SESSION['user_id'];
$approvedId = $_POST['approvedid'] ?? null;
$bidAmount = $_POST['bidamount'] ?? null;

// Input validation
if (!$approvedId || !$bidAmount || !is_numeric($bidAmount)) {
    die("❌ Invalid bid.");
}

// Fetch crop details
$stmt = $conn->prepare("SELECT baseprice, sellingdate FROM approved_submissions WHERE approvedid = ?");
$stmt->bind_param("i", $approvedId);
$stmt->execute();
$result = $stmt->get_result();
$crop = $result->fetch_assoc();
$stmt->close();

if (!$crop) {
    die("❌ Crop not found.");
}

// Check if bidding is still open (at least 1 hour before selling time)
$currentTime = new DateTime();
$sellingDate = new DateTime($crop['sellingdate']);
$cutoffTime = clone $sellingDate;
$cutoffTime->modify('-1 hour');

if ($currentTime > $cutoffTime) {
    die("❌ Bidding has closed for this crop.");
}

// Get current highest bid
$stmt = $conn->prepare("SELECT * FROM crop_bids WHERE approvedid = ? ORDER BY bidamount DESC, bidad ASC LIMIT 1");
$stmt->bind_param("i", $approvedId);
$stmt->execute();
$highestResult = $stmt->get_result();
$currentHighest = $highestResult->fetch_assoc();
$stmt->close();

if ($currentHighest) {
    if ($currentHighest['bpartnerid'] == $partnerId) {
        die("❌ You are already the highest bidder.");
    }

   if ($bidAmount <= $currentHighest['bidamount']) {
    die("❌ Your bid must be *strictly higher* than the current highest bid of ₱" . number_format($currentHighest['bidamount'], 2));
    }

    $previousBidderId = $currentHighest['bpartnerid'];

// Get crop type for message
$stmt = $conn->prepare("SELECT croptype FROM approved_submissions WHERE approvedid = ?");
$stmt->bind_param("i", $approvedId);
$stmt->execute();
$stmt->bind_result($cropType);
$stmt->fetch();
$stmt->close();

$message = "❌ You’ve been outbid for $cropType! Place a new bid to regain the lead.";

notify($conn, $previousBidderId, 'businessPartner', $message);

} else {
    // No bids yet, compare to base price
    if ($bidAmount <= $crop['baseprice']) {
        die("❌ Your bid must be higher than the base price of ₱" . number_format($crop['baseprice'], 2));
    }
}

// Check if this partner has already placed a bid
$stmt = $conn->prepare("SELECT * FROM crop_bids WHERE approvedid = ? AND bpartnerid = ?");
$stmt->bind_param("ii", $approvedId, $partnerId);
$stmt->execute();
$existingBid = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existingBid) {
    // Update existing bid
    $stmt = $conn->prepare("UPDATE crop_bids SET bidamount = ?, bidad = NOW() WHERE approvedid = ? AND bpartnerid = ?");
    $stmt->bind_param("dii", $bidAmount, $approvedId, $partnerId);
} else {
    // Insert new bid
    $stmt = $conn->prepare("INSERT INTO crop_bids (approvedid, bpartnerid, bidamount) VALUES (?, ?, ?)");
    $stmt->bind_param("iid", $approvedId, $partnerId, $bidAmount);
}

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    $_SESSION['toast_message'] = "Your bid on $cropType was placed successfully!";

    header("Location: bid_crops.php?success=1");
    exit();
} else {
    $stmt->close();
    die("❌ Something went wrong. Please try again.");
}
?>
