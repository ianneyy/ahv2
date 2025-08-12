<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

// Make sure only farmers can view this
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'farmer') {
  header("Location: ../auth/login.php");
  exit();
}

$farmerid = $_SESSION['user_id'];

// Get submissions from the database
$stmt = $conn->prepare("SELECT * FROM crop_submissions WHERE farmerid = ? ORDER BY submittedat DESC");
$stmt->bind_param("i", $farmerid);
$stmt->execute();
$result = $stmt->get_result();

$statusFilter = $_GET['status'] ?? 'all';
$farmerid = $_SESSION['user_id'];

// Build the query based on filter
if ($statusFilter === 'all') {
  $stmt = $conn->prepare("SELECT * FROM crop_submissions WHERE farmerid = ? ORDER BY submittedat DESC");
  $stmt->bind_param("i", $farmerid);
} else {
  $stmt = $conn->prepare("SELECT * FROM crop_submissions WHERE farmerid = ? AND status = ? ORDER BY submittedat DESC");
  $stmt->bind_param("is", $farmerid, $statusFilter);
}
$stmt->execute();
$result = $stmt->get_result();

?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Submissions</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="p-10 bg-gray-50">
  <div class="max-w-7xl mx-auto">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-8">
      <div>
        <a href="dashboard.php" class="text-sm text-gray-900 hover:text-gray-700 mt-1 inline-flex items-center">
          
          <svg class="w-6 h-6 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
            <h2 class="text-2xl font-bold text-gray-900">My Submissions</h2>

        </a>
      </div>

      <!-- Filter -->
      <form method="GET" class="flex items-center space-x-2">
        <label for="status" class="text-sm font-medium text-gray-700">Filter by Status:</label>
        <select name="status" id="status" onchange="this.form.submit()"
          class="rounded-md border border-gray-300 py-2 pl-3 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
          <option value="all" <?php if ($statusFilter === 'all')
            echo 'selected'; ?>>All</option>
          <option value="pending" <?php if ($statusFilter === 'pending')
            echo 'selected'; ?>>Pending</option>
          <option value="verified" <?php if ($statusFilter === 'verified')
            echo 'selected'; ?>>Verified</option>
          <option value="rejected" <?php if ($statusFilter === 'rejected')
            echo 'selected'; ?>>Rejected</option>
        </select>
      </form>
    </div>

    <!-- Table -->
    <div class="mt-4 flex flex-col">
      <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-muted/100">
                <tr>
                  <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 border-l">Crop Type</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Quantity</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Unit</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Image</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Submitted At</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Verified At</th>
                  <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 border-r">Rejection Reason
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <?php while ($row = $result->fetch_assoc()): ?>
                  <tr class="hover:bg-gray-50">
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900 border-l">
                      <?php echo htmlspecialchars($row['croptype']); ?></td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <?php echo htmlspecialchars($row['quantity']); ?></td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <?php echo htmlspecialchars($row['unit']); ?></td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <img src="../assets/uploads/<?php echo htmlspecialchars($row['imagepath']); ?>"
                        class="h-16 w-16 object-cover rounded-md" alt="Crop Image">
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm">
                      <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                            <?php echo match ($row['status']) {
                                              'pending' => 'bg-yellow-100 text-yellow-800',
                                              'verified' => 'bg-green-100 text-green-800',
                                              'rejected' => 'bg-red-100 text-red-800',
                                              default => 'bg-gray-100 text-gray-800'
                                            }; ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                      </span>
                    </td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <?php echo htmlspecialchars($row['submittedat']); ?></td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                      <?php echo $row['verifiedat'] ? htmlspecialchars($row['verifiedat']) : '-'; ?></td>
                    <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 border-r">
                      <?php echo $row['rejectionreason'] ? htmlspecialchars($row['rejectionreason']) : '-'; ?></td>
                  </tr>

                  <?php if ($row['status'] === 'rejected'): ?>
                    <tr class="bg-gray-50">
                      <td colspan="8" class="px-3 py-2">
                        <form action="submit_crop.php" method="GET" class="flex justify-end">
                          <input type="hidden" name="submissionid" value="<?php echo htmlspecialchars($row['submissionid']); ?>">

                          <input type="hidden" name="croptype" value="<?php echo htmlspecialchars($row['croptype']); ?>">
                          <input type="hidden" name="quantity" value="<?php echo htmlspecialchars($row['quantity']); ?>">
                          <input type="hidden" name="unit" value="<?php echo htmlspecialchars($row['unit']); ?>">
                          <input type="hidden" name="imagepath" value="<?php echo htmlspecialchars($row['imagepath']); ?>">
                          <button type="submit"
                            class="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Resubmit
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endif; ?>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>