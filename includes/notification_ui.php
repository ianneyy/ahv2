<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/notify.php'; // ✅ Reuse your logic

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    return;
}

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];
$notifications = get_notifications($conn, $userId, $userType);
$unreadCount = 0;

foreach ($notifications as $notif) {
    if (!$notif['is_read']) {
        $unreadCount++;
    }
}
?>

<style>
.notification-container {
     /* position: absolute;
     top: 20px;
    right: 20px;  */
    z-index: 999;
    font-family: Arial, sans-serif;
}

.notification-bell {
    position: relative;
    display: inline-block;
    cursor: pointer;
    font-size: 24px;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -10px;
    background: red;
    color: white;
    font-size: 12px;
    padding: 3px 6px;
    border-radius: 50%;
}

.notification-dropdown {
    position: absolute;
    top: 35px;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    width: 320px;
    max-height: 400px;
    overflow-y: auto;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    display: none;
    border-radius: 5px;
}

.notification-dropdown.show {
    display: block;
}

.notification-item {
    padding: 10px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.notification-item.unread {
    background: #EDF6EC;
    font-weight: bold;
}

.notification-item time {
    font-size: 12px;
    color: #888;
    display: block;
    margin-top: 5px;
}
</style>


<!-- Wrap everything in a container -->
<div class="notification-container flex items-center justify-center">
    <div class="notification-bell" onclick="toggleNotificationDropdown()">
              <i data-lucide="bell" class="w-6 h-6"></i>
        
        <?php if ($unreadCount > 0): ?>
            <span class="notification-badge"><?= $unreadCount ?></span>
        <?php endif; ?>
    </div>

    <div class="notification-dropdown" id="notificationDropdown">
        <?php if (count($notifications) > 0): ?>
            
<?php foreach ($notifications as $notif): ?>
    <div class="notification-item text-black <?= !$notif['is_read'] ? 'unread' : '' ?>"
         data-id="<?= $notif['notificationid'] ?>"
         onclick="markNotificationAsRead(this)">
        <?= htmlspecialchars($notif['message']) ?>
        <time><?= date("F j, Y, g:i a", strtotime($notif['created_at'])) ?></time>
    </div>
<?php endforeach; ?>


        <?php else: ?>
            <div class="notification-item text-black">No notifications yet.</div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

// ✅ New function to mark as read
function markNotificationAsRead(el) {
    const notifId = el.getAttribute('data-id');

    fetch('../includes/notify.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_read_id=' + encodeURIComponent(notifId)
    })
    .then(res => res.text())
    .then(data => {
        // ✅ Update UI instantly
        el.classList.remove('unread');
        el.style.fontWeight = 'normal';
    });
}
</script>
