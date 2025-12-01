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
        <a href="../farmer/dashboard.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B]  text-[#28453E] flex items-center gap-3  <?= $current_page === 'dashboard.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
            <span>Dashboard</span></a>

        <a href="../farmer/submit_crop.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'submit_crop.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="plus" class="w-5 h-5"></i>
            <span>New Crop</span></a>
        <!-- <a href="../farmer/my_submissions.php"
            class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3  <?= $current_page === 'my_submissions.php' ? 'bg-[#BFF49B]' : '' ?>">
            <i data-lucide="file-text" class="w-5 h-5"></i>
            <span>Submissions</span></a> -->



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