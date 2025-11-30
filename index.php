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
<?php
require_once 'includes/header.php';
?>
<style>
    /* From Uiverse.io by Creatlydev */
    .button {
        line-height: 1;
        text-decoration: none;
        display: inline-flex;
        border: none;
        cursor: pointer;
        align-items: center;
        gap: 0.75rem;
        background-color: var(--clr);
        color: #fff;
        border-radius: 10rem;
        font-weight: 600;
        padding: 0.75rem 1.5rem;
        padding-left: 20px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background-color 0.3s;
    }

    .button__icon-wrapper {
        flex-shrink: 0;
        width: 25px;
        height: 25px;
        position: relative;
        color: var(--clr);
        background-color: #fff;
        border-radius: 50%;
        display: grid;
        place-items: center;
        overflow: hidden;
    }

    .button:hover {
        background-color: #000;
    }

    .button:hover .button__icon-wrapper {
        color: #000;
    }

    .button__icon-svg--copy {
        position: absolute;
        transform: translate(-150%, 150%);
    }

    .button:hover .button__icon-svg:first-child {
        transition: transform 0.3s ease-in-out;
        transform: translate(150%, -150%);
    }

    .button:hover .button__icon-svg--copy {
        transition: transform 0.3s ease-in-out 0.1s;
        transform: translate(0);
    }
    .hero-spotlight {
    background:
         radial-gradient(circle at 100% 0%,
                rgba(191, 244, 155, 0.8) 0%,
                rgba(191, 244, 155, 0.5) 20%,
                rgba(191, 244, 155, 0.2) 40%,
                transparent 70%);
}
</style>
<div class="min-h-screen hero-spotlight">

    <header class="px-16 py-6">
        <div class="flex justify-between">

            <h1 class="text-lg font-semibold text-emerald-600">AniHanda</h1>
            <div class="flex items-center gap-12 text-gray-900 text-sm">
                <a href="#home">Home</a>
                <a href="">About</a>
                <a href="">Contact</a>
            </div>

            <div>
                <button class="bg-emerald-600 px-3 py-2 rounded-lg text-white text-sm">Get Started</button>
            </div>

        </div>

    </header>

    <section id="home" class="flex flex-col">
        <div class="text-center flex flex-col justify-center w-full">

            <h1 class="text-zinc-900 mt-8 max-w-4xl text-balance mx-auto text-6xl md:text-7xl lg:mt-16 ">
                AniHanda — Connecting Farmers & Buyers
            </h1>
            <p class="mx-auto mt-8 max-w-2xl text-balance text-lg text-zinc-800">
                Farmers can easily list their harvested crops, while bidders and buyers can place competitive offers
                in a transparent and secure marketplace.
            </p>
        </div>

        <div class="flex justify-center items-center mt-5">
<!-- 
            <button class="px-4 py-3 rounded-lg bg-emerald-600 text-sm text-white flex gap-2 items-center">
                <span>Get Started</span>
                <i data-lucide="arrow-up-right" class="w-5 h-5 text-gray-100"></i>

            </button> -->
            <a href="auth/login.php" class="button" style="--clr: #059669">
                <span class="button__icon-wrapper">
                    <svg viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg" class="button__icon-svg"
                        width="10">
                        <path
                            d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                            fill="currentColor"></path>
                    </svg>

                    <svg viewBox="0 0 14 15" fill="none" width="10" xmlns="http://www.w3.org/2000/svg"
                        class="button__icon-svg button__icon-svg--copy">
                        <path
                            d="M13.376 11.552l-.264-10.44-10.44-.24.024 2.28 6.96-.048L.2 12.56l1.488 1.488 9.432-9.432-.048 6.912 2.304.024z"
                            fill="currentColor"></path>
                    </svg>
                </span>
                Get Started
            </a>
        </div>


    </section>
    <!-- <div class="min-h-screen flex items-center justify-center">
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
    </div> -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <script>
        lucide.createIcons();

    </script>
    </body>


    </html>