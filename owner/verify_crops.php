<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);

// Block non-owners
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
  header("Location: ../auth/login.php");
  exit();
}

$ownerId = $_SESSION['user_id'];
$cropFilter = $_GET['croptype'] ?? 'all';
$sortOption = $_GET['sort'] ?? 'newest';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $submissionId = $_POST['submissionid'];
  $verifiedAt = date("Y-m-d H:i:s");

  if (isset($_POST['confirm_approve'])) {

    $basePrice = $_POST['baseprice'];
    $sellingDate = $_POST['sellingdate']; // will be in YYYY-MM-DDTHH:MM format
    // optional: validate format if you want to be strict

    // Get original crop details
    $stmt = $conn->prepare("SELECT * FROM crop_submissions WHERE submissionid = ?");
    $stmt->bind_param("i", $submissionId);
    $stmt->execute();
    $crop = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 1. Update crop_submissions status
    $stmt = $conn->prepare("UPDATE crop_submissions SET status='verified', verifiedat=?, verifiedby=? WHERE submissionid=?");
    $stmt->bind_param("sii", $verifiedAt, $ownerId, $submissionId);
    $stmt->execute();
    $stmt->close();

    // 2. Insert into approved_submissions
    $stmt = $conn->prepare("INSERT INTO approved_submissions 
          (submissionsid, farmerid, croptype, quantity, unit, imagepath, baseprice, sellingdate, verifiedat, verifiedby) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
      "iissssdssi",
      $submissionId,
      $crop['farmerid'],
      $crop['croptype'],
      $crop['quantity'],
      $crop['unit'],
      $crop['imagepath'],
      $basePrice,
      $sellingDate,
      $verifiedAt,
      $ownerId
    );
    $stmt->execute();
    $stmt->close();
    $_SESSION['toast_message'] = "Crop has been approved successfully!";
    // ✅ 4. Send Notification AFTER everything is ready
    notify(
      $conn,
      $crop['farmerid'],
      'farmer',
      'Your crop submission has been verified! Base price: ₱' . $basePrice,

    );
    header("Location: verify_crops.php");
    exit;
  }

  if (isset($_POST['action']) && $_POST['action'] === 'reject') {
    $reason = $_POST['rejectionreason'];

    $stmt = $conn->prepare("UPDATE crop_submissions SET status='rejected', verifiedat=?, verifiedby=?, rejectionreason=? WHERE submissionid=?");
    $stmt->bind_param("sisi", $verifiedAt, $ownerId, $reason, $submissionId);
    $stmt->execute();
    $stmt->close();

    // 🔄 Re-fetch crop to get farmerid
    $stmt = $conn->prepare("SELECT farmerid FROM crop_submissions WHERE submissionid = ?");
    $stmt->bind_param("i", $submissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $crop = $result->fetch_assoc();
    $stmt->close();

    notify(
      $conn,
      $crop['farmerid'],
      'farmer',
      'Your crop submission was rejected. Reason: ' . $reason
    );
  }
}

// Fetch pending submissions
$query = "SELECT cs.*, u.name AS farmer_name 
          FROM crop_submissions cs 
          JOIN users u ON cs.farmerid = u.id 
          WHERE cs.status = 'pending'";

$params = [];
$types = "";

if ($cropFilter !== 'all') {
  $query .= " AND cs.croptype = ?";
  $params[] = $cropFilter;
  $types .= "s";
}

switch ($sortOption) {
  case 'oldest':
    $query .= " ORDER BY cs.submittedat ASC";
    break;
  case 'qty_desc':
    $query .= " ORDER BY cs.quantity DESC";
    break;
  case 'qty_asc':
    $query .= " ORDER BY cs.quantity ASC";
    break;
  default:
    $query .= " ORDER BY cs.submittedat DESC";
    break;
}

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


<a href="dashboard.php"
  class="inline-flex items-center gap-2 text-gray-600 hover:text-emerald-900 px-4 py-1 justify-center rounded-lg">
  <i data-lucide="chevron-left" class="w-6 h-6"></i>

  <span class="text-md">Dashboard</span>
</a>


