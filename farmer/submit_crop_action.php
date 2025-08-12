<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
require_once '../includes/notification_ui.php';


// Check if farmer is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}

// Validate form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $farmerid = $_SESSION['user_id'];
    $croptype = $_POST['croptype'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    $status = 'pending';
    $submittedat = date("Y-m-d H:i:s");
    $submissionid = $_POST['submissionid'] ?? '';

    // Validate crop-unit logic
    $validCropUnitMap = [
        'buko' => 'pcs',
        'saba' => 'pcs',
        'lanzones' => 'kg',
        'rambutan' => 'kg'
    ];

    if (!isset($validCropUnitMap[$croptype]) || $validCropUnitMap[$croptype] !== $unit) {
        die("❌ Invalid crop and unit combination.");
    }

    // Handle image upload
    $uploadDir = '../assets/uploads/';
    if (!empty($_FILES['image']['name'])) {
        $imageName = basename($_FILES["image"]["name"]);
        $targetFile = $uploadDir . uniqid() . "_" . $imageName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($imageFileType, $allowedTypes)) {
            die("❌ Only JPG, JPEG, PNG, or WEBP files are allowed.");
        }

        if ($_FILES["image"]["size"] > 5 * 1024 * 1024) { // 5MB limit
            die("❌ Image is too large. Max 5MB.");
        }

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
            die("❌ Failed to upload image.");
        }

        $imagepath = basename($targetFile); // Save only the filename
    } else {
        $imagepath = $_POST['old_image'] ?? '';
    }

    if (!empty($submissionid)) {


        $stmt = $conn->prepare("UPDATE crop_submissions 
                            SET farmerid = ?, croptype = ?, quantity = ?, unit = ?, imagepath = ?, status = ?, submittedat = ?
                            WHERE submissionid = ?");
        $stmt->bind_param("isdssssi", $farmerid, $croptype, $quantity, $unit, $imagepath, $status, $submittedat, $submissionid);

        if ($stmt->execute()) {
            $message = "📢 A crop submission has been updated by a farmer on " . date("F j, Y, g:i a");
            sendNotificationToUserType($conn, 'businessOwner', $message);

            $_SESSION['toast_message'] = "Crop submission updated successfully!";
            header("Location: http://localhost/AHV2/farmer/dashboard.php");
            exit();
        } else {
            echo "❌ Failed to update crop submission.";
        }
    } else {




        // Insert into DB
        $stmt = $conn->prepare("INSERT INTO crop_submissions (farmerid, croptype, quantity, unit, imagepath, status, submittedat)
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdssss", $farmerid, $croptype, $quantity, $unit, $imagepath, $status, $submittedat);

        if ($stmt->execute()) {

            $message = "📢 A new crop submission has been made by a farmer on " . date("F j, Y, g:i a");
            sendNotificationToUserType($conn, 'businessOwner', $message);
            $_SESSION['toast_message'] = "Crop submitted successfully!";
            header("Location: http://localhost/AHV2/farmer/dashboard.php");


        } else {
            echo "❌ Failed to submit crop. Please try again.";
        }
    }

    $stmt->close();
    $conn->close();
} else {
    echo "Invalid request.";
}
?>