<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';


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

<?php
require_once '../includes/header.php';
?>


<a href="dashboard.php"
    class="inline-flex items-center gap-2 text-gray-600 hover:text-emerald-900 px-4 py-1 justify-center rounded-lg">
    <i data-lucide="chevron-left" class="w-6 h-6"></i>

    <span class="text-md">Dashboard</span>
</a>


<div class=" ml-4 mt-5 mb-10 flex justify-between items-center ">

    <div>

        <h2 class="text-4xl text-emerald-900 font-semibold ">Payment Confirmations</h2>
        <span class="text-lg text-gray-600 ">Review and manage payment verification requests</span>

    </div>

    <div class="max-w-md  bg-white rounded-2xl shadow-sm border border-gray-200">
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <select name="status" id="status" class="hidden" onchange="this.form.submit()"
                    class="select border border-emerald-600 px-2 bg-transparent focus:border-emerald-900 focus:ring focus:ring-green-200 w-36">
                    <option value="">All</option>
                    <option value="pending" <?= (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : '' ?>>
                        Pending</option>
                    <option value="awaiting_verification" <?= (isset($_GET['status']) && $_GET['status'] === 'awaiting_verification') ? 'selected' : '' ?>>Awaiting Verification
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




<!-- Cards Grid -->
<?php if (mysqli_num_rows($result) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <?php
            $statusColors = [
                'pending' => ['border' => 'border-yellow-200', 'bg' => 'bg-yellow-50', 'badge' => 'bg-yellow-100 text-yellow-800'],
                'awaiting_verification' => ['border' => 'border-blue-200', 'bg' => 'bg-blue-50', 'badge' => 'bg-blue-100 text-blue-800'],
                'verified' => ['border' => 'border-green-200', 'bg' => 'bg-green-50', 'badge' => 'bg-green-100 text-green-800'],
                'rejected' => ['border' => 'border-red-200', 'bg' => 'bg-red-50', 'badge' => 'bg-red-100 text-red-800']
            ];
            $colors = $statusColors[$row['status']] ?? ['border' => 'border-gray-200', 'bg' => 'bg-gray-50', 'badge' => 'bg-gray-100 text-gray-800'];
            ?>

            <div
                class="bg-white rounded-3xl shadow-sm border hover:shadow-md transition-shadow flex flex-col justify-between h-full">

                <!-- Card Header -->
                <div class="p-5 border-b border-gray-100 bg-emerald-900 rounded-tl-3xl rounded-tr-3xl">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center text-center gap-2">
                            <span class="text-xs font-medium text-gray-300">Transaction ID:</span>
                            <h3 class="text-lg font-semibold text-gray-50">#<?= $row['transactionid'] ?></h3>
                        </div>

                        <span class="<?= $colors['badge'] ?> text-xs px-2 py-1 rounded-full font-medium">
                            <?= ucfirst(str_replace('_', ' ', $row['status'])) ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-100 mt-1"><?= htmlspecialchars($row['croptype']) ?> • <?= $row['quantity'] ?>
                        <?= $row['unit'] ?>
                    </p>
                </div>

                <!-- Crop Image -->
                <div class="p-5 border-b border-gray-100">
                    <div class="aspect-video bg-gray-50 rounded-lg overflow-hidden">
                        <img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>"
                            class="w-full h-full object-cover" alt="Crop">
                    </div>
                </div>
                <?php if ($row['status'] === 'rejected' && !empty($row['rejectionreason'])): ?>
                    <div class="p-5 bg-red-50 border-t border-b border-red-200">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-red-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-xs font-medium text-red-900 mb-1">Rejection Reason</p>
                                <p class="text-sm text-red-700"><?= htmlspecialchars($row['rejectionreason']) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Details -->
                <div class="p-5 space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 text-xs">Partner</span>
                        <span class="font-medium text-gray-900 "><?= htmlspecialchars($row['partner_name']) ?></span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 text-xs">Bid Price</span>
                        <span class="font-semibold text-green-600">₱<?= number_format($row['winningbidprice'], 2) ?></span>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 text-xs">Total</span>
                        <span class="font-bold text-green-600">₱<?= number_format($row['totalprice'], 2) ?></span>
                    </div>

                    <div class="flex items-center justify-between text-sm pt-2 border-t border-gray-100">
                        <span class="text-gray-600 text-xs">Uploaded</span>
                        <span class="text-gray-900"><?= date('M d, Y', strtotime($row['uploadedat'])) ?></span>
                    </div>
                </div>

                <!-- Payment Proof Section -->
                <div class="p-5 border-t border-gray-100 ">
                    <?php if (!empty($row['payment_proof'])): ?>
                        <div class="space-y-3">
                            <button onclick="togglePaymentProof('proof-<?= $row['transactionid'] ?>')"
                                class="w-full flex items-center justify-center gap-2 py-2 px-3 bg-white border border-gray-300 rounded-full hover:bg-gray-50 transition-colors text-sm font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                    </path>
                                </svg>
                                View Payment Proof
                            </button>

                            <!-- Hidden Payment Proof Image -->
                            <div id="proof-<?= $row['transactionid'] ?>" class="hidden">
                                <div class="aspect-video bg-gray-100 rounded-lg overflow-hidden">
                                    <img src="../assets/payment_proofs/<?= htmlspecialchars($row['payment_proof']) ?>"
                                        class="w-full h-full object-cover" alt="Payment Proof">
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center gap-2 text-gray-500 text-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Waiting for payment proof
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <?php if (!empty($row['payment_proof'])): ?>
                    <?php $isAwaiting = $row['status'] === 'awaiting_verification'; ?>
                    <div class="p-5 bg-white rounded-b-3xl space-y-2 flex flex-col items-end">
                        <div class="mt-auto space-y-2 w-full">
                            <form method="POST" class="w-full">
                                <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                <button type="submit" name="confirm" <?= $isAwaiting ? '' : 'disabled' ?> class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-full font-medium text-sm transition-all
                             <?= $isAwaiting
                                 ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                 : 'bg-gray-200 text-gray-400 cursor-not-allowed' ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                                        </path>
                                    </svg>
                                    Confirm Payment
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirmReject(this);" class="w-full">
                                <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                <input type="hidden" name="rejectionreason" class="reject-reason">
                                <button type="submit" name="reject" <?= $isAwaiting ? '' : 'disabled' ?> class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-full font-medium text-sm transition-all
                             <?= $isAwaiting
                                 ? 'bg-red-600 hover:bg-red-700 text-white'
                                 : 'bg-gray-200 text-gray-400 cursor-not-allowed' ?>">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12">
                                        </path>
                                    </svg>
                                    Reject Payment
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Rejection Reason -->


            </div>
        <?php endwhile; ?>
    </div>

<?php else: ?>
    <div class="bg-white rounded-xl border shadow-sm">
        <div class="text-center py-16">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Payment Confirmations</h3>
            <p class="text-gray-500 text-sm">Payment verification requests will appear here</p>
        </div>
    </div>
<?php endif; ?>





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
    function togglePaymentProof(elementId) {
        const element = document.getElementById(elementId);
        if (element.classList.contains('hidden')) {
            element.classList.remove('hidden');
        } else {
            element.classList.add('hidden');
        }
    }
</script>
<script src="./assets/confirm_payments.js"></script>
<?php
require_once '../includes/footer.php';
?>