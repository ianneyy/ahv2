<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
    header("Location: ../auth/login.php");
    exit();
}

$ownerId = $_SESSION['user_id'];
$cropFilter = $_GET['croptype'] ?? 'all';

// Fetch verified crops for this owner
$query = "SELECT a.*, u.name AS farmer_name 
          FROM approved_submissions a
          JOIN users u ON a.farmerid = u.id
          WHERE a.verifiedby = ?";

$params = [$ownerId];
$types = "i";

if ($cropFilter !== 'all') {
    $query .= " AND a.croptype = ?";
    $params[] = $cropFilter;
    $types .= "s";
}

$query .= " ORDER BY a.sellingdate ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Verified Crops</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<a href="dashboard.php" class="btn btn-secondary mb-3">← Back to Dashboard</a>

<h2>Verified Crops</h2>

<form method="GET" class="mb-4">
  <label for="croptype">Filter by Crop Type:</label>
  <select name="croptype" id="croptype" onchange="this.form.submit()" class="form-select w-auto d-inline-block ms-2">
    <option value="all" <?= $cropFilter === 'all' ? 'selected' : '' ?>>All</option>
    <option value="buko" <?= $cropFilter === 'buko' ? 'selected' : '' ?>>Buko</option>
    <option value="saba" <?= $cropFilter === 'saba' ? 'selected' : '' ?>>Saba</option>
    <option value="lanzones" <?= $cropFilter === 'lanzones' ? 'selected' : '' ?>>Lanzones</option>
    <option value="rambutan" <?= $cropFilter === 'rambutan' ? 'selected' : '' ?>>Rambutan</option>
  </select>
</form>

<?php if ($result->num_rows > 0): ?>
  <div class="row row-cols-1 row-cols-md-2 g-4">
    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="col">
        <div class="card h-100">
          <img src="../assets/uploads/<?= htmlspecialchars($row['imagepath']) ?>" class="card-img-top" style="max-height:200px; object-fit:contain;">
          <div class="card-body">
            <h5 class="card-title"><?= ucfirst($row['croptype']) ?> — <?= htmlspecialchars($row['quantity']) . ' ' . $row['unit'] ?></h5>
            <p class="card-text">
              <strong>Farmer:</strong> <?= htmlspecialchars($row['farmer_name']) ?><br>
              <strong>Base Price:</strong> ₱<?= htmlspecialchars(number_format($row['baseprice'], 2)) ?><br>
              <strong>Selling Date:</strong> <?= htmlspecialchars($row['sellingdate']) ?><br>
              <strong>Approved at:</strong> <?= htmlspecialchars($row['verifiedat']) ?>
            </p>
          </div>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
<?php else: ?>
  <p>No verified crops found.</p>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $conn->close(); ?>
