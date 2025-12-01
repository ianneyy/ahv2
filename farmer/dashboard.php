<?php
require_once '../includes/session.php';
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);
$farmerid = $_SESSION['user_id'];

// Optional status filter

// 1️⃣ Query for filtered crop submissions (if you want all, remove AND clause)
$stmt = $conn->prepare("SELECT * FROM crop_submissions WHERE farmerid = ?  ORDER BY submittedat DESC");
$stmt->bind_param("i", $farmerid);
$stmt->execute();
$result = $stmt->get_result();


// 2️⃣ Query for KPIs: total, pending, rejected, verified
$kpiStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'rejected') AS rejected,
        SUM(status = 'verified') AS verified
    FROM crop_submissions
    WHERE farmerid = ?
");

$kpiStmt->bind_param("i", $farmerid);
$kpiStmt->execute();
$kpiResult = $kpiStmt->get_result();
$kpis = $kpiResult->fetch_assoc();


$statusFilter = $_GET['status'] ?? 'all';

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

// Store all data in an array first
$allData = [];
while ($row = $result->fetch_assoc()) {
  $allData[] = $row;
}

// Build grid data from the stored array
$gridData = [];
foreach ($allData as $row) {
  $statusClass = match ($row['status']) {
    'pending' => 'bg-yellow-100 text-yellow-800',
    'verified' => 'bg-green-100 text-green-800',
    'rejected' => 'bg-red-100 text-red-800',
    default => 'bg-gray-100 text-gray-800'
  };

  $gridData[] = [
    htmlspecialchars($row['croptype']),
    htmlspecialchars($row['quantity']),
    htmlspecialchars($row['unit']),
    "<img src='../assets/uploads/" . htmlspecialchars($row['imagepath']) . "' class='h-16 w-16 object-cover rounded-md'>",
    "<span class='inline-flex rounded-full px-2 text-xs font-semibold leading-5 $statusClass'>" . ucfirst(htmlspecialchars($row['status'])) . "</span>",
    htmlspecialchars($row['submittedat']),
    $row['verifiedat'] ? htmlspecialchars($row['verifiedat']) : '-',
    $row['rejectionreason'] ? htmlspecialchars($row['rejectionreason']) : '-',
    $row['status'] === 'rejected' ?
    "<form action='submit_crop.php' method='GET' class='flex justify-end'>
                <input type='hidden' name='submissionid' value='" . htmlspecialchars($row['submissionid']) . "'>
                <input type='hidden' name='croptype' value='" . htmlspecialchars($row['croptype']) . "'>
                <input type='hidden' name='quantity' value='" . htmlspecialchars($row['quantity']) . "'>
                <input type='hidden' name='unit' value='" . htmlspecialchars($row['unit']) . "'>
                <input type='hidden' name='imagepath' value='" . htmlspecialchars($row['imagepath']) . "'>
                <button type='submit' class='inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500'>
                    <svg class='w-4 h-4 mr-1' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15' />
                    </svg>
                    Resubmit
                </button>
            </form>" : ""
  ];
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'farmer') {
  header("Location: ../auth/login.php");
  exit();
}




?>

