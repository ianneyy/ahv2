<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);
require_once '../includes/header.php';


$currentUserId = $_SESSION['user_id'];
$query = "SELECT u.id, u.name, u.email, u.google_id,
                (
                    SELECT MAX(created_at)
                    FROM messages m
                    WHERE (m.sender_id = u.id AND m.receiver_id = ?)
                       OR (m.sender_id = ? AND m.receiver_id = u.id)
                ) AS last_time,
                (
                    SELECT COUNT(*)
                    FROM messages um
                    WHERE um.sender_id = u.id AND um.receiver_id = ? AND um.message_read = 0
                ) AS unread_count
            FROM users u
            WHERE u.id != ?
            ORDER BY (last_time IS NULL), last_time DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $currentUserId, $currentUserId, $currentUserId, $currentUserId);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Build the initial unread counts array for JavaScript
$initialUnreadCounts = [];
foreach ($users as $user) {
    $initialUnreadCounts[$user['id']] = (int)$user['unread_count'];
}


?>
<?php
require_once '../includes/header.php';
?>

<div class="flex min-h-screen">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 bg-[#FCFBFC]  rounded-bl-4xl rounded-tl-4xl h-screen">
        <div class="lg:max-w-7xl mx-auto h-full">

            <header class="border-b h-16 flex items-center">
                <div class="flex items-center p-6">
                    <span class="font-semibold text-lg">Chat</span>
                </div>
            </header>


            <div x-data="chatApp()" x-init="init()" class="flex h-[calc(100%-4rem)] overflow-hidden pb-4">
                <!-- chat sidebar -->
                <div class="border-r h-full min-w-sm  overflow-y-auto">
                    <div class="p-3">

                        <label class="input border rounded-lg">
                            <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                    stroke="currentColor">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.3-4.3"></path>
                                </g>
                            </svg>
                            <input type="search" required placeholder="Search" />
                        </label>
                    </div>
                    <div class="px-2">
                        <span class="text-xs text-gray-400">Direct messages</span>
                        <div class="space-y-3" id="dm-list">
                            <?php foreach ($users as $row): ?>
                                <?php
                                $googleId = htmlspecialchars($row['google_id']);
                                $name = htmlspecialchars($row['name']);
                                $profilePath = "../assets/profile/{$googleId}.jpg";

                                // Fetch latest message between current user and this user
                                $latestStmt = $conn->prepare("
                                    SELECT message, created_at, sender_id
                                    FROM messages
                                    WHERE (sender_id = ? AND receiver_id = ?)
                                       OR (sender_id = ? AND receiver_id = ?)
                                    ORDER BY created_at DESC
                                    LIMIT 1
                                ");
                                $latestStmt->bind_param("iiii", $currentUserId, $row['id'], $row['id'], $currentUserId);
                                $latestStmt->execute();
                                $latestRes = $latestStmt->get_result();
                                $latestRow = $latestRes->fetch_assoc();
                                $latestMsgText = $latestRow ? htmlspecialchars($latestRow['message']) : 'No messages yet';
                                $latestMsgDisplay = $latestRow ? (($latestRow['sender_id'] == $currentUserId) ? ('You: ' . $latestMsgText) : $latestMsgText) : 'No messages yet';
                                $latestMsgTime = $latestRow ? date('M d, h:i A', strtotime($latestRow['created_at'])) : '';
                                $unreadCount = (int)($row['unread_count'] ?? 0);
                                $userId = (int)$row['id'];
                                ?>
                                <div class="mt-2 cursor-pointer hover:bg-gray-100 py-2 px-2 rounded-lg"
                                    :class="{ 'bg-gray-100': selectedUserId === <?= $userId ?> }"
                                    id="dm-item-<?= $userId ?>"
                                    data-latest-preview="<?= htmlspecialchars($latestMsgDisplay) ?>"
                                    data-latest-time="<?= htmlspecialchars($latestMsgTime) ?>"
                                    x-init="
                                            latestPreview[<?= $userId ?>] = <?= json_encode($latestMsgDisplay) ?>; latestTime[<?= $userId ?>] = <?= json_encode($latestMsgTime) ?>;"
                                    @click="selectUser(<?= $userId ?>, '<?= htmlspecialchars($row['name']) ?>', '<?= htmlspecialchars($row['google_id']) ?>')">

                                    <div class="flex justify-between">
                                        <div class="flex gap-3">
                                            <?php if (!empty($googleId) && file_exists($profilePath)): ?>
                                                <div class="avatar">
                                                    <div class="w-12 rounded-full">
                                                        <img src="<?= $profilePath ?>" alt="<?= $name ?>'s profile">
                                                    </div>
                                                </div>
                                            <?php else:
                                                // Generate initials from name
                                                $nameParts = explode(' ', trim($name));
                                                $initials = strtoupper(substr($nameParts[0], 0, 1));
                                                if (count($nameParts) > 1) {
                                                    $initials .= strtoupper(substr(end($nameParts), 0, 1));
                                                }
                                            ?>
                                                <div class="avatar avatar-placeholder">
                                                    <div
                                                        class="bg-neutral text-neutral-content w-12 rounded-full flex items-center justify-center">
                                                        <span><?= $initials ?></span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex flex-col">
                                                <span><?= htmlspecialchars($row['name']) ?></span>
                                                <span class="text-sm text-gray-500" :title="latestTime[<?= $userId ?>]" x-text="latestPreview[<?= $userId ?>] || 'No messages yet'"></span>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-400" x-text="latestTime[<?= $userId ?>] || ''"></span>
                                            <!-- ✅ Unread indicator -->
                                            <div class="flex justify-end" x-show="unreadCounts[<?= $userId ?>] > 0">
                                                <div
                                                    class="h-3 w-3 bg-[#FF0000] rounded-full flex items-center p-2 justify-center text-xs text-white">
                                                    <span x-text="unreadCounts[<?= $userId ?>]"></span>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                        </div>


                    </div>

                </div>
                <div class="flex-1 flex flex-col h-full">
                    <!-- Chat Header -->
                    <div class="p-3 border-b w-full flex justify-between items-center gap-4" x-show="selectedUserId">
                        <div class="flex items-center gap-4">
                            <template x-if="selectedUserPic">
                                <div class="avatar">
                                    <div class="w-12 rounded-full">
                                        <img :src="selectedUserPic" alt="" />
                                    </div>
                                </div>
                            </template>

                            <template x-if="!selectedUserPic">
                                <div class="avatar avatar-placeholder">
                                    <div
                                        class="bg-neutral text-neutral-content w-12 rounded-full flex items-center justify-center">
                                        <span x-text="initials"></span>
                                    </div>
                                </div>
                            </template>

                            <span class="text-md font-semibold" x-text="selectedUserName"></span>
                        </div>
                        <span class="text-sm text-gray-400">Business Partner</span>
                    </div>

                    <!-- Chat Content -->
                    <div class="p-4 flex flex-col h-full justify-between overflow-y-auto space-y-3">
                        <div class="space-y-4 overflow-y-auto" x-show="selectedUserId">
                            <template x-for="msg in messages" :key="msg.id">
                                <div :class="msg.sender === 'me' ? 'chat chat-end' : 'chat chat-start'">
                                    <div class="chat-bubble" x-text="msg.text"></div>
                                </div>
                            </template>
                        </div>

                        <div x-show="!selectedUserId" class="text-center text-gray-400 mt-10">
                            Select a user to start chatting
                        </div>

                        <!-- Message Input -->
                        <div class="w-full mt-auto ">
                            <div class="join w-full">
                                <label class="input validator join-item w-full">
                                    <input type="text" placeholder="Type Message..." x-model="newMessage"
                                        @keyup.enter="sendMessage" class="w-full">
                                    <button @click="sendMessage">
                                        <i data-lucide="send-horizontal" class="w-5 h-5"></i>
                                    </button>
                                </label>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

        </div>
    </main>
</div>

<script>
    function chatApp() {
        return {
            socket: null,
            socketReady: false,
            sendQueue: [],
            // backend-driven unread count; reactive for real-time updates
            unreadCounts: <?= json_encode($initialUnreadCounts) ?>,
            latestPreview: {},
            latestTime: {},
            selectedUserId: null,
            messages: [],
            newMessage: '',
            selectedUserName: '',
            selectedUserPic: '',
            initials: '',

            init() {
                this.connectSocket();
                document.querySelectorAll('[id^="dm-item-"]').forEach(item => {
                    const uid = parseInt(item.id.replace('dm-item-', ''));
                    const preview = item.getAttribute('data-latest-preview');
                    const time = item.getAttribute('data-latest-time');
                    if (preview && !this.latestPreview[uid]) {
                        this.latestPreview[uid] = preview;
                    }
                    if (time && !this.latestTime[uid]) {
                        this.latestTime[uid] = time;
                    }
                });
            },

            connectSocket() {
                // Use your actual session user ID from PHP
                const userId = <?= json_encode($_SESSION['user_id']); ?>;
                this.socket = new WebSocket(`ws://localhost:8080/chat?user_id=${userId}`);

                this.socket.onopen = () => {
                    this.socketReady = true;
                    // flush queued messages
                    while (this.sendQueue.length) {
                        const queued = this.sendQueue.shift();
                        this.socket.send(JSON.stringify(queued));
                    }
                };

                this.socket.onclose = () => {
                    this.socketReady = false;
                    // retry connection after short delay
                    setTimeout(() => this.connectSocket(), 1000);
                };

                this.socket.onerror = () => {
                    this.socketReady = false;
                };

                this.socket.onmessage = (event) => {
                    const data = JSON.parse(event.data);
                    if (this.selectedUserId == data.sender_id || this.selectedUserId == data.receiver_id) {
                        this.messages.push({
                            id: data.id || Date.now(),
                            text: data.message,
                            sender: data.sender_id == userId ? 'me' : 'them'
                        });
                        this.$nextTick(() => {
                            const container = document.querySelector('.space-y-4.overflow-y-auto');
                            if (container) container.scrollTop = container.scrollHeight;
                        });
                    }

                    // Increment unread count for messages from others if that chat isn't open
                    if (data.sender_id && data.sender_id != userId && this.selectedUserId != data.sender_id) {
                        const fromId = data.sender_id;
                        this.unreadCounts[fromId] = (this.unreadCounts[fromId] || 0) + 1;
                        // update latest preview/time for sender
                        this.latestPreview[fromId] = data.message;
                        this.latestTime[fromId] = new Date().toLocaleString();
                        this.bumpDmToTop(fromId);
                    }

                    // Always update counterpart preview for the other participant
                    const otherId = data.sender_id == userId ? data.receiver_id : data.sender_id;
                    this.latestPreview[otherId] = (data.sender_id == userId) ? `You: ${data.message}` : data.message;
                    this.latestTime[otherId] = new Date().toLocaleString();
                    this.bumpDmToTop(otherId);
                };
            },

            selectUser(id, name, googleId) {
                this.selectedUserId = id;
                this.selectedUserName = name;

                if (googleId) {
                    // If user has a Google ID profile image
                    this.selectedUserPic = `../assets/profile/${googleId}.jpg`;
                    this.initials = '';
                } else {
                    // Generate initials if no image
                    const parts = name.trim().split(' ');
                    this.initials = parts.map(p => p[0].toUpperCase()).join('').slice(0, 2);
                    this.selectedUserPic = '';
                }

                this.markAsRead(id)
                    .then(() => {
                        this.unreadCounts[id] = 0;
                    })
                    .finally(() => this.fetchMessages(id));
            },

            fetchMessages(userId) {
                fetch(`../includes/fetch_messages.php?user_id=${userId}`)
                    .then(res => res.json())
                    .then(data => {
                        this.messages = Array.isArray(data) ? data : [];
                        this.$nextTick(() => {
                            const container = document.querySelector('.space-y-4.overflow-y-auto');
                            if (container) container.scrollTop = container.scrollHeight;
                        });
                    })
                    .catch(console.error);
            },

            markAsRead(otherId) {
                return fetch(`../includes/mark_read.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        other_id: otherId
                    })
                }).catch(() => {});
            },

            sendMessage() {
                if (!this.newMessage.trim() || !this.selectedUserId) return;

                const msg = this.newMessage.trim();
                const payload = {
                    receiver_id: this.selectedUserId,
                    message: msg
                };

                // Send to socket (queue if not ready yet)
                if (this.socket && this.socketReady && this.socket.readyState === WebSocket.OPEN) {
                    this.socket.send(JSON.stringify(payload));
                } else {
                    this.sendQueue.push(payload);
                }

                this.newMessage = '';

                // Optimistically update sidebar preview/time and position
                const now = new Date().toLocaleString();
                this.latestPreview[this.selectedUserId] = `You: ${msg}`;
                this.latestTime[this.selectedUserId] = now;
                this.bumpDmToTop(this.selectedUserId);
            },

            bumpDmToTop(userId) {
                const list = document.getElementById('dm-list');
                const item = document.getElementById(`dm-item-${userId}`);
                if (list && item) {
                    list.prepend(item);
                }
            }
        }
    }
</script>
<?php
require_once '../includes/footer.php';
?>