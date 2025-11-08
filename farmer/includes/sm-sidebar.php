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
                <li><a href="../farmer/dashboard.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B] text-[#28453E] <?= $current_page === 'dashboard.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                    </a></li>
                <hr class="border-gray-300">
                <li><a href="../farmer/submit_crop.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E] <?= $current_page === 'submit_crop.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <i data-lucide="plus" class="w-5 h-5"></i>
                        <span>New Crop</span>
                    </a></li>
                <hr class="border-gray-300">
                <li><a href="../farmer/my_submissions.php"
                        class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E] <?= $current_page === 'my_submissions.php' ? 'bg-[#BFF49B]' : '' ?>">
                        <i data-lucide="notebook-text" class="w-5 h-5"></i>
                        <span>Submissions</span>
                    </a></li>




                <hr class="border-gray-300">
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