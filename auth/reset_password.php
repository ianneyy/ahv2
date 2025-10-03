<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);


$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast_error']);




if (isset($_GET['token'])) {

    $token = $_GET['token'];
    // Check token validity
    $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $reset = $result->fetch_assoc();
    $stmt->close();

    if ($reset && strtotime($reset['expires_at']) > time()) {


        if ($_SERVER['REQUEST_METHOD'] == 'POST') {


            if (strlen($_POST['password']) < 8) {
                $_SESSION['toast_error'] = "Password must be at least 8 characters long.";
            } else {
                $newPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

                // Update user password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->bind_param("ss", $newPassword, $reset['email']);
                $stmt->execute();
                $stmt->close();

                // Delete token after use
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast_message'] = "Password reset successful! You can now log in.";
                header("Location: login.php");
                exit;
            }


        }
    }
}
?>


<?php
require_once '../includes/header.php';
?>


<div class="min-h-screen flex items-center justify-center">
    <div class="max-w-sm w-full space-y-8 p-8 bg-white rounded-2xl shadow-md border">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-[#28453E]">Reset your password</h2>
        </div>
        <div>
            <form method="post">
                <div class="flex flex-col gap-4">

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend text-emerald-900 mb-2">New Password</legend>
                        <input type="password" name="password" required placeholder="Enter your new password"
                            class="w-full border border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg py-2 px-3">
                        <p class="label">Must be atleast 8 characters</p>
                    </fieldset>
                    <button type="submit"
                        class="w-full bg-emerald-900 text-white py-2 px-4 rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">Reset
                        Password</button>
                </div>

            </form>
        </div>
    </div>
</div>
<?php
require_once '../includes/footer.php';
?>