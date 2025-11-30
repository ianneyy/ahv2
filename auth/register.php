<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);

// Check if this is an invitation
$invite_email = null;
$invite_role = null;


if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);
    $inviteResult = $conn->query("SELECT * FROM invitations WHERE token='$token' AND status='pending'");
    if ($inviteResult->num_rows > 0) {
        $invite = $inviteResult->fetch_assoc();
        $invite_email = $invite['email'];
        $invite_role = $invite['role'];
    } else {
        die("Invitation is invalid or already used.");
    }
}
function prettyRole($role)
{
    $map = [
        'farmer' => 'Farmer',
        'businessPartner' => 'Business Partner',
        'businessOwner' => 'Business Owner',
        'transactionVerifier' => 'Transaction Verifier'
    ];
    return $map[$role] ?? $role;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    // $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

    $email = $invite_email ?? $_POST["email"];
    $user_type = $invite_role ?? $_POST["user_type"];

    $sql = "INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $password, $user_type);

    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id; // Get ID of newly registered user
        $stmt->close();

        // Mark invitation as used if applicable
        if ($invite_email) {
            $conn->query("UPDATE invitations SET status='used' WHERE token='$token'");
        }

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
                        <h1 class="text-5xl font-semibold">Register</h1>
                        <p class="text-lg">Create your first account.</p>
                    </div>
                    <div class="w-2/3 mx-auto">
                        <form method="POST" class="mt-8 space-y-6">
                            <div class="rounded-md  flex flex-col gap-3">
                                <div class="mb-2">
                                    <label
                                        class="input w-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 bg-transparent border-2 border-[#059669] rounded-lg px-3 py-2  flex items-center gap-2 ">
                                        <i data-lucide="user" class="h-[1em]"></i>

                                        <input type="text" name="name" required class="grow placeholder-gray-600"
                                            placeholder="Name" />
                                    </label>

                                </div>
                                <?php if ($invite_email): ?>
                                    <div class="mb-2">
                                        <label
                                            class="input w-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 bg-transparent border-2 border-[#059669] rounded-lg px-3 py-2  flex items-center gap-2">
                                            <i data-lucide="mail" class="h-[1em]"></i>

                                            <input type="text" name="email" value="<?= htmlspecialchars($invite_email) ?>"
                                                required class="grow placeholder-gray-600" placeholder="Email" readonly />
                                        </label>
                                        <!-- <label for="email"
                                        class="block text-sm font-semibold text-emerald-700">Email</label>
                                    <input type="email" name="email" required
                                        class="mt-1 appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-400 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm "> -->
                                    </div>

                                <?php else: ?>
                                    <div class="mb-2">
                                        <label
                                            class="input w-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 bg-transparent border-2 border-[#059669] rounded-lg px-3 py-2  flex items-center gap-2">
                                            <i data-lucide="mail" class="h-[1em]"></i>

                                            <input type="text" name="email" required class="grow placeholder-gray-600"
                                                placeholder="Email" />
                                        </label>
                                        <!-- <label for="email"
                                        class="block text-sm font-semibold text-emerald-700">Email</label>
                                    <input type="email" name="email" required
                                        class="mt-1 appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-400 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 sm:text-sm "> -->
                                    </div>
                                <?php endif; ?>
                                <div class="mb-2 relative">
                                    <label
                                        class="input w-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 bg-transparent border-2 border-[#059669] rounded-lg px-3 py-2  flex items-center gap-2">
                                        <i data-lucide="lock" class="h-[1em]"></i>

                                        <input type="password" name="password" required
                                            class="grow placeholder-gray-600" placeholder="Password"
                                            title="Must be more than 8 characters, including number, lowercase letter, uppercase letter"
                                            pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" autocomplete="new-password" />
                                        <p
                                            class="validator-hint text-xs absolute p-4 w-full rounded-md mt-2 border border-red-500 bg-white">
                                            Must be more than 8 characters, including
                                            <br />At least one number
                                            <br />At least one lowercase letter
                                            <br />At least one uppercase letter
                                        </p>
                                    </label>


                                </div>

                                <?php if ($invite_role): ?>
                                    <input type="text" value="<?= htmlspecialchars(prettyRole($invite_role)) ?>" readonly
                                        class="w-full border border-dashed border-emerald-600 rounded px-3 py-2 bg-gray-100 cursor-not-allowed bg-transparent">

                                <?php else: ?>
                                    <div class="mb-4">
                                        <label class="block text-sm font-semibold text-emerald-700">
                                            Please select your role
                                        </label>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Select which role best describes you. This helps us customize your AniHanda
                                            experience.
                                        </p>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-3">

                                            <!-- Farmer Option -->
                                            <input type="radio" name="user_type" id="farmer" value="farmer"
                                                class="hidden peer/farmer">
                                            <label for="farmer"
                                                class="cursor-pointer border border-gray-300 rounded-xl p-4 block peer-checked/farmer:border-green-500 peer-checked/farmer:bg-[#BFF49B]">
                                                <h3 class="text-lg font-semibold text-emerald-700">Farmer</h3>
                                                <p class="text-sm text-gray-600">Submit your harvested crops to be listed
                                                    for bidding by business partners.
                                                </p>
                                            </label>

                                            <!-- Business Partner Option -->
                                            <input type="radio" name="user_type" id="businessPartner"
                                                value="businessPartner" class="hidden peer/business">
                                            <label for="businessPartner"
                                                class="cursor-pointer border border-gray-300 rounded-xl p-4 block peer-checked/business:border-green-500 peer-checked/business:bg-[#BFF49B]">
                                                <h3 class="text-lg font-semibold text-emerald-700">Business Partner</h3>
                                                <p class="text-sm text-gray-600">Place bids on crops submitted by farmers to
                                                    purchase them.</p>
                                            </label>

                                        </div>
                                    </div>
                                <?php endif; ?>

                            </div>

                            <div>
                                <button type="submit"
                                    class="w-full flex justify-center py-3 px-4  border border-transparent rounded-full shadow-sm text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
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