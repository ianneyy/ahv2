<?php
require_once '../includes/session.php';
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessPartner') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$limit = 2;
$partnerId = $_SESSION['user_id'];
$approvedId = $_GET['approvedid'] ?? 0;

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0
    ? (int) $_GET['page']
    : 1;

$offset = ($page - 1) * $limit;

// Get total count for pagination
$countStmt = $conn->prepare("SELECT COUNT(*) AS total 
                             FROM crop_bids 
                             WHERE approvedid = ?");

$countStmt->bind_param("i", $approvedId);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

$totalPages = ceil($totalRows / $limit);

$response = [
    'highest_bid' => null,
    'history_html' => '',
    'is_highest' => false,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_bids' => $totalRows
    ]
];

// Get highest bid
$stmt = $conn->prepare("SELECT cb.*, u.name 
                        FROM crop_bids cb 
                        JOIN users u ON cb.bpartnerid = u.id 
                        WHERE cb.approvedid = ? 
                        ORDER BY cb.bidamount DESC, cb.bidad ASC LIMIT 1");
$stmt->bind_param("i", $approvedId);
$stmt->execute();
$highest = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($highest) {
    $response['highest_bid'] = [
        'amount' => $highest['bidamount'],
        'name' => $highest['name'],
        'time' => date("M j, g:i A", strtotime($highest['bidad']))
    ];
    $response['is_highest'] = $highest['bpartnerid'] == $partnerId;
}

// Get bid history (with pagination)
$stmt = $conn->prepare("SELECT cb.*, u.name 
                        FROM crop_bids cb 
                        JOIN users u ON cb.bpartnerid = u.id 
                        WHERE cb.approvedid = ? 
                        ORDER BY cb.bidamount DESC, cb.bidad ASC
                        LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $approvedId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$historyHTML = '<ul class="list-group list-group-flush">';
$first = $offset === 0;

while ($bid = $result->fetch_assoc()) {
    $class = $first ? 'bg-[#ECF5E9] border border-emerald-900/20' : '';

    $historyHTML .= "
        <div class='list-group-item $class text-slate-600 px-3 py-2 rounded-md'>
            <div class='flex items-center justify-between'>
                <div class='flex flex-col'>
                    <div class=' font-semibold text-slate-700'>" . htmlspecialchars($bid['name']) . "</div>
                    <div>" . date("M j, g:i A", strtotime($bid['bidad'])) . "</div>
                </div>
                <div class='text-lg font-bold text-slate-900'> ₱" . number_format($bid['bidamount'], 2) . "</div>
            </div>
        </div>
    ";

    $first = false;
}

if ($result->num_rows === 0) {
    $historyHTML .= "<li class='list-group-item text-muted'>No bids yet.</li>";
}

$historyHTML .= "</ul>";
$stmt->close();

// Include pagination info in response
$response['history_html'] = $historyHTML;

echo json_encode($response);
$conn->close();
