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

        $stmt->bind_param("iissssdssi",
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
    case 'oldest': $query .= " ORDER BY cs.submittedat ASC"; break;
    case 'qty_desc': $query .= " ORDER BY cs.quantity DESC"; break;
    case 'qty_asc': $query .= " ORDER BY cs.quantity ASC"; break;
    default: $query .= " ORDER BY cs.submittedat DESC"; break;
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Verify Crops</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body></body>

<h2>Verify Crop Submissions</h2>

<form method="GET" style="margin-bottom: 20px;">
  <label>Filter by Crop Type:</label>
  <select name="croptype">
    <option value="all" <?= $cropFilter === 'all' ? 'selected' : '' ?>>All</option>
    <option value="buko" <?= $cropFilter === 'buko' ? 'selected' : '' ?>>Buko</option>
    <option value="saba" <?= $cropFilter === 'saba' ? 'selected' : '' ?>>Saba</option>
    <option value="lanzones" <?= $cropFilter === 'lanzones' ? 'selected' : '' ?>>Lanzones</option>
    <option value="rambutan" <?= $cropFilter === 'rambutan' ? 'selected' : '' ?>>Rambutan</option>
  </select>

  <label>Sort by:</label>
  <select name="sort">
    <option value="newest" <?= $sortOption === 'newest' ? 'selected' : '' ?>>Newest</option>
    <option value="oldest" <?= $sortOption === 'oldest' ? 'selected' : '' ?>>Oldest</option>
    <option value="qty_desc" <?= $sortOption === 'qty_desc' ? 'selected' : '' ?>>Quantity: High to Low</option>
    <option value="qty_asc" <?= $sortOption === 'qty_asc' ? 'selected' : '' ?>>Quantity: Low to High</option>
  </select>
  <button type="submit">Apply</button>
</form>

<a href="dashboard.php">← Back to Dashboard</a><br><br>

<?php if ($result->num_rows > 0): ?>
  <?php while ($row = $result->fetch_assoc()): ?>
    <div style="border:1px solid #aaa; padding:15px; margin-bottom:20px;">
      <strong>Farmer:</strong> <?= htmlspecialchars($row['farmer_name']) ?><br>
      <strong>Crop:</strong> <?= htmlspecialchars($row['croptype']) ?><br>
      <strong>Quantity:</strong> <?= htmlspecialchars($row['quantity']) . ' ' . htmlspecialchars($row['unit']) ?><br>
      <strong>Submitted at:</strong> <?= htmlspecialchars($row['submittedat']) ?><br>
      <img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" width="150" height="150" alt="Crop Image"><br><br>

      <!-- Approve Button -->
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?= $row['submissionid'] ?>">Approve</button>

      <!-- Reject Form -->
      <form method="POST" style="display:inline-block;" onsubmit="return confirmReject(this);">
        <input type="hidden" name="submissionid" value="<?= $row['submissionid'] ?>">
        <input type="hidden" name="action" value="reject">
        <input type="text" name="rejectionreason" placeholder="Reason for rejection" required>
        <button type="submit">❌ Reject</button>
      </form>

      <!-- Modal -->
      <div class="modal fade" id="approveModal<?= $row['submissionid'] ?>" tabindex="-1" aria-labelledby="approveLabel<?= $row['submissionid'] ?>" aria-hidden="true">
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
                    <input type="datetime-local" name="sellingdate" class="form-control" required
                        min="<?= $minDateTime ?>"
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

<?php $conn->close(); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>