<?php
require_once '../includes/header.php';
?>
<div class="flex min-h-screen">
  <?php include 'includes/sidebar.php'; ?>
  <!-- Main content -->
  <main class="flex-1 bg-[#FCFBFC] lg:p-6 rounded-bl-4xl rounded-tl-4xl ">
    <div class="lg:max-w-7xl" style=" margin: auto; padding: 20px;">
      <div class="flex items-center justify-center">

        <div id="bar" class="flex w-full justify-between items-center  mb-10  rounded-full">
          <h2 class="text-2xl lg:text-4xl font-semibold text-emerald-800">Welcome,
            <?= ucfirst(htmlspecialchars($_SESSION["user_name"])) ?>!
          </h2>
          <div class="flex items-center gap-5">



            <div class="relative">
              <div
                class="rounded-full p-2 flex items-center justify-center hover:bg-emerald-900 hover:text-white transition duration-300 ease-in-out">

                <?php include '../includes/notification_ui.php'; ?>
              </div>

            </div>
            <?php include 'includes/sm-sidebar.php'; ?>
          </div>

        </div>



      </div>

      <section class="flex flex-col lg:flex-row gap-5">
        <div class="w-full flex flex-col  gap-3">
          <!-- <h3>Crop Submission Summary</h3> -->
          <div class="w-full flex flex-col lg:flex-row gap-5">

            <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl ">
              <div class="flex item-center justify-between px-5 lg:px-10 pt-6">

                <span class="text-slate-600 font-semibold">Total Crops Submitted</span>
                <div class="p-3">

                  <i data-lucide="clipboard-list" class="w-6 h-6 text-emerald-700"></i>
                </div>

              </div>
              <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                <span class="text-3xl font-semibold text-gray-100">
                  <?= $kpis['total'] ?>
                </span>
              </div>
            </div>
            <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
              <div class="flex item-center justify-between px-5 lg:px-10 pt-6">

                <span class="text-slate-600 font-semibold">Verified Crops</span>
                <div class="p-3">

                  <i data-lucide="clipboard-clock" class="w-6 h-6 text-emerald-700"></i>

                </div>
              </div>
              <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                <span class="text-3xl font-semibold text-gray-100">
                  <?= $kpis['verified'] ?>
                </span>
              </div>
            </div>
          </div>

          <div class="w-full flex flex-col lg:flex-row gap-5">

            <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl ">
              <div class="flex item-center justify-between px-5 lg:px-10 pt-6">

                <span class="text-slate-600 font-semibold">Pending Crops</span>
                <div class="p-3">

                  <i data-lucide="clipboard-list" class="w-6 h-6 text-emerald-700"></i>
                </div>

              </div>
              <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                <span class="text-3xl font-semibold text-gray-100">
                  <?= $kpis['pending'] ?>
                </span>
              </div>
            </div>
            <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
              <div class="flex item-center justify-between px-5 lg:px-10 pt-6">

                <span class="text-slate-600 font-semibold">Rejected Crops</span>
                <div class="p-3">

                  <i data-lucide="clipboard-clock" class="w-6 h-6 text-emerald-700"></i>

                </div>
              </div>
              <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                <span class="text-3xl font-semibold text-gray-100">
                  <?= $kpis['rejected'] ?>
                </span>
              </div>
            </div>
          </div>

        </div>

      </section>
      <section class="mt-10">
        <div class="flex justify-end">
          <div
            class="max-w-md mt-3 lg:mt-0  bg-white rounded-2xl shadow-sm border border-b-[7px] border-l-[4px] border-emerald-900">
            <form method="GET">
              <div class="flex items-center gap-2 p-4 border-gray-200">
                <!-- View Button -->
                <button type="button" id="statusButton"
                  class="flex items-center gap-2 bg-white text-gray-600 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-200">
                  <i data-lucide="wheat" class="h-4 w-4"></i>
                  <?php
                  $statusLabel = match ($_GET['status'] ?? null) {
                    'all' => 'All',
                    'pending' => 'Pending',
                    'awaiting_verification' => 'Pending Verification',
                    'verified' => 'Verified',
                    'rejected' => 'Rejected',
                    default => 'Status'
                  };
                  ?>
                  <span><?= $statusLabel ?></span>
                  <svg id="statusArrow" class="w-4 h-4 transition-transform duration-200" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                <select name="status" id="status" class="hidden" onchange="this.form.submit()"
                  class="select border border-emerald-600 px-2 bg-transparent focus:border-emerald-900 focus:ring focus:ring-green-200 w-36">
                  <option value="all" <?php if ($statusFilter === 'all')
                    echo 'selected'; ?>>All</option>
                  <option value="pending" <?php if ($statusFilter === 'pending')
                    echo 'selected'; ?>>Pending</option>
                  <option value="verified" <?php if ($statusFilter === 'verified')
                    echo 'selected'; ?>>Verified</option>
                  <option value="rejected" <?php if ($statusFilter === 'rejected')
                    echo 'selected'; ?>>Rejected</option>
                </select>

              </div>
              <div class="relative">
                <!-- Dropdown -->
                <div id="statusDropdown"
                  class="hidden absolute left-0 mt-2 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-50">
                  <!-- Sort Options -->
                  <div data-status-value="all"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                    <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>

                    All
                  </div>
                  <div data-status-value="pending"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                    <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>

                    Pending
                  </div>

                  <div data-status-value="verified"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                    <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                    Verified
                  </div>
                  <div data-status-value="rejected"
                    class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
                    <div class="w-2 h-2 bg-orange-400 rounded-full mr-3 hidden"></div>
                    Rejected
                  </div>

                </div>
              </div>
            </form>
          </div>
        </div>
        <!-- GridJS Table -->
        <div class="mt-4 w-full">
          <div class="overflow-x-auto -mx-4 sm:mx-0">
            <div class="inline-block min-w-full py-2 align-middle px-4 sm:px-0">
              <div id="my-grid" class="w-full"></div>
            </div>
          </div>
        </div>
      </section>

    </div>
  </main>

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

  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
  <script>
    lucide.createIcons();
    // anime({
    //   targets: '#bar',
    //   width: ['30%', '100%'],
    //   duration: 1500,
    //   // easing: 'easeInOutSine'
    //   // easing: 'easeInOutCirc'
    //   easing: 'easeInExpo'
    // });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/gridjs/dist/gridjs.umd.js"></script>
  <script>
    new gridjs.Grid({
      columns: [{
        name: 'Crop Type',
        sort: true
      },
      {
        name: 'Quantity',
        sort: true
      },
      {
        name: 'Unit',
        sort: true
      },
      {
        name: 'Image',
        sort: false,
        formatter: (_, row) => gridjs.html(row.cells[3].data)
      },
      {
        name: 'Status',
        sort: true,
        formatter: (_, row) => gridjs.html(row.cells[4].data)
      },
      {
        name: 'Submitted At',
        sort: true
      },
      {
        name: 'Verified At',
        sort: true
      },
      {
        name: 'Rejection Reason',
        sort: true
      },
      {
        name: 'Actions',
        sort: false,
        formatter: (_, row) => gridjs.html(row.cells[8].data)
      }
      ],
      data: <?= json_encode($gridData) ?>,
      search: {
        enabled: true,
        placeholder: 'Search bids...'
      },
      sort: true,
      pagination: {
        enabled: true,
        limit: 5
      },
      className: {
        row: 'bg-gray-100 hover:bg-gray-200',
      },
      style: {
        table: {
          'border': '1px solid #e5e7eb',
          'border-radius': '0.5rem',
          'font-size': '14px',
        },
        th: {
          'background-color': 'rgba(16,185,129,0.2)',
          'color': '#065f46',
          'font-weight': '600',
          'font-size': '12px',
        },
        td: {
          'font-size': '12px',
        }
      },
      resizable: true
    }).render(document.getElementById("my-grid"));
  </script>
  <script src="./assets/my_submission.js"></script>


  </body>

  </html>