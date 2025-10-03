<?php
require_once '../includes/db.php';
require_once '../includes/session.php';

$partnerId = $_SESSION['user_id'] ?? 0;

// Total Bids
$totalBidsQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM crop_bids WHERE bpartnerid = $partnerId");
$totalBids = mysqli_fetch_assoc($totalBidsQuery)['total'] ?? 0;

// Successful Bids (highest bid per approved crop)
// $successBidsQuery = mysqli_query($conn, "
//     SELECT COUNT(DISTINCT b.approvedid) as successful
//     FROM crop_bids b
//     JOIN (
//         SELECT approvedid, MAX(bidamount) AS max_bid
//         FROM crop_bids
//         GROUP BY approvedid
//     ) max_bids ON b.approvedid = max_bids.approvedid AND b.bidamount = max_bids.max_bid
//      JOIN approved_submissions ON b.approvedid = approved_submissions.approvedid
//     WHERE approved_submissions.status = 'closed'
//     AND b.bpartnerid = $partnerId
// ");

$successBidsQuery = "SELECT COUNT(*) AS successful 
                     FROM approved_submissions 
                     WHERE winner_id = ?";

$stmt = $conn->prepare($successBidsQuery);
$stmt->bind_param("i", $partnerId); // assuming $userid is an integer
$stmt->execute();

$result = $stmt->get_result();
$row = $result->fetch_assoc();

$successfulBids = $row['successful'] ?? 0;
$stmt->close();
// Lost Bids = Total - Successful
$lostBids = $totalBids - $successfulBids;

// Success Rate
$successRate = $totalBids > 0 ? round(($successfulBids / $totalBids) * 100, 2) : 0;
?>

<section class="flex flex-col lg:flex-row gap-5">
  <div class="w-full flex flex-col  gap-3">
    <!-- <h3>Crop Submission Summary</h3> -->
    <div class="w-full flex flex-col lg:flex-row gap-5">

      <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl ">
        <div class="flex item-center justify-between px-5 lg:px-10 pt-6">

          <span class="text-slate-600 font-semibold">Total</span>
          <div class="p-3">

            <i data-lucide="clipboard-list" class="w-6 h-6 text-emerald-700"></i>
          </div>

        </div>
        <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

          <span class="text-3xl font-semibold text-gray-100">
            <?= $totalBids ?>
          </span>
        </div>
      </div>
      <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
        <div class="flex item-center justify-between  px-5 lg:px-10 pt-6">

          <span class="text-slate-600 font-semibold">Success Rate</span>
          <div class="p-3">

            <i data-lucide="clipboard-clock" class="w-6 h-6 text-emerald-700"></i>

          </div>
        </div>
        <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

          <span class="text-3xl font-semibold text-gray-100">
            <?= $successRate ?>%
          </span>
        </div>
      </div>
    </div>
    <div class="flex gap-5 flex-col lg:flex-row">
      <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
        <div class="flex item-center justify-between  px-5 lg:px-10 pt-6">

          <span class="text-slate-600 font-semibold">Successful Bids</span>
          <div class="p-3">

            <i data-lucide="clipboard-check" class="w-6 h-6 text-emerald-700"></i>

          </div>
        </div>
        <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

          <span class="text-3xl font-semibold text-gray-100">
            <?= $successfulBids ?>
          </span>
        </div>
      </div>
      <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
        <div class="flex item-center justify-between  px-5 lg:px-10 pt-6">

          <span class="text-slate-600 font-semibold ">Lost Bids</span>
          <div class="p-3">

            <i data-lucide="clipboard-x" class="w-6 h-6 text-emerald-700"></i>

          </div>
        </div>
        <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

          <span class="text-3xl font-semibold text-gray-100">
            <?= $lostBids ?>
          </span>
        </div>
      </div>
    </div>
  </div>

</section>
<div class="w-full  rounded-3xl border border-slate-300 bg-gray-100 p-4 lg:p-6 mt-5">
  <h2 class="text-md lg:text-lg font-semibold mb-6 text-slate-600">Bidding Performance Overview</h2>

  <div class="flex flex-col gap-8">
    <!-- Chart Column -->
    <div class="flex justify-center items-center">
      <div class="w-64 h-64">
        <canvas id="biddingSuccessChart"></canvas>
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
        backgroundColor: ['#BFF49B', '#fee2e2'],
        borderColor: ['#064E3B', '#ef4444'],
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