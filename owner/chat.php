<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
$toast_message = $_SESSION['toast_message'] ?? null;
unset($_SESSION['toast_message']);
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}
$currentUserType = $_SESSION['user_type'] ?? '';
$currentUserId = $_SESSION['user_id'];
$allowedTypes = [];
if ($currentUserType === 'businessPartner' || $currentUserType === 'farmer') {
    $allowedTypes = ['businessOwner'];
} elseif ($currentUserType === 'businessOwner') {
    $allowedTypes = ['businessPartner', 'farmer'];
}

$query = "SELECT u.id, u.name, u.email, u.google_id, u.user_type,
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
            WHERE
                u.id != ?
                AND u.user_type IN ('" . implode("','", $allowedTypes) . "')
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
    $initialUnreadCounts[$user['id']] = (int) $user['unread_count'];
}
function formatChatTime($datetime)
{
    if (!$datetime)
        return '';

    $ts = strtotime($datetime);
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');

    if ($ts >= $today) {
        // Today → show only time
        return date('h:i A', $ts);
    } elseif ($ts >= $yesterday && $ts < $today) {
        return 'Yesterday';
    } elseif ($ts >= strtotime('last Sunday')) { // within this week
        return date('l', $ts); // day name, e.g., Friday
    } else {
        return date('M d', $ts); // older → show month/day
    }
}

?>
<?php
require_once '../includes/header.php';
?>