<div class="flex flex-col lg:flex-row justify-between items-center ml-4 mt-5">
  <div>
    <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold ">Verify Crop Submissions</h2>
    <span class="text-md lg:text-lg text-gray-600 ">Review and approve farmer crop submissions</span>
  </div>
  <div
    class="mt-3 lg:mt-0 max-w-md  bg-white rounded-2xl shadow-sm border border-b-[7px] border-l-[4px] border-emerald-900">
    <form method="GET">
      <!-- Header with Sort and View buttons -->
      <div class="flex items-center gap-2 p-4 border-gray-200">
        <!-- Sort Button -->
        <button type="button" id="sortButton"
          class="flex items-center gap-2 bg-white text-gray-600   px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
          </svg>
          Sort
          <svg id="sortArrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

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

        <fieldset class="fieldset ">
          <select name="sort" class="hidden select ">
            <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sortOption === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="qty_desc" <?= $sortOption === 'qty_desc' ? 'selected' : '' ?>>Quantity: High to Low</option>
            <option value="qty_asc" <?= $sortOption === 'qty_asc' ? 'selected' : '' ?>>Quantity: Low to High</option>
          </select>

        </fieldset>


        <!-- More Options Button -->
        <a href="verify_crops.php"
          class="ml-auto text-gray-400 hover:text-gray-600 p-2 hover:bg-[#ECF5E9] rounded-lg px-4">
          <!-- <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
      </svg> -->
          <span>Default</span>
        </a>
      </div>
      <!-- Dropdown Menu -->
      <div class="relative">
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
          <!-- <div class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
        Quantity: High to Low
      </div>
       <div class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
         Quantity: Low to High
      </div> -->
        </div>
      </div>
      <!-- Dropdown Menu -->
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
          <div data-sort-value="oldest"
            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>

            Oldest
          </div>

          <!-- Separator -->
          <div class="border-t border-gray-200 my-2"></div>

          <!-- Order Options -->
          <div data-sort-value="qty_asc"
            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
            Ascending
          </div>
          <div data-sort-value="qty_desc"
            class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
            <div class="w-2 h-2 bg-green-400 rounded-full mr-3 hidden"></div>
            Descending
          </div>
          <!-- <div class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
        Quantity: High to Low
      </div>
       <div class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
         Quantity: Low to High
      </div> -->
        </div>
      </div>
    </form>

  </div>


</div>








