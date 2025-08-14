<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';


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

    // ✅ 4. Send Notification AFTER everything is ready
    notify(
      $conn,
      $crop['farmerid'],
      'farmer',
      'Your crop submission has been verified! Base price: ₱' . $basePrice,

    );
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


<div class="flex justify-between items-center ml-4 mt-5">
  <div>
    <h2 class="text-4xl text-emerald-900 font-semibold ">Verify Crop Submissions</h2>
    <span class="text-lg text-gray-600 ">Review and approve farmer crop submissions</span>
  </div>
  <div class="max-w-md  bg-white rounded-2xl shadow-sm border border-gray-200">
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
        <button type="submit" class="ml-auto text-gray-400 hover:text-gray-600 p-2 hover:bg-[#ECF5E9] rounded-lg px-4">
          <!-- <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
      </svg> -->
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

                  <input type="text" name="rejectionreason" placeholder="Enter rejection reason..." required
                    class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 text-sm min-w-0 sm:w-48">

                  <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 whitespace-nowrap gap-2">
                    <i data-lucide="x" class="h-4 w-4"></i>

                    Reject
                  </button>
                </form>
              </div>

              <!-- Approve Button -->
              <button type="button"
                class="inline-flex items-center px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 whitespace-nowrap gap-2"
                data-bs-toggle="modal" data-bs-target="#approveModal<?= $row['submissionid'] ?>">

                <i data-lucide="stamp" class="h-4 w-4"></i>
                <span>Approve</span>
              </button>

            </div>
          </div>
        </div>

      </div>
      <!-- Modal -->
      <div class="modal fade" id="approveModal<?= $row['submissionid'] ?>" tabindex="-1"
        aria-labelledby="approveLabel<?= $row['submissionid'] ?>" aria-hidden="true">
        <div class="modal-dialog">
          <form method="POST" action="verify_crops.php">
            <input type="hidden" name="submissionid" value="<?= $row['submissionid'] ?>">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="approveLabel<?= $row['submissionid'] ?>">Approve Crop Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label">Base Price (₱)</label>
                  <input type="number" name="baseprice" class="form-control" required min="0" step="0.01">
                </div>
                <div class="mb-3">
                  <label class="form-label">Selling Date</label>
                  <?php
                  $minDateTime = date('Y-m-d\TH:i', strtotime('+3 days 08:00')); // start from 8AM
                  $maxDateTime = date('Y-m-d\TH:i', strtotime('+10 days 17:00')); // latest 5PM
                  ?>
                  <input type="datetime-local" name="sellingdate" class="form-control" required min="<?= $minDateTime ?>"
                    max="<?= $maxDateTime ?>">
                </div>
              </div>
              <div class="modal-footer">
                <button type="submit" name="confirm_approve" class="btn btn-primary">Confirm Approve</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endwhile; ?>
<?php else: ?>
  <p>No pending submissions to verify.</p>
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