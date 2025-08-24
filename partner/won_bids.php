<?php
require_once '../includes/session.php';
require_once '../includes/db.php';




if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessPartner') {
    header("Location: ../auth/login.php");
    exit();
}
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);


$user_id = $_SESSION['user_id'];

$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$allowed_statuses = ['pending', 'awaiting_verification', 'verified', 'rejected'];
$status_condition = "";

if (in_array($status_filter, $allowed_statuses)) {
    $status_condition = " AND t.status = ?";
}


// Get all winning bids by this user
$sql = "SELECT ab.approvedid, ab.croptype, ab.quantity, ab.unit, ab.imagepath, cbid.reason as cancel_reason, cbid.status as cancel_status, t.verifiedat, t.signature, ab.sellingdate, ab.expired_at,
               cb.bidamount AS winningbidprice,
               t.transactionid, t.payment_proof, t.status, t.rejectionreason
        FROM approved_submissions ab
       
        JOIN crop_bids cb ON ab.approvedid = cb.approvedid
        LEFT JOIN cancel_bid cbid  ON ab.approvedid = cbid.approvedid AND cbid.userid = ?
        LEFT JOIN transactions t ON ab.approvedid = t.approvedid AND t.bpartnerid = cb.bpartnerid
        WHERE cb.bpartnerid = ?
          AND ab.status = 'closed'
          AND ab.winner_id = ?
         " . $status_condition;

// $sql = "SELECT * FROM approved_submissions
// WHERE winner_id = ?

// " . $status_condition;

if (!empty($status_condition)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $user_id, $user_id, $user_id, $status_filter);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
}
$stmt->execute();
$result = $stmt->get_result();


function hasNextHighestBidder($conn, $approvedid, $userid)
{
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

    $hasNext = ($nextHighestResult && $nextHighestResult->num_rows > 0);
    $nextHigheststmt->close();

    return $hasNext;
}



// $query = "SELECT ";
// $request = '';


// while ($row = $result->fetch_assoc()) {

//     echo "<pre>";
//     var_dump($row);   // dumps all columns (approvedid, croptype, etc.)
//     echo "</pre>";
// }



?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Won Bids</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <link rel="stylesheet" href="../assets/style.css">
</head>

