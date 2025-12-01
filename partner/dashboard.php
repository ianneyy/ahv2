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
    <div class="lg:max-w-7xl" style=" margin: auto; padding: 20px;">
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
          <?php include 'includes/sm-sidebar.php'; ?>
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