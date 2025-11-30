<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../gClientSetup.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);



$wrong_password = "";
$wrong_email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_type"] = $user["user_type"];

            // Redirect based on user type
            switch ($user["user_type"]) {
                case 'farmer':
                    header("Location: ../farmer/dashboard.php");
                    break;
                case 'businessOwner':
                    header("Location: ../owner/dashboard.php");
                    break;
                case 'businessPartner':
                    header("Location: ../partner/dashboard.php");
                    break;
                case 'transactionVerifier':
                    header("Location: ../verifier/dashboard.php");
                    break;
                default:
                    echo "❌ Unknown usertype";
                    exit();
            }
        } else {
            // echo "❌ Incorrect password.";
            $wrong_password = "Incorrect password.";
        }
    } else {
        $wrong_email = "Email not found.";
    }
}
?>
<?php
require_once '../includes/header.php';
?>
<style>
    .hero-spotlight {
        background:
            radial-gradient(circle at 0% 0%,
                rgba(191, 244, 155, 0.8) 0%,
                rgba(191, 244, 155, 0.5) 20%,
                rgba(191, 244, 155, 0.2) 40%,
                transparent 70%);
    }
</style>
<div class="min-h-screen hero-spotlight">
    <div class="flex gap-5">

        <section class="flex justify-center items-center w-1/2">
            <div class="w-full">
                <div class="px-12">

                    <div class="text-center">
                        <h1 class="text-5xl font-semibold">Welcome!</h1>
                        <p class="text-lg">Please login your account.</p>
                    </div>
                    <div class="w-2/3 mx-auto">
                        <form method="POST" class="mt-8 space-y-6">
                            <div class="rounded-md -space-y-px">
                                <div class="mb-4">
                                    <label
                                        class="input w-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 bg-transparent border-2 border-[#059669] rounded-lg px-3 py-2  flex items-center gap-2">
                                        <i data-lucide="mail" class="h-[1em]"></i>

                                        <input type="text" name="email" required class="grow placeholder-gray-600"
                                            placeholder="Email" />
                                    </label>
                                    <!-- <label for="email" class="block text-sm font-semibold text-gray-700">Email</label>
                                    <input type="email" name="email" required
                                        class="mt-1 appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm"> -->
                                </div>
                                <?php if ($wrong_email != null): ?>
                                    <div class="text-right">
                                        <span class="text-xs text-red-500 font-semibold">
                                            <?php echo $wrong_email; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-4 ">

                                    <label
                                        class="input w-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 bg-transparent border-2 border-[#059669] rounded-lg px-3 py-2  flex items-center gap-2 ">
                                        <i data-lucide="lock" class="h-[1em]"></i>

                                        <input type="password" name="password" required
                                            class="grow placeholder-gray-600" placeholder="Password" />
                                    </label>
                                    <div class="flex justify-end mt-2">

                                        <a href="forgot_password.php"
                                            class="text-xs font-medium text-gray-700 hover:text-red-500">Forgot
                                            Password?</a>
                                    </div>

                                    <!-- <input type="password" name="password" required
                                        class="input validator mt-1 appearance-none bg-transparent rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm focus:ring-2"> -->


                                    <!-- <p class="validator-hint text-xs">
                                                Must be more than 8 characters, including
                                                <br />At least one number
                                                <br />At least one lowercase letter
                                                <br />At least one uppercase letter
                                            </p> -->
                                </div>
                                <?php if ($wrong_password != null): ?>
                                    <div class="text-right">
                                        <span class="text-xs text-red-500 font-semibold">
                                            <?php echo $wrong_password; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>


                            <div>
                                <button type="submit"
                                    class="w-full flex justify-center py-3 px-4  border border-transparent rounded-full shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Sign in
                                </button>
                            </div>
                        </form>
                        <div class="flex items-center w-full my-4">
                            <hr class="flex-grow border-gray-300">
                            <span class="mx-2 text-gray-500">or</span>
                            <hr class="flex-grow border-gray-300">
                        </div>

                        <div class="mt-2">

                            <a href="<?php echo $client->createAuthUrl(); ?>"
                                class=" flex justify-center py-3 px-4 border rounded-full border-gray-300 text-sm font-medium text-black bg-transparent hover:border-gray-400 gap-2">
                                <img src="../assets/google.png" alt="Google" class="w-4 h-4">
                            </a>
                        </div>

                        <div class="text-center mt-4">
                            <p class="text-sm text-gray-600">
                                Don't have an account?
                                <a href="register.php" class="font-medium text-green-600 hover:text-green-500">
                                    Register here
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>


        </section>
        <section class="flex justify-center items-center w-1/2 min-h-screen ">
            <div class="relative bg-[#BFF49B] rounded-3xl w-[90%] min-h-[90vh] bg-cover bg-center"
                style="background-image: url('../assets/img/farm.svg');">

                <div class="p-5 absolute top-0 left-0">
                    <h1 class="text-3xl font-bold text-zinc-700 ">
                        AniHanda
                    </h1>
                    <!-- Bottom-right text -->

                </div>
                <div class="absolute bottom-0 right-0 p-5 text-right font-medium text-xl">
                    <p>Hey</p>
                    <p>Welcome to</p>
                    <p>AniHanda</p>
                </div>

            </div>
        </section>
    </div>

</div>
<?php if ($toast_message): ?>
    <div class="toast">
        <div class="alert alert-success">
            <span class="text-emerald-900"><?php echo htmlspecialchars($toast_message); ?></span>
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
<script>
    tailwind.config = {
        plugins: [daisyui],
    }
</script>

</body>

</html>