<body class="bg-gray-50">
    <div class="min-h-screen p-8">

        <!-- Header Section -->
        <div class="max-w-7xl mx-auto">
            <div class="flex  gap-4 flex-col">
                <a href="dashboard.php" class="text-gray-600 hover:text-gray-900 flex gap-2 items-center">
                    <i data-lucide="chevron-left" class="w-6 h-6"></i>
                    <span>Dashboard</span>

                </a>
                <div class="flex justify-between items-center">


                    <div>

                        <h2 class="text-4xl text-emerald-900 font-semibold ">Bid on Available Crops</h2>
                        <span class="text-lg text-gray-600 ">Browse and bid on listed crops.</span>
                    </div>
                    <div
                        class="max-w-md  bg-white rounded-2xl shadow-sm border border-b-[7px] border-l-[4px] border-emerald-900">
                        <form method="GET">
                            <!-- Header with Sort and View buttons -->
                            <div class="flex items-center gap-2 p-4 border-gray-200">


                                <!-- View Button -->
                                <button type="button" id="statusButton"
                                    class="flex items-center gap-2 bg-white text-gray-600 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                                    <i data-lucide="wheat" class="h-4 w-4"></i>
                                    Status
                                    <svg id="statusArrow" class="w-4 h-4 transition-transform duration-200" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <select name="status" id="status" class="hidden" onchange="this.form.submit()"
                                    class="select border border-emerald-600 px-2 bg-transparent focus:border-emerald-900 focus:ring focus:ring-green-200 w-36">
                                    <option value="">All</option>
                                    <option value="pending" <?= (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : '' ?>>
                                        Pending</option>
                                    <option value="awaiting_verification" <?= (isset($_GET['status']) && $_GET['status'] === 'awaiting_verification') ? 'selected' : '' ?>>Awaiting
                                        Verification
                                    </option>
                                    <option value="verified" <?= (isset($_GET['status']) && $_GET['status'] === 'verified') ? 'selected' : '' ?>>Verified
                                    </option>
                                    <option value="rejected" <?= (isset($_GET['status']) && $_GET['status'] === 'rejected') ? 'selected' : '' ?>>Rejected
                                    </option>
                                </select>

                            </div>
                            <!-- Dropdown Menu -->
                            <div class="relative">
                                <!-- Dropdown -->
                                <div id="statusDropdown"
                                    class="hidden absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                                    <!-- Sort Options -->
                                    <div data-status-value="all"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>

                                        All
                                    </div>
                                    <div data-status-value="pending"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>

                                        Pending
                                    </div>
                                    <!-- Order Options -->
                                    <div data-status-value="awaiting_verification"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                                        Pending Verification
                                    </div>
                                    <div data-status-value="verified"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                                        Verified
                                    </div>
                                    <div data-status-value="rejected"
                                        class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                                        <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                                        Rejected
                                    </div>

                                </div>
                            </div>

                        </form>

                    </div>
                </div>
            </div>



            <!-- No Results Message -->
            <?php if ($result->num_rows === 0): ?>

                <div class="text-center py-12">
                    <div class="max-w-md mx-auto">
                        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                                </path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Empty</h3>

                    </div>
                </div>
            <?php endif; ?>

            <!-- Bids Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div
                        class="bg-white rounded-2xl border border-slate-300 hover:shadow-lg transition-all duration-300 ease-in-out shadow-sm overflow-auto max-h-150 flex flex-col">

                        <div class="flex justify-center relative h-52">
                            <img
                                onclick="my_modal_5<?= htmlspecialchars($row['approvedid']) ?>.showModal()"
                                src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" alt="Crop Image"
                                class="h-full w-full object-cover">

                            <dialog id="my_modal_5<?= htmlspecialchars($row['approvedid']) ?>" class="modal modal-bottom sm:modal-middle">
                                <div class="modal-box">

                                    <img
                                        src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>"
                                        alt="Crop Image Preview"
                                        class="w-full h-auto rounded-md mt-2">

                                </div>
                                <form method="dialog" class="modal-backdrop">
                                    <button>close</button>
                                </form>
                            </dialog>
                        </div>

                        <div class="bg-green-50 px-4 py-3 border-b border-t border-green-200">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-green-900">
                                    <?= ucfirst(htmlspecialchars($row['croptype'])) ?>
                                </h3>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800">
                                        Won
                                    </span>


                                </div>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-4 space-y-4 flex flex-col justify-between flex-1">

                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Quantity:</span>
                                    <span
                                        class="font-medium"><?= htmlspecialchars($row['quantity'] . ' ' . $row['unit']) ?></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Winning Bid:</span>
                                    <span class="font-medium">₱<?= number_format($row['winningbidprice'], 2) ?></span>
                                </div>
                            </div>

                            <!-- Transaction Actions -->
                            <?php if ($row['transactionid'] === null): ?>

                                <?php if ($row['cancel_reason'] === null || $row['cancel_status'] === 'rejected'): ?>
                                    <?php if ($row['sellingdate']): ?>
                                        <?php
                                        $expired_at = new DateTime($row['expired_at']);

                                        $now = new DateTime();
                                        ?>
                                        <?php if ($now > $expired_at): ?>
                                            <div class="text-center">
                                                <h3 class="text-lg font-semibold text-red-600 mb-2 ">Transaction Expired</h3>
                                                <p class="text-red-300 text-sm mb-6">Your transaction session has timed out.</p>
                                            </div>

                                            <!-- Always submit to expired_transaction.php to handle database updates -->
                                            <form id="expiredForm<?= htmlspecialchars($row['approvedid']) ?>" action="expired_transaction.php" method="POST" class="hidden">
                                                <input type="hidden" name="approvedid" value="<?= htmlspecialchars($row['approvedid']) ?>">
                                            </form>
                                            <script>
                                                // Auto-submit the form once this block renders to handle both cases
                                                document.getElementById("expiredForm<?= htmlspecialchars($row['approvedid']) ?>").submit();
                                            </script>
                                        <?php else: ?>
                                            <?php
                                            // Current time
                                            $now = new DateTime();
                                            $expire = new DateTime($row['expired_at']);
                                            $interval = $now->diff($expire);

                                            // Check if still active
                                            if ($expire > $now) {
                                                if ($interval->d > 0) {
                                                    $remaining = $interval->d . "d " . $interval->h . "h left";
                                                } elseif ($interval->h > 0) {
                                                    $remaining = $interval->h . "h " . $interval->i . "m left";
                                                } else {
                                                    $remaining = $interval->i . "m left";
                                                }
                                            } else {
                                                $remaining = "Expired";
                                            }
                                            ?>
                                            <!-- Only show the primary action button -->
                                            <div class="mt-auto">
                                                <div class="text-center">

                                                    <span class="text-xs text-gray-500 ">
                                                        <?php
                                                        $now = new DateTime();
                                                        $expiredAt = new DateTime($row['expired_at']);
                                                        $interval = $now->diff($expiredAt);

                                                        if ($expiredAt > $now) {
                                                            if ($interval->h > 0) {
                                                                echo $interval->h . "h " . $interval->i . "m left before this transaction expires.";
                                                            } else {
                                                                echo $interval->i . " minutes left before this transaction expires.";
                                                            }
                                                        } else {
                                                            echo "This transaction has expired.";
                                                        }
                                                        ?>
                                                    </span>
                                                </div>

                                                <form action="proceed_transaction.php" method="POST">
                                                    <input type="hidden" name="approvedid" value="<?= $row['approvedid'] ?>">
                                                    <input type="hidden" name="winningbidprice" value="<?= $row['winningbidprice'] ?>">
                                                    <button type="submit"
                                                        class="w-full bg-emerald-600 text-white py-3 px-4 rounded-full hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors font-medium">
                                                        Proceed to Transaction
                                                    </button>
                                                </form>

                                                <?php if ($row['cancel_status'] === null): ?>

                                                    <!-- Alternative: If you prefer a text link approach -->
                                                    <div class="mt-2 text-center">
                                                        <button onclick="withdrawModal<?= $row['approvedid'] ?>.showModal()" type="button"
                                                            class="text-xs text-gray-400 hover:text-red-500 transition-colors underline">
                                                            Need to withdraw this bid?
                                                        </button>
                                                    </div>
                                                <?php endif; ?>

                                            </div>

                                            <!-- Withdraw Modal (unchanged but improved) -->
                                            <dialog id="withdrawModal<?= $row['approvedid'] ?>" class="modal modal-bottom sm:modal-middle">
                                                <div class="modal-box">
                                                    <form action="withdraw_bid.php" method="POST" class="mt-2">
                                                        <!-- Header -->
                                                        <h3 class="text-xl font-semibold text-red-600 flex items-center gap-2">
                                                            <i data-lucide="triangle-alert" class="w-6 h-6"></i>
                                                            Withdraw Bid
                                                        </h3>
                                                        <input type="hidden" name="approvedid" value="<?= $row['approvedid'] ?>">
                                                        <input type="hidden" name="winningbidprice" value="<?= $row['winningbidprice'] ?>">

                                                        <!-- Warning -->
                                                        <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                                                            <p class="flex items-center gap-2 text-red-600 font-medium">
                                                                <i data-lucide="alert-circle" class="w-5 h-5"></i>
                                                                Warning!
                                                            </p>
                                                            <p class="text-sm text-red-500 mt-2 leading-relaxed">
                                                                By submitting a cancellation request, your winning bid will need to be verified by the owner.
                                                                If you change your mind after submitting this request, please contact the owner directly.
                                                                <span class="font-semibold">This action cannot be undone automatically.</span>
                                                            </p>
                                                        </div>

                                                        <!-- Confirmation -->
                                                        <p class="mt-6 text-gray-700 text-sm leading-relaxed">
                                                            Are you absolutely sure you want to withdraw your winning bid for
                                                            <strong><?= ucfirst(htmlspecialchars($row['croptype'])) ?></strong>?
                                                        </p>

                                                        <!-- Reason Input -->
                                                        <fieldset class="mt-4 space-y-2">
                                                            <legend class="text-sm font-medium text-gray-700 mb-2">Please provide your reason for withdrawal</legend>
                                                            <textarea name="reason"
                                                                class="w-full h-24 p-3 border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                                                placeholder="Explain why you need to withdraw this bid..." required></textarea>
                                                        </fieldset>

                                                        <!-- Actions -->
                                                        <div class="mt-6 flex justify-end gap-3">
                                                            <button onclick="withdrawModal<?= $row['approvedid'] ?>.close()" type="button"
                                                                class="px-5 py-2.5 text-gray-600 hover:text-gray-800 border border-gray-300 hover:border-gray-400 rounded-full transition-colors">
                                                                Cancel
                                                            </button>
                                                            <button type="submit"
                                                                class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white font-medium rounded-full shadow-sm transition-colors">
                                                                Yes, Withdraw Bid
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                                <!-- Click outside to close -->
                                                <form method="dialog" class="modal-backdrop">
                                                    <button>close</button>
                                                </form>
                                            </dialog>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <?php if ($row['cancel_status'] === 'rejected'): ?>
                                        <div class="flex items-center justify-center py-4">
                                            <div class="text-center">
                                                <i data-lucide="x-circle" class="w-8 h-8 text-red-400 mx-auto mb-2"></i>
                                                <span class="text-sm text-red-600 font-medium">Cancel request rejected</span>
                                                <p class="text-xs text-gray-500 mt-1">You can proceed with the transaction</p>
                                            </div>
                                        </div>
                                    <?php elseif ($row['cancel_status'] === 'approved'): ?>
                                        <div class="flex items-center justify-center py-4">
                                            <div class="text-center">
                                                <i data-lucide="check" class="w-8 h-8 text-green-400 mx-auto mb-2"></i>
                                                <span class="text-sm text-green-600 font-medium">Cancel request approved</span>
                                                <p class="text-xs text-gray-500 mt-1">The crop has been passed to the second highest bidder</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center justify-center py-4">
                                            <div class="text-center">
                                                <i data-lucide="clock" class="w-8 h-8 text-yellow-400 mx-auto mb-2"></i>
                                                <span class="text-sm text-yellow-600 font-medium">Cancel request under review</span>
                                                <p class="text-xs text-gray-500 mt-1">Please wait for admin approval</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                            <?php else: ?>
                                <!-- Transaction-related content (unchanged) -->
                                <?php if (empty($row['payment_proof']) && $row['status'] === 'pending'): ?>
                                    <form action="upload_payment.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                                        <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                        <div class="flex flex-col w-full">
                                            <label
                                                class="flex flex-col w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer hover:bg-gray-50">
                                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                    <svg class="w-8 h-8 mb-4 text-gray-500" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                    </svg>
                                                    <p class="mb-2 text-sm text-gray-500">Click to upload payment proof</p>
                                                </div>
                                                <input id="paymentProof" type="file" name="payment_proof" accept="image/*" class="hidden" required>
                                            </label>
                                            <!-- File name display -->
                                            <div id="selectedFile" class="mt-2 text-blue-600 text-sm ax-w-full overflow-hidden whitespace-nowrap text-ellipsis"></div>
                                        </div>
                                        <button type="submit"
                                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                            Upload Payment Proof
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php
                                $statusClass = match ($row['status']) {
                                    'rejected' => 'bg-red-50 text-red-700',
                                    'verified' => 'bg-green-50 text-green-700',
                                    default => 'bg-yellow-50 text-yellow-700'
                                };
                                ?>

                                <!-- Status Badges (rest of the code unchanged) -->
                                <?php if (!empty($row['payment_proof'])): ?>
                                    <div class="rounded-md p-4 <?= $statusClass ?>">
                                        <?php if ($row['status'] === 'awaiting_verification'): ?>
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <p class="text-sm text-yellow-700">Awaiting verification</p>
                                            </div>
                                        <?php elseif ($row['status'] === 'verified'): ?>
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2">

                                                    <i data-lucide="check" class="w-5 h-5 text-green-700"></i>
                                                    <p class="text-sm text-green-700">Verified</p>
                                                </div>

                                                <span class="text-sm text-gray-400"> <?= date("M d, Y h:i A", strtotime($row['verifiedat'])) ?></span>
                                            </div>
                                        <?php elseif ($row['status'] === 'rejected'): ?>
                                            <div class="space-y-2">
                                                <div class="flex items-start">
                                                    <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <div>
                                                        <p class="text-sm text-red-700">Payment proof rejected</p>
                                                        <?php if (!empty($row['rejectionreason'])): ?>
                                                            <p class="text-sm text-red-600 mt-1">
                                                                <?= htmlspecialchars($row['rejectionreason']) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <!-- Resubmit Form -->
                                                <form action="resubmit_proof.php" method="POST" enctype="multipart/form-data"
                                                    class="mt-3">
                                                    <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                                    <div class="flex items-center space-x-2">
                                                        <input type="file" name="payment_proof" accept="image/*" required
                                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                                                        <button type="submit"
                                                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                                            Resubmit
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($row['status'] === 'verified'): ?>
                                        <div class="flex justify-center">
                                            <button
                                                onclick="signatureModal<?= htmlspecialchars($row['approvedid']) ?>.showModal()"
                                                type=" button" class="text-xs text-gray-400 hover:text-green-500 transition-colors underline">View proof of delivery</button>

                                            <dialog id="signatureModal<?= htmlspecialchars($row['approvedid']) ?>" class="modal modal-bottom sm:modal-middle">
                                                <div class="modal-box">

                                                    <img
                                                        src="../assets/signatures/<?= htmlspecialchars($row['signature']) ?>"
                                                        alt="Crop Image Preview"
                                                        class="w-full h-auto rounded-md mt-2">

                                                </div>
                                                <form method="dialog" class="modal-backdrop">
                                                    <button>close</button>
                                                </form>
                                            </dialog>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>


                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <?php if ($toast_message): ?>
        <div class="toast">
            <div class="alert alert-success">
                <span class="text-emerald-900 "><?php echo htmlspecialchars($toast_message); ?></span>
            </div>
        </div>

        <script>
            // Hide toast after 3 seconds
            setTimeout(() => {
                document.querySelector('.toast')?.remove();
            }, 3000);
        </script>
    <?php endif; ?>
    <script src="./assets/won_bids.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        const fileInput = document.getElementById('paymentProof');
        const fileDisplay = document.getElementById('selectedFile');

        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Create a link that opens the file in a new tab
                fileDisplay.innerHTML = `<a href="${URL.createObjectURL(file)}" target="_blank" class="underline">${file.name}</a>`;
            } else {
                fileDisplay.textContent = '';
            }
        });
    </script>
</body>

</html>