<?php
require_once '../includes/db.php';

// Get list of all BPs who have winning bids
$bpResult = mysqli_query($conn, "
    SELECT DISTINCT u.id, u.name
    FROM users u
    JOIN crop_bids b ON u.id = b.bpartnerid
    JOIN approved_submissions a ON b.approvedid = a.approvedid
    WHERE b.bidamount = (
        SELECT MAX(b2.bidamount)
        FROM crop_bids b2
        WHERE b2.approvedid = b.approvedid
    )
    AND u.user_type = 'businessPartner'
");

$selectedBP = $_GET['bp'] ?? '';
?>

<!-- Business Partner Selection -->
<div class="mb-4">
  <form method="GET" class="form-inline">
    <div class="flex justify-between items-center">

      <label for="bp" class="text-lg font-semibold text-slate-700">Business Partner</label>
      <select name="bp" id="bp" class="form-select d-inline-block px-4 border border-lg rounded-lg py-2" onchange="this.form.submit()">
        <option value="">-- Choose a BP --</option>
        <?php while ($row = mysqli_fetch_assoc($bpResult)): ?>
          <option value="<?= $row['id'] ?>" <?= $selectedBP == $row['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($row['name']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

  </form>
</div>

<?php if ($selectedBP):

  $query = "
  SELECT 
      a.croptype,
      a.quantity,
      a.baseprice,
      a.quantity * a.baseprice AS totalprice,
      a.submittedat,
      p.delivery_received_at,
      COALESCE(p.status, 'Unpaid') AS payment_status
  FROM approved_submissions a
  JOIN crop_bids b ON a.approvedid = b.approvedid
  LEFT JOIN transactions p ON a.approvedid = p.approvedid
  WHERE b.bpartnerid = $selectedBP
  AND a.winner_id != 0
    AND b.bidamount = (
      SELECT MAX(b2.bidamount)
      FROM crop_bids b2
      WHERE b2.approvedid = b.approvedid
    )
";

  $result = mysqli_query($conn, $query);
  ?>

  <!-- Transaction Table -->
  <div class="px-5">
    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
      <div class="inline-block min-w-full py-2 align-middle">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-muted/100">
              <tr>
                <th class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 border-l">Crop</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Quantity</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Base Price</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Total Price</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Payment Status</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Delivery</th>
                <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 border-r">Submitted At</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900 border-l">
                    <?= htmlspecialchars($row['croptype']) ?>
                  </td>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                    <?= htmlspecialchars($row['quantity']) ?>
                  </td>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                    ₱<?= number_format($row['baseprice'], 2) ?></td>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                    ₱<?= number_format($row['totalprice'], 2) ?></td>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                    <?php
                    $status = $row['payment_status'];
                    $badgeClass = match ($status) {
                      'verified' => 'bg-green-500',
                      'Unpaid' => 'bg-yellow-500',
                      default => 'bg-blue-500'
                    };
                    $statusLabel = $row['payment_status'];
                    $labelClass = match ($statusLabel) {
                      'verified' => 'Verified',
                      'Unpaid' => 'Unpaid',
                      default => 'Pending Verification'
                    };
                    ?>
                    <span class="py-1 px-2 rounded-full text-white text-xs <?= $badgeClass ?>"><?= $labelClass ?></span>
                  </td>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900">
                    <?= $row['delivery_received_at'] ? '<span class=" text-emerald-600 font-semibold">Received</span>' : '<span class="text-red-400 font-semibold">Not Received</span>' ?>
                  </td>
                  <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-900 border-r">
                    <?= date('M d, Y', strtotime($row['submittedat'])) ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<?php endif; ?>