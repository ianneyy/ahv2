<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);



if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'businessPartner' && $_SESSION['user_type'] !== 'businessOwner')) {
  header("Location: ../auth/login.php");
  exit();
}

$partnerId = $_SESSION['user_id'];


$conn->query("
    UPDATE approved_submissions
    SET status = 'closed'
    WHERE sellingdate <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
");

$conn->query("
    UPDATE approved_submissions
    SET status = 'open'
    WHERE sellingdate > DATE_ADD(NOW(), INTERVAL 1 HOUR)
");


// Get filter values from dropdowns
$cropTypeFilter = $_GET['croptype'] ?? 'all';
$biddingStatus = $_GET['status'] ?? 'open';      // For open/closed bidding filter
$statusFilter = $_GET['userstatus'] ?? 'all';  // For winning/outbid filter
$sortOption = $_GET['sort'] ?? 'newest';

$whereClauses = [];
$params = [];
$types = "";


// Filter by bidding status
// if ($biddingStatus === 'open') {
//   $whereClauses[] = "a.sellingdate > DATE_ADD(NOW(), INTERVAL 1 HOUR)";
//   // $whereClauses[] = "a.sellingdate == open)";
// } elseif ($biddingStatus === 'closed') {
//   $whereClauses[] = "a.sellingdate <= DATE_ADD(NOW(), INTERVAL 1 HOUR)";
//   // $whereClauses[] = "a.sellingdate == closed)";
// }
if ($biddingStatus === 'open') {
  $whereClauses[] = "a.status = 'open'";
} elseif ($biddingStatus === 'closed') {
  $whereClauses[] = "a.status = 'closed'";
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
          $whereSQL
           AND a.approvedid NOT IN (
              SELECT b.approvedid 
              FROM blocklist b 
              WHERE b.userid = {$partnerId}
          )";


// Sorting
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

<?php
require_once '../includes/header.php';
?>
<div class="flex min-h-screen ">
  <!-- Sidebar -->
  <?php if ($_SESSION['user_type'] === 'businessOwner'): ?>
    <aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col sticky top-0 h-screen">
      <div class="p-4 text-xl font-bold  text-[#28453E]">
        AniHanda
      </div>
      <nav class="flex-1 p-4 space-y-4">
        <a href="../owner/dashboard.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3"> <i
            data-lucide="layout-dashboard" class="w-5 h-5"></i>
          <span>Dashboard</span></a>

        <a href="../partner/bid_crops.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E] flex items-center gap-3"> <i
            data-lucide="gavel" class="w-5 h-5"></i>
          <span>Bidding</span></a>
        <!-- Crops Dropdown -->
        <div>
          <button onclick="toggleDropdown('cropsDropdown', 'chevronIcon')"
            class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
            <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i> <span>Crops</span>
            </span> <i id="chevronIcon" data-lucide="chevron-down" class="w-5 h-5 transition-transform duration-300"></i>
          </button> <!-- Dropdown links -->
          <div id="cropsDropdown" class="hidden ml-5  border-l border-gray-300">
            <div class="ml-3 mt-2 space-y-2">

              <a href="../owner/verify_crops.php"
                class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
                <span>Crop Submission</span>
              </a>
              <a href="../owner/verified_crops.php"
                class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
                <span>Verified Crops</span>
              </a>
            </div>

          </div>
        </div>
        <a href="../owner/confirm_payments.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="credit-card" class="w-5 h-5"></i>
          <span>Payments</span></a>
        <a href="../owner/bid_cancellations.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="ban" class="w-5 h-5"></i>
          <span>Cancellations</span></a>
        <a onclick="logoutModal.showModal()"
          class="block px-4 py-2 rounded-lg cursor-pointer hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="log-out" class="w-5 h-5"></i>
          <span>Logout</span>
        </a>

      </nav>
      <div class="p-4 border-t border-gray-300 text-sm text-gray-400">
        © 2025 AniHanda
      </div>
    </aside>
  <?php else: ?>
    <aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col sticky top-0 h-screen">
      <div class="p-4 text-xl font-bold  text-[#28453E]">
        AniHanda
      </div>
      <nav class="flex-1 p-4 space-y-4">
        <a href="dashboard.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3"> <i
            data-lucide="layout-dashboard" class="w-5 h-5"></i>
          <span>Dashboard</span></a>

        <a href="bid_crops.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="gavel" class="w-5 h-5"></i>
          <span>Bidding</span></a>
        <a href="won_bids.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="sparkles" class="w-5 h-5"></i>
          <span>Won</span></a>
        <a onclick="logoutModal.showModal()"
          class="block px-4 py-2 rounded-lg cursor-pointer hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="log-out" class="w-5 h-5"></i>
          <span>Logout</span>
        </a>

      </nav>
      <div class="p-4 border-t border-gray-300 text-sm text-gray-400">
        © 2025 AniHanda
      </div>
    </aside>
  <?php endif; ?>


  <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
    <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
      <!-- Header Section -->
      <div class="flex flex-col lg:flex-row lg:justify-between  lg:ml-4 lg:mt-5 mb-5">
        <div class="flex justify-between items-center">

          <div>

            <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold ">Bid on Available Crops</h2>
            <span class="text-md lg:text-lg text-gray-600 ">Browse and bid on listed crops.</span>
          </div>
          <!-- Small screen -->
          <?php if ($_SESSION['user_type'] === 'businessOwner'): ?>
            <div class="block lg:hidden">
              <div class="drawer">
                <input id="my-drawer" type="checkbox" class="drawer-toggle" />
                <div class="drawer-content">
                  <!-- Page content here -->
                  <label for="my-drawer" class=" drawer-button"><i data-lucide="menu" class="w-5 h-5"></i></label>

                </div>
                <div class="drawer-side ">
                  <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>


                  <ul class="menu  bg-[#ECF5E9] text-base-content min-h-full w-80 p-4 gap-3">
                    <li>
                      <div class="p-4 text-xl font-bold  text-[#28453E]">
                        AniHanda
                      </div>
                    </li>
                    <!-- Sidebar content here -->
                    <li><a href="../owner/dashboard.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E]">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a href="../partner/bid_crops.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E]">
                        <i data-lucide="gavel" class="w-5 h-5"></i>
                        <span>Bidding</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <div>
                      <button onclick="toggleDropdownSmall('cropsDropdownSmall', 'chevronIconSmall')"
                        class=" w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
                        <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i>
                          <span>Crops</span>
                        </span> <i id="chevronIconSmall" data-lucide="chevron-down"
                          class="w-5 h-5 transition-transform duration-300"></i>
                      </button> <!-- Dropdown links -->
                      <div id="cropsDropdownSmall" class="hidden ml-5  border-l border-gray-300">
                        <div class="ml-3 mt-2 space-y-2">

                          <a href="../owner/verify_crops.php"
                            class="block px-4 py-2 text-sm rounded-lg active:bg-[#BFF49B]  text-[#28453E]  flex items-center gap-2">
                            <span>Crop Submission</span>
                          </a>
                          <a href="../owner/verified_crops.php"
                            class="block px-4 py-2 text-sm  rounded-lg active:bg-[#BFF49B]  text-[#28453E]  flex items-center gap-2">
                            <span>Verified Crops</span>
                          </a>
                        </div>

                      </div>
                    </div>



                    <hr class="border-gray-300">

                    <li><a href="../owner/confirm_payments.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="credit-card" class="w-5 h-5"></i>
                        <span>Payments</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a href="../owner/bid_cancellations.php"
                        class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="ban" class="w-5 h-5"></i>
                        <span>Cancellations</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a onclick="logoutModal.showModal()"
                        class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                        <span>Logout</span>
                      </a></li>
                  </ul>
                </div>
              </div>
            </div>
          <?php else: ?>
            <!-- Small screen -->
            <div class="block lg:hidden">
              <div class="drawer">
                <input id="my-drawer" type="checkbox" class="drawer-toggle" />
                <div class="drawer-content">
                  <!-- Page content here -->
                  <label for="my-drawer" class=" drawer-button"><i data-lucide="menu" class="w-5 h-5"></i></label>

                </div>
                <div class="drawer-side ">
                  <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>


                  <ul class="menu  bg-[#ECF5E9] text-base-content min-h-full w-80 p-4 gap-3">
                    <li>
                      <div class="p-4 text-xl font-bold  text-[#28453E]">
                        AniHanda
                      </div>
                    </li>
                    <!-- Sidebar content here -->
                    <li><a href="dashboard.php" class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E]">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a href="bid_crops.php"
                        class="flex active:bg-[#BFF49B] bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="gavel" class="w-5 h-5"></i>
                        <span>Bidding</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a href="won_bids.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="sparkles" class="w-5 h-5"></i>
                        <span>Won</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a onclick="logoutModal.showModal()"
                        class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                        <span>Logout</span>
                      </a></li>
                  </ul>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
    <div
      class="max-w-4xl mb-10 bg-white rounded-2xl shadow-sm border  border-b-[7px] border-l-[4px] border-emerald-900 ">
      <form method="GET">
        <!-- Header with Sort and View buttons -->
        <div
          class="flex items-center  gap-1 mx-1 lg:gap-2 p-1 lg:p-4 border-gray-200 overflow-x-auto lg:overflow-hidden">
          <!-- Sort Button -->


          <!-- View Button -->
          <button type="button" id="cropButton"
            class="flex items-center gap-2 bg-white text-gray-600 px-2 lg:px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
            <i data-lucide="wheat" class="h-4 w-4"></i>
            <span class="text-xs lg:text-md"
              id="crop"><?php echo ucfirst(htmlspecialchars($_GET['croptype'] ?? 'Crop')); ?>
            </span>
            <svg id="cropArrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>



          <button type="button" id="sortButton"
            class="flex items-center gap-2 bg-white text-gray-600  px-2 lg:px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
            </svg>
            <?php
            $sortLabel = match ($_GET['sort'] ?? null) {
              'newest' => 'Newest',
              'ending_soon' => 'Ending Soon',
              'price_desc' => 'Highest Price',
              default => 'Sort'
            };
            ?>
            <span class="text-xs lg:text-md"><?= $sortLabel ?></span>
            <svg id="sortArrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <button type="button" id="biddingStatusButton"
            class="flex items-center gap-2 bg-white text-gray-600 px-2 lg:px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
            <i data-lucide="door-open" class="h-4 w-4"></i>

            <?php
            $bidLabel = match ($_GET['status'] ?? null) {
              'all' => 'All',
              'open' => 'Open',
              'closed' => 'Closed',
              default => 'Bidding Status'
            };
            ?>
            <span
              class="text-xs lg:text-md max-w-[50px] lg:max-w-[100px] truncate sm:whitespace-nowrap block"><?= $bidLabel ?></span>
            <svg id="biddingStatusArrow" class="w-4 h-4 transition-transform duration-200" fill="none"
              stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <?php if ($_SESSION['user_type'] !== 'businessOwner'): ?>
            <button type="button" id="yourStatusButton"
              class="flex items-center gap-2 bg-white text-gray-600 px-2 lg:px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
              <i data-lucide="chart-column" class="h-4 w-4"></i>
              <?php
              $userLabel = match ($_GET['userstatus'] ?? null) {
                'all' => 'All Bids',
                'winning' => 'Winning',
                'outbid' => 'Outbid',
                default => 'Your Status'
              };
              ?>
              <span
                class="text-xs lg:text-md max-w-[50px] lg:max-w-[100px] truncate sm:whitespace-nowrap block"><?= $userLabel ?></span>
              <svg id="yourStatusArrow" class="w-4 h-4 transition-transform duration-200" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          <?php endif; ?>
          <fieldset class="fieldset space-y-2">
            <select class="hidden select border px-2 focus:border-green-600 focus:ring focus:ring-green-200"
              name="croptype">
              <option value="all">All Crops</option>
              <option value="buko" <?= ($_GET['croptype'] ?? '') === 'buko' ? 'selected' : '' ?>>Buko</option>
              <option value="saba" <?= ($_GET['croptype'] ?? '') === 'saba' ? 'selected' : '' ?>>Saba</option>
              <option value="lanzones" <?= ($_GET['croptype'] ?? '') === 'lanzones' ? 'selected' : '' ?>>Lanzones
              </option>
              <option value="rambutan" <?= ($_GET['croptype'] ?? '') === 'rambutan' ? 'selected' : '' ?>>Rambutan
              </option>
            </select>
          </fieldset>
          <fieldset class="fieldset space-y-2">
            <select class="hidden select border px-2 focus:border-green-600 focus:ring focus:ring-green-200"
              name="status">
              <option value="all" <?= ($biddingStatus === 'all') ? 'selected' : '' ?>>All</option>
              <option value="open" <?= ($biddingStatus === 'open') ? 'selected' : '' ?>>Open</option>
              <option value="closed" <?= ($biddingStatus === 'closed') ? 'selected' : '' ?>>Closed</option>
            </select>
          </fieldset>


          <!-- Sort Option -->
          <fieldset class="fieldset space-y-2">
            <select class="hidden select border px-2 focus:border-green-600 focus:ring focus:ring-green-200"
              name="sort">
              <option value="ending_soon">Ending Soon</option>
              <option value="newest" <?= ($_GET['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest</option>
              <option value="price_desc" <?= ($_GET['sort'] ?? '') === 'price_desc' ? 'selected' : '' ?>>Highest
                Price
              </option>
            </select>
          </fieldset>



          <!-- Your Status -->
          <fieldset class="fieldset space-y-2">
            <select class="hidden select border px-2 focus:border-green-600 focus:ring focus:ring-green-200"
              name="userstatus">
              <option value="all">All Bids</option>
              <option value="winning" <?= ($_GET['status'] ?? '') === 'winning' ? 'selected' : '' ?>>Winning</option>
              <option value="outbid" <?= ($_GET['status'] ?? '') === 'outbid' ? 'selected' : '' ?>>Outbid</option>
            </select>
          </fieldset>



          <!-- More Options Button -->
          <button type="button" onclick="window.location.href = 'bid_crops.php';"
            class="ml-auto text-gray-400 hover:text-gray-600 p-2 hover:bg-[#ECF5E9] rounded-lg px-4">

            <span class="text-xs lg:text-md">Default</span>
          </button>
        </div>
        <!-- Sort Dropdown Menu -->
        <div class="relative z-[999]">
          <!-- Dropdown -->
          <div id="cropDropdown"
            class="hidden absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <!-- Sort Options -->
            <div data-crop-value="all"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              All
            </div>
            <div data-crop-value="buko"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              Buko
            </div>
            <!-- Order Options -->
            <div data-crop-value="saba"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
              Saba
            </div>
            <div data-crop-value="lanzones"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
              Lanzones
            </div>
            <div data-crop-value="rambutan"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
              Rambutan
            </div>

          </div>
        </div>
        <!-- Crop Dropdown Menu -->
        <div class="relative">
          <!-- Dropdown -->
          <div id="sortDropdown"
            class="hidden absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <!-- Sort Options -->
            <div data-sort-value="newest"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              Newest
            </div>
            <div data-sort-value="ending_soon"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              Ending Soon
            </div>

            <!-- Order Options -->
            <div data-sort-value="price_desc"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
              Highest Price
            </div>

          </div>
        </div>

        <!-- Bidding Status Dropdown Menu -->
        <div class="relative">
          <!-- Dropdown -->
          <div id="biddingStatusDropdown"
            class="hidden absolute left-0 lg:left-60 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <!-- Sort Options -->
            <div data-biddingStatus-value="all"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              All
            </div>
            <div data-biddingStatus-value="open"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              Open
            </div>



            <!-- Order Options -->
            <div data-biddingStatus-value="closed"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
              Closed
            </div>


          </div>
        </div>

        <!-- Your Status Dropdown Menu -->
        <div class="relative">
          <!-- Dropdown -->
          <div id="yourStatusDropdown"
            class="hidden absolute left-0 lg:left-80 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
            <!-- Sort Options -->
            <div data-yourStatus-value="all"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              All Bids
            </div>
            <div data-yourStatus-value="winning"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

              Winning
            </div>
            <!-- Order Options -->
            <div data-yourStatus-value="outbid"
              class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
              Outbid
            </div>


          </div>
        </div>

      </form>

    </div>

    <!-- Crops Grid -->
    <?php if (count($filteredRows) > 0): ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($filteredRows as $row):
          $approvedId = $row['approvedid'];
          $status = $row['status'];

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
            class="bg-gray-100 rounded-2xl border border-slate-300 shadow-sm overflow-hidden flex flex-col h-full hover:shadow-md transition-shadow duration-200 ">
            <!-- 6 78 59 -->
            <!-- Crop Image -->
            <div id="img-wrap-<?= $approvedId ?>" class=" relative h-48">
              <img onclick="imgModal<?= $approvedId ?>.showModal()" id="img-<?= $approvedId ?>"
                src=" ../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>"
                class="w-full h-full object-cover <?= $status === 'closed' ? 'opacity-30' : '' ?>"
                alt="<?= ucfirst(htmlspecialchars($row['croptype'])) ?>">

              <dialog id="imgModal<?= $approvedId ?>" class="modal modal-bottom sm:modal-middle">
                <div class="modal-box">

                  <img src=" ../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" alt="Crop Image Preview"
                    class="w-full h-auto rounded-md mt-2">

                </div>
                <form method="dialog" class="modal-backdrop">
                  <button>close</button>
                </form>
              </dialog>
              <?php if ($status === 'closed'): ?>
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
                <!-- <span><?= $status ?></span> -->


              </div>
              <!-- Keep existing JavaScript for timers and auto-refresh -->
              <script>
                (function countdownTimer() {
                  const timerEl = document.getElementById("timer-<?= $approvedId ?>");
                  const imgEl = document.getElementById("img-<?= $approvedId ?>");
                  const overlayEl = document.getElementById("overlay-<?= $approvedId ?>");
                  const endTime = new Date("<?= $endTime->format('Y-m-d H:i:s') ?>").getTime();
                  const status = "<?= $status ?>";

                  function updateTimer() {
                    const now = new Date().getTime();
                    const diff = endTime - now;

                    if (status == "closed") {

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
                          ${days}d ${hours}h ${minutes}m ${seconds}s
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
              <div id="history-section-<?= $approvedId ?>" class="p-3 hidden">
                <div class="text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                  <i data-lucide="history" class="w-4 h-4"></i>
                  <span> Bidding History</span>
                </div>
                <div id="history-<?= $approvedId ?>" class="space-y-1 text-sm text-slate-600"></div>

                <div class="flex items-center justify-between px-4">
                  <button id="prev-<?= $approvedId ?>"
                    class="text-xs bg-gray-200 px-2 py-1 rounded-md cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Prev</button>
                  <span id="page-info-<?= $approvedId ?>" class="text-xs text-gray-500"></span>
                  <button id="next-<?= $approvedId ?>"
                    class="text-xs bg-gray-200 px-2 py-1 rounded-md cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
                </div>
              </div>

              <div class="mt-auto pt-4">
                <!-- Bid Form -->
                <div id="bid-form-wrap-<?= $approvedId ?>" class="space-y-3">
                  <?php if ($_SESSION['user_type'] !== 'businessOwner'): ?>
                    <form method="POST" action="submit_bid.php" class="space-y-3 w-full">
                      <input type="hidden" name="approvedid" value="<?= $approvedId ?>">
                      <div>
                        <fieldset class="fieldset">
                          <legend class="fieldset-legend">Enter Bid Amount</legend>

                          <label class="input mt-2 border w-full">
                            <i data-lucide="philippine-peso" class="w-4 h-4 text-gray-500"></i>
                            <input type="number" name="bidamount" step="0.01"
                              min="<?= $highestBid ? ($highestBid['bidamount'] + 0.01) : ($row['baseprice'] + 0.01) ?>"
                              class="grow input-md " <?= $status === 'closed' ? 'disabled' : '' ?> required
                              placeholder="Starting from ₱<?= number_format($highestBid ? ($highestBid['bidamount'] + 0.01) : ($row['baseprice'] + 0.01), 2) ?>">
                          </label>

                        </fieldset>
                      </div>
                      <button type="submit"
                        class="w-full flex justify-center items-center gap-3 bg-emerald-600 text-white px-4 py-2 rounded-full hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed"
                        <?= $status === 'closed' ? 'disabled' : '' ?>>
                        <i data-lucide="gavel" class="w-4 h-4"></i>
                        <span>Place Bid</span>

                      </button>
                    </form>
                  <?php endif; ?>

                </div>
              </div>
              <script>
                (function autoRefreshBids() {
                  const approvedId = <?= $approvedId ?>;
                  let currentPage = 1;
                  const historySection = document.getElementById(`history-section-${approvedId}`);

                  const prevBtn = document.getElementById('prev-<?= $approvedId ?>');
                  const nextBtn = document.getElementById('next-<?= $approvedId ?>');
                  const pageInfo = document.getElementById('page-info-<?= $approvedId ?>');


                  function refresh() {
                    // Check if we're trying to go beyond available pages
                    if (currentPage < 1) {
                      currentPage = 1;
                    }

                    fetch(`get_latest_bids.php?approvedid=${approvedId}&page=${currentPage}&limit=2`)
                      .then(res => res.json())
                      .then(data => {
                        // If we're on a page that doesn't exist, go back to the last valid page
                        if (data.pagination && currentPage > data.pagination.total_pages) {
                          currentPage = data.pagination.total_pages;
                          if (currentPage < 1) currentPage = 1;
                          // Don't call refresh again to avoid infinite loop
                          return;
                        }
                        const highestEl = document.getElementById('highest-' + approvedId);
                        const historyEl = document.getElementById('history-' + approvedId);
                        const formWrap = document.getElementById('bid-form-wrap-' + approvedId);
                        const banner = document.getElementById('you-highest-' + approvedId);
                        // Show/Hide history section
                        if (data.history_html && data.history_html.trim() !== '' && !data.history_html.includes('No bids yet')) {
                          historySection.classList.remove('hidden');
                        } else {
                          historySection.classList.add('hidden');
                        }
                        // Update highest bid
                        if (data.highest_bid) {
                          highestEl.innerHTML = `
                        <div class="flex gap-2 text-slate-700 font-semibold">
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

                        // Update history
                        historyEl.innerHTML = data.history_html;

                        // Update page info
                        if (data.pagination) {
                          if (data.pagination.total_pages > 0) {
                            pageInfo.textContent = `Page ${data.pagination.current_page} of ${data.pagination.total_pages}`;
                          } else {
                            pageInfo.textContent = 'No bids available';
                          }
                        } else {
                          pageInfo.textContent = '';
                        }

                        // Enable/disable pagination buttons
                        if (data.pagination) {
                          const hasMultiplePages = data.pagination.total_pages > 1;
                          const showPagination = hasMultiplePages && data.pagination.total_bids > 0;

                          // Show/hide pagination container
                          const paginationContainer = prevBtn.parentElement;
                          paginationContainer.style.display = showPagination ? 'flex' : 'none';

                          if (showPagination) {
                            prevBtn.disabled = currentPage <= 1;
                            nextBtn.disabled = currentPage >= data.pagination.total_pages;

                            // Update button styles
                            prevBtn.classList.toggle('opacity-50', currentPage <= 1);
                            prevBtn.classList.toggle('cursor-not-allowed', currentPage <= 1);
                            nextBtn.classList.toggle('opacity-50', currentPage >= data.pagination.total_pages);
                            nextBtn.classList.toggle('cursor-not-allowed', currentPage >= data.pagination.total_pages);
                          }
                        }

                        // Show/hide bid form and banner
                        if (data.is_highest) {
                          formWrap.style.display = 'none';
                          banner.style.display = 'block';
                        } else {
                          formWrap.style.display = 'block';
                          banner.style.display = 'none';
                        }
                      })
                      .catch(error => {
                        console.error('Error fetching bids:', error);
                      });
                  }

                  // Button click handlers
                  prevBtn.addEventListener('click', () => {
                    if (currentPage > 1) {
                      currentPage--;
                      refresh();
                    }
                  });

                  nextBtn.addEventListener('click', () => {
                    // We need to check pagination in the refresh function
                    // For now, just increment and let refresh handle the validation
                    currentPage++;
                    refresh();
                  });

                  // Initial load
                  refresh();

                  // Auto refresh every 3 seconds (but don't change page)
                  setInterval(() => {
                    if (currentPage === 1) { // Only auto-refresh first page
                      refresh();
                    }
                  }, 3000);
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
</main>
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


<script src="https://unpkg.com/lucide@latest"></script>

<script>
  lucide.createIcons();
  function toggleDropdown(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

  }
  function toggleDropdownSmall(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

  }
</script>
<script src="./assets/bid_crops.js"></script>
</body>

</html>

<?php $conn->close(); ?>