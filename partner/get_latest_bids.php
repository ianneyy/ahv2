<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';





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

// Get highest eligible (non-blocklisted) bid
$stmt = $conn->prepare("SELECT cb.*, u.name 
                        FROM crop_bids cb 
                        JOIN users u ON cb.bpartnerid = u.id 
                        LEFT JOIN blocklist bl ON cb.bpartnerid = bl.userid AND cb.approvedid = bl.approvedid
                        WHERE cb.approvedid = ? AND bl.userid IS NULL
                        ORDER BY cb.bidamount DESC, cb.bidad ASC LIMIT 1");
$stmt->bind_param("i", $approvedId);
$stmt->execute();
$highest = $stmt->get_result()->fetch_assoc();


$stmt->close();




// Check if the approved submission is closed
$statusStmt = $conn->prepare("
    SELECT status, winner_id, croptype, farmerid
    FROM approved_submissions 
    WHERE approvedid = ?
");
$statusStmt->bind_param("i", $approvedId);
$statusStmt->execute();
$statusResult = $statusStmt->get_result()->fetch_assoc();
$statusStmt->close();


// If status is 'closed' and we have a highest bidder, update the winner_id
if (
    $statusResult &&
    strtolower($statusResult['status']) === 'closed' &&
    $statusResult['winner_id'] == 0 && // Only if no winner yet
    $highest
) {

    $sql = "
     SELECT DISTINCT cb.bpartnerid AS userid
        FROM crop_bids cb
        WHERE cb.approvedid = ?
         ";

    $stmt = $conn->prepare($sql);

    // Bind parameters dynamically
    $stmt->bind_param('i', $approvedId);
    $stmt->execute();
    $allBidders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


    // Set winner
    $updateStmt = $conn->prepare("
        UPDATE approved_submissions
        SET winner_id = ?
        WHERE approvedid = ?
    ");


    $updateStmt->bind_param("ii", $highest['bpartnerid'], $approvedId);
    $updateStmt->execute();

    if ($updateStmt->affected_rows > 0) { // Only send notifications if update actually happened
        $cropType = $statusResult['croptype'] ?? 'NA';

        $message = "🏆 Congratulations! You won the bid for {$cropType} with a final bid of ₱"
            . number_format($highest['bidamount'], 2) . "!";


        notify($conn, $highest['bpartnerid'], 'businessPartner', $message);

        $allBiddersMessage = "📢 The bidding for {$cropType} has closed. "
            . "Winning bid: ₱" . number_format($highest['bidamount'], 2) . ".";

        // Notify the farmer who owns this approved submission
        if (!empty($statusResult['farmerid'])) {
            notify($conn, (int)$statusResult['farmerid'], 'farmer', $allBiddersMessage);
        }

        // Notify all business owners
        sendNotificationToUserType($conn, 'businessOwner', $allBiddersMessage);

        foreach ($allBidders as $bidder) {
            if ((int)$bidder['userid'] === (int)$highest['bpartnerid']) {
                continue;
            }
            notify($conn, $bidder['userid'], 'businessPartner', $allBiddersMessage);
        }
    }

    $updateStmt->close();
}





if ($highest) {
    $response['highest_bid'] = [
        'amount' => $highest['bidamount'],
        'name' => $highest['name'],
        'time' => date("M j, g:i A", strtotime($highest['bidad']))
    ];
    $response['is_highest'] = $highest['bpartnerid'] == $partnerId;
}

// Get bid history (with pagination)
$stmt = $conn->prepare("SELECT cb.*, u.name, 
                        CASE WHEN bl.userid IS NOT NULL THEN 1 ELSE 0 END as is_blocklisted
                        FROM crop_bids cb 
                        JOIN users u ON cb.bpartnerid = u.id 
                        LEFT JOIN blocklist bl ON cb.bpartnerid = bl.userid AND cb.approvedid = bl.approvedid
                        WHERE cb.approvedid = ? 
                        ORDER BY bl.userid IS NOT NULL ASC, cb.bidamount DESC, cb.bidad ASC
                        LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $approvedId, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();




// echo "<pre>";
// var_dump($result);
// echo "</pre>";
// die;
$historyHTML = '<ul class="list-group list-group-flush flex flex-col gap-2">';
$first = $offset === 0;
$bidCount = 0;

while ($bid = $result->fetch_assoc()) {
    $bidCount++;

    // Determine background color based on position and blocklist status
    if ($bid['is_blocklisted']) {
        $class = 'bg-red-100 border border-red-300'; // Red for blocklisted users
    } elseif ($bidCount === 1) {
        $class = 'bg-[#ECF5E9] border border-emerald-900/20'; // Green for first/highest bidder
    } else {
        $class = ''; // Default for other bidders
    }


    $historyHTML .= "
        <div class='list-group-item $class text-slate-600 px-3 py-2 rounded-md  gap-2'>
            <div class='flex items-center justify-between'>
                <div class='flex flex-col gap-2'>
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





// $stmt = $conn->prepare("
//     SELECT crop_bids.*, approved_submissions.*
//     FROM crop_bids
//     JOIN approved_submissions ON crop_bids.approvedid = approved_submissions.approvedid
// ");
// $stmt->execute();
// $res = $stmt->get_result();

// $data = $res->fetch_all(MYSQLI_ASSOC); // Get all rows as associative array

// echo "<pre>";
// var_dump($data); // Dumps the actual rows
// echo "</pre>";
// die;
// Include pagination info in response
$response['history_html'] = $historyHTML;

echo json_encode($response);
$conn->close();
