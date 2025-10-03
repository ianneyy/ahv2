<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';


$stmt = $conn->prepare("
    SELECT 
        bh.id,
        bh.amount,
        bh.created_at,
        u.name AS username,
        a.croptype
    FROM history bh
    JOIN users u ON bh.userid = u.id
    JOIN approved_submissions a ON bh.approvedid = a.approvedid
    ORDER BY bh.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
  $history[] = $row;
}
$jsonHistory = json_encode($history);
$stmt->close();


?>




<?php
require_once '../includes/header.php';
?>
<style>
  .gridjs-table,
  .gridjs-th,
  .gridjs-td,
  .gridjs-tr {
    border: none !important;
  }

  /* Optional: remove inner borders (grid lines) */
  .gridjs-tr>.gridjs-td,
  .gridjs-th {
    border: none !important;
  }

  .gridjs-pages {
    font-size: 12px;
    /* equivalent to Tailwind text-xs */
  }

  .gridjs-summary {
    font-size: 12px;
  }
</style>
<div class="flex min-h-screen">
  <aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col sticky top-0 h-screen">
    <div class="p-4 text-xl font-bold  text-[#28453E]">
      AniHanda
    </div>
    <nav class="flex-1 p-4 space-y-4">
      <a href="dashboard.php"
        class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3"> <i
          data-lucide="layout-dashboard" class="w-5 h-5"></i>
        <span>Dashboard</span></a>

      <a href="../partner/bid_crops.php"
        class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3"> <i
          data-lucide="gavel" class="w-5 h-5"></i>
        <span>Bidding</span></a>
      <a href="../owner/bid_records.php"
        class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E] flex items-center gap-3"> <i
          data-lucide="notepad-text" class="w-5 h-5"></i>
        <span>Bid Records</span></a>
      <!-- Crops Dropdown -->
      <div>
        <button onclick="toggleDropdown('cropsDropdown', 'chevronIcon')"
          class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
          <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i> <span>Crops</span>
          </span> <i id="chevronIcon" data-lucide="chevron-down" class="w-5 h-5 transition-transform duration-300"></i>
        </button> <!-- Dropdown links -->
        <div id="cropsDropdown" class="hidden ml-5  border-l border-gray-300">
          <div class="ml-3 mt-2 space-y-2">

            <a href="verify_crops.php"
              class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
              <span>Crop Submission</span>
            </a>
            <a href="verified_crops.php"
              class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
              <span>Verified Crops</span>
            </a>
          </div>

        </div>
      </div>
      <a href="confirm_payments.php"
        class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
        <i data-lucide="credit-card" class="w-5 h-5"></i>
        <span>Payments</span></a>
      <a href="bid_cancellations.php"
        class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
        <i data-lucide="ban" class="w-5 h-5"></i>
        <span>Cancellations</span></a>
      <a onclick="logoutModal.showModal()"
        class="block px-4 py-2 rounded-lg cursor-pointer hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
        <i data-lucide="log-out" class="w-5 h-5"></i>
        <span>Logout</span>
      </a>

    </nav>
    <div class="p-4 border-t border-gray-300 text-sm text-gray-400">
      © 2025 AniHanda
    </div>
  </aside>
  <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
    <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
      <div class="flex flex-col lg:flex-row lg:justify-between  lg:ml-4 mt-5 mb-5">
        <div class="flex justify-between items-center">
          <div>
            <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold">
              Bid Records
            </h2>
            <span class="text-md lg:text-lg text-gray-600">
              Review the full history and progress of crop bidding events.
            </span>
          </div>
          <!-- Small screen -->
          <div class="block lg:hidden">
            <div class="drawer">
              <input id="my-drawer" type="checkbox" class="drawer-toggle" />
              <div class="drawer-content">
                <!-- Page content here -->
                <label for="my-drawer" class=" drawer-button"><i data-lucide="menu" class="w-5 h-5"></i></label>

              </div>
              <div class="drawer-side ">
                <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>


                <ul class="menu  bg-[#ECF5E9] text-base-content min-h-full w-80 p-4 gap-3">
                  <li>
                    <div class="p-4 text-xl font-bold  text-[#28453E]">
                      AniHanda
                    </div>
                  </li>
                  <!-- Sidebar content here -->
                  <li><a href="dashboard.php" class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E]">
                      <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                      <span>Dashboard</span>
                    </a></li>
                  <hr class="border-gray-300">
                  <li><a href="../partner/bid_crops.php"
                      class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E]">
                      <i data-lucide="gavel" class="w-5 h-5"></i>
                      <span>Bidding</span>
                    </a></li>
                  <hr class="border-gray-300">
                  <li><a href="../owner/bid_records.php"
                      class="flex items-center gap-3 active:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E]">
                      <i data-lucide="notebook-text" class="w-5 h-5"></i>
                      <span>Bid Records</span>
                    </a></li>
                  <hr class="border-gray-300">
                  <div>
                    <button onclick="toggleDropdownSmall('cropsDropdownSmall', 'chevronIconSmall')"
                      class=" w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
                      <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i>
                        <span>Crops</span>
                      </span> <i id="chevronIconSmall" data-lucide="chevron-down"
                        class="w-5 h-5 transition-transform duration-300"></i>
                    </button> <!-- Dropdown links -->
                    <div id="cropsDropdownSmall" class="hidden ml-5  border-l border-gray-300">
                      <div class="ml-3 mt-2 space-y-2">

                        <a href="verify_crops.php"
                          class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
                          <span>Crop Submission</span>
                        </a>
                        <a href="verified_crops.php"
                          class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
                          <span>Verified Crops</span>
                        </a>
                      </div>

                    </div>
                  </div>


                  <hr class="border-gray-300">

                  <li><a href="confirm_payments.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                      <i data-lucide="credit-card" class="w-5 h-5"></i>
                      <span>Payments</span>
                    </a></li>
                  <hr class="border-gray-300">

                  <li><a href="bid_cancellations.php"
                      class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                      <i data-lucide="ban" class="w-5 h-5"></i>
                      <span>Cancellations</span>
                    </a></li>
                  <hr class="border-gray-300">

                  <li><a onclick="logoutModal.showModal()"
                      class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                      <i data-lucide="log-out" class="w-5 h-5"></i>
                      <span>Logout</span>
                    </a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mt-4 px-6 flex flex-col">
        <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
          <div class="inline-block min-w-full py-2 align-middle">


            <div id="my-grid"></div>

          </div>
        </div>
      </div>
    </div>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/gridjs/dist/gridjs.umd.js"></script>

<script>
  const historyData = <?php echo $jsonHistory; ?>;

  const formattedData = historyData.map(row => [
    row.created_at,
    row.username,
    row.croptype,
    "₱" + parseFloat(row.amount).toLocaleString()
  ]);
  new gridjs.Grid({
    columns: [{
      name: 'Date and Time',
      sort: true
    },
    {
      name: 'Name',
      sort: true
    },
    {
      name: 'Crop Type',
      sort: true
    },
    {
      name: 'Bid Amount',
      sort: true
    },


    ],
    data: formattedData,
    search: true,
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
        'border': '1px solid #ECF5E9',
        'border-radius': '0.5rem',
        'font-size': '14px',
      },
      th: {
        'background-color': '#ECF5E9',
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

<script>
  function toggleDropdown(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

  }
  function toggleDropdownSmall(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

  }
</script>
<?php
require_once '../includes/footer.php';
?>