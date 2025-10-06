<?php
$current_page = basename($_SERVER['PHP_SELF']); // e.g., "dashboard.php"
$is_crop_page = in_array($current_page, ['verify_crops.php', 'verified_crops.php']);
?>
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
                <li><a href="dashboard.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B] text-[#28453E] <?= $current_page === 'dashboard.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                    </a></li>
                <hr class="border-gray-300">
                <li><a href="../partner/bid_crops.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E] <?= $current_page === 'bid_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <i data-lucide="gavel" class="w-5 h-5"></i>
                        <span>Bidding</span>
                    </a></li>
                <hr class="border-gray-300">
                <li><a href="../owner/bid_records.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E] <?= $current_page === 'bid_records.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <i data-lucide="notebook-text" class="w-5 h-5"></i>
                        <span>Bid Records</span>
                    </a></li>
                <hr class="border-gray-300">

                <div>
                    <button onclick="toggleDropdownSmall('cropsDropdownSmall', 'chevronIconSmall')"
                        class=" w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] <?= $is_crop_page  ? 'bg-[#BFF49B]' : '' ?>">
                        <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i>
                            <span>Crops</span>
                        </span> <i id="chevronIconSmall" data-lucide="chevron-down"
                            class="w-5 h-5 transition-transform duration-300"></i>
                    </button> <!-- Dropdown links -->
                    <div id="cropsDropdownSmall" class="hidden ml-5  border-l border-gray-300">
                        <div class="ml-3 mt-2 space-y-2">

                            <a href="verify_crops.php"
                                class="block px-4 py-2 text-sm rounded-lg active:bg-[#BFF49B]  text-[#28453E]  flex items-center gap-2 <?= $current_page === 'verify_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
                                <span>Crop Submission</span>
                            </a>
                            <a href="verified_crops.php"
                                class="block px-4 py-2 text-sm  rounded-lg active:bg-[#BFF49B]  text-[#28453E]  flex items-center gap-2 <?= $current_page === 'verified_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
                                <span>Verified Crops</span>
                            </a>
                        </div>

                    </div>
                </div>



                <hr class="border-gray-300">

                <li><a href="confirm_payments.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E] <?= $current_page === 'confirm_payments.php' ? 'bg-[#BFF49B]' : '' ?> ">
                        <i data-lucide="credit-card" class="w-5 h-5"></i>
                        <span>Payments</span>
                    </a></li>
                <hr class="border-gray-300">

                <li><a href="bid_cancellations.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E] <?= $current_page === 'bid_cancellations.php' ? 'bg-[#BFF49B]' : '' ?>">
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