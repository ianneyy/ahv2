<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);


$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast_error']);


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
<div class="flex min-h-screen">
    <aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col">
        <div class="p-4 text-xl font-bold  text-[#28453E]">
            AniHanda
        </div>
        <nav class="flex-1 p-4 space-y-4">
            <a href="dashboard.php"
                class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3"> <i
                    data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span></a>
            <!-- Crops Dropdown -->
            <div>
                <button onclick="toggleDropdown('cropsDropdown', 'chevronIcon')"
                    class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
                    <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i>
                        <span>Crops</span>
                    </span> <i id="chevronIcon" data-lucide="chevron-down"
                        class="w-5 h-5 transition-transform duration-300"></i> </button> <!-- Dropdown links -->
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
                class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3">
                <i data-lucide="credit-card" class="w-5 h-5"></i>
                <span>Payments</span></a>
            <a href="bid_cancellations.php"
                class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
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
                    <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold">Bid Cancellations</h2>
                    <span class="text-md lg:text-lg text-gray-600">Track and manage cancellation requests</span>

                </div>
                <!-- Small screen -->
                <div class="block lg:hidden">
                    <div class="drawer">
                        <input id="my-drawer" type="checkbox" class="drawer-toggle" />
                        <div class="drawer-content">
                            <!-- Page content here -->
                            <label for="my-drawer" class=" drawer-button"><i data-lucide="menu"
                                    class="w-5 h-5"></i></label>

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

                                <div>
                                    <button onclick="toggleDropdownSmall('cropsDropdownSmall', 'chevronIconSmall')"
                                        class=" w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
                                        <span class="flex items-center gap-3"> <i data-lucide="wheat"
                                                class="w-5 h-5"></i>
                                            <span>Crops</span>
                                        </span> <i id="chevronIconSmall" data-lucide="chevron-down"
                                            class="w-5 h-5 transition-transform duration-300"></i>
                                    </button> <!-- Dropdown links -->
                                    <div id="cropsDropdownSmall" class="hidden ml-5  border-l border-gray-300">
                                        <div class="ml-3 mt-2 space-y-2">

                                            <a href="verify_crops.php"
                                                class="block px-4 py-2 active:bg-[#BFF49B] text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E]  flex items-center gap-2">
                                                <span>Crop Submission</span>
                                            </a>
                                            <a href="verified_crops.php"
                                                class="block px-4 py-2 active:bg-[#BFF49B] text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E]  flex items-center gap-2">
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

                                <li><a href="bid_cancellations.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                                        <i data-lucide="ban" class="w-5 h-5"></i>
                                        <span>Cancellations</span>
                                    </a></li>
                                <hr class="border-gray-300">

                                <li><a onclick="logoutModal.showModal()" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
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
    function toggleDropdown(dropdownId, iconId) {
        const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

    }
    function toggleDropdownSmall(dropdownId, iconId) {
        const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-180");

    }
</script>
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
        "<div class='flex gap-4'>
    
                <button onclick='rejectModal{$row['approvedid']}.showModal()' class='text-red-500 hover:text-red-600'>Reject</button>


                 <form action='approve_cancellations.php' method='POST'  >
                <button class='text-emerald-600 hover:text-emerald-700 font-semibold'>Approve</button>
                 <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>
                </form>
             </div>
          
           <dialog id='rejectModal{$row['approvedid']}' class='modal modal-bottom sm:modal-middle'>
    <div class='modal-box'>
        <form action='reject_cancellations.php' method='POST' class='mt-2'>
            <!-- Header -->
            <h3 class='text-xl font-semibold text-red-600 flex items-center gap-2'>
                
                Reject Cancellation Request
            </h3>
           <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>
            <!-- Warning -->
            <div class='mt-4 bg-red-50 border border-red-200 rounded-lg p-4'>
                <p class='flex items-center gap-2 text-red-600 font-medium'>
                    
                    Important Notice !
                </p>
                <p class='text-sm text-red-500 mt-2 leading-relaxed'>
                    You are about to <span class='font-semibold'>reject this cancellation request.</span><br>
                    Once submitted, the bidder will be notified and will remain responsible for their winning bid.  
                    If you wish to reconsider after rejecting, please contact the bidder directly.
                </p>
            </div>

            <!-- Reason Input -->
            <fieldset class='mt-4 space-y-2'>
                <legend class='text-sm font-medium text-gray-700 mb-2'>Provide your reason for rejection</legend>
                <textarea name='reason'
                    class='w-full h-24 p-3 border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-red-500 focus:border-red-500'
                    placeholder='Explain why this cancellation request is being rejected...' required></textarea>
            </fieldset>

            <!-- Actions -->
            <div class='mt-6 flex justify-end gap-3'>
                <button onclick='rejectModal{$row['approvedid']}.close()' type='button'
                    class='px-5 py-2.5 text-gray-600 hover:text-gray-800 border border-gray-300 hover:border-gray-400 rounded-full transition-colors'>
                    Cancel
                </button>
                <button type='submit'
                    class='px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white font-medium rounded-full shadow-sm transition-colors'>
                    Yes, Reject Request
                </button>
            </div>
        </form>
    </div>
    <!-- Click outside to close -->
    <form method='dialog' class='modal-backdrop'>
        <button>close</button>
    </form>
</dialog>

             
             
             " : ""
    ];
}
?>
<?php if ($toast_error): ?>
    <div class="toast">
        <div class="alert alert-error">
            <span class="text-white"><?php echo htmlspecialchars($toast_error); ?></span>
        </div>
    </div>

    <script>
        // Hide toast after 3 seconds
        setTimeout(() => {
            document.querySelector('.toast')?.remove();
        }, 3000);
    </script>
<?php endif; ?>

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