<?php if ($result->num_rows > 0): ?>
  <?php while ($row = $result->fetch_assoc()): ?>
    <div class="border rounded-3xl p-6 mt-5 border-slate-300 hover:shadow-md transition duration-200 ease-in">

      <div class="flex flex-col lg:flex-row gap-6">

        <!-- Image Section -->
        <div class="flex-shrink-0">
          <div class="relative h-64 w-64 rounded-xl overflow-hidden">
            <img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" class="w-full h-full object-cover"
              alt="Crop Image">
          </div>
        </div>

        <!-- Content Section -->
        <div class="flex-1">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="space-y-3">
              <div class="flex flex-col">
                <span class="text-xs font-medium text-gray-600">Farmer</span>
                <span class="text-lg text-gray-900"><?= htmlspecialchars($row['farmer_name']) ?></span>
              </div>
              <div class="flex flex-col">
                <span class="text-xs font-medium text-gray-600">Crop Type</span>
                <span class="text-lg text-gray-900"><?= htmlspecialchars($row['croptype']) ?></span>
              </div>
            </div>
            <div class="space-y-3">
              <div class="flex flex-col">
                <span class="text-xs font-medium text-gray-600">Quantity</span>
                <span
                  class="text-lg text-gray-900"><?= htmlspecialchars($row['quantity']) . ' ' . htmlspecialchars($row['unit']) ?></span>
              </div>
              <div class="flex flex-col">
                <span class="text-xs font-medium text-gray-600">Submitted</span>
                <span class="text-lg text-gray-900">
                  <?= date('F j, Y g:i A', strtotime($row['submittedat'])) ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Action Buttons Section -->
          <div class="border-t pt-4">
            <div class="flex flex-col sm:flex-row gap-3 justify-end">

              <!-- Reject Section -->
              <div class="flex flex-col sm:flex-row gap-2 items-start sm:items-center">
                <form method="POST" class="flex flex-col sm:flex-row gap-2 items-start sm:items-center"
                  onsubmit="return confirmReject(this);">
                  <input type="hidden" name="submissionid" value="<?= $row['submissionid'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <div class="flex justify-between gap-2">

                    <input type="text" name="rejectionreason" placeholder="Enter rejection reason..." required
                      class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm ">

                    <button type="submit"
                      class="inline-flex items-center px-4 py-2 bg-red-600/80 hover:bg-red-700 text-white text-sm font-medium rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 whitespace-nowrap gap-2">
                      <i data-lucide="x" class="h-4 w-4"></i>

                      Reject
                    </button>
                  </div>

                </form>
              </div>

              <!-- Approve Button -->
              <button type="button" onclick="approveModal<?= $row['submissionid'] ?>.showModal()"
                class="inline-flex items-center px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 whitespace-nowrap gap-2"
                data-bs-toggle="modal" data-bs-target="#approveModal<?= $row['submissionid'] ?>">
                <div class="flex justify-center items-center gap-2 w-full">

                  <i data-lucide="stamp" class="h-4 w-4"></i>
                  <span>Approve</span>
                </div>

              </button>

            </div>
          </div>
        </div>

      </div>
      <!-- Modal -->
      <dialog id="approveModal<?= $row['submissionid'] ?>" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box">


          <form method="POST" action="verify_crops.php">
            <input type="hidden" name="submissionid" value="<?= $row['submissionid'] ?>">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="text-lg text-gray-500" id="approveLabel<?= $row['submissionid'] ?>">Approve Crop Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="p-5 border rounded-md mb-5 italic gap-3">


                <div class="flex gap-20 items-center">

                  <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-gray-600">Farmer:</span>
                    <span
                      class="text-sm text-gray-900"><?= ucfirst(strtolower(htmlspecialchars($row['farmer_name']))) ?></span>
                  </div>
                  <div class="flex items-center gap-3">
                    <span class="text-xs font-medium text-gray-600">Crop</span>
                    <span class="text-sm text-gray-900"><?= ucfirst(strtolower(htmlspecialchars($row['croptype']))) ?>
                    </span>
                  </div>
                </div>
                <div class="flex items-center gap-3">
                  <span class="text-xs font-medium text-gray-600">Quantity:</span>
                  <span
                    class="text-sm text-gray-900"><?= htmlspecialchars($row['quantity']) . ' ' . htmlspecialchars($row['unit']) ?></span>
                </div>

              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <fieldset class="fieldset">
                    <legend class="fieldset-legend text-emerald-900">Base Price</legend>

                    <label class="input input-success mt-2 border w-full">
                      <i data-lucide="philippine-peso" class="w-4 h-4 text-gray-500"></i>
                      <input type="number" name="baseprice" required min="0" step="0.01">

                    </label>

                  </fieldset>
                  <!-- <label class="form-label">Base Price (₱)</label>
                  <input type="number" name="baseprice" class="form-control" required min="0" step="0.01"> -->
                </div>
                <!-- <div class="mb-3">
                  <label class="form-label">Selling Date</label>
                  <?php
                  $minDateTime = date('Y-m-d\TH:i', strtotime('+3 days 08:00')); // start from 8AM
                  $maxDateTime = date('Y-m-d\TH:i', strtotime('+10 days 17:00')); // latest 5PM
                  ?>
                  <input type="datetime-local" name="sellingdate" class="form-control" required min="<?= $minDateTime ?>"
                    max="<?= $maxDateTime ?>">
                </div> -->


                <div class="space-y-2">

                  <fieldset class="fieldset">
                    <legend class="fieldset-legend text-emerald-900"> Selling Date & Time</legend>

                    <div class="relative mt-2">
                      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                      </div>
                      <?php
                      $minDateTime = date('Y-m-d\TH:i', strtotime('+3 days 08:00'));
                      $maxDateTime = date('Y-m-d\TH:i', strtotime('+10 days 17:00'));
                      ?>
                      <input type="datetime-local" name="sellingdate"
                        class=" w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors text-gray-900"
                        required min="<?= $minDateTime ?>" max="<?= $maxDateTime ?>">
                    </div>
                    <p class="text-xs text-gray-500">Available: 3-10 days from now, 8AM-5PM</p>
                  </fieldset>
                </div>

              </div>
              <div class="flex justify-end gap-4 mt-5">
                <button onclick="document.getElementById('approveModal<?= $row['submissionid'] ?>').close()" type="button"
                  class="text-sm px-4 py-2 text-gray-400 hover:text-gray-600" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="confirm_approve"
                  class="text-sm px-5 py-2 bg-emerald-600 hover:bg-emerald-700 rounded-full text-white">Confirm
                  Approve</button>
              </div>
            </div>
          </form>
        </div>
      </dialog>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <!-- Enhanced Empty State -->
  <div class="flex flex-col items-center justify-center py-16 px-4 min-h-[400px]">
    <!-- Animated Icon Container -->
    <div class="relative mb-6">
      <div class="bg-gradient-to-br from-green-50 to-emerald-100 rounded-full p-8 animate-float">
        <i data-lucide="folder-open" class="h-16 w-16 text-emerald-400"></i>
      </div>
      <!-- Floating particles -->
      <div class="absolute -top-2 -right-2 w-3 h-3 bg-emerald-200 rounded-full animate-pulse"></div>
      <div class="absolute -bottom-1 -left-3 w-2 h-2 bg-emerald-200 rounded-full animate-pulse"
        style="animation-delay: 1s;"></div>
    </div>

    <!-- Content -->
    <div class="text-center space-y-3 max-w-md">
      <h3 class="text-xl font-semibold text-gray-800">No pending submissions</h3>
      <p class="text-gray-500 leading-relaxed">
        All submissions have been processed. New submissions will appear here for verification.
      </p>

      <!-- Optional Action Button -->
      <div class="pt-4">
        <button onclick="window.location.reload()"
          class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition-colors text-sm font-medium focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
          <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
          Refresh
        </button>
      </div>
    </div>
  </div>

  <!-- Alternative: Minimal Version -->

  <!-- <div class="flex flex-col items-center justify-center py-20 px-4 min-h-[300px]">
    <div class="bg-gray-50 rounded-2xl p-6 mb-6">
        <i data-lucide="check-circle-2" class="h-12 w-12 text-gray-300"></i>
    </div>
    
    <div class="text-center space-y-2">
        <h3 class="text-lg font-medium text-gray-800">All caught up!</h3>
        <p class="text-gray-500 text-sm">No pending submissions to verify.</p>
    </div>
