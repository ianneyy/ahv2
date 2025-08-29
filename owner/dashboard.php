<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';


// 🔐 Check login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
  header("Location: ../auth/login.php");
  exit();
}

// 📊 Crop submission counts
$total = $conn->query("SELECT COUNT(*) as count FROM crop_submissions")->fetch_assoc()['count'];
$pending = $conn->query("SELECT COUNT(*) as count FROM crop_submissions WHERE status='pending'")->fetch_assoc()['count'];
$verified = $conn->query("SELECT COUNT(*) as count FROM crop_submissions WHERE status='verified'")->fetch_assoc()['count'];
$rejected = $conn->query("SELECT COUNT(*) as count FROM crop_submissions WHERE status='rejected'")->fetch_assoc()['count'];

// 🌾 Crop Type Breakdown
$cropCounts = [];
$cropQuery = $conn->query("SELECT croptype, COUNT(*) as total FROM crop_submissions GROUP BY croptype");
while ($row = $cropQuery->fetch_assoc()) {
  $cropCounts[$row['croptype']] = $row['total'];
}

// 📈 Submission Trends (Week, Month, Year)
$range = $_GET['range'] ?? 'week';
switch ($range) {
  case 'month':
    $query = "
            SELECT DATE(submittedat) as submission_date, COUNT(*) as count
            FROM crop_submissions
            WHERE submittedat >= CURDATE() - INTERVAL 1 MONTH
            GROUP BY DATE(submittedat)
        ";
    break;
  case 'year':
    $query = "
            SELECT DATE_FORMAT(submittedat, '%Y-%m') as submission_date, COUNT(*) as count
            FROM crop_submissions
            WHERE submittedat >= CURDATE() - INTERVAL 1 YEAR
            GROUP BY DATE_FORMAT(submittedat, '%Y-%m')
        ";
    break;
  default: // week
    $query = "
            SELECT DATE(submittedat) as submission_date, COUNT(*) as count
            FROM crop_submissions
            WHERE submittedat >= CURDATE() - INTERVAL 6 DAY
            GROUP BY DATE(submittedat)
        ";
}

$submissionTrends = [];
$trendResult = $conn->query($query);
while ($row = $trendResult->fetch_assoc()) {
  $submissionTrends[$row['submission_date']] = $row['count'];
}

// Fill in missing dates for uniformity
if ($range === 'week' || $range === 'month') {
  $days = $range === 'week' ? 6 : 30;
  for ($i = $days; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    if (!isset($submissionTrends[$date]))
      $submissionTrends[$date] = 0;
  }
} else {
  for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    if (!isset($submissionTrends[$month]))
      $submissionTrends[$month] = 0;
  }
}
ksort($submissionTrends);

// 💸 Top 3 Paying Business Partners
$partnerNames = [];
$partnerBids = [];
$top3Query = "
    SELECT u.name, SUM(cb.bidamount) AS total_bids
    FROM crop_bids cb
    JOIN users u ON cb.bpartnerid = u.id
    GROUP BY cb.bpartnerid
    ORDER BY total_bids DESC
    LIMIT 3
";
$top3Result = $conn->query($top3Query);
while ($row = $top3Result->fetch_assoc()) {
  $partnerNames[] = $row['name'];
  $partnerBids[] = $row['total_bids'];
}

