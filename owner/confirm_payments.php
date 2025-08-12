<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/notify.php';
require_once '../includes/notification_ui.php';


// Ensure only owner is accessing
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
    header('Location: ../auth/login.php');
    exit();
}


// Handle Confirm or Reject POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['confirm']) && isset($_POST['transactionid'])) {
        $id = (int) $_POST['transactionid'];
        $query = "UPDATE transactions SET status = 'verified', verifiedat = NOW() WHERE transactionid = $id";
        mysqli_query($conn, $query);

// Step 1: Get bpartner ID and crop type
    $detailsQuery = "
        SELECT t.bpartnerid, ab.croptype
        FROM transactions t
        JOIN approved_submissions ab ON t.approvedid = ab.approvedid
        WHERE t.transactionid = $id
    ";
    $detailsResult = mysqli_query($conn, $detailsQuery);

    if ($detailsResult && mysqli_num_rows($detailsResult) > 0) {
        $details = mysqli_fetch_assoc($detailsResult);
        $bpartnerId = $details['bpartnerid'];
        $croptype = $details['croptype'];

    // Step 2: Send notification
        require_once '../includes/notify.php';
        notify($conn, $bpartnerId, 'businessPartner', "Your payment for $croptype has been approved.");
    }

    }

    if (isset($_POST['reject']) && isset($_POST['transactionid'])) {
        $id = (int) $_POST['transactionid'];
        $reason = mysqli_real_escape_string($conn, $_POST['rejectionreason']);
        $query = "UPDATE transactions SET status = 'rejected', rejectionreason = '$reason' WHERE transactionid = $id";
        mysqli_query($conn, $query);

        // Step 1: Get bpartner ID and crop type
    $detailsQuery = "
        SELECT t.bpartnerid, ab.croptype
        FROM transactions t
        JOIN approved_submissions ab ON t.approvedid = ab.approvedid
        WHERE t.transactionid = $id
    ";
    $detailsResult = mysqli_query($conn, $detailsQuery);

    if ($detailsResult && mysqli_num_rows($detailsResult) > 0) {
        $details = mysqli_fetch_assoc($detailsResult);
        $bpartnerId = $details['bpartnerid'];
        $croptype = $details['croptype'];

    // Step 2: Send notification
    require_once '../includes/notify.php';
    notify($conn, $bpartnerId, 'businessPartner', "Your payment for $croptype was rejected. Reason: $reason");
}

    }

    header("Location: confirm_payments.php");
    exit();
}

// Fetch transactions with pending payment proofs
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

$baseQuery = "
SELECT ct.transactionid, ct.approvedid, ct.bpartnerid, ct.payment_proof, ct.createdat AS uploadedat,
       ct.winningbidprice, ct.totalprice, ct.status, ct.rejectionreason,
       ab.croptype, ab.quantity, ab.unit, ab.imagepath, 
       u.name AS partner_name
FROM transactions ct
JOIN approved_submissions ab ON ct.approvedid = ab.approvedid
JOIN users u ON ct.bpartnerid = u.id
";

