<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
  header("Location: ../auth/login.php");
  exit();
}

$ownerId = $_SESSION['user_id'];
$cropFilter = $_GET['croptype'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';


// Build WHERE clauses similar to bid_crops
$whereClauses = [];
$params = [];
$types = "";

if ($cropFilter !== 'all') {
  $whereClauses[] = "approved_submissions.croptype = ?";
  $params[] = $cropFilter;
  $types .= "s";
}

if ($statusFilter !== 'all') {
  $whereClauses[] = "approved_submissions.status = ?";
  $params[] = $statusFilter;
  $types .= "s";
}

$whereSQL = "";
if (!empty($whereClauses)) {
  $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

$query = "SELECT approved_submissions.*, users.name AS farmer_name FROM approved_submissions

JOIN users ON approved_submissions.farmerid = users.id
$whereSQL";


$stmt = $conn->prepare($query);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();


?>
<?php
require_once '../includes/header.php';
?>
<div class="flex min-h-screen">
  <aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col">
    <div class="p-4 text-xl font-bold  text-[#28453E]">
      AniHanda
    </div>
    <nav class="flex-1 p-4 space-y-4">
      <a href="dashboard.php"
        class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3"> <i
          data-lucide="layout-dashboard" class="w-5 h-5"></i>
        <span>Dashboard</span></a>
      <!-- Crops Dropdown -->
      <div>
        <button onclick="toggleDropdown('cropsDropdown', 'chevronIcon')"
          class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] bg-[#BFF49B]">
          <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i> <span>Crops</span>
          </span> <i id="chevronIcon" data-lucide="chevron-down" class="w-5 h-5 transition-transform duration-300"></i>
        </button> <!-- Dropdown links -->
        <div id="cropsDropdown" class="hidden ml-5  border-l border-gray-300">
          <div class="ml-3 mt-2 space-y-2">

            <a href="verify_crops.php"
              class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
              <span>Crop Submission</span>
            </a>
            <a href="verified_crops.php"
              class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2 bg-[#BFF49B]">
              <span>Verified Crops</span>
            </a>
          </div>

        </div>
      </div>
      <a href="confirm_payments.php"
        class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
        <i data-lucide="credit-card" class="w-5 h-5"></i>
        <span>Payments</span></a>
      <a href="bid_cancellations.php"
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
  <!-- <a href="dashboard.php"
  class="inline-flex items-center gap-2 text-gray-600 hover:text-emerald-900 px-4 py-1 justify-center rounded-lg">
  <i data-lucide="chevron-left" class="w-6 h-6"></i>

  <span class="text-md">Dashboard</span>
</a> -->
  <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
    <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
      <div class="flex flex-col lg:flex-row lg:justify-between  lg:ml-4 mt-5 mb-5">
        <div class="flex justify-between items-center">
          <div>
            <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold ">Verified Crops</h2>
            <span class="text-lg text-gray-600 ">All crops approved and ready for bidding.</span>
          </div>

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

                  <div>
                    <button onclick="toggleDropdownSmall('cropsDropdownSmall', 'chevronIconSmall')"
                      class="bg-[#BFF49B] w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
                      <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i>
                        <span>Crops</span>
                      </span> <i id="chevronIconSmall" data-lucide="chevron-down"
                        class="w-5 h-5 transition-transform duration-300"></i>
                    </button> <!-- Dropdown links -->
                    <div id="cropsDropdownSmall" class="hidden ml-5  border-l border-gray-300">
                      <div class="ml-3 mt-2 space-y-2">

                        <a href="verify_crops.php"
                          class="block px-4 py-2 text-sm rounded-lg active:bg-[#BFF49B] text-[#28453E]  flex items-center gap-2">
                          <span>Crop Submission</span>
                        </a>
                        <a href="verified_crops.php"
                          class="block px-4 py-2 text-sm  rounded-lg active:bg-[#BFF49B] text-[#28453E] bg-[#BFF49B] flex items-center gap-2">
                          <span>Verified Crops</span>
                        </a>
                      </div>

                    </div>
                  </div>
          


                  <hr class="border-gray-300">

                  <li><a href="confirm_payments.php" class="flex items-center active:bg-[#BFF49B] gap-3 text-[#28453E]">
                          <i data-lucide="credit-card" class="w-5 h-5"></i>
                          <span>Payments</span>
                        </a></li>
                      <hr class="border-gray-300">

                      <li><a href="bid_cancellations.php" class="flex items-center active:bg-[#BFF49B] gap-3 text-[#28453E]">
                          <i data-lucide="ban" class="w-5 h-5"></i>
                          <span>Cancellations</span>
                        </a></li>
                      <hr class="border-gray-300">

                      <li><a onclick="logoutModal.showModal()" class="flex items-center active:bg-[#BFF49B] gap-3 text-[#28453E]">
                          <i data-lucide="log-out" class="w-5 h-5"></i>
                          <span>Logout</span>
                        </a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div
          class="mt-3 lg:mt-0 w-full lg:max-w-md  bg-white rounded-2xl shadow-sm border border-b-[7px] border-l-[4px] border-emerald-900">
          <form method="GET">
            <!-- Header with Sort and View buttons -->
            <div class="flex items-center gap-2 p-4 border-gray-200">


              <!-- Crop Button -->
              <button type="button" id="cropButton"
                class="flex items-center gap-2 bg-white text-gray-600 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                <i data-lucide="wheat" class="h-4 w-4"></i>
                <span><?php echo ucfirst(htmlspecialchars($_GET['croptype'] ?? 'Crop')); ?></span>
                <svg id="cropArrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor"
                  viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>

              <!-- Status Button -->
              <button type="button" id="statusButton"
                class="flex items-center gap-2 bg-white text-gray-600 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                <i data-lucide="badge-check" class="h-4 w-4"></i>
                <span><?php echo ucfirst(htmlspecialchars($_GET['status'] ?? 'Status')); ?></span>
                <svg id="statusArrow" class="w-4 h-4 transition-transform duration-200" fill="none"
                  stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </button>


              <fieldset class="fieldset">
                <select name="croptype" id="cropSelect" class="hidden select">
                  <option value="all" <?= $cropFilter === 'all' ? 'selected' : '' ?>>All</option>
                  <option value="buko" <?= $cropFilter === 'buko' ? 'selected' : '' ?>>Buko</option>
                  <option value="saba" <?= $cropFilter === 'saba' ? 'selected' : '' ?>>Saba</option>
                  <option value="lanzones" <?= $cropFilter === 'lanzones' ? 'selected' : '' ?>>Lanzones</option>
                  <option value="rambutan" <?= $cropFilter === 'rambutan' ? 'selected' : '' ?>>Rambutan</option>
                </select>

                <select name="status" id="statusSelect" class="hidden select">
                  <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                  <option value="open" <?= $statusFilter === 'open' ? 'selected' : '' ?>>Open</option>
                  <option value="closed" <?= $statusFilter === 'closed' ? 'selected' : '' ?>>Closed</option>
                </select>

              </fieldset>
              <button type="submit"
                class="ml-auto text-gray-400 hover:text-gray-600 p-2 hover:bg-[#ECF5E9] rounded-lg px-4">

                <span>Apply</span>
              </button>
            </div>
            <!-- Dropdown Menus -->
            <div class="relative">
              <!-- Crop Dropdown -->
              <div id="cropDropdown"
                class="hidden absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                <div data-crop-value="all"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                  All
                </div>
                <div data-crop-value="buko"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                  Buko
                </div>
                <div data-crop-value="saba"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                  Saba
                </div>
                <div data-crop-value="lanzones"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                  Lanzones
                </div>
                <div data-crop-value="rambutan"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                  Rambutan
                </div>
              </div>
            </div>

            <div class="relative">
              <!-- Status Dropdown -->
              <div id="statusDropdown"
                class="hidden absolute left-40 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                <div data-status-value="all"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-blue-400 rounded-full mr-3 hidden"></div>
                  All
                </div>
                <div data-status-value="open"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
                  Open
                </div>
                <div data-status-value="closed"
                  class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                  <div class="w-2 h-2 bg-red-400 rounded-full mr-3 hidden"></div>
                  Closed
                </div>
              </div>
            </div>

          </form>

        </div>
      </div>



      <?php if ($result->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php while ($row = $result->fetch_assoc()): ?>
            <div class="group">
              <div
                class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden border border-gray-100 hover:border-gray-200 hover:-translate-y-1">

                <!-- Image Container with Overlay -->
                <div class="relative overflow-hidden bg-gradient-to-br from-gray-50 to-gray-100">
                  <img onclick="imgModal<?= $row['approvedid'] ?>.showModal()"
                    src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>"
                    class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105"
                    alt="<?= ucfirst($row['croptype']) ?>">

                  <dialog id="imgModal<?= $row['approvedid'] ?>" class="modal modal-bottom sm:modal-middle">
                    <div class="modal-box">

                      <img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" alt="Crop Image Preview"
                        class="w-full h-auto rounded-md mt-2">

                    </div>
                    <form method="dialog" class="modal-backdrop">
                      <button>close</button>
                    </form>
                  </dialog>
                  <!-- Floating Price Badge -->
                  <div class="absolute top-4 right-4">
                    <div class="bg-[#B9F4A0] text-gray-700 px-3 py-1 rounded-full text-sm font-semibold shadow-lg">
                      ₱<?= htmlspecialchars(number_format($row['baseprice'], 2)) ?>
                    </div>
                  </div>

                  <!-- Crop Type Badge -->
                  <div class="absolute bottom-4 left-4">
                    <div
                      class="bg-white/90 backdrop-blur-sm text-gray-800 px-3 py-1 rounded-full text-sm font-medium shadow-sm">
                      <?= ucfirst($row['croptype']) ?>
                    </div>
                  </div>
                </div>

                <!-- Card Content -->
                <div class="p-6">

                  <!-- Header Section -->
                  <div class="mb-4">
                    <h3 class="text-xl font-bold text-gray-900 mb-1 line-clamp-1">
                      <?= ucfirst($row['croptype']) ?>
                    </h3>
                    <div class="flex items-center gap-2 text-gray-600">
                      <i data-lucide="package" class="w-5 h-5"></i>

                      <span class="font-semibold"><?= htmlspecialchars($row['quantity']) . ' ' . $row['unit'] ?></span>
                    </div>
                  </div>
                  <hr class="mb-4">
                  <!-- Info Grid -->
                  <div class=" mb-6 flex justify-between">

                    <!-- Farmer Info -->
                    <div class="flex items-center gap-3">
                      <div
                        class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <i data-lucide="tractor" class="w-5 h-5"></i>

                      </div>
                      <div>
                        <p class="text-sm text-gray-500">Farmer</p>
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($row['farmer_name']) ?></p>
                      </div>
                    </div>

                    <!-- Selling Date -->
                    <div class="flex items-center gap-3">
                      <div
                        class="w-10 h-10 bg-gradient-to-br from-orange-500 to-orange-600 rounded-full flex items-center justify-center flex-shrink-0">
                        <i data-lucide="calendar-clock" class="w-5 h-5"></i>

                      </div>
                      <div>
                        <p class="text-sm text-gray-500">Selling Date</p>
                        <p class="font-semibold text-gray-900"><?= date('M d, Y', strtotime($row['sellingdate'])) ?></p>
                      </div>
                    </div>

                  </div>
                  <?php
                  $statusLabel = match ($row['status']) {
                    'open' => 'Open',

                    default => 'Closed'
                  };
                  $statusColor = match ($row['status']) {
                    'open' => 'bg-green-500',
                    default => 'bg-red-500'
                  };

                  ?>
                  <!-- Footer Section -->
                  <div class="pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <div class="w-2 h-2 <?= $statusColor ?> rounded-full"></div>
                        <span class="text-xs text-gray-500"><?= $statusLabel ?></span>
                      </div>
                      <div class="text-xs text-gray-400">
                        <?= date('M d, Y', strtotime($row['verifiedat'])) ?>
                      </div>
                    </div>
                  </div>

                  <!-- Action Button (Optional) -->
                  <!-- <div class="mt-4 pt-2">
              <button
                class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 shadow-sm">
                <span class="flex items-center justify-center gap-2">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                    </path>
                  </svg>
                  View Details
                </span>
              </button>
            </div> -->

                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>

      <?php else: ?>
        <div class="text-center py-12">
          <div class="max-w-md mx-auto">
            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4">
                </path>
              </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No Crops Available</h3>
            <p class="text-gray-500">No verified crops found at the moment. Check back later for new listings.</p>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>

</div>
<?php
require_once '../includes/footer.php';
?>
<script src="./assets/verified_crops.js"></script>
<?php $conn->close(); ?>

<script>
  function toggleDropdown(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

  }
  function toggleDropdownSmall(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

  }
</script>