<div class="flex min-h-screen">

    <?php
    // Dynamically include the correct sidebar
    $currentUserType = $_SESSION['user_type'] ?? '';

    if ($currentUserType === 'businessOwner') {
        include __DIR__ . '/includes/sidebar.php'; // Owner sidebar
    } elseif ($currentUserType === 'farmer') {
        include __DIR__ . '/../farmer/includes/sidebar.php'; // Partner sidebar
    } elseif ($currentUserType === 'businessPartner') {
        include __DIR__ . '/../partner/includes/sidebar.php'; // Partner sidebar
    } else {
        include __DIR__ . '/includes/sidebar.php'; // fallback
    }
    ?>

    <main class="flex-1 bg-[#FCFBFC]  lg:rounded-bl-4xl lg:rounded-tl-4xl h-screen">
        <div class="lg:max-w-7xl mx-auto h-full">

            <header class="border-b h-16 flex items-center">
                <div class="flex justify-between items-center w-full  p-6">

                    <div class="flex items-center">
                        <span class="font-semibold text-lg">Chat</span>
                    </div>
                    <div>
                        <?php
                        // Dynamically include the correct sidebar
                        $currentUserType = $_SESSION['user_type'] ?? '';

                        if ($currentUserType === 'businessOwner') {
                            include __DIR__ . '/includes/sm-sidebar.php'; // Owner sidebar
                        } elseif ($currentUserType === 'farmer') {
                            include __DIR__ . '/../farmer/includes/sm-sidebar.php'; // Partner sidebar
                        } elseif ($currentUserType === 'businessPartner') {
                            include __DIR__ . '/../partner/includes/sm-sidebar.php'; // Partner sidebar
                        } else {
                            include __DIR__ . '/includes/sm-sidebar.php'; // fallback
                        }
                        ?>

                    </div>

                </div>

            </header>


            <div x-data="chatApp()" x-init="init()" class="flex h-[calc(100%-4rem)] overflow-hidden pb-4">
                <!-- chat sidebar -->
                <div class="border-r h-full w-full lg:w-auto lg:min-w-sm overflow-y-auto">
                    <div class="p-3">

                        <label class="input border rounded-lg">
                            <svg class="h-[1em] opacity-50" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                <g stroke-linejoin="round" stroke-linecap="round" stroke-width="2.5" fill="none"
                                    stroke="currentColor">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.3-4.3"></path>
                                </g>
                            </svg>
                            <input type="search" x-model="searchQuery" placeholder="Search"
                                class="focus:outline-none focus:ring-0 focus:border-transparent" />
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
                                $latestMsgTime = $latestRow ? formatChatTime($latestRow['created_at']) : '';
                                $unreadCount = (int) ($row['unread_count'] ?? 0);
                                $userId = (int) $row['id'];
                                ?>
                                <div class="mt-2 cursor-pointer hover:bg-gray-100 py-2 px-2 rounded-lg"
                                    :class="{ 'bg-gray-100': selectedUserId === <?= $userId ?> }"
                                    id="dm-item-<?= $userId ?>" data-user-name="<?= strtolower($name) ?>"
                                    data-latest-preview="<?= htmlspecialchars($latestMsgDisplay) ?>"
                                    data-latest-time="<?= htmlspecialchars($latestMsgTime) ?>"
                                    x-show="filterUser('<?= strtolower($name) ?>')"
                                    x-init="
                                            latestPreview[<?= $userId ?>] = <?= json_encode($latestMsgDisplay) ?>; latestTime[<?= $userId ?>] = <?= json_encode($latestMsgTime) ?>;"
                                    @click="selectUser(<?= $userId ?>, '<?= htmlspecialchars($row['name']) ?>', '<?= htmlspecialchars($row['google_id']) ?>', '<?= htmlspecialchars($row['user_type']) ?>'); window.innerWidth < 1024 && (showMobileChat = true)">

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
                                                <span class="text-sm text-gray-500" :title="latestTime[<?= $userId ?>]"
                                                    x-text="latestPreview[<?= $userId ?>] || 'No messages yet'"></span>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-400"
                                                x-text="latestTime[<?= $userId ?>] || ''"></span>
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

                <!-- Mobile Chat Modal -->
                <div x-show="showMobileChat" @click.self="showMobileChat = false"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-black bg-opacity-50 z-50 lg:hidden" style="display: none;">

                    <div @click.stop x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="absolute right-0 top-0 h-full w-full bg-white flex flex-col">

                        <!-- Mobile Chat Header -->
                        <div class="p-4 border-b flex items-center gap-4">
                            <button @click="showMobileChat = false" class="text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>

                            <template x-if="selectedUserPic">
                                <div class="avatar">
                                    <div class="w-10 rounded-full">
                                        <img :src="selectedUserPic" alt="" />
                                    </div>
                                </div>
                            </template>

                            <template x-if="!selectedUserPic">
                                <div class="avatar avatar-placeholder">
                                    <div
                                        class="bg-neutral text-neutral-content w-10 rounded-full flex items-center justify-center">
                                        <span x-text="initials"></span>
                                    </div>
                                </div>
                            </template>

                            <div class="flex-1">
                                <div class="font-semibold" x-text="selectedUserName"></div>
                                <div class="text-xs text-gray-400" x-text="selectedUserType"></div>
                            </div>
                        </div>

                        <!-- Mobile Chat Content -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-4" x-ref="mobileMessages">
                            <template x-for="msg in messages" :key="msg.id">
                                <div :class="msg.sender === 'me' ? 'chat chat-end' : 'chat chat-start'">
                                    <div class="chat-bubble" x-text="msg.text"></div>
                                </div>
                            </template>
                        </div>

                        <!-- Mobile Message Input -->
                        <div class="p-4 border-t">
                            <div class="join w-full">
                                <label class="input validator join-item w-full">
                                    <input type="text" placeholder="Type Message..." x-model="newMessage"
                                        @keyup.enter="sendMessage"
                                        class="w-full focus:outline-none focus:ring-0 focus:border-transparent">
                                    <button @click="sendMessage">
                                        <i data-lucide="send-horizontal" class="w-5 h-5 text-emerald-600"></i>
                                    </button>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conversation Section (Desktop) -->
                <div class="lg:flex-1 hidden lg:flex flex-col h-full">
                    <!-- Chat Header -->
                    <div class="p-3 border-b w-full hidden lg:flex justify-between items-center gap-4"
                        x-show="selectedUserId">
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
                        <span class="text-sm text-gray-400" x-text="selectedUserType"></span>

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
                                        @keyup.enter="sendMessage"
                                        class="w-full focus:outline-none focus:ring-0 focus:border-transparent">
                                    <button @click="sendMessage">
                                        <i data-lucide="send-horizontal" class="w-5 h-5 text-emerald-600"></i>
                                    </button>
                                </label>
                            </div>
                        </div>
                    </div>


                </div>
            </div>

        </div>
    </main>

    <!-- Pusher JS (for realtime chat) -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    <script>
        function chatApp() {
            return {
                pusher: null,
                currentUserId: <?= json_encode($_SESSION['user_id']); ?>,
                showMobileChat: false,
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
                selectedUserType: '',
                searchQuery: '',

                init() {
                    this.initPusher();
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

                initPusher() {
                    const userId = this.currentUserId;

                    // Optional: enable Pusher logging in dev
                    // Pusher.logToConsole = true;

                    this.pusher = new Pusher('4fc0a68218e1989eb11f', {
                        cluster: 'ap1',
                        forceTLS: true
                    });

                    const channel = this.pusher.subscribe(`chat_${userId}`);
                    channel.bind('new_message', (data) => {
                        // data is already an object from Pusher
                        if (this.selectedUserId == data.sender_id || this.selectedUserId == data.receiver_id) {
                            this.messages.push({
                                id: data.id || Date.now(),
                                text: data.message,
                                sender: data.sender_id == userId ? 'me' : 'them'
                            });
                            this.$nextTick(() => {
                                const desktopContainer = document.querySelector('.space-y-4.overflow-y-auto');
                                if (desktopContainer) desktopContainer.scrollTop = desktopContainer.scrollHeight;

                                if (this.$refs.mobileMessages) {
                                    this.$refs.mobileMessages.scrollTop = this.$refs.mobileMessages.scrollHeight;
                                }
                            });
                        }

                        // Increment unread count for messages from others if that chat isn't open
                        if (data.sender_id && data.sender_id != userId && this.selectedUserId != data.sender_id) {
                            const fromId = data.sender_id;
                            this.unreadCounts[fromId] = (this.unreadCounts[fromId] || 0) + 1;
                            this.latestPreview[fromId] = data.message;
                            this.latestTime[fromId] = new Date().toLocaleString();
                            this.bumpDmToTop(fromId);
                        }

                        const otherId = data.sender_id == userId ? data.receiver_id : data.sender_id;
                        this.latestPreview[otherId] = (data.sender_id == userId) ? `You: ${data.message}` : data.message;
                        this.latestTime[otherId] = new Date().toLocaleString();
                        this.bumpDmToTop(otherId);
                    });
                },

                filterUser(userName) {
                    if (!this.searchQuery.trim()) {
                        return true;
                    }
                    return userName.includes(this.searchQuery.toLowerCase());
                },

                selectUser(id, name, googleId, userType) {
                    this.selectedUserId = id;
                    this.selectedUserName = name;
                    this.selectedUserType = userType;
                    if (userType) {
                        // Replace camelCase with spaced words and capitalize each
                        const formatted = userType
                            .replace(/([A-Z])/g, ' $1') // add space before capitals
                            .replace(/^./, str => str.toUpperCase()) // capitalize first letter
                            .trim();
                        this.selectedUserType = formatted;
                    } else {
                        this.selectedUserType = '';
                    }
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
                                const desktopContainer = document.querySelector('.space-y-4.overflow-y-auto');
                                if (desktopContainer) desktopContainer.scrollTop = desktopContainer.scrollHeight;

                                // Scroll mobile chat
                                if (this.$refs.mobileMessages) {
                                    this.$refs.mobileMessages.scrollTop = this.$refs.mobileMessages.scrollHeight;
                                }
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
                        sender_id: this.currentUserId,
                        receiver_id: this.selectedUserId,
                        message: msg
                    };

                    fetch('../includes/pusher.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(res => res.json())
                        .then(data => {
                            // Immediately show our own message in the thread
                            this.messages.push({
                                id: data.id || Date.now(),
                                text: data.message,
                                sender: 'me'
                            });
                            this.$nextTick(() => {
                                const desktopContainer = document.querySelector('.space-y-4.overflow-y-auto');
                                if (desktopContainer) desktopContainer.scrollTop = desktopContainer.scrollHeight;

                                if (this.$refs.mobileMessages) {
                                    this.$refs.mobileMessages.scrollTop = this.$refs.mobileMessages.scrollHeight;
                                }
                            });
                        })
                        .catch(console.error);

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