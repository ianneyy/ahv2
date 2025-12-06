<?php


$currentUserId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_type'] ?? 'guest';
$unreadTotal = 0;
$baseUrl = ($userRole === 'businessOwner') ? '/owner' : '/partner';
if ($currentUserId && isset($conn)) {
    $unreadStmt = $conn->prepare("
        SELECT COUNT(*) AS unread_total 
        FROM messages 
        WHERE receiver_id = ? 
          AND message_read = 0
    ");
    if ($unreadStmt) {
        $unreadStmt->bind_param("i", $currentUserId);
        if ($unreadStmt->execute()) {
            $unreadResult = $unreadStmt->get_result();
            if ($unreadResult) {
                $unreadRow = $unreadResult->fetch_assoc();
                $unreadTotal = (int) ($unreadRow['unread_total'] ?? 0);
            }
        }
        $unreadStmt->close();
    }
}
$current_page = basename($_SERVER['SCRIPT_NAME']);

$is_crop_page = in_array($current_page, ['verify_crops.php', 'verified_crops.php']);
$is_forecasting_page = in_array($current_page, ['forecasting.php', 'forecast_dashboard.php']);
?>

<aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col sticky top-0 h-screen">
    <div class="p-4 text-xl font-bold  text-[#28453E]">
        AniHanda
    </div>
    <nav class="flex-1 p-4 space-y-4">
        <a href="../owner/dashboard"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3  <?= $current_page === 'dashboard.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <span>Dashboard</span></a>

       <a href="..<?= $baseUrl ?>/bid_crops"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'bid_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="gavel" class="w-5 h-5"></i>
            <span>Bidding</span></a>
        <a href="../owner/bid_records"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'bid_records.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="notepad-text" class="w-5 h-5"></i>
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

                    <a href="../owner/verify_crops"
                        class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2  <?= $current_page === 'verify_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <span>Crop Submission</span>
                    </a>
                    <a href="../owner/verified_crops"
                        class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2  <?= $current_page === 'verified_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <span>Verified Crops</span>
                    </a>
                </div>

            </div>
        </div>
        <div>
            <button onclick="toggleDropdown('forecastingDropdown', 'forecastingIcon')"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]    <?= $is_forecasting_page ? 'bg-[#BFF49B]' : '' ?>">
                <span class="flex items-center gap-3"> <i data-lucide="trending-up-down" class="w-5 h-5"></i>
                    <span>Forecasting</span>
                </span> <i id="forecastingIcon" data-lucide="chevron-down"
                    class="w-5 h-5 transition-transform duration-300"></i>
            </button> <!-- Dropdown links -->
            <div id="forecastingDropdown" class="hidden ml-5  border-l border-gray-300">
                <div class="ml-3 mt-2 space-y-2">

                    <a href="forecast_dashboard"
                        class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2  <?= $current_page === 'forecast_dashboard.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <span>Dashboard</span>
                    </a>
                    <a href="forecasting.php"
                        class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2  <?= $current_page === 'forecasting.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <span>Records</span>
                    </a>
                </div>

            </div>
        </div>
        <a href="confirm_payments"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'confirm_payments.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="credit-card" class="w-5 h-5"></i>
            <span>Payments</span></a>
        <a href="bid_cancellations"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'bid_cancellations.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="ban" class="w-5 h-5"></i>
            <span>Cancellations</span></a>

        <a href="chat"
            class=" block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'chat.php' ? 'bg-[#BFF49B]' : '' ?>">

            <div class="indicator">
                <?php if ($unreadTotal > 0): ?>
                    <div
                        class="indicator-item bg-[#FF0000] w-5 h-5 text-white text-xs flex items-center justify-center p-1 rounded-full top-0 right-0 translate-x-3 -translate-y-1">
                        <?= $unreadTotal ?>
                    </div>
                <?php endif; ?>

                <div class="flex gap-3 ">

                    <i data-lucide="message-circle" class="w-5 h-5"></i>
                    <span>Messages</span>
                </div>

            </div>


        </a>
        <a href="users"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'users.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span>Users</span></a>





    </nav>
    <div class="p-4 flex flex-col gap-4">

        <div class="flex items-center gap-2 px-4">
            <div class="avatar avatar-placeholder">
                <?php if (isset($_SESSION['user_picture']) && !empty($_SESSION['user_picture'])): ?>
                    <!-- Google profile picture -->
                    <img src="<?= $_SESSION['user_picture'] ?>" alt="Profile" class="w-8 h-8 rounded-full">
                <?php elseif (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])): ?>
                    <!-- First letters of name -->
                    <div class="bg-neutral text-neutral-content w-8 h-8 rounded-full flex items-center justify-center">
                        <?php
                        $name = $_SESSION['user_name'];
                        $initials = '';
                        $words = explode(' ', $name);
                        foreach ($words as $w) {
                            $initials .= strtoupper($w[0]);
                        }
                        echo substr($initials, 0, 2); // show first 2 letters
                        ?>
                    </div>
                <?php else: ?>
                    <!-- Default placeholder -->
                    <div class="bg-neutral text-neutral-content w-8 h-8 rounded-full flex items-center justify-center">
                        <span class="text-xs">??</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col text-sm">

                <span class="text-emerald-900 font-semibold">

                    <?= isset($_SESSION['user_name']) ? ucfirst($_SESSION['user_name']) : 'Guest' ?>
                </span>
                <span class="text-gray-400">
                    <?php
                    if (isset($_SESSION['user_type'])) {
                        // Insert space before each uppercase letter (except the first)
                        echo preg_replace('/(?<!^)([A-Z])/', ' $1', ucfirst($_SESSION['user_type']));
                    }
                    ?>
                </span>
            </div>
        </div>

        <a onclick="logoutModal.showModal()"
            class="block px-4 py-2 rounded-lg cursor-pointer hover:text-red-500 text-[#28453E] flex items-center gap-3">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span>Logout</span>
        </a>
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