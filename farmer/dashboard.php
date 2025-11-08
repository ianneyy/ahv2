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

<?php
require_once '../includes/header.php';
?>
  <div class="flex min-h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <!-- Main content -->
    <main class="flex-1 bg-[#FCFBFC] lg:p-6 rounded-bl-4xl rounded-tl-4xl">
      <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
        <div class="flex items-center justify-center">

          <div id="bar" class="flex w-full justify-between items-center  mb-10  rounded-full">
            <h2 class="text-2xl lg:text-4xl font-semibold text-emerald-800">Welcome,
              <?= ucfirst(htmlspecialchars($_SESSION["user_name"])) ?>!
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