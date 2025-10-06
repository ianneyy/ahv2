<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../gClientSetup.php';

try {
    // Check if we have the authorization code
    if (isset($_GET['code'])) {
        // Exchange the authorization code for an access token
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        if (isset($token['error'])) {
            throw new Exception('Failed to get access token: ' . $token['error']);
        }

        // Set the access token
        $client->setAccessToken($token);

        // Get user info from Google
        $service = new Google\Service\Oauth2($client);
        $userInfo = $service->userinfo->get();

        $email = $userInfo->getEmail();
        $name = $userInfo->getName();
        $googleId = $userInfo->getId();
        $pictureUrl = $userInfo->getPicture();


        // ✅ Save profile image locally
        $profileDir = '../assets/profile/';
        if (!is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }
        $profileFileName = $googleId . '.jpg';
        $profileFilePath = $profileDir . $profileFileName;

        file_put_contents($profileFilePath, file_get_contents($pictureUrl));

        // Check if user exists in database
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            // User exists, log them in
            $user = $result->fetch_assoc();

            if (empty($user['google_id'])) {
                $updateStmt = $conn->prepare("UPDATE users SET google_id = ? WHERE email = ?");
                $updateStmt->bind_param("ss", $googleId, $email);
                $updateStmt->execute();
            }
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["user_type"] = $user["user_type"];
            $_SESSION["user_picture"] = $pictureUrl;


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
            // User doesn't exist, redirect to registration with Google info
            $_SESSION['flash_message'] = "Create an account first";
            $_SESSION['google_user_info'] = [
                'email' => $email,
                'name' => $name,
                'google_id' => $googleId,
                'picture' => $picture
            ];
            header("Location: ../auth/register.php?google=1");
        }
    } else {
        // No authorization code, redirect to login
        header("Location: ../auth/login.php?error=oauth_failed");
    }
} catch (Exception $e) {
    // Handle errors
    error_log("Google OAuth Error: " . $e->getMessage());
    header("Location: ../auth/login.php?error=oauth_error");
}

exit();
