<?php
$current_page = basename($_SERVER['PHP_SELF']); // e.g., "dashboard.php"
$is_crop_page = in_array($current_page, ['verify_crops.php', 'verified_crops.php']);
?>

<aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col sticky top-0 h-screen">
    <div class="p-4 text-xl font-bold  text-[#28453E]">
        AniHanda
    </div>
    <nav class="flex-1 p-4 space-y-4">
        <a href="dashboard.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3  <?= $current_page === 'dashboard.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <span>Dashboard</span></a>

        <a href="../partner/bid_crops.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'bid_crops.php' ? 'bg-[#BFF49B]' : '' ?>"> <i
                data-lucide="gavel" class="w-5 h-5"></i>
            <span>Bidding</span></a>
        <a href="../owner/bid_records.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'bid_records.php' ? 'bg-[#BFF49B]' : '' ?>"> <i
                data-lucide="notepad-text" class="w-5 h-5"></i>
            <span>Bid Records</span></a>
        <!-- Crops Dropdown -->
        <div>
            <button onclick="toggleDropdown('cropsDropdown', 'chevronIcon')"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]    <?= $is_crop_page ? 'bg-[#BFF49B]' : '' ?>">
                <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i> <span>Crops</span>
                </span> <i id="chevronIcon" data-lucide="chevron-down"
                    class="w-5 h-5 transition-transform duration-300"></i> </button> <!-- Dropdown links -->
            <div id="cropsDropdown" class="hidden ml-5  border-l border-gray-300">
                <div class="ml-3 mt-2 space-y-2">

                    <a href="verify_crops.php"
                        class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2  <?= $current_page === 'verify_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <span>Crop Submission</span>
                    </a>
                    <a href="verified_crops.php"
                        class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2  <?= $current_page === 'verified_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <span>Verified Crops</span>
                    </a>
                </div>

            </div>
        </div>
        <a href="confirm_payments.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'confirm_payments.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="credit-card" class="w-5 h-5"></i>
            <span>Payments</span></a>
        <a href="bid_cancellations.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'bid_cancellations.php' ? 'bg-[#BFF49B]' : '' ?>">
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



<script>
  lucide.createIcons();
  function toggleDropdown(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-90");

  }
  function toggleDropdownSmall(dropdownId, iconId) {
    const dropdown = document.getElementById(dropdownId); const icon = document.getElementById(iconId); dropdown.classList.toggle("hidden"); icon.classList.toggle("rotate-90");

  }
</script>