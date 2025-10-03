<?php
require_once '../includes/session.php';
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);


if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'farmer') {
  header("Location: ../auth/login.php");
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<body class="">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col">
      <div class="p-4 text-xl font-bold  text-[#28453E]">
        AniHanda
      </div>
      <nav class="flex-1 p-4 space-y-4">
        <a href="dashboard.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E] flex items-center gap-3"> <i
            data-lucide="layout-dashboard" class="w-5 h-5"></i>
          <span>Dashboard</span></a>
        <!-- Crops Dropdown -->
        <div>
          <button onclick="toggleDropdown('cropsDropdown', 'chevronIcon')"
            class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
            <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i> <span>Crops</span>
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
    <!-- Main content -->
    <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
      <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
        <div id="bar"
          class="mx-auto  max-w-2xl flex justify-between items-center lg:mt-8 mb-10  px-8 lg:py-4 rounded-full">

          <h2 class="text-2xl font-semibold text-emerald-800">Welcome,
            <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!
          </h2>
          <div class="relative">
            <div
              class="rounded-full p-2 flex items-center justify-center hover:bg-emerald-900 hover:text-white transition duration-300 ease-in-out">

              <?php include '../includes/notification_ui.php'; ?>
            </div>

          </div>

        </div>

        <div class="max-w-2xl mx-auto py-8 lg:px-4 border border-emerald-900 shadow-md bg-[#BFF49B] rounded-3xl "
          style="box-shadow: 6px 6px 0px #28453E;">

          <div class="flex flex-col gap-5 px-5">

            <a href="submit_crop.php"
              class="flex gap-2  text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

              <i data-lucide="plus" class="w-5 h-5"></i>
              <span>Submit New Crop</span>
            </a>

            <hr class="border-emerald-600">


            <a href="my_submissions.php"
              class="flex gap-2  text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

              <i data-lucide="file-text" class="w-5 h-5"></i>
              <span>My Submissions</span>
            </a>

            <hr class="border-emerald-600">


            <a onclick="logoutModal.showModal()"
              class="flex gap-2 text-gray-500 hover:text-red-400  py-4 px-4 cursor-pointer rounded-lg items-center transition duration-300 ease-in-out">

              <i data-lucide="log-out" class="w-5 h-5"></i>
              <span>Logout</span>
            </a>
            <dialog id="logoutModal" class="modal">
              <div class="modal-box">
                <h3 class="text-lg font-bold">Log Out</h3>
                <p class="py-4">Do you really want to log out now?</p>
                <div class="mt-6 flex justify-end gap-3">
                  <button onclick="logoutModal.close()" type="button"
                    class="px-5 py-2.5 text-gray-600 hover:text-gray-800 border border-gray-300 hover:border-gray-400 rounded-full transition-colors">
                    Cancel
                  </button>
                  <a href="../auth/logout.php"
                    class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white font-medium rounded-full shadow-sm transition-colors">
                    Yes, Log Out
                  </a>
                </div>
              </div>
            </dialog>
          </div>


        </div>
      </div>
    </main>

    <?php if ($toast_message): ?>
      <div class="toast">
        <div class="alert alert-success">
          <span class="text-emerald-900 "><?php echo htmlspecialchars($toast_message); ?></span>
        </div>
      </div>

      <script>
        // Hide toast after 3 seconds
        setTimeout(() => {
          document.querySelector('.toast')?.remove();
        }, 3000);
      </script>
    <?php endif; ?>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <script>
      lucide.createIcons();
      // anime({
      //   targets: '#bar',
      //   width: ['30%', '100%'],
      //   duration: 1500,
      //   // easing: 'easeInOutSine'
      //   // easing: 'easeInOutCirc'
      //   easing: 'easeInExpo'
      // });
    </script>

</body>

</html>