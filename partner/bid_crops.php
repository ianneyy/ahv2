<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessPartner') {
  header("Location: ../auth/login.php");
  exit();
}

$partnerId = $_SESSION['user_id'];



// Get filter values from dropdowns
$cropTypeFilter = $_GET['croptype'] ?? 'all';
$biddingStatus = $_GET['status'] ?? 'all';      // For open/closed bidding filter
$statusFilter = $_GET['userstatus'] ?? 'all';  // For winning/outbid filter
$sortOption = $_GET['sort'] ?? 'newest';

$whereClauses = [];
$params = [];
$types = "";


// Filter by bidding status
if ($biddingStatus === 'open') {
  $whereClauses[] = "a.sellingdate > DATE_ADD(NOW(), INTERVAL 1 HOUR)";
} elseif ($biddingStatus === 'closed') {
  $whereClauses[] = "a.sellingdate <= DATE_ADD(NOW(), INTERVAL 1 HOUR)";
}

// Filter by crop type
if ($cropTypeFilter !== 'all') {
  $whereClauses[] = "a.croptype = ?";
  $params[] = $cropTypeFilter;
  $types .= "s";
}

// Combine WHERE clauses
$whereSQL = "";
if (!empty($whereClauses)) {
  $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}



$query = "SELECT a.*, u.name AS farmer_name
          FROM approved_submissions a
          JOIN users u ON a.farmerid = u.id
          $whereSQL";


