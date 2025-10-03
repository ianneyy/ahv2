<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $user_type = $_POST["user_type"];

    $sql = "INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $password, $user_type);

    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id; // Get ID of newly registered user
        $stmt->close();

        // ✅ Log the user in right after registration
        $_SESSION["user_id"] = $new_user_id;
        $_SESSION["user_name"] = $name;
        $_SESSION["user_type"] = $user_type;

        // ✅ Redirect based on user type
        switch ($user_type) {
            case 'farmer':
                header("Location: ../farmer/dashboard.php");
                exit;
            case 'businessOwner':
                header("Location: ../owner/dashboard.php");
                exit;
            case 'businessPartner':
                header("Location: ../partner/dashboard.php");
                exit;
            case 'transactionVerifier':
                header("Location: ../verifier/dashboard.php");
                exit;
            default:
                // If somehow user_type is invalid
                $_SESSION['toast_error'] = "Unknown user type.";
                header("Location: register.php");
                exit;
        }
    } else {
        $_SESSION['toast_error'] = "Registration failed. Please try again.";
        header("Location: register.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AHV2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />

    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../assets/style.css">

</head>

<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-sm w-full space-y-8 p-8 bg-white border border-emerald-900 rounded-3xl shadow-lg"
            style="box-shadow: 6px 6px 0px #28453E;">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-[#28453E]">Register</h2>
                <p class="mt-2 text-gray-600">Create your account</p>
            </div>

            <form method="POST" class="mt-8 space-y-6">
                <div class="rounded-md  flex flex-col gap-3">
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-semibold text-emerald-700">Name</label>
                        <input type="text" name="name" required
                            class="mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm focus:ring-2">
                    </div>

                    <div class="mb-4">
                        <label for="email" class="block text-sm font-semibold text-emerald-700">Email</label>
                        <input type="email" name="email" required
                            class="mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm focus:ring-2">
                    </div>


                    <div class="mb-4 relative">
                        <label class="block text-sm font-semibold text-emerald-700">Password</label>
                        <input type="password" name="password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" autocomplete="new-password"
                            title="Must be more than 8 characters, including number, lowercase letter, uppercase letter"
                            class="input validator  appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm focus:ring-2">
                        <p
                            class="validator-hint text-xs absolute p-4 w-full rounded-md mt-2 border border-red-500 bg-white">
                            Must be more than 8 characters, including
                            <br />At least one number
                            <br />At least one lowercase letter
                            <br />At least one uppercase letter
                        </p>

                    </div>
                    <div class="mb-4">
                        <label for="user_type" class="block text-sm font-semibold text-emerald-700">User Type</label>
                        <select name="user_type" required
                            class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-gray-900 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                            <option value="businessOwner">Business Owner</option>
                            <option value="farmer">Farmer</option>
                            <option value="businessPartner">Business Partner</option>
                            <option value="transactionVerifier">Transaction Verifier</option>
                        </select>
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2 px-4 rounded-md shadow-sm text-sm font-medium text-white bg-emerald-900 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Register
                    </button>
                </div>
            </form>

            <div class="text-center mt-4">
                <p class="text-sm text-gray-600">
                    Already have an account?
                    <a href="login.php" class="font-medium text-green-600 hover:text-green-500">
                        Sign in here
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
</body>


</html>
<!-- <form method="POST">
  Name: <input type="text" name="name" required><br>
  Email: <input type="email" name="email" required><br>
  Password: <input type="password" name="password" required><br>
  User Type:
  <select name="user_type" required>
    <option value="businessOwner">Business Owner</option>
    <option value="farmer">Farmer</option>
    <option value="businessPartner">Business Partner</option>
    <option value="transactionVerifier">Transaction Verifier</option>
  </select><br>
  <button type="submit">Register</button>
</form> -->