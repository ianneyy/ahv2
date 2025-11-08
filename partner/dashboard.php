<?php
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

$sql = "SELECT name FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userName);
$stmt->fetch();
$stmt->close();

$notifications = get_notifications($conn, $userId, $userType);



?>


<?php
require_once '../includes/header.php';
?>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main content -->
    <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
      <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
        <div id="bar" class=" max-w-7xl mx-auto flex justify-between items-center mb-10    rounded-full">
          <h2 class="text-2xl lg:text-4xl font-semibold text-emerald-800  ">Welcome,
            <?php echo ucfirst(htmlspecialchars($userName)); ?>!


          </h2>
          <div class="flex items-center gap-5">
            <div class="relative">
              <div
                class="rounded-full p-2 flex items-center justify-center hover:bg-emerald-900 hover:text-white transition duration-300 ease-in-out">

                <?php include '../includes/notification_ui.php'; ?>
              </div>

            </div>
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
                    <li><a href="dashboard.php" class="flex items-center gap-3 bg-[#BFF49B] active:bg-[#BFF49B]  text-[#28453E]">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a href="bid_crops.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="gavel" class="w-5 h-5"></i>
                        <span>Bidding</span>
                      </a></li>
                    <hr class="border-gray-300">

                    <li><a href="won_bids.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                        <i data-lucide="sparkles" class="w-5 h-5"></i>
                        <span>Won</span>
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
          </div>
        </div>

        <div class=" max-w-7xl mx-auto flex items-center justify-center">

          <div class="flex flex-col lg:flex-row w-full gap-5 item-center justify-between">

            <!-- Bidding Success Rate -->
            <div class="w-full">
              <?php include 'partials/bidding_successrate.php'; ?>
            </div>
          </div>

        </div>

      </div>
    </main>


    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <script>
      lucide.createIcons();
      //  anime({
      //       targets: '#bar',
      //       width: ['60%', '100%'],
      //       duration: 1500,
      //       easing: 'easeInExpo'
      //     });
    </script>
</body>

</html>