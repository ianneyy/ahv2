<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

$partnerId = $_SESSION['user_id'] ?? 0;

// Total Bids
$totalBidsQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM crop_bids WHERE bpartnerid = $partnerId");
$totalBids = mysqli_fetch_assoc($totalBidsQuery)['total'] ?? 0;

// Successful Bids (highest bid per approved crop)
$successBidsQuery = mysqli_query($conn, "
    SELECT COUNT(DISTINCT b.approvedid) as successful
    FROM crop_bids b
    JOIN (
        SELECT approvedid, MAX(bidamount) AS max_bid
        FROM crop_bids
        GROUP BY approvedid
    ) max_bids ON b.approvedid = max_bids.approvedid AND b.bidamount = max_bids.max_bid
    WHERE b.bpartnerid = $partnerId
");
$successfulBids = mysqli_fetch_assoc($successBidsQuery)['successful'] ?? 0;

// Lost Bids = Total - Successful
$lostBids = $totalBids - $successfulBids;

// Success Rate
$successRate = $totalBids > 0 ? round(($successfulBids / $totalBids) * 100, 2) : 0;
?>

<div class="bg-white max-w-2xl  rounded-3xl border shadow-md p-6">
  <h2 class="text-lg font-semibold mb-6">Bidding Performance Overview</h2>

  <div class="flex flex-col gap-8">
    <!-- Chart Column -->
    <div class="flex justify-center items-center">
      <div class="w-64 h-64">
        <canvas id="biddingSuccessChart"></canvas>
      </div>
    </div>

    <!-- Stats Column -->
    <div class="space-y-6">
      <div class="flex flex-col gap-4">
        <div class="flex justify-between items-center gap-4">

          <!-- Total Bids Card -->
          <div class=" rounded-lg p-4 w-full">
            <div class="text-lg text-slate-600">Total Bids</div>
            <div class="text-4xl font-bold text-gray-900"><?= $totalBids ?></div>
          </div>

          <!-- Success Rate Card -->
          <div class=" rounded-lg p-4 w-full">
            <div class="text-lg text-slate-600">Success Rate</div>
            <div class="text-4xl font-bold text-green-700"><?= $successRate ?>%</div>
          </div>
        </div>

        <div class="flex flex-col gap-4 mt-5">
          <!-- Successful Bids Card -->
          <!-- <div class=" rounded-lg p-4 w-full">
            <div class="text-sm text-gray-500">Successful Bids</div>
            <div class="flex items-center gap-2">
              <div class="text-4xl font-bold text-gray-900"><?= $successfulBids ?></div>
              <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
          </div>

          <div class=" rounded-lg p-4 w-full">
            <div class="text-sm text-gray-500">Lost Bids</div>
            <div class="flex items-center gap-2">
              <div class="text-4xl font-bold text-gray-900"><?= $lostBids ?></div>
              <div class="bg-red-200 p-y-2 px-4 rounded-full">
                <i class="w-4 h-4 text-red-500" data-lucide="x"></i>

              </div>

            </div>
          </div> -->

          <div class="flex justify-between items-center gap-4 px-5">
            <div class="flex items-center gap-2">
              <i class="w-6 h-6 text-green-500" data-lucide="check"></i>
              <span class="text-lg text-gray-500 font-semibold">Successful Bids</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-2xl text-green-500 font-bold"><?= $successfulBids ?></span>
            </div>
          </div>
          <hr>
          <div class="flex justify-between items-center gap-4 px-5">
            <div class="flex items-center gap-2">
              <i class="w-6 h-6 text-red-500" data-lucide="x"></i>
              <span class="text-lg text-gray-500 font-semibold">Lost Bids</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-2xl text-red-500 font-bold"><?= $lostBids ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>



</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const ctx = document.getElementById('biddingSuccessChart').getContext('2d');
  new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Successful Bids', 'Lost Bids'],
      datasets: [{
        data: [<?= $successfulBids ?>, <?= $lostBids ?>],
        backgroundColor: ['#dcfce7', '#fee2e2'],
        borderColor: ['#22c55e', '#ef4444'],
        borderWidth: 2,
        cutout: '75%'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            padding: 20,
            font: {
              size: 12
            }
          }
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              const label = context.label || '';
              const value = context.raw;
              const total = <?= $totalBids ?>;
              const percent = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
              return `${label}: ${value} (${percent}%)`;
            }
          }
        }
      }
    }
  });
</script>