// We'll sort later based on dropdown
switch ($sortOption) {
  case 'price_desc':
    $query .= " ORDER BY a.baseprice DESC";
    break;
  case 'newest':
    $query .= " ORDER BY a.submittedat DESC";
    break;
  case 'ending_soon':
  default:
    $query .= " ORDER BY a.sellingdate ASC";
    break;
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


// Filter based on bidding status (winning/outbid) after fetching
$filteredRows = [];
while ($row = $result->fetch_assoc()) {
  $approvedId = $row['approvedid'];

  // Get current highest bid
  $bidStmt = $conn->prepare("SELECT bpartnerid FROM crop_bids WHERE approvedid = ? ORDER BY bidamount DESC, bidad ASC LIMIT 1");
  $bidStmt->bind_param("i", $approvedId);
  $bidStmt->execute();
  $bidResult = $bidStmt->get_result()->fetch_assoc();
  $bidStmt->close();

  $isWinning = $bidResult && $bidResult['bpartnerid'] == $partnerId;

  // Filter logic
  if ($statusFilter === 'winning' && !$isWinning)
    continue;
  if ($statusFilter === 'outbid' && $bidResult && $bidResult['bpartnerid'] != $partnerId) {
    $filteredRows[] = $row;
  } elseif ($statusFilter === 'winning' && $isWinning) {
    $filteredRows[] = $row;
  } elseif ($statusFilter === 'all') {
    $filteredRows[] = $row;
  }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bid on Crops - AHV2</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<style>
  /* For Chrome, Safari, Edge, Opera */
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  /* For Firefox */
  input[type=number] {
    -moz-appearance: textfield;
  }
</style>

<body class="bg-gray-50">
  <div class="min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
      <!-- Header Section -->
      <div class="flex justify-between items-center mb-8">
        <div class="flex  gap-4 flex-col">
          <a href="dashboard.php" class="text-gray-600 hover:text-gray-900 flex gap-2 items-center">
            <i data-lucide="chevron-left" class="w-6 h-6"></i>
            <span>Dashboard</span>

          </a>
          <div>

            <h2 class="text-4xl text-emerald-900 font-semibold ">Bid on Available Crops</h2>
            <span class="text-lg text-gray-600 ">Browse and bid on listed crops.</span>
          </div>

        </div>
      </div>

      <!-- Filters Section -->
      <div class="bg-white rounded-2xl border shadow-sm p-6 mb-8">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Crop Type Filter -->

          <fieldset class="fieldset space-y-2">
            <legend class="fieldset-legend">Crop Type</legend>
            <select class="select border px-2 focus:border-green-600 focus:ring focus:ring-green-200" name="croptype">
              <option value="all">All Crops</option>
              <option value="buko" <?= ($_GET['croptype'] ?? '') === 'buko' ? 'selected' : '' ?>>Buko</option>
              <option value="saba" <?= ($_GET['croptype'] ?? '') === 'saba' ? 'selected' : '' ?>>Saba</option>
              <option value="lanzones" <?= ($_GET['croptype'] ?? '') === 'lanzones' ? 'selected' : '' ?>>Lanzones</option>
              <option value="rambutan" <?= ($_GET['croptype'] ?? '') === 'rambutan' ? 'selected' : '' ?>>Rambutan</option>
            </select>
          </fieldset>

          <!-- <div class=" space-y-2">
            <label class="text-sm font-medium text-gray-700">Crop Type</label>
            <select name="croptype"
              class="w-full rounded-md border border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 py-3 px-4 appearance-none">
              <option value="all">All Crops</option>

            </select>


          </div> -->

          <!-- Bidding Status -->
          <fieldset class="fieldset space-y-2">
            <legend class="fieldset-legend">Bidding Status</legend>
            <select class="select border px-2 focus:border-green-600 focus:ring focus:ring-green-200" name="status">
              <option value="all" <?= ($biddingStatus === 'all') ? 'selected' : '' ?>>All</option>
              <option value="open" <?= ($biddingStatus === 'open') ? 'selected' : '' ?>>Open</option>
              <option value="closed" <?= ($biddingStatus === 'closed') ? 'selected' : '' ?>>Closed</option>
            </select>
          </fieldset>


          <!-- Sort Option -->
          <fieldset class="fieldset space-y-2">
            <legend class="fieldset-legend">Sort By</legend>
            <select class="select border px-2 focus:border-green-600 focus:ring focus:ring-green-200" name="sort">
              <option value="ending_soon">Ending Soon</option>
              <option value="newest" <?= ($_GET['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest</option>
              <option value="price_desc" <?= ($_GET['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Highest Price
              </option>
            </select>
          </fieldset>



          <!-- Your Status -->
          <fieldset class="fieldset space-y-2">
            <legend class="fieldset-legend">Your Status</legend>
            <select class="select border px-2 focus:border-green-600 focus:ring focus:ring-green-200" name="userstatus">
              <option value="all">All Bids</option>
              <option value="winning" <?= ($_GET['status'] ?? '') === 'winning' ? 'selected' : '' ?>>Winning</option>
              <option value="outbid" <?= ($_GET['status'] ?? '') === 'outbid' ? 'selected' : '' ?>>Outbid</option>
            </select>
          </fieldset>



          <!-- Apply Filters Button -->
          <div class="lg:col-span-4 flex justify-end">
            <button type="submit"
              class="bg-emerald-600 text-white px-4 py-2 rounded-md hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-200 ease-in-out">
              Apply Filters
            </button>
          </div>
        </form>
      </div>

      <!-- Crops Grid -->
      <?php if (count($filteredRows) > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($filteredRows as $row):
            $approvedId = $row['approvedid'];

            // Get current highest bid
            $bidQuery = $conn->prepare("SELECT * FROM crop_bids WHERE approvedid = ? ORDER BY bidamount DESC, bidad ASC LIMIT 1");
            $bidQuery->bind_param("i", $approvedId);
            $bidQuery->execute();
            $highestBid = $bidQuery->get_result()->fetch_assoc();
            $bidQuery->close();

            $isHighest = $highestBid && $highestBid['bpartnerid'] == $partnerId;
            $endTime = new DateTime($row['sellingdate']);
            $biddingClosed = $endTime < new DateTime(); // Check if bidding is already closed
            ?>
            <div
              class="bg-white rounded-2xl border shadow-sm overflow-hidden flex flex-col h-full hover:shadow-lg transition-shadow duration-200">

              <!-- Crop Image -->
              <div id="img-wrap-<?= $approvedId ?>" class=" relative h-48">
                <img id="img-<?= $approvedId ?>" src=" ../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>"
                  class="w-full h-full object-cover <?= $biddingClosed ? 'opacity-30' : '' ?>"
                  alt="<?= ucfirst(htmlspecialchars($row['croptype'])) ?>">
                <?php if ($biddingClosed): ?>
                  <div class="absolute inset-0 bg-black bg-opacity-70 flex items-center justify-center">
                    <span class="text-white text-xl font-bold tracking-wider">
                      AUCTION ENDED
                    </span>
                  </div>
                <?php endif; ?>
              </div>


              <!-- Card Content -->
              <div class="p-6 flex flex-col flex-1">
                <div class="flex justify-between items-start">
                  <h3 class="text-2xl  text-gray-900">
                    <?= ucfirst(htmlspecialchars($row['croptype'])) ?>
                  </h3>
                  <span class="inline-flex items-center rounded-full  px-2.5 py-0.5 text-lg font-medium text-green-800">
                    <?= htmlspecialchars($row['quantity']) . ' ' . htmlspecialchars($row['unit']) ?>
                  </span>
                </div>
                <hr class="my-5">
                <!-- Price and Timer -->
                <div class=" flex justify-between">

                  <div class="flex flex-col">
                    <span class="text-sm text-gray-500">Base Price</span>
                    <span class="font-semibold text-xl text-gray-900">₱<?= number_format($row['baseprice'], 2) ?></span>
                  </div>

                  <div id="timer-<?= $approvedId ?>" class="text-sm font-medium"></div>


                </div>
                <!-- Keep existing JavaScript for timers and auto-refresh -->
                <script>
                  (function countdownTimer() {
                    const timerEl = document.getElementById("timer-<?= $approvedId ?>");
                    const imgEl = document.getElementById("img-<?= $approvedId ?> ");
                    const overlayEl = document.getElementById("overlay-<?= $approvedId ?>");
                    const endTime = new Date("<?= $endTime->format('Y-m-d H:i:s') ?>").getTime();

                    function updateTimer() {
                      const now = new Date().getTime();
                      const diff = endTime - now;

                      if (diff <= 0) {

                        timerEl.innerHTML = "<div class='flex gap-2 items-center text-red-500'>  <i data-lucide='lock' class='w-4 h-4'></i> <span> Bidding Closed</span></div>";

                        timerEl.className = "text-danger";
                        lucide.createIcons();
                        return;
                      }

                      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                      const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                      const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                      const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                      timerEl.innerHTML = `
                       <div class='flex flex-col'>
                        <span class='text-sm text-gray-500'>
                          Bidding ends in:
                        </span> 
                         <span class='font-mono text-lg'>
                          ${days}d ${hours}:${minutes}:${seconds}
                        </span> 
                      </div>
                      
                      
                      
                      
                      `;

                      timerEl.className = diff < 3600000 ? "text-danger fw-bold" : "text-muted";
                    }

                    updateTimer();
                    setInterval(updateTimer, 1000);
                  })();
                </script>

                <!-- Current Highest Bid -->
                <div id="highest-<?= $approvedId ?>" class="py-2 px-3 bg-gray-50 rounded-md mt-5 mb-5"></div>

                <!-- Bid History -->
                <div class=" p-3 ">
                  <div class="text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                    <i data-lucide="history" class="w-4 h-4"></i>
                    <span> Bidding History</span>
                  </div>
                  <div id="history-<?= $approvedId ?>" class="space-y-1 text-sm text-slate-600"></div>
                </div>
                <div class="mt-auto pt-4">
                  <!-- Bid Form -->
                  <div id="bid-form-wrap-<?= $approvedId ?>" class="space-y-3">
                    <form method="POST" action="submit_bid.php" class="space-y-3 w-full">
                      <input type="hidden" name="approvedid" value="<?= $approvedId ?>">
                      <div>
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Enter Bid Amount</legend>

                          <label class="input mt-2 border w-full">
                            <i data-lucide="philippine-peso" class="w-4 h-4 text-gray-500"></i>
                            <input type="number" name="bidamount" step="0.01"
                              min="<?= $highestBid ? ($highestBid['bidamount'] + 0.01) : ($row['baseprice'] + 0.01) ?>"
                              class="grow input-md " <?= $biddingClosed ? 'disabled' : '' ?> required
                              placeholder="Starting from ₱<?= number_format($highestBid ? ($highestBid['bidamount'] + 0.01) : ($row['baseprice'] + 0.01), 2) ?>">
                          </label>

                        </fieldset>
                      </div>
                      <button type="submit"
                        class="w-full flex justify-center items-center gap-3 bg-emerald-600 text-white px-4 py-2 rounded-full hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed"
                        <?= $biddingClosed ? 'disabled' : '' ?>>
                        <i data-lucide="gavel" class="w-4 h-4"></i>
                        <span>Place Bid</span>

                      </button>
                    </form>
                  </div>
                </div>
                <script>
                  (function autoRefreshBids() {
                    const approvedId = <?= $approvedId ?>;
                    // console.log("Auto-refreshing bids for approvedId:", approvedId);
                    function refresh() {
                      fetch('get_latest_bids.php?approvedid=' + approvedId)
                        .then(res => res.json())
                        .then(data => {
                          const highestEl = document.getElementById('highest-' + approvedId);
                          const historyEl = document.getElementById('history-' + approvedId);
                          const formWrap = document.getElementById('bid-form-wrap-' + approvedId);
                          const banner = document.getElementById('you-highest-' + approvedId);

                          if (data.highest_bid) {
                            highestEl.innerHTML = `<div class="flex gap-2 text-slate-700 font-semibold">
                            <i data-lucide="trending-up" class="w-5 h-5"></i>
                            <span>Current Highest Bid:</span>

                            </div>
                          <div class="flex flex-col">
                            <span class="font-bold text-2xl text-emerald-700">
                              ₱${parseFloat(data.highest_bid.amount).toFixed(2)}
                            </span>
                            <span class="text-sm text-gray-500">
                              by ${data.highest_bid.name} • ${data.highest_bid.time}
                            </span>
                          </div>
                            
                            
                            `;
                            lucide.createIcons();

                          } else {
                            highestEl.innerHTML = "<span class='text-muted'>No bids yet.</span>";
                          }

                          historyEl.innerHTML = data.history_html;

                          if (data.is_highest) {
                            formWrap.style.display = 'none';
                            banner.style.display = 'block';
                          } else {
                            formWrap.style.display = 'block';
                            banner.style.display = 'none';
                          }
                        });
                    }

                    setInterval(refresh, 3000); // Refresh every 3 seconds
                    refresh(); // First run
                  })();
                </script>

                <!-- You're Highest Message -->
                <div id="you-highest-<?= $approvedId ?>"
                  class="hidden p-3 bg-green-50 text-green-700 rounded-md text-sm font-medium">
                  You are currently the highest bidder!
                </div>


              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="rounded-lg bg-blue-50 p-4 text-sm text-blue-600">
          No crops are currently available for bidding.
        </div>
      <?php endif; ?>
    </div>
  </div>




  <script src="https://unpkg.com/lucide@latest"></script>

  <script> lucide.createIcons();</script>
</body>

</html>

<?php $conn->close(); ?>