// 👨‍🌾 Top Contributing Farmers
$topFarmers = [];
$farmerQuery = $conn->query("
    SELECT u.name AS farmer_name, COUNT(cs.submissionid) AS total
    FROM crop_submissions cs
    JOIN users u ON cs.farmerid = u.id
    GROUP BY cs.farmerid
    ORDER BY total DESC
    LIMIT 5
");
while ($row = $farmerQuery->fetch_assoc()) {
  $topFarmers[$row['farmer_name']] = $row['total'];
}

// 📈 Revenue by Crop Type
$revRange = $_GET['revRange'] ?? 'month';
$dateCondition = $revRange === 'year' ? "WHERE sellingdate >= CURDATE() - INTERVAL 1 YEAR" : "WHERE sellingdate >= CURDATE() - INTERVAL 1 MONTH";
$revenueData = [];

$sql = "
    SELECT croptype, SUM(baseprice) AS total_revenue
    FROM approved_submissions
    $dateCondition
    GROUP BY croptype
    ORDER BY total_revenue DESC
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
  $revenueData[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

</head>

<body>
  <div class="max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
    <div class="flex items-center justify-center">

      <div id="bar" class="flex w-full justify-between items-center mt-8 mb-10 px-8 py-4 rounded-full">
        <h2 class="text-2xl font-semibold text-emerald-800">Welcome,
          <?= ucfirst(htmlspecialchars($_SESSION["user_name"])) ?>!
        </h2>

        <div class="relative">
          <div
            class="rounded-full p-2 flex items-center justify-center hover:bg-emerald-900 hover:text-white transition duration-300 ease-in-out">

            <?php include '../includes/notification_ui.php'; ?>
          </div>

        </div>
      </div>
    </div>



    <section class="flex gap-5">


      <div class="w-full  border  border-emerald-900 shadow-lg bg-[#BFF49B] rounded-3xl flex items-center "
        style="box-shadow: 6px 6px 0px #28453E;">

        <div class="flex flex-col gap-5 px-5 w-full ">

          <a href="verify_crops.php"
            class="flex gap-2 text-emerald-900 hover:bg-emerald-800 hover:text-gray-100  py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

            <i data-lucide="clipboard-list" class="w-5 h-5"></i>
            <span>Verify Crop Submission</span>
          </a>

          <hr class="border-emerald-600">

          <a href="verified_crops.php"
            class="flex gap-2 text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

            <i data-lucide="book-check" class="w-5 h-5"></i>
            <span>View Verified Crops</span>
          </a>

          <hr class="border-emerald-600">
          <a href="confirm_payments.php"
            class="flex gap-2 text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

            <i data-lucide="credit-card" class="w-5 h-5"></i>
            <span>Confirm Payments</span>
          </a>

          <hr class="border-emerald-600">
          <a href="bid_cancellations.php"
            class="flex gap-2 text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

            <i data-lucide="ban" class="w-5 h-5"></i>
            <span>Bid Cancellations</span>
          </a>

          <hr class="border-emerald-600">

          <a onclick="logoutModal.showModal()"
            class="flex gap-2 text-gray-500 hover:text-red-400 py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span>Logout</span>
          </a>
          <dialog id="logoutModal" class="modal">
            <div class="modal-box">
              <h3 class="text-lg font-bold">Log Out</h3>
              <p class="py-4">Do you really want to log out now?</p>
              <div class="mt-6 flex justify-end gap-3">
                <button onclick="logoutModal.close()" type="button"
                  class="px-5 py-2.5 text-gray-600 hover:text-gray-800 border border-gray-300 hover:border-gray-400 rounded-full transition-colors">
                  Cancel
                </button>
                <a href="../auth/logout.php"
                  class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white font-medium rounded-full shadow-sm transition-colors">
                  Yes, Log Out
                </a>
              </div>
            </div>
          </dialog>

        </div>


      </div>
      <div class="w-full flex flex-col gap-3">
        <!-- <h3>Crop Submission Summary</h3> -->

        <div class="bg-white w-full border border-slate-300  flex flex-col rounded-xl ">
          <div class="flex item-center justify-between px-10 pt-6">

            <span class="text-slate-600 font-semibold">Total</span>
            <div class="p-3">

              <i data-lucide="clipboard-list" class="w-6 h-6 text-emerald-700"></i>
            </div>

          </div>
          <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

            <span class="text-3xl font-semibold text-gray-100">
              <?= $total ?>
            </span>
          </div>
        </div>
        <div class="bg-white w-full border border-slate-300  flex flex-col rounded-xl">
          <div class="flex item-center justify-between px-10 pt-6">

            <span class="text-slate-600 font-semibold">Pending</span>
            <div class="p-3">

              <i data-lucide="clipboard-clock" class="w-6 h-6 text-emerald-700"></i>

            </div>
          </div>
          <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

            <span class="text-3xl font-semibold text-gray-100">
              <?= $pending ?>
            </span>
          </div>
        </div>
        <div class="bg-white w-full border border-slate-300  flex flex-col rounded-xl">
          <div class="flex item-center justify-between px-10 pt-6">

            <span class="text-slate-600 font-semibold">Verified</span>
            <div class="p-3">

              <i data-lucide="clipboard-check" class="w-6 h-6 text-emerald-700"></i>

            </div>
          </div>
          <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

            <span class="text-3xl font-semibold text-gray-100">
              <?= $verified ?>
            </span>
          </div>
        </div>
        <div class="bg-white w-full border border-slate-300  flex flex-col rounded-xl">
          <div class="flex item-center justify-between px-10 pt-6">

            <span class="text-slate-600 font-semibold ">Rejected</span>
            <div class="p-3">

              <i data-lucide="clipboard-x" class="w-6 h-6 text-emerald-700"></i>

            </div>
          </div>
          <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

            <span class="text-3xl font-semibold text-gray-100">
              <?= $rejected ?>
            </span>
          </div>
        </div>

      </div>

    </section>

    <div class="mt-10 w-full border border-slate-300 p-10 rounded-2xl">
      <?php include 'partials/transaction_summary.php'; ?>
    </div>



    <div class="mt-10 w-full border border-slate-300 p-10 rounded-2xl">

      <div class="flex justify-between items-center">

        <h3 class="text-slate-700 text-lg font-semibold">Submissions This <?= ucfirst($range) ?></h3>
        <form method="get" style="text-align:center; margin:10px 0;">
          <!-- <label for="range">View:</label> -->
          <select name="range" onchange="this.form.submit()" class="border rounded-lg px-4 py-2">
            <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>Week</option>
            <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>Month</option>
            <option value="year" <?= $range === 'year' ? 'selected' : '' ?>>Year</option>
          </select>
        </form>
      </div>


      <div class="w-full max-w-full  " style=" height:300px; margin:auto; ">
        <canvas id="trendChart"></canvas>
      </div>
    </div>
    <section class="mt-10 flex gap-5">


      <div class="w-full border border-slate-300  p-10 rounded-2xl">
        <h3 class="text-slate-700 text-lg font-semibold mb-4">Top 3 Paying Business Partners</h3>
        <div class="w-7/8" style=" height:300px; margin:auto;">

          <canvas id="topBiddersChart"></canvas>
        </div>
      </div>
      <!-- 👨‍🌾 Top Farmers -->
      <div class="w-full border border-slate-300  p-10 rounded-2xl">
        <h3 class="text-slate-700 text-lg font-semibold mb-4">Top Contributing Farmers</h3>
        <div style="max-width:600px; height:300px; margin:auto;">
          <canvas id="topFarmersChart"></canvas>
        </div>
        <!-- <table border="1" cellpadding="10" width="100%" style="margin-top:10px;">
          <tr>
            <th>Farmer Name</th>
            <th>Total Submissions</th>
          </tr>
          <?php foreach ($topFarmers as $farmer => $count): ?>
            <tr>
              <td><?= htmlspecialchars($farmer) ?></td>
              <td><?= $count ?></td>
            </tr>
          <?php endforeach; ?>
        </table> -->
      </div>


    </section>



    <section class="mt-10 flex gap-5">
      <div class="w-full border border-slate-300  p-10 rounded-2xl">
        <div class="flex justify-between items-center">

          <h3 class="text-slate-700 text-lg font-semibold mb-4">Total Revenue per Crop Type
          </h3>
          <form method="get" style="text-align:center; margin-bottom:10px;">
            <select name="revRange" onchange="this.form.submit()" class="border rounded-lg px-4 py-2">
              <option value="month" <?= $revRange === 'month' ? 'selected' : '' ?>>Month</option>
              <option value="year" <?= $revRange === 'year' ? 'selected' : '' ?>>Year</option>
            </select>
          </form>
        </div>

        <div style="max-width:600px; height:300px; margin:auto;">
          <canvas id="revenueChart"></canvas>
        </div>
      </div>
      <div class="w-full border border-slate-300  p-10 rounded-2xl">
        <h3 class="text-slate-700 text-lg font-semibold mb-4">Crop Type Breakdown</h3>
        <div style="max-width:600px; height:300px; margin:auto;">
          <canvas id="cropChart"></canvas>
        </div>
        <!-- <table border="1" cellpadding="10" width="100%" style="margin-top:10px;">
          <tr>
            <th>Crop Type</th>
            <th>Total Submissions</th>
          </tr>
          <?php foreach ($cropCounts as $crop => $count): ?>
            <tr>
              <td><?= htmlspecialchars(ucfirst($crop)) ?></td>
              <td><?= $count ?></td>
            </tr>
          <?php endforeach; ?>
        </table> -->

      </div>
    </section>





  </div>

  <script src="https://unpkg.com/lucide@latest"></script>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
  <script>
    lucide.createIcons();
    // anime({
    //   targets: '#bar',
    //   width: ['60%', '100%'],
    //   duration: 1500,
    //   easing: 'easeInExpo'
    // });
  </script>
</body>

</html>


<!-- 📊 Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  const trendCtx = document.getElementById('trendChart').getContext('2d');
  new Chart(trendCtx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_keys($submissionTrends)) ?>,
      datasets: [{
        label: 'Submissions',
        data: <?= json_encode(array_values($submissionTrends)) ?>,
        borderColor: '#064e3b',
        backgroundColor: '#b9f4a097',
        fill: true,
        tension: 0.3
      }]
    },
    options: {

      maintainAspectRatio: false,
      responsive: true,

      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });
  const colors = ['#065f46', '#B9F4A0'];
  new Chart(document.getElementById('cropChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($cropCounts)) ?>,
      datasets: [{
        label: 'Crop Submissions',
        data: <?= json_encode(array_values($cropCounts)) ?>,
        backgroundColor: <?= json_encode(array_values($topFarmers)) ?>.map((_, i) => colors[i % 2])
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });

  new Chart(document.getElementById('topBiddersChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($partnerNames) ?>,
      datasets: [{
        label: 'Total Bids (₱)',
        data: <?= json_encode($partnerBids) ?>,
        backgroundColor: ['#065f46', '#B9F4A0', '#a1a1aa']
      }]
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: val => '₱' + val.toLocaleString()
          }
        }
      }
    }
  });

  new Chart(document.getElementById('topFarmersChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_keys($topFarmers)) ?>,
      datasets: [{
        label: 'Submissions',
        data: <?= json_encode(array_values($topFarmers)) ?>,
        backgroundColor: <?= json_encode(array_values($topFarmers)) ?>.map((_, i) => colors[i % 2])
      }]
    },
    options: {
      plugins: {
        legend: {
          display: false
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            stepSize: 1
          }
        }
      }
    }
  });

  new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($revenueData, 'croptype')) ?>,
      datasets: [{
        label: 'Revenue (₱)',
        data: <?= json_encode(array_column($revenueData, 'total_revenue')) ?>,
        backgroundColor: <?= json_encode(array_values($topFarmers)) ?>.map((_, i) => colors[i % 2])
      }]
    },
    options: {
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: val => '₱' + val.toLocaleString()
          }
        }
      }
    }
  });
</script>