<?php
session_start();
require_once 'includes/db.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Get user type from database
    $query = "SELECT user_type FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Redirect based on user type
    switch ($user['user_type']) {
        case 'farmer':
            header("Location: http://localhost/AHV2/farmer/dashboard.php");
            exit();
        case 'businessOwner':
            header("Location: http://localhost/AHV2/owner/dashboard.php");
            exit();
        case 'admin':
            header("Location: http://localhost/AHV2/admin/dashboard.php");
            exit();
        case 'veterinarian':
            header("Location: http://localhost/AHV2/vet/dashboard.php");
            exit();
        default:
            // If unknown user type, logout and redirect to login
            session_destroy();
            header("Location: login.php");
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AHV2 System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist+Sans:wght@100..900&display=swap"> -->
    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-gray-50 px-5 lg:px-0">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8 p-8 bg-white border border-emerald-900 rounded-3xl shadow-lg"  style="box-shadow: 6px 6px 0px #28453E;">
            <div class="text-center">
                <h2 class="text-3xl font-bold text-green-600">Welcome to AniHanda</h2>
                <p class="mt-2 text-gray-600">Please log in to continue</p>
            </div>
            <div class="mt-8 space-y-4">
                <a href="auth/login.php"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-emerald-900 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Login
                </a>
                <a href="auth/register.php"
                    class="w-full flex justify-center py-2 px-4 border border-emerald-900 rounded-md shadow-sm text-sm font-medium text-emerald-900 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Register
                </a>
            </div>
        </div>
    </div>
</body>

</html>