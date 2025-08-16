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

<body class="p-10">
  <div id="bar" class="mx-auto  max-w-2xl flex justify-between items-center mt-8 mb-10  px-8 py-4 rounded-full">

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

  <div class="max-w-2xl mx-auto py-8 lg:px-4 border border-b-[10px] border-l-[6px] border-emerald-900 shadow-md bg-[#BFF49B] rounded-3xl ">

    <div class="flex flex-col gap-5 px-5">

      <a href="submit_crop.php"
        class="flex gap-2  text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center">

        <i data-lucide="plus" class="w-5 h-5"></i>
        <span>Submit New Crop</span>
      </a>

      <hr class="border-emerald-600">


      <a href="my_submissions.php"
        class="flex gap-2  text-emerald-900 hover:bg-emerald-800 hover:text-gray-100 py-4 px-4 cursor-pointer rounded-lg items-center">

        <i data-lucide="file-text" class="w-5 h-5"></i>
        <span>My Submissions</span>
      </a>

      <hr class="border-emerald-600">


      <a href="../auth/logout.php"
        class="flex gap-2 text-gray-500 hover:text-red-400  py-4 px-4 cursor-pointer rounded-lg items-center">

        <i data-lucide="log-out" class="w-5 h-5"></i>
        <span>Logout</span>
      </a>
    </div>


  </div>


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