</div> -->


  <!-- Alternative: With Stats -->
  <!--   
<div class="flex flex-col items-center justify-center py-16 px-4 min-h-[400px]">
    <div class="relative mb-8">
        <div class="absolute inset-0 bg-gradient-to-br from-green-50 to-emerald-50 rounded-full transform scale-110 animate-pulse"></div>
        <div class="relative bg-gradient-to-br from-green-100 to-emerald-100 rounded-full p-8">
            <div class="relative">
                <i data-lucide="clipboard-check" class="h-16 w-16 text-green-500"></i>
                <div class="absolute -top-1 -right-1 bg-green-500 rounded-full p-1">
                    <i data-lucide="check" class="h-3 w-3 text-white"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center space-y-4 max-w-sm">
        <h3 class="text-xl font-semibold text-gray-800">Great work!</h3>
        <p class="text-gray-500">
            You've reviewed all submissions. Check back later for new ones.
        </p>
        
        <?php if (isset($stats)): ?>
        <div class="bg-gray-50 rounded-lg p-4 mt-6">
            <div class="flex items-center justify-center space-x-6 text-sm">
                <div class="text-center">
                    <div class="font-semibold text-gray-800"><?= $stats['this_week'] ?? '0' ?></div>
                    <div class="text-gray-500">This week</div>
                </div>
                <div class="w-px h-8 bg-gray-300"></div>
                <div class="text-center">
                    <div class="font-semibold text-gray-800"><?= $stats['total'] ?? '0' ?></div>
                    <div class="text-gray-500">Total</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div> -->


  <!-- Add these CSS animations if not already included -->
  <style>
    @keyframes float {

      0%,
      100% {
        transform: translateY(0px);
      }

      50% {
        transform: translateY(-10px);
      }
    }

    .animate-float {
      animation: float 3s ease-in-out infinite;
    }
  </style>
<?php endif; ?>

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
<?php
require_once '../includes/footer.php';
?>
<script>
  function confirmReject(form) {
    const reason = form.rejectionreason.value.trim();
    if (reason === '') {
      alert("Please provide a reason for rejection.");
      return false;
    }
    return confirm("Are you sure you want to reject this submission?");
  }
</script>
<script src="./assets/script.js"></script>

<?php $conn->close(); ?>

</body>

</html>