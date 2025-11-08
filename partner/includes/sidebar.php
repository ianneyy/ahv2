<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

$currentUserId = $_SESSION['user_id'] ?? null;
$unreadTotal = 0;

if ($currentUserId) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS unread_total 
        FROM messages 
        WHERE receiver_id = ? 
          AND message_read = 0
    ");
    $stmt->bind_param("i", $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $unreadTotal = (int) ($row['unread_total'] ?? 0);
}
$current_page = basename($_SERVER['PHP_SELF']); // e.g., "dashboard.php"
$is_crop_page = in_array($current_page, ['verify_crops.php', 'verified_crops.php']);
?>

<aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col sticky top-0 h-screen">
    <div class="p-4 text-xl font-bold  text-[#28453E]">
        AniHanda
    </div>
    <nav class="flex-1 p-4 space-y-4">
        <a href="../partner/dashboard.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3  <?= $current_page === 'dashboard.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <span>Dashboard</span></a>

        <a href="../partner/bid_crops.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'bid_crops.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="gavel" class="w-5 h-5"></i>
            <span>Bidding</span></a>
        <a href="../partner/won_bids.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'won_bids.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="notepad-text" class="w-5 h-5"></i>
            <span>Won</span></a>
     
       
      
        <a href="../owner/chat.php"
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