if (!empty($statusFilter)) {
    $baseQuery .= "WHERE ct.status = ?";
    $stmt = $conn->prepare($baseQuery);
    $stmt->bind_param("s", $statusFilter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $baseQuery .= "ORDER BY ct.createdat DESC";
    $result = mysqli_query($conn, $baseQuery);
}


?>

<!DOCTYPE html>
<html>
<head>
    
    <title>Confirm Payments</title>
    <a href="dashboard.php">← Back to Dashboard</a><br><br>
    <meta charset="UTF-8">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">


<style>
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; border: 1px solid #ccc; text-align: center; }
    img { max-width: 100px; height: auto; }
    button:disabled { background-color: #ccc; cursor: not-allowed; }
    .action-form { display: inline-block; margin: 0 5px; }

    /* Background for rows */
    .row-pending { background-color: #fff8e1; }              /* light orange */
    .row-awaiting { background-color: #e3f2fd; }              /* light blue */
    .row-verified { background-color: #e8f5e9; }              /* light green */
    .row-rejected { background-color: #ffebee; }              /* light red */

    .status-icon {
        font-size: 18px;
    }
</style>

</head>
<body>



<h2>Pending Payment Confirmations</h2>

<form method="GET" class="filter-form" style="margin-bottom: 20px;">
  <label for="status">Filter by Status:</label>
  <select name="status" id="status" onchange="this.form.submit()">
    <option value="">All</option>
    <option value="pending" <?= (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
    <option value="awaiting_verification" <?= (isset($_GET['status']) && $_GET['status'] === 'awaiting_verification') ? 'selected' : '' ?>>Awaiting Verification</option>
    <option value="verified" <?= (isset($_GET['status']) && $_GET['status'] === 'verified') ? 'selected' : '' ?>>Verified</option>
    <option value="rejected" <?= (isset($_GET['status']) && $_GET['status'] === 'rejected') ? 'selected' : '' ?>>Rejected</option>
  </select>
</form>

<table>
    <thead>
        <tr>
            <th>Transaction ID</th>
            <th>Crop</th>
            <th>Quantity</th>
            <th>Unit</th>
            <th>Crop Image</th>
            <th>Payment Proof</th>
            <th>Partner</th>
            <th>Uploaded At</th>
            <th>Bid Price</th>
            <th>Total Price</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if (mysqli_num_rows($result) > 0): ?>
            
<?php while ($row = mysqli_fetch_assoc($result)): ?>
    <?php
        // Assign row class based on status
        $statusClass = '';
        switch ($row['status']) {
            case 'pending': $statusClass = 'row-pending'; break;
            case 'awaiting_verification': $statusClass = 'row-awaiting'; break;
            case 'verified': $statusClass = 'row-verified'; break;
            case 'rejected': $statusClass = 'row-rejected'; break;
        }
    ?>
    <tr class="<?= $statusClass ?>">
        <td><?= $row['transactionid'] ?></td>
        <td><?= htmlspecialchars($row['croptype']) ?></td>
        <td><?= $row['quantity'] ?></td>
        <td><?= $row['unit'] ?></td>
        <td><img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" alt="Crop Image"></td>
        <td>
            <?php if (!empty($row['payment_proof'])): ?>
                <img src="../assets/payment_proofs/<?= htmlspecialchars($row['payment_proof']) ?>" alt="Proof">
            <?php else: ?>
                <span style="color:red;">Waiting for payment proof</span>
            <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($row['partner_name']) ?></td>
        <td><?= $row['uploadedat'] ?></td>
        <td>₱<?= number_format($row['winningbidprice'], 2) ?></td>
        <td>₱<?= number_format($row['totalprice'], 2) ?></td>

        <!-- ✅ ICON-BASED STATUS DISPLAY -->
<td>
    <?php
        switch ($row['status']) {
            case 'pending':
                echo '<span class="status-icon" title="Pending - Waiting for user upload">🕓</span>';
                break;
            case 'awaiting_verification':
                echo '<span class="status-icon" title="Awaiting Verification - Proof uploaded, needs your review">📤</span>';
                break;
            case 'verified':
                echo '<span class="status-icon" title="Verified - Payment proof confirmed">✅</span>';
                break;
            case 'rejected':
                echo '<span class="status-icon" title="Rejected - Payment proof was rejected">❌</span>';
                break;
            default:
                echo '<span class="status-icon" title="Unknown Status">❔</span>';
        }
    ?>
</td>




                    
                    <td>
                        <?php if (!empty($row['payment_proof'])): ?>
                            <?php
                                $isAwaiting = $row['status'] === 'awaiting_verification';
                                $confirmDisabled = $isAwaiting ? '' : 'disabled';
                                $rejectDisabled = $isAwaiting ? '' : 'disabled';
                            ?>
                            <form class="action-form" method="POST">
                                <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                <button type="submit" name="confirm" <?= $confirmDisabled ?> style="background-color:<?= $isAwaiting ? '#4CAF50' : '#ccc' ?>; color:white; cursor:<?= $isAwaiting ? 'pointer' : 'not-allowed' ?>;">✅ Confirm</button>
                            </form>
                            <form class="action-form" method="POST" onsubmit="return confirmReject(this);">
                                <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                <input type="hidden" name="rejectionreason" class="reject-reason">
                                <button type="submit" name="reject" <?= $rejectDisabled ?> style="background-color:<?= $isAwaiting ? '#f44336' : '#ccc' ?>; color:white; cursor:<?= $isAwaiting ? 'pointer' : 'not-allowed' ?>;">❌ Reject</button>
                            </form>
                        <?php else: ?>
                            <span style="color:red;">Waiting for payment proof</span>
                        <?php endif; ?>
                    </td>

                </tr>

                
<?php if ($row['status'] === 'rejected' && !empty($row['rejectionreason'])): ?>
<tr class="rejection-row" style="display: none; background-color: #fff3f3;">
    <td colspan="11" style="text-align: left; color: #a94442; padding: 15px;">
        <strong>Rejection Reason:</strong> <?= htmlspecialchars($row['rejectionreason']) ?>
    </td>
</tr>
<tr>
    <td colspan="11" style="text-align: right;">
        <button class="toggle-reason-btn" onclick="toggleReason(this)">🔽 View Reason</button>
    </td>
</tr>
<?php endif; ?>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="11">No pending payment proofs.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<script>
function confirmReject(form) {
    const reason = prompt("Please enter a reason for rejection:");
    if (!reason || reason.trim() === "") {
        alert("Rejection reason is required.");
        return false;
    }
    form.querySelector(".reject-reason").value = reason;
    return true;
}

function toggleReason(button) {
    const row = button.closest('tr');
    const reasonRow = row.previousElementSibling;
    if (reasonRow.style.display === 'none') {
        reasonRow.style.display = '';
        button.textContent = "🔼 Hide Reason";
    } else {
        reasonRow.style.display = 'none';
        button.textContent = "🔽 View Reason";
    }
}
</script>

</body>
</html>
