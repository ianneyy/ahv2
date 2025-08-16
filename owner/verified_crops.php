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

// Fetch verified crops for this owner
// $query = "SELECT a.*, u.name AS farmer_name 
//           FROM approved_submissions a
//           JOIN users u ON a.farmerid = u.id
//           WHERE a.verifiedby = ?";
$query = "

SELECT 
    transactions.*,
    approved_submissions.*,
    users.name AS farmer_name
FROM transactions
JOIN approved_submissions 
    ON transactions.approvedid = approved_submissions.approvedid
JOIN users 
    ON approved_submissions.farmerid = users.id;



";
$params = [$ownerId];
$types = "i";

if ($cropFilter !== 'all') {
  $query .= " AND a.croptype = ?";
  $params[] = $cropFilter;
  $types .= "s";
}

// $query .= " ORDER BY a.sellingdate ASC";

$stmt = $conn->prepare($query);
// $stmt->bind_param($types, ...$params);
// $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// echo "<pre>";
// print_r($result->fetch_all(MYSQLI_ASSOC));
// echo "</pre>";
// exit;
?>

<?php
require_once '../includes/header.php';
?>
<a href="dashboard.php"
  class="inline-flex items-center gap-2 text-gray-600 hover:text-emerald-900 px-4 py-1 justify-center rounded-lg">
  <i data-lucide="chevron-left" class="w-6 h-6"></i>

  <span class="text-md">Dashboard</span>
</a>

<div class="flex justify-between items-center ml-4 mt-5 mb-5">
  <div>
    <h2 class="text-4xl text-emerald-900 font-semibold ">Verified Crops</h2>
    <span class="text-lg text-gray-600 ">All crops approved and ready for bidding.</span>
  </div>
  <div class="max-w-md  bg-white rounded-2xl shadow-sm border border-b-[7px] border-l-[4px] border-emerald-900">
    <form method="GET">
      <!-- Header with Sort and View buttons -->
      <div class="flex items-center gap-2 p-4 border-gray-200">


        <!-- View Button -->
        <button type="button" id="cropButton"
          class="flex items-center gap-2 bg-white text-gray-600 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
          <i data-lucide="wheat" class="h-4 w-4"></i>
          Crop
          <svg id="cropArrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <fieldset class="fieldset">
          <select name="croptype" class="hidden select">
            <option value="all" <?= $cropFilter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="buko" <?= $cropFilter === 'buko' ? 'selected' : '' ?>>Buko</option>
            <option value="saba" <?= $cropFilter === 'saba' ? 'selected' : '' ?>>Saba</option>
            <option value="lanzones" <?= $cropFilter === 'lanzones' ? 'selected' : '' ?>>Lanzones</option>
            <option value="rambutan" <?= $cropFilter === 'rambutan' ? 'selected' : '' ?>>Rambutan</option>
          </select>

        </fieldset>
        <button type="submit" class="ml-auto text-gray-400 hover:text-gray-600 p-2 hover:bg-[#ECF5E9] rounded-lg px-4">

          <span>Apply</span>
        </button>
      </div>
      <!-- Dropdown Menu -->
      <div class="relative">
        <!-- Dropdown -->
        <div id="cropDropdown"
          class="hidden absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
          <!-- Sort Options -->
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
          <!-- Order Options -->
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
            <img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>"
              class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105"
              alt="<?= ucfirst($row['croptype']) ?>">

            <!-- Floating Price Badge -->
            <div class="absolute top-4 right-4">
              <div class="bg-[#B9F4A0] text-gray-700 px-3 py-1 rounded-full text-sm font-semibold shadow-lg">
                ₱<?= htmlspecialchars(number_format($row['baseprice'], 2)) ?>
              </div>
            </div>

            <!-- Crop Type Badge -->
            <div class="absolute bottom-4 left-4">
              <div class="bg-white/90 backdrop-blur-sm text-gray-800 px-3 py-1 rounded-full text-sm font-medium shadow-sm">
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
              'rejected' => 'Rejected',
              'verified' => 'Verified',
              'awaiting_verification' => 'Pending Verification',
              default => 'Pending'
            };
            $statusColor = match ($row['status']) {
              'rejected' => 'bg-red-500',
              'verified' => 'bg-green-500',
              'awaiting_verification' => 'bg-yellow-500',
              default => 'bg-blue-500'
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


<?php
require_once '../includes/footer.php';
?>
<script src="./assets/script2.js"></script>
<?php $conn->close(); ?>