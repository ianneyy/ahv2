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


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Partner Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/style.css">

</head>

<body class="bg-gray-50 p-10">
  <div id="bar" class=" max-w-7xl mx-auto flex justify-between items-center mb-10  px-8 py-4 rounded-full">
    <h2 class="text-md lg:text-2xl font-semibold text-emerald-800  ">Partner Dashboard | <span class="text-sm lg:text-lg text-emerald-600"> <?php echo ucfirst(htmlspecialchars($userName)); ?></span>


    </h2>
    <div class="relative">
      <div
        class="rounded-full p-2 flex items-center justify-center hover:bg-emerald-900 hover:text-white transition duration-300 ease-in-out">

        <?php include '../includes/notification_ui.php'; ?>
      </div>

    </div>
  </div>
  <div class=" max-w-7xl mx-auto flex items-center justify-center">

    <div class="flex flex-col lg:flex-row w-full gap-5 item-center justify-between">

      <div class="w-full flex flex-col gap-10">




        <div class="max-w-2xl py-8 lg:px-4 border border-b-[10px] border-l-[6px] border-emerald-900 shadow-md bg-[#BFF49B] rounded-3xl ">

          <div class="flex flex-col gap-5 px-5">

            <a href="bid_crops.php"
              class="flex gap-2 text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center">

              <i data-lucide="gavel" class="w-5 h-5"></i>
              <span class="">Bid on Crops</span>
            </a>

            <hr class="border-emerald-600">

            <a href="won_bids.php"
              class="flex gap-2  text-emerald-900 hover:bg-emerald-800  hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center">

              <i data-lucide="sparkles" class="w-5 h-5"></i>
              <span>My Won Bids</span>
            </a>

            <hr class="border-emerald-600">


            <a href="../auth/logout.php"
              class="flex gap-2 text-gray-500 hover:text-red-400 py-4 px-4 cursor-pointer rounded-lg items-center">

              <i data-lucide="log-out" class="w-5 h-5"></i>
              <span>Logout</span>
            </a>
          </div>


        </div>
      </div>




      <!-- Bidding Success Rate -->
      <div class="w-full">
        <?php include 'partials/bidding_successrate.php'; ?>
      </div>
    </div>

  </div>
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