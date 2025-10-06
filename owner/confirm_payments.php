<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';

$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);

// Ensure only owner is accessing
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
    header('Location: ../auth/login.php');
    exit();
}


// Handle Confirm, Reject, or Signature POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Save signature (AJAX to same file)
    if (isset($_POST['save_signature']) && isset($_POST['transactionid']) && isset($_POST['signature_image'])) {
        header('Content-Type: application/json');

        $id = (int) $_POST['transactionid'];
        $imageData = $_POST['signature_image'];

        // Strip data URL prefix if present
        if (strpos($imageData, 'data:image') === 0) {
            $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
        }

        $binary = base64_decode($imageData);
        if ($binary === false) {
            echo json_encode(['success' => false, 'message' => 'Invalid image data']);
            exit();
        }

        $signatureDir = __DIR__ . '/../assets/signatures/';
        if (!is_dir($signatureDir)) {
            @mkdir($signatureDir, 0777, true);
        }

        $filename = 'sign_' . uniqid('', true) . '.png';
        $filePath = $signatureDir . $filename;

        if (file_put_contents($filePath, $binary) === false) {
            echo json_encode(['success' => false, 'message' => 'Failed to save image']);
            exit();
        }

        // Update transaction with signature and verify
        if ($stmt = $conn->prepare("UPDATE transactions SET signature = ?, status = 'verified', verifiedat = NOW(),  delivery_received_at = NOW() WHERE transactionid = ?")) {
            $stmt->bind_param('si', $filename, $id);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                echo json_encode(['success' => false, 'message' => 'Failed to update transaction']);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare statement']);
            exit();
        }

        // Send notification to business partner about approval
        $detailsQuery = "
            SELECT t.bpartnerid, ab.croptype
            FROM transactions t
            JOIN approved_submissions ab ON t.approvedid = ab.approvedid
            WHERE t.transactionid = $id
        ";
        $detailsResult = mysqli_query($conn, $detailsQuery);
        if ($detailsResult && mysqli_num_rows($detailsResult) > 0) {
            $details = mysqli_fetch_assoc($detailsResult);
            $bpartnerId = $details['bpartnerid'] ?? null;
            $croptype = $details['croptype'] ?? '';
            if (!empty($bpartnerId)) {
                require_once '../includes/notify.php';
                notify($conn, (int) $bpartnerId, 'businessPartner', "Your payment for $croptype has been approved.");
            }
        }

        echo json_encode(['success' => true, 'filename' => $filename]);
        exit();
    }
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
<div class="flex min-h-screen">
    <?php include 'includes/sidebar.php'; ?>


  

    <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
        <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
            <div class="flex flex-col  lg:ml-4 mt-5 mb-5">
                <div class="flex justify-between items-center">
                    <div>

                        <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold ">Payment Confirmations</h2>
                        <span class="text-md lg:text-lg text-gray-600 ">Review and manage payment verification
                            requests</span>

                    </div>
                                    <?php include 'includes/sm-sidebar.php'; ?>

                </div>
                <div class="flex justify-end items-end w-full ">

                    <div
                        class="max-w-md mt-3 lg:mt-0  bg-white rounded-2xl shadow-sm border border-b-[7px] border-l-[4px] border-emerald-900">
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
                            class="bg-gray-100 rounded-3xl shadow-sm border hover:shadow-md transition-shadow flex flex-col justify-between h-full">

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
                                <p class="text-sm text-gray-100 mt-1"><?= htmlspecialchars($row['croptype']) ?> •
                                    <?= $row['quantity'] ?>
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
                                    <span
                                        class="font-medium text-gray-900 "><?= htmlspecialchars($row['partner_name']) ?></span>
                                </div>

                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 text-xs">Bid Price</span>
                                    <span
                                        class="font-semibold text-green-600">₱<?= number_format($row['winningbidprice'], 2) ?></span>
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
                            <div class="p-5 border-t border-gray-100">
                                <?php if (!empty($row['payment_proof'])): ?>
                                    <div class="space-y-4">
                                        <!-- Payment Proof View Button -->
                                        <button onclick="proofModal<?= $row['transactionid'] ?>.showModal()"
                                            class="w-full flex items-center justify-center gap-2 py-2.5 px-4 bg-slate-50 border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors text-sm font-medium text-slate-700">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                            Review Payment Proof
                                        </button>

                                        <!-- Payment Verification Actions -->
                                        <?php $isAwaiting = $row['status'] === 'awaiting_verification'; ?>
                                        <?php if ($isAwaiting): ?>
                                            <div
                                                class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-100">
                                                <div class="flex items-center gap-2 mb-3">
                                                    <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
                                                    <span class="text-sm font-medium text-blue-900">Verification Required</span>
                                                </div>
                                                <p class="text-xs text-blue-700 mb-4">Review the payment proof and choose an action
                                                    below.</p>

                                                <div class="grid grid-cols-2 gap-2">

                                                    <!-- Reject Button -->
                                                    <form method="POST" onsubmit="return confirmReject(this);" class="flex">
                                                        <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                                        <input type="hidden" name="rejectionreason" class="reject-reason">
                                                        <button type="submit" name="reject"
                                                            class="flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 bg-red-600 text-white rounded-lg font-medium text-xs transition-all hover:bg-red-700 transition duration-300 ease-in-out shadow-sm">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                                    d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                            <span>Reject</span>
                                                        </button>
                                                    </form>
                                                    <!-- Approve Button -->
                                                    <form method="POST" class="flex">
                                                        <!-- <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>"> -->
                                                        <button type="button" name="confirm"
                                                            onclick="signModal<?= $row['transactionid'] ?>.showModal()"
                                                            class="flex-1 flex items-center justify-center gap-1.5 py-2.5 px-3 bg-emerald-600 text-white rounded-lg font-medium text-xs transition-all hover:bg-emerald-700 transition duration-300 ease-in-out shadow-sm">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                                    d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                            <span>Approve</span>
                                                        </button>
                                                    </form>
                                                    <dialog id="signModal<?= $row['transactionid'] ?>"
                                                        class="modal modal-bottom sm:modal-middle">
                                                        <div
                                                            class="modal-box bg-zinc-50 border border-zinc-300 lg:w-11/12 lg:max-w-xl max-h-[70vh] overflow-visible">
                                                            <form method="dialog">
                                                                <button
                                                                    class="btn btn-sm btn-circle shadow-none btn-ghost hover:bg-zinc-200 hover:border-zinc-200 absolute right-2 top-2 text-zinc-800">✕</button>
                                                            </form>
                                                            <h1 class="text-lg font-bold text-zinc-900">
                                                                Transaction # <?= $row['transactionid'] ?> - Signature
                                                            </h1>
                                                            <h3 class="text-sm font-bold text-zinc-500 mb-4">Use your finger or stylus
                                                                to sign in
                                                                the area below</h3>

                                                            <div>
                                                                <canvas id="signatures-<?= $row['transactionid'] ?>"
                                                                    class="w-full h-64 border border-gray-300 bg-white rounded-lg"></canvas>
                                                            </div>
                                                            <div class="flex justify-between mt-3">
                                                                <button id="clears-<?= $row['transactionid'] ?>"
                                                                    class="px-6 py-2 text-zinc-700 flex gap-3 text-sm border rounded-md items-center hover:bg-gray-100 transition duration-300">
                                                                    <i data-lucide="undo-2" class="h-4 w-4"></i>
                                                                    <span>Clear</span>

                                                                </button>
                                                                <button id="saves-<?= $row['transactionid'] ?>"
                                                                    class="px-6 py-2  text-white bg-emerald-600 flex gap-3 text-sm border rounded-md items-center hover:bg-emerald-700 transition duration-300">
                                                                    <i data-lucide="check" class="h-4 w-4"></i>
                                                                    <span>Submit Signature</span>

                                                                </button>
                                                            </div>


                                                        </div>
                                                    </dialog>


                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <!-- Status Display for Non-Awaiting Items -->
                                            <div class="bg-gray-50 p-4 rounded-xl border border-gray-200">
                                                <div class="flex items-center justify-center gap-2 text-gray-600">
                                                    <?php if ($row['status'] === 'verified'): ?>
                                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <span class="text-sm font-medium text-green-700">Payment Verified</span>
                                                    <?php elseif ($row['status'] === 'rejected'): ?>
                                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z">
                                                            </path>
                                                        </svg>
                                                        <span class="text-sm font-medium text-red-700">Payment Rejected</span>
                                                    <?php else: ?>
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <span class="text-sm font-medium">Processing</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Modal for Payment Proof -->
                                        <dialog id="proofModal<?= htmlspecialchars($row['transactionid']) ?>"
                                            class="modal modal-bottom sm:modal-middle">
                                            <div class="modal-box max-w-2xl">
                                                <h3 class="font-bold text-lg mb-4">Payment Proof - Transaction
                                                    #<?= $row['transactionid'] ?>
                                                </h3>
                                                <img src="../assets/payment_proofs/<?= htmlspecialchars($row['payment_proof']) ?>"
                                                    alt="Payment Proof Preview" class="w-full h-auto rounded-lg shadow-sm">
                                            </div>
                                            <form method="dialog" class="modal-backdrop">
                                                <button>close</button>
                                            </form>
                                        </dialog>
                                    </div>
                                <?php else: ?>
                                    <div
                                        class="flex items-center justify-center gap-3 text-gray-500 text-sm bg-gray-50 p-4 rounded-xl border border-gray-200">
                                        <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <div class="">
                                            <p class="font-medium text-gray-700">Awaiting Payment Proof</p>
                                            <p class="text-xs text-gray-500">Partner will upload payment confirmation</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
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




        </div>
    </main>

