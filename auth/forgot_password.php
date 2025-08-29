<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);


$toast_error = $_SESSION['toast_error'] ?? null;
unset($_SESSION['toast_error']);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php'; // if installed with Composer


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $email = trim($_POST['email']);
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();


    if ($user) {
        $token = bin2hex(random_bytes(50));
        // var_dump($token);
        $expires = date("Y-m-d H:i:s", strtotime('+1 hour'));
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expires);
        $stmt->execute();
        $stmt->close();


        $resetLink = " http://localhost/AHV2/auth/reset_password.php?token=$token";
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = '0322-2070@lspu.edu.ph';   // your Gmail
            $mail->Password = 'cfvp pdhf chui dvcs';     // use Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            //Recipients
            $mail->setFrom('0322-2070@lspu.edu.ph', 'AHV2');
            $mail->addAddress($email);


            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset';
            $mail->Body =

                '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            line-height: 1.6;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #064e3b;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 300;
        }
        .content {
            padding: 40px 30px;
            color: #333333;
        }
        .content h2 {
            color: #2c3e50;
            margin-top: 0;
            font-size: 20px;
        }
        .reset-button {
            display: inline-block;
            padding: 15px 30px;
            background-color: #059669;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
            transition: background-color 0.3s;
        }
        .reset-button:hover {
            background-color: #047857;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .footer {
            background-color: #ecf0f1;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #7f8c8d;
            border-top: 1px solid #bdc3c7;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .link-fallback {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 14px;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
            .content {
                padding: 20px !important;
            }
            .reset-button {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>
        
        <div class="content">
            <h2>Hello!</h2>
            
            <p>We received a request to reset the password for your account. If you made this request, please click the button below to reset your password:</p>
            
            <div class="button-container">
                <a href="' . $resetLink . '" class="reset-button" style="color:white">Reset My Password</a>
            </div>
            
            <div class="warning-box">
                <strong>⏰ Important:</strong> This link will expire in 1 hour for security reasons.
            </div>
            
            <p>If the button above doesn\'t work, you can copy and paste the following link into your browser:</p>
            
            <div class="link-fallback">
                ' . $resetLink . '
            </div>
            
            <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
            
            <p><strong>Didn\'t request a password reset?</strong><br>
            If you didn\'t request this password reset, you can safely ignore this email. Your password will remain unchanged.</p>
            
            <p>For security reasons, please don\'t forward this email to anyone.</p>
        </div>
        
        <div class="footer">
            <p>&copy; ' . date('Y') . ' AHV2. All rights reserved.</p>
            <p>This is an automated email. Please do not reply to this message.</p>
        </div>
    </div>
</body>
</html>';

            $mail->AltBody = "
Password Reset Request

Hello!

We received a request to reset the password for your account.

To reset your password, copy and paste this link in your browser:
$resetLink

IMPORTANT: This link will expire in 1 hour for security reasons.

If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.

For security reasons, please don't forward this email to anyone.

© " . date('Y') . " Your Company Name. All rights reserved.
This is an automated email. Please do not reply to this message.
";

            $mail->send();
            $_SESSION['toast_message'] = "Check your email for a reset link.";
            header("Location: forgot_password.php"); // ✅ redirect back here
            exit;

        } catch (Exception $e) {
            echo "❌ Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

    } else {
        $_SESSION['toast_error'] = "No account found with that email.";
        header("Location: forgot_password.php"); // ✅ redirect back here
        exit;
    }
}
?>

<?php
require_once '../includes/header.php';
?>


<div class="min-h-screen flex items-center justify-center">
    <div class="max-w-sm w-full space-y-8 p-8 bg-white rounded-2xl shadow-md border border-emerald-900" style="box-shadow: 6px 6px 0px #28453E;">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-[#28453E]">Forgot Password?</h2>
            <p class="mt-2 text-gray-500">No worries, we'll send you reset information</p>
        </div>
        <div>
            <form method="post">
                <div class="flex flex-col gap-4">

                    <fieldset class="fieldset space-y-2">
                        <legend class="fieldset-legend text-emerald-900">Email</legend>
                        <input type="email" name="email" required placeholder="Enter your email"
                            class="w-full border border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg py-2 px-3">
                    </fieldset>
                    <button type="submit"
                        class="w-full bg-emerald-900 text-white py-2 px-4 rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">Send
                        Reset Link</button>
                </div>
                <div class="flex justify-center mt-7">

                    <a href="login.php" class="flex items-center gap-2 text-gray-600 hover:text-emerald-600">

                        <i data-lucide="arrow-left" class=" w-4 h-4"></i>
                        <span class="text-sm">Go back to Login</span>
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>


<?php
require_once '../includes/footer.php';
?>

<?php if ($toast_error): ?>
    <div class="toast">
        <div class="alert alert-error">
            <span class="text-white"><?php echo htmlspecialchars($toast_error); ?></span>
        </div>
    </div>
<?php endif; ?>