<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);

$query = "SELECT approved_submissions.*, cancel_bid.* , users.name, crop_bids.bidamount   FROM approved_submissions
RIGHT JOIN cancel_bid ON approved_submissions.approvedid = cancel_bid.approvedid
 LEFT JOIN users on cancel_bid.userid = users.id

 LEFT JOIN crop_bids on users.id = crop_bids.bpartnerid AND approved_submissions.approvedid = crop_bids.approvedid

    
 ";

$stmt = $conn->prepare($query);
$stmt->execute();

$result = $stmt->get_result();

// while ($row = $result->fetch_assoc()) {
//     echo "<pre>";
//     var_dump($row); // dump each row
//     echo "</pre>";
// }
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
<a href="dashboard.php"
    class="inline-flex items-center gap-2 text-gray-600 hover:text-emerald-900 px-4 py-1 justify-center rounded-lg">
    <i data-lucide="chevron-left" class="w-6 h-6"></i>

    <span class="text-md">Dashboard</span>
</a>

<div class="flex  justify-between items-center ml-4 mt-5 mb-5">
    <div>
        <h2 class="text-4xl text-emerald-900 font-semibold">Bid Cancellations</h2>
        <span class="text-lg text-gray-600">Track and manage cancellation requests</span>

    </div>

</div>

<div class="mt-4 px-6 flex flex-col">
    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle">


            <div id="my-grid"></div>
        </div>
    </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/gridjs/dist/gridjs.umd.js"></script>

<?php
// Store all data in an array first
$allData = [];
while ($row = $result->fetch_assoc()) {
    $allData[] = $row;
}

// Build grid data from the stored array
$gridData = [];
foreach ($allData as $row) {
    $statusClass = match ($row['status']) {
        'rejected' => 'bg-red-200 text-red-500',
        'verified' => 'bg-green-50 text-green-700',
        default => 'bg-yellow-50 text-yellow-700'
    };

    $gridData[] = [
        $row['id'],
        htmlspecialchars($row['name']),
        htmlspecialchars($row['croptype']),
        htmlspecialchars($row['quantity']),
        htmlspecialchars($row['unit']),
        "<img src='../assets/uploads/" . htmlspecialchars($row['imagepath']) . "' class='h-16 w-16 object-cover rounded-md'>",
        htmlspecialchars($row['bidamount']),
        htmlspecialchars($row['reason']),
        "<span class='rounded-full px-2 py-1 $statusClass text-center'>" . ucfirst(htmlspecialchars($row['status'])) . "</span>",
        htmlspecialchars($row['created_at']),
        $row['status'] !== 'rejected' && $row['status'] !== 'approved' ?
            "<div class='flex gap-2'>
                <button onclick=\"rejectModal{$row['approvedid']}.showModal()\" class='text-red-500 hover:text-red-600'>Reject</button>
                <button class='text-emerald-600 hover:text-emerald-700'>Approve</button>
             </div>" : ""
    ];
}
?>


<script>
    new gridjs.Grid({
        columns: [{
                name: 'ID',
                sort: true
            },
            {
                name: 'Request By',
                sort: true
            },
            {
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
                formatter: (_, row) => gridjs.html(row.cells[5].data)
            },
            {
                name: 'Bid Amount',
                sort: true
            },
            {
                name: 'Cancellation Reason',
                sort: true
            },
            {
                name: 'Status',
                sort: true,
                formatter: (_, row) => gridjs.html(row.cells[8].data)
            },
            {
                name: 'Request Date',
                sort: true
            },
            {
                name: 'Actions',
                sort: false,
                formatter: (_, row) => gridjs.html(row.cells[10].data)
            }
        ],
        data: <?= json_encode($gridData) ?>,
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



<?php
require_once '../includes/footer.php';
?>