</div>
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


<script>
    // Store signature pads for each allocation
    const signaturePads = {};

    // Function to initialize signature pad for a specific allocation
    function initializeSignaturePad(allocationId) {
        const canvas = document.getElementById(`signatures-${allocationId}`);
        if (!canvas) return;

        // Create new signature pad instance
        signaturePads[allocationId] = new SignaturePad(canvas);

        // Resize canvas
        resizeCanvas(canvas, signaturePads[allocationId]);

        // Add event listeners for this specific allocation
        const clearBtn = document.getElementById(`clears-${allocationId}`);
        const saveBtn = document.getElementById(`saves-${allocationId}`);

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                signaturePads[allocationId].clear();
            });
        }
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                if (signaturePads[allocationId].isEmpty()) {
                    alert("Please provide a signature.");
                    return;
                }

                const dataURL = signaturePads[allocationId].toDataURL(); // base64 PNG

                fetch('confirm_payments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams({
                        save_signature: '1',
                        transactionid: allocationId,
                        signature_image: dataURL
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert('Signature saved!');
                            // Optionally show image preview:
                            const signModal = document.getElementById(`signModal${allocationId}`);
                            if (signModal && typeof signModal.close === 'function') {
                                signModal.close();
                                location.reload();
                            }
                        } else {
                            alert('Failed to save signature.');
                        }
                    });
            });
        }

    }

    function resizeCanvas(canvas, signaturePad) {
        const ratio = window.devicePixelRatio || 1;
        const styles = getComputedStyle(canvas);
        const width = parseInt(styles.width);
        const height = parseInt(styles.height);

        canvas.width = width * ratio;
        canvas.height = height * ratio;
        canvas.getContext("2d").scale(ratio, ratio);

        // Important: clear previous drawing
        signaturePad.clear();
    }

    // Initialize signature pads for all allocations when page loads
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[id^="signatures-"]').forEach(element => {
            const allocationId = element.id.replace('signatures-', '');
            initializeSignaturePad(allocationId);
        });
    });
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType === 1) { // Element node
                    const canvases = node.querySelectorAll?.('[id^="signatures-"]') || [];
                    canvases.forEach(canvas => {
                        const allocationId = canvas.id.replace('signatures-', '');
                        initializeSignaturePad(allocationId);
                    });
                }
            });
        });
    });
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Handle window resize for all signature pads
    window.addEventListener('resize', () => {
        Object.keys(signaturePads).forEach(allocationId => {
            const canvas = document.getElementById(`signatures-${allocationId}`);
            if (canvas && signaturePads[allocationId]) {
                resizeCanvas(canvas, signaturePads[allocationId]);
            }
        });
    });
</script>
<?php
require_once '../includes/footer.php';
?>