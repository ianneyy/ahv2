<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';
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
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AHV2</title>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="../assets/style.css">
</head>

<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-sm w-full space-y-8 p-8 bg-white border border-emerald-900 rounded-3xl shadow-lg"
         style="box-shadow: 6px 6px 0px #28453E;">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-[#28453E]">Login</h2>
                <p class="mt-2 text-gray-600">Please enter your credentials</p>
            </div>

            <form method="POST" class="mt-8 space-y-6">
                <div class="rounded-md -space-y-px">
                    <div class="mb-4">
                        <label for="email" class="block text-sm font-semibold text-emerald-700">Email</label>
                        <input type="email" name="email" required
                            class="mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm">
                    </div>
                    <?php if ($wrong_email != null): ?>
                        <div class="text-right">
                            <span class="text-xs text-red-500 font-semibold">
                                <?php echo $wrong_email; ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <div class="mb-4 ">
                        <div class="flex justify-between">
                            <label for="password" class="block text-sm font-semibold text-emerald-700">Password</label>
                            <a href="forgot_password.php"
                                class="text-xs font-medium text-gray-700 hover:text-red-500">Forgot Password?</a>
                        </div>

                        <input type="password" name="password" required 
                            class="input validator mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm">


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
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-900 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Sign in
                    </button>
                </div>
            </form>

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
    <script>
        tailwind.config = {
            plugins: [daisyui],
        }
    </script>

</body>

</html>