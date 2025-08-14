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
$sql = "SELECT ab.approvedid, ab.croptype, ab.quantity, ab.unit, ab.imagepath,
               cb.bidamount AS winningbidprice,
               t.transactionid, t.payment_proof, t.status, t.rejectionreason
        FROM approved_submissions ab
        JOIN crop_bids cb ON ab.approvedid = cb.approvedid
        LEFT JOIN transactions t ON ab.approvedid = t.approvedid AND t.bpartnerid = cb.bpartnerid
        WHERE cb.bpartnerid = ?
          AND cb.bidamount = (
              SELECT MAX(bidamount)
              FROM crop_bids
              WHERE approvedid = ab.approvedid
          )" . $status_condition;



if (!empty($status_condition)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $status_filter);
} else {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
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
</head>

<body class="bg-gray-50">
    <div class="min-h-screen p-8">
        <!-- Header Section -->
        <div class="max-w-7xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <!-- <div class="flex items-center gap-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900">
                        <i data-lucide="chevron-left" class="w-6 h-6"></i>
                       
                    </a>
                    <h1 class="text-2xl font-semibold text-gray-900">My Won Bids</h1>
                </div> -->
                <!-- <div class="flex  gap-4 flex-col">
                    <a href="dashboard.php" class="text-gray-600 hover:text-gray-900 flex gap-2 items-center">
                        <i data-lucide="chevron-left" class="w-6 h-6"></i>
                        <span>Dashboard</span>

                    </a>
                    <div>

                        <h2 class="text-4xl text-emerald-900 font-semibold">My Won Bids</h2>
<span class="text-lg text-gray-600">View your winning bids and upload proof of payment to claim your crop</span>

                    </div>

                </div> -->
                

                <!-- Filter -->
                <form method="GET" class="flex items-center gap-2">
                    <label for="status" class="text-sm font-medium text-gray-700">Filter by Status:</label>
                    <select name="status" id="status" onchange="this.form.submit()"
                        class="rounded-md border border-gray-300 py-2 pl-3 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="awaiting_verification" <?= $status_filter === 'awaiting_verification' ? 'selected' : '' ?>>Awaiting Verification</option>
                        <option value="verified" <?= $status_filter === 'verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </form>
            </div>

            <!-- No Results Message -->
            <?php if ($result->num_rows === 0): ?>
                <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-600">
                    You haven't won any bids yet or all your transactions are complete.
                </div>
            <?php endif; ?>

            <!-- Bids Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">
                        <!-- Card Header -->
                        <div class="bg-green-50 px-4 py-3 border-b">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-green-900">
                                    <?= htmlspecialchars($row['croptype']) ?>
                                </h3>
                                <span
                                    class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800">
                                    Won
                                </span>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-4 space-y-4">
                            <div class="flex justify-center">
                                <img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" alt="Crop Image"
                                    class="h-48 w-48 object-cover rounded-lg">
                            </div>

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
                                <form action="proceed_transaction.php" method="POST">
                                    <input type="hidden" name="approvedid" value="<?= $row['approvedid'] ?>">
                                    <input type="hidden" name="winningbidprice" value="<?= $row['winningbidprice'] ?>">
                                    <button type="submit"
                                        class="w-full bg-emerald-600 text-white py-2 px-4 rounded-full hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                                        Proceed to Transaction
                                    </button>
                                </form>
                            <?php else: ?>
                                <?php if (empty($row['payment_proof']) && $row['status'] === 'pending'): ?>
                                    <form action="upload_payment.php" method="POST" enctype="multipart/form-data" class="space-y-3">
                                        <input type="hidden" name="transactionid" value="<?= $row['transactionid'] ?>">
                                        <div class="flex items-center justify-center w-full">
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
                                                <input type="file" name="payment_proof" accept="image/*" class="hidden" required>
                                            </label>
                                        </div>
                                        <button type="submit"
                                            class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
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
                                <!-- Status Badges -->
                                <?php if (!empty($row['payment_proof'])): ?>
                                    <div class="rounded-md p-4 
                                          <?= $statusClass ?>">
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
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="check" class="w-5 h-5 text-green-700"></i>
                                               
                                                <p class="text-sm text-green-700">Verified</p>
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
            <span><?php echo htmlspecialchars($toast_message); ?></span>
        </div>
    </div>

    <script>
        // Hide toast after 3 seconds
        setTimeout(() => {
            document.querySelector('.toast')?.remove();
        }, 3000);
    </script>
<?php endif; ?>

 <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
</body>

</html>