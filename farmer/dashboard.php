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
  <div class="mx-auto max-w-2xl py-8 px-4 border border-slate-200 shadow-md bg-white rounded-2xl">
    <div class="flex justify-between items-center">
      <h2 class="ml-5 text-slate-700 font-semibold text-lg">Welcome,
        <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!
      </h2>
      <div class="relative">
        <?php include '../includes/notification_ui.php'; ?>
      </div>
    </div>
  </div>

  <div class=" mx-auto max-w-2xl py-8 px-4 border border-slate-200 shadow-md bg-white rounded-2xl mt-10">

    <div class="flex flex-col gap-5 px-5">

      <a href="submit_crop.php"
        class="flex gap-2 text-slate-700 hover:bg-green-50 hover:text-green-700 py-4 px-4 cursor-pointer rounded-lg items-center">

        <i data-lucide="plus" class="w-5 h-5"></i>
        <span>Submit New Crop</span>
      </a>

      <hr>

      <a href="my_submissions.php"
        class="flex gap-2 text-slate-700 hover:bg-green-50 hover:text-green-700 py-4 px-4 cursor-pointer rounded-lg items-center">

        <i data-lucide="file-text" class="w-5 h-5"></i>
        <span>My Submissions</span>
      </a>

      <hr>

      <a href="../auth/logout.php"
        class="flex gap-2 text-slate-700 hover:bg-red-50 hover:text-red-700 py-4 px-4 cursor-pointer rounded-lg items-center">

        <i data-lucide="log-out" class="w-5 h-5"></i>
        <span>Logout</span>
      </a>
    </div>

   
  </div>


  <?php if ($toast_message): ?>
    <div class="toast">
      <div class="alert alert-success">
        <span><?php echo htmlspecialchars($toast_message); ?></span>
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
  <script>
    lucide.createIcons();
  </script>

</body>

</html>