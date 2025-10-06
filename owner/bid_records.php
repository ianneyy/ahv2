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
  <?php include 'includes/sidebar.php'; ?>

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
          <?php include 'includes/sm-sidebar.php'; ?>

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


<?php
require_once '../includes/footer.php';
?>