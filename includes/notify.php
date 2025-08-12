
<?php
require_once '../includes/db.php'; // Adjust path if needed
require_once '../includes/session.php'; // So we can use $_SESSION['user_id'] and $_SESSION['user_type']

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    $notifId = intval($_POST['mark_read_id']);
    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        http_response_code(403);
        echo 'Unauthorized';
        exit;
    }

    // Only mark the notification if it belongs to the logged-in user
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notificationid = ? AND userid = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    $stmt->close();

    echo "Marked as read";
    exit;
}

// Reusable function to insert a notification
function notify($conn, $userId, $userType, $message) {
    $allowedTypes = ['farmer', 'businessPartner', 'businessOwner'];
    if (!in_array($userType, $allowedTypes)) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO notifications (userid, user_type, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("iss", $userId, $userType, $message);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

// ✅ Send a notification to all users of a specific user type
function sendNotificationToUserType($conn, $userType, $message) {
    $allowedTypes = ['farmer', 'businessPartner', 'businessOwner'];
    if (!in_array($userType, $allowedTypes)) {
        return false;
    }

    // Get all user IDs with that user type
    $stmt = $conn->prepare("SELECT id FROM users WHERE user_type = ?");
    $stmt->bind_param("s", $userType);
    $stmt->execute();
    $result = $stmt->get_result();

    $success = true;

    while ($row = $result->fetch_assoc()) {
        $userId = $row['id'];
        $insertStmt = $conn->prepare("INSERT INTO notifications (userid, user_type, message) VALUES (?, ?, ?)");
        if (!$insertStmt) {
            $success = false;
            continue;
        }
        $insertStmt->bind_param("iss", $userId, $userType, $message);
        if (!$insertStmt->execute()) {
            $success = false;
        }
        $insertStmt->close();
    }

    $stmt->close();
    return $success;
}


function get_notifications($conn, $userId, $userType) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE userid = ? AND user_type = ? ORDER BY created_at DESC");
    $stmt->bind_param("is", $userId, $userType);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $notifications;
}

?>

