<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';
// require_once '../includes/notification_ui.php';


// 🔐 Check login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
  header("Location: ../auth/login.php");
  exit();
}


///

// ====== Part 1: Backend handlers (place after your includes & auth check, before any HTML) ======

// Simple flash messages to show in the UI (Part 2 will read these)
$flash = [
    'success' => null,
    'error' => null,
];

// Allowed values (whitelist)
$allowed_crops = ['buko', 'saba', 'rambutan', 'lanzones'];
$allowed_units = ['pcs', 'kg'];

/*
 * 1) SYNC from approved_submissions
 * Trigger: GET ?action=sync
 */
if (isset($_GET['action']) && $_GET['action'] === 'sync') {
    // Insert only new approved submissions that haven't been referenced yet
    $sync_sql = "
        INSERT INTO yield_records (crop_type, quantity, unit, source, reference_id, recorded_at)
        SELECT 
            croptype,
            quantity,
            unit,
            'system',
            approvedid,
            DATE(submittedat)
        FROM approved_submissions
        WHERE approvedid NOT IN (
            SELECT COALESCE(reference_id, 0) FROM yield_records WHERE source = 'system'
        )
    ";
    if ($conn->query($sync_sql) === TRUE) {
        $affected = $conn->affected_rows;
        $flash['success'] = "Sync complete. {$affected} new record(s) imported from approved_submissions.";
    } else {
        $flash['error'] = "Sync failed: " . $conn->error;
    }

    // After sync redirect to clean the URL and avoid duplicate actions on refresh
    header("Location: forecasting.php");
    exit();
}

/*
 * 2) ADD manual record
 * Trigger: POST with action=add_manual_record
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_manual_record') {
    // Simple raw handling with minimal validation
    $crop_type  = isset($_POST['crop_type']) ? trim($_POST['crop_type']) : '';
    $quantity   = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
    $unit       = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $recorded_at= isset($_POST['recorded_at']) ? trim($_POST['recorded_at']) : '';

        // Year validation (2016 to current)
    $year = (int)substr($recorded_at, 0, 4);
    if ($year < 2016 || $year > (int)date('Y')) {
        $flash['error'] = "Date must be between 2016 and the current year.";
        header("Location: forecasting.php");
        exit();
    }

    // Basic validation
    if (!in_array($crop_type, $allowed_crops)) {
        $flash['error'] = "Invalid crop type.";
    } elseif (!in_array($unit, $allowed_units)) {
        $flash['error'] = "Invalid unit.";
    } elseif ($quantity <= 0) {
        $flash['error'] = "Quantity must be greater than 0.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $recorded_at)) {
        $flash['error'] = "Invalid date format. Use YYYY-MM-DD.";
    } else {
        // Safe-ish insert using the values (kept simple per request)
        $sql = "
            INSERT INTO yield_records (crop_type, quantity, unit, source, recorded_at)
            VALUES ('{$crop_type}', {$quantity}, '{$unit}', 'manual', '{$recorded_at}')
        ";
        if ($conn->query($sql) === TRUE) {
            $flash['success'] = "Manual record added successfully.";
        } else {
            $flash['error'] = "Failed to add manual record: " . $conn->error;
        }
    }

    // After processing redirect back to avoid duplicate form submission
    header("Location: forecasting.php");
    exit();
}

/*
 * 3) EDIT manual record
 * Trigger: POST with action=edit_manual_record
 * Note: Only records with source='manual' can be edited
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_manual_record') {
    $yield_id   = isset($_POST['yield_id']) ? intval($_POST['yield_id']) : 0;
    $crop_type  = isset($_POST['crop_type']) ? trim($_POST['crop_type']) : '';
    $quantity   = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
    $unit       = isset($_POST['unit']) ? trim($_POST['unit']) : '';
    $recorded_at= isset($_POST['recorded_at']) ? trim($_POST['recorded_at']) : '';

    //Year validation (2016 to current)
    $year = (int)substr($recorded_at, 0, 4);
    if ($year < 2016 || $year > (int)date('Y')) {
        $flash['error'] = "Date must be between 2016 and the current year.";
        header("Location: forecasting.php");
        exit();
    }

    // Basic validation
    if ($yield_id <= 0) {
        $flash['error'] = "Invalid record ID.";
    } elseif (!in_array($crop_type, $allowed_crops)) {
        $flash['error'] = "Invalid crop type.";
    } elseif (!in_array($unit, $allowed_units)) {
        $flash['error'] = "Invalid unit.";
    } elseif ($quantity <= 0) {
        $flash['error'] = "Quantity must be greater than 0.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $recorded_at)) {
        $flash['error'] = "Invalid date format. Use YYYY-MM-DD.";
    } else {
        // Ensure this is a manual record
        $check = $conn->query("SELECT source FROM yield_records WHERE yield_id = {$yield_id} LIMIT 1");
        if ($check && $row = $check->fetch_assoc()) {
            if ($row['source'] !== 'manual') {
                $flash['error'] = "Only manual records can be edited.";
            } else {
                $update_sql = "
                    UPDATE yield_records
                    SET crop_type='{$crop_type}', quantity={$quantity}, unit='{$unit}', recorded_at='{$recorded_at}'
                    WHERE yield_id = {$yield_id} LIMIT 1
                ";
                if ($conn->query($update_sql) === TRUE) {
                    $flash['success'] = "Record updated successfully.";
                } else {
                    $flash['error'] = "Failed to update record: " . $conn->error;
                }
            }
        } else {
            $flash['error'] = "Record not found.";
        }
    }

    header("Location: forecasting.php");
    exit();
}

/*
 * 4) DELETE manual record
 * Trigger: GET ?action=delete_manual_record&id=Y
 * Note: Only manual records can be deleted
 */
if (isset($_GET['action']) && $_GET['action'] === 'delete_manual_record' && isset($_GET['id'])) {
    $yield_id = intval($_GET['id']);
    if ($yield_id <= 0) {
        $flash['error'] = "Invalid record ID.";
    } else {
        // Ensure record is manual before deleting
        $check = $conn->query("SELECT source FROM yield_records WHERE yield_id = {$yield_id} LIMIT 1");
        if ($check && $row = $check->fetch_assoc()) {
            if ($row['source'] !== 'manual') {
                $flash['error'] = "Only manual records can be deleted.";
            } else {
                $delete_sql = "DELETE FROM yield_records WHERE yield_id = {$yield_id} LIMIT 1";
                if ($conn->query($delete_sql) === TRUE) {
                    $flash['success'] = "Record deleted successfully.";
                } else {
                    $flash['error'] = "Failed to delete record: " . $conn->error;
                }
            }
        } else {
            $flash['error'] = "Record not found.";
        }
    }

    header("Location: forecasting.php");
    exit();
}

/*
 * 5) Load yield_records for display
 * This result set will be consumed in Part 2 (HTML table)
 */

// Pagination logic
$limit = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Fetch records
$records_sql = "
    SELECT * FROM yield_records 
    ORDER BY recorded_at DESC, created_at DESC 
    LIMIT $limit OFFSET $offset";
$records_result = $conn->query($records_sql);

// Get total count for page numbers
$total_sql = "SELECT COUNT(*) as total FROM yield_records";
$total_rows = $conn->query($total_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);


// Expose $flash and $records_result to the HTML below
// (Part 2 will read $flash and loop through $records_result)

///

/* ===== Bulk Import Handler (insert after your other handlers in Part 1) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_bulk') {
    $flash = ['success' => null, 'error' => null];
    $bulk_json = isset($_POST['bulk_data']) ? trim($_POST['bulk_data']) : '';
    if (empty($bulk_json)) {
        $flash['error'] = "No bulk data received.";
    } else {
        $rows = json_decode($bulk_json, true);
        if (!is_array($rows)) {
            $flash['error'] = "Invalid bulk data format.";
        } else {
            $inserted = 0;
            $errors = [];
            $curY = (int)date('Y');

            foreach ($rows as $i => $r) {
                $crop = isset($r['crop']) ? trim($r['crop']) : '';
                $qty  = isset($r['quantity']) ? floatval($r['quantity']) : 0;
                $date = isset($r['recorded_at']) ? trim($r['recorded_at']) : '';

                // derive unit
                $unit = in_array($crop, ['buko','saba']) ? 'pcs' : 'kg';

                // validation
                $rowNum = $i + 1;
                if (!in_array($crop, ['buko','saba','rambutan','lanzones'])) {
                    $errors[] = "Row {$rowNum}: Invalid crop '{$crop}'.";
                    continue;
                }
                if ($qty <= 0) {
                    $errors[] = "Row {$rowNum}: Quantity must be > 0.";
                    continue;
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $errors[] = "Row {$rowNum}: Invalid date format.";
                    continue;
                }
                $year = (int)substr($date,0,4);
                if ($year < 2016 || $year > $curY) {
                    $errors[] = "Row {$rowNum}: Date year must be between 2016 and {$curY}.";
                    continue;
                }

                // insert (simple, consistent with other handlers)
                $crop_esc = $conn->real_escape_string($crop);
                $date_esc = $conn->real_escape_string($date);

                $sql = "
                  INSERT INTO yield_records (crop_type, quantity, unit, source, recorded_at)
                  VALUES ('{$crop_esc}', {$qty}, '{$unit}', 'manual', '{$date_esc}')
                ";
                if ($conn->query($sql) === TRUE) {
                    $inserted++;
                } else {
                    $errors[] = "Row {$rowNum}: DB error - " . $conn->error;
                }
            } // foreach

            if ($inserted > 0) $flash['success'] = "{$inserted} record(s) imported successfully.";
            if (!empty($errors)) $flash['error'] = implode(' ', $errors);
        }
    }

    // persist flash and redirect to avoid resubmission
    $_SESSION['bulk_flash'] = $flash;
    header("Location: forecasting.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecasting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    

<body class="bg-[#ECF5E9]">
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-[#ECF5E9] text-white hidden lg:flex flex-col sticky top-0 h-screen">
      <div class="p-4 text-xl font-bold  text-[#28453E]">
        AniHanda
      </div>
      <nav class="flex-1 p-4 space-y-4">
        <a href="dashboard.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] bg-[#BFF49B] text-[#28453E] flex items-center gap-3"> <i
            data-lucide="layout-dashboard" class="w-5 h-5"></i>
          <span>Dashboard</span></a>

          <a href="../partner/bid_crops.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3"> <i
            data-lucide="gavel" class="w-5 h-5"></i>
          <span>Bidding</span></a>
        <!-- Crops Dropdown -->
        <div>
          <button onclick="toggleDropdown('cropsDropdown', 'chevronIcon')"
            class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
            <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i> <span>Crops</span>
            </span> <i id="chevronIcon" data-lucide="chevron-down"
              class="w-5 h-5 transition-transform duration-300"></i> </button> <!-- Dropdown links -->
          <div id="cropsDropdown" class="hidden ml-5  border-l border-gray-300">
            <div class="ml-3 mt-2 space-y-2">

              <a href="verify_crops.php"
                class="block px-4 py-2 text-sm rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
                <span>Crop Submission</span>
              </a>
              <a href="verified_crops.php"
                class="block px-4 py-2 text-sm  rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-2">
                <span>Verified Crops</span>
              </a>
            </div>

          </div>
        </div>
        <a href="confirm_payments.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="credit-card" class="w-5 h-5"></i>
          <span>Payments</span></a>
        <a href="bid_cancellations.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="ban" class="w-5 h-5"></i>
          <span>Cancellations</span></a>
        <a href="forecasting.php"
          class="block px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="trending-up-down" class="w-5 h-5"></i>
          <span>Forecasting</span></a>
        <a onclick="logoutModal.showModal()"
          class="block px-4 py-2 rounded-lg cursor-pointer hover:bg-[#BFF49B] text-[#28453E] flex items-center gap-3">
          <i data-lucide="log-out" class="w-5 h-5"></i>
          <span>Logout</span>
        </a>

      </nav>
      <div class="p-4 border-t border-gray-300 text-sm text-gray-400">
        © 2025 AniHanda
      </div>
    </aside>



    <!-- Main content -->
    <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
      <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
        
      <div class="flex gap-2 mb-4"> <!-- Top 2 Buttons -->
        <button id="tabYieldBtn" class="px-4 py-2 bg-emerald-600 text-white rounded">📥 Data Input</button>
        <button id="tabForecastBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">📈 Forecast Results</button>
      </div>

      <div id="tab-yield">


<!-- ====== Part 2: HTML / UI (paste this INSIDE <main> right after the Welcome header) ====== -->

<!-- Flash messages -->
<?php if (!empty($flash['success']) || !empty($flash['error'])): ?>
  <div class="mb-4">
    <?php if (!empty($flash['success'])): ?>
      <div class="p-3 mb-2 rounded bg-emerald-100 text-emerald-800 border border-emerald-200">
        <?= htmlspecialchars($flash['success']) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($flash['error'])): ?>
      <div class="p-3 rounded bg-red-100 text-red-800 border border-red-200">
        <?= htmlspecialchars($flash['error']) ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Container for Data Preparation UI -->
<section class="bg-white p-6 rounded-lg shadow-sm">
  <div class="flex items-center justify-between mb-6">
    <h3 class="text-xl font-semibold text-emerald-700">📁 Historical Yield Records</h3>

    <div class="flex items-center gap-3">
  <!-- Sync button -->
  <a href="forecasting.php?action=sync"
     class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">
    🔄 Sync from AHV2
  </a>

  <!-- Add Manual toggle -->
  <button id="showAddFormBtn"
          class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-100 text-emerald-800 rounded hover:bg-emerald-200">
    ➕ Add Manual Record
  </button>

  <!-- Bulk Upload button (new) -->
  <button id="bulkUploadBtn"
          class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-100 text-emerald-800 rounded hover:bg-emerald-200">
    📥 Bulk Upload
  </button>
</div>

  </div>

  <!-- Manual Add Form (hidden by default) -->
  <div id="manualFormContainer" class="mb-6 p-4 border rounded bg-emerald-50 hidden">
    <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
      <input type="hidden" name="action" value="add_manual_record">

      <div>
        <label class="block text-sm font-medium text-gray-700">Crop Type</label>
        <select id="add_crop_type" name="crop_type" required
                class="mt-1 block w-full border rounded px-3 py-2">
          <option value="buko">Buko</option>
          <option value="saba">Saba</option>
          <option value="rambutan">Rambutan</option>
          <option value="lanzones">Lanzones</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Quantity</label>
        <input name="quantity" type="number" step="0.01" min="0" required
               class="mt-1 block w-full border rounded px-3 py-2" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Unit</label>
        <input id="add_unit" name="unit" type="text" readonly
         class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Date Recorded</label>
        <input 
          name="recorded_at" 
          type="date" 
          required 
          class="mt-1 block w-full border rounded px-3 py-2"
          min="2016-01-01"
          max="<?= date('Y-m-d') ?>"
          />
      </div>

      <div class="md:col-span-4 flex gap-2 mt-2">
        <button type="submit"
                class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">✅ Save</button>
        <button type="button" id="cancelAddForm"
                class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
      </div>

    </form>
  </div>

  <!-- Records table -->
  <div class="overflow-x-auto">
    <table class="w-full table-auto border-collapse">
      <thead class="bg-emerald-100 text-emerald-800">
        <tr>
          <th class="px-4 py-2 text-left">Crop</th>
          <th class="px-4 py-2 text-right">Quantity</th>
          <th class="px-4 py-2 text-left">Unit</th>
          <th class="px-4 py-2 text-left">Source</th>
          <th class="px-4 py-2 text-left">Date</th>
          <th class="px-4 py-2 text-left">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($records_result && $records_result->num_rows > 0): ?>
          <?php while ($r = $records_result->fetch_assoc()): ?>
            <tr class="border-b">
              <td class="px-4 py-2"><?= htmlspecialchars(ucfirst($r['crop_type'])) ?></td>
              <td class="px-4 py-2 text-right"><?= htmlspecialchars((string)$r['quantity']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['unit']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['source']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($r['recorded_at']) ?></td>
              <td class="px-4 py-2">
                <?php if ($r['source'] === 'manual'): ?>
                  <!-- Edit button triggers modal with data attributes -->
                  <button class="openEditModal inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-800 rounded"
                          data-yield_id="<?= (int)$r['yield_id'] ?>"
                          data-crop_type="<?= htmlspecialchars($r['crop_type']) ?>"
                          data-quantity="<?= htmlspecialchars((string)$r['quantity']) ?>"
                          data-unit="<?= htmlspecialchars($r['unit']) ?>"
                          data-recorded_at="<?= htmlspecialchars($r['recorded_at']) ?>">
                    ✏️ Edit
                  </button>

                  <!-- Delete -->
                  <a href="forecasting.php?action=delete_manual_record&id=<?= (int)$r['yield_id'] ?>"
                     onclick="return confirm('Are you sure you want to delete this manual record?');"
                     class="inline-flex items-center gap-2 px-3 py-1 bg-red-100 text-red-700 rounded ml-2">
                    🗑️ Delete
                  </a>
                <?php else: ?>
                  <span class="text-sm text-gray-500 italic">— system</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" class="px-4 py-6 text-center text-gray-500">No yield records found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
      <div class="mt-4 flex justify-center gap-2">
        <?php if ($page > 1): ?>
          <a href="forecasting.php?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-100 rounded">⬅ Prev</a>
          <?php endif; ?>
          <span class="px-3 py-1 bg-emerald-100 rounded"><?= $page ?> / <?= $total_pages ?></span>
          <?php if ($page < $total_pages): ?>
           <a href="forecasting.php?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-100 rounded">Next ➡</a>
          <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

</section>

<!-- ===== Edit Modal (overlay) ===== -->
<div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
  <div class="bg-white rounded-lg w-full max-w-2xl p-6 shadow-lg">
    <div class="flex items-center justify-between mb-4">
      <h4 class="text-lg font-semibold text-emerald-700">Edit Manual Record</h4>
      <button id="closeEditModal" class="text-gray-500 hover:text-gray-800">✖</button>
    </div>

    <form method="POST" id="editForm">
      <input type="hidden" name="action" value="edit_manual_record">
      <input type="hidden" name="yield_id" id="edit_yield_id" value="">

      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700">Crop Type</label>
          <select id="edit_crop_type" name="crop_type" required
                  class="mt-1 block w-full border rounded px-3 py-2">
            <option value="buko">Buko</option>
            <option value="saba">Saba</option>
            <option value="rambutan">Rambutan</option>
            <option value="lanzones">Lanzones</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Quantity</label>
          <input id="edit_quantity" name="quantity" type="number" step="0.01" min="0" required
                 class="mt-1 block w-full border rounded px-3 py-2" />
        </div>

<div>
  <label class="block text-sm font-medium text-gray-700">Unit</label>
  <input id="edit_unit" name="unit" type="text" readonly
         class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100" />
</div>


        <div>
          <label class="block text-sm font-medium text-gray-700">Date Recorded</label>
          <input 
  id="edit_recorded_at" 
  name="recorded_at" 
  type="date" 
  required 
  class="mt-1 block w-full border rounded px-3 py-2"
  min="2016-01-01"
  max="<?= date('Y-m-d') ?>"
/>

        </div>
      </div>

      <div class="mt-4 flex justify-end gap-2">
        <button type="button" id="cancelEditBtn" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">Save changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ===== Bulk Upload Modal (full-screen overlay, max-w-5xl) ===== -->
<div id="bulkModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
  <div class="bg-white rounded-lg w-full max-w-5xl p-6 shadow-lg">
    <div class="flex items-center justify-between mb-4">
      <h4 class="text-lg font-semibold text-emerald-700">Bulk Upload — Add Multiple Manual Records</h4>
      <button id="closeBulkModal" class="text-gray-500 hover:text-gray-800">✖</button>
    </div>

    <p class="text-sm text-gray-600 mb-4">Add multiple rows below. Units auto-fill and are read-only. Dates allowed: 2016 to today. Click <strong>Preview</strong> to validate before import.</p>

<!-- Dynamic rows table (Scrollable rows only) -->
<div class="overflow-x-auto mb-4">
  <table id="bulkTableModal" class="w-full table-auto border-collapse">
    <thead class="bg-emerald-100 text-emerald-800">
      <tr>
        <th class="px-3 py-2 text-left">#</th>
        <th class="px-3 py-2 text-left">Crop</th>
        <th class="px-3 py-2 text-right">Quantity</th>
        <th class="px-3 py-2 text-left">Unit</th>
        <th class="px-3 py-2 text-left">Date Recorded</th>
        <th class="px-3 py-2 text-left">Remove</th>
      </tr>
    </thead>
  </table>

  <!-- Scrollable row container -->
  <div class="max-h-[250px] overflow-y-auto">
    <table class="w-full table-auto border-collapse">
      <tbody id="bulkTbodyModal"></tbody>
    </table>
  </div>
</div>


    <div class="flex gap-2 mb-4">
      <button id="addRowModalBtn" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">+ Add Row</button>
      <button id="previewModalBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Preview</button>
      <button id="clearAllModalBtn" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Clear All</button>
    </div>

    <!-- Preview area (hidden initially) -->
    <div id="bulkPreviewSection" class="hidden">
      <h5 class="font-medium text-gray-700 mb-2">Preview</h5>
      <div id="bulkPreviewErrors" class="mb-3"></div>
      <div class="overflow-x-auto max-h-64 mb-4">
        <table id="bulkPreviewTable" class="w-full table-auto text-sm">
          <thead class="bg-emerald-100"><tr><th class="px-2 py-1">#</th><th class="px-2 py-1">Crop</th><th class="px-2 py-1 text-right">Qty</th><th class="px-2 py-1">Unit</th><th class="px-2 py-1">Date</th><th class="px-2 py-1">Status</th></tr></thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <div class="mt-4 flex justify-end gap-2">
      <button id="cancelBulkBtn" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>

      <!-- Confirm form posts to this same page -->
      <form method="POST" id="bulkConfirmForm" class="inline">
        <input type="hidden" name="action" value="confirm_bulk">
        <input type="hidden" name="bulk_data" id="bulk_data_input" value="">
        <button id="confirmBulkImportBtn" type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">Confirm & Import</button>
      </form>
    </div>
  </div>
</div>

<!-- ====== JavaScript: toggles, modal, populate edit data, auto-set unit ====== -->
<script>
  // Toggle Add Form
  const showAddBtn = document.getElementById('showAddFormBtn');
  const manualFormContainer = document.getElementById('manualFormContainer');
  const cancelAddBtn = document.getElementById('cancelAddForm');

  showAddBtn.addEventListener('click', () => {
    manualFormContainer.classList.toggle('hidden');
    window.scrollTo({ top: manualFormContainer.offsetTop - 80, behavior: 'smooth' });
  });
  cancelAddBtn.addEventListener('click', () => {
    manualFormContainer.classList.add('hidden');
  });

  // Auto-set unit when crop changes (Add Form)
  const addCrop = document.getElementById('add_crop_type');
  const addUnit = document.getElementById('add_unit');
  addCrop.addEventListener('change', () => {
  const c = addCrop.value;
  if (c === 'buko' || c === 'saba') addUnit.value = 'pcs';
  else addUnit.value = 'kg';
});

  // Initialize add form unit
  if (addCrop) {
    const ev = new Event('change');
    addCrop.dispatchEvent(ev);
  }

  // Edit modal logic
  const editModal = document.getElementById('editModal');
  const openEditButtons = document.querySelectorAll('.openEditModal');
  const closeEditModalBtn = document.getElementById('closeEditModal');
  const cancelEditBtn = document.getElementById('cancelEditBtn');

  openEditButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.dataset.yield_id;
      const crop = btn.dataset.crop_type;
      const qty = btn.dataset.quantity;
      const unit = btn.dataset.unit;
      const date = btn.dataset.recorded_at;

      document.getElementById('edit_yield_id').value = id;
      document.getElementById('edit_crop_type').value = crop;
      document.getElementById('edit_quantity').value = qty;
      document.getElementById('edit_unit').value = unit;
      document.getElementById('edit_recorded_at').value = date;

      editModal.classList.remove('hidden');
      editModal.classList.add('flex');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  function closeEdit() {
    editModal.classList.add('hidden');
    editModal.classList.remove('flex');
  }
  closeEditModalBtn.addEventListener('click', closeEdit);
  cancelEditBtn.addEventListener('click', closeEdit);

  // Auto-set unit in edit modal when crop changes
  const editCrop = document.getElementById('edit_crop_type');
  const editUnit = document.getElementById('edit_unit');
  editCrop.addEventListener('change', () => {
  const c = editCrop.value;
  if (c === 'buko' || c === 'saba') editUnit.value = 'pcs';
  else editUnit.value = 'kg';
});

/* ===== Bulk Upload modal JS ===== */
(function(){
  const bulkBtn = document.getElementById('bulkUploadBtn');
  const bulkModal = document.getElementById('bulkModal');
  const closeBulk = document.getElementById('closeBulkModal');
  const cancelBulk = document.getElementById('cancelBulkBtn');

  const addRowBtn = document.getElementById('addRowModalBtn');
  const previewBtn = document.getElementById('previewModalBtn');
  const clearBtn = document.getElementById('clearAllModalBtn');

  const tbody = document.getElementById('bulkTbodyModal');
  const previewSection = document.getElementById('bulkPreviewSection');
  const previewTableBody = document.querySelector('#bulkPreviewTable tbody');
  const previewErrors = document.getElementById('bulkPreviewErrors');
  const bulkDataInput = document.getElementById('bulk_data_input');

  const minDate = '2016-01-01';
  const maxDate = (new Date()).toISOString().slice(0,10);
  const allowedCrops = ['buko','saba','rambutan','lanzones'];

  function openModal(){
    bulkModal.classList.remove('hidden');
    bulkModal.classList.add('flex');
    // ensure one row exists
    if (tbody.children.length === 0) createRow();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  function closeModal(){
    bulkModal.classList.add('hidden');
    bulkModal.classList.remove('flex');
    previewSection.classList.add('hidden');
    previewTableBody.innerHTML = '';
    previewErrors.innerHTML = '';
    bulkDataInput.value = '';
  }

  bulkBtn.addEventListener('click', openModal);
  closeBulk.addEventListener('click', closeModal);
  cancelBulk.addEventListener('click', closeModal);

  // create dynamic row
  let counter = 0;
  function createRow(data = {}){
    counter++;
    const tr = document.createElement('tr');
    tr.dataset.row = counter;

    const cropVal = data.crop || 'buko';
    const qtyVal = (data.quantity !== undefined) ? data.quantity : '';
    const dateVal = data.recorded_at || '';

    tr.innerHTML = `
      <td class="px-2 py-2 text-sm">${counter}</td>
      <td class="px-2 py-2">
        <select class="crop_sel border rounded px-2 py-1 text-sm" required>
          <option value="buko">Buko</option>
          <option value="saba">Saba</option>
          <option value="rambutan">Rambutan</option>
          <option value="lanzones">Lanzones</option>
        </select>
      </td>
      <td class="px-2 py-2 text-right">
        <input type="number" step="0.01" min="0" class="qty_input border rounded px-2 py-1 text-sm w-28" value="${qtyVal}" />
      </td>
      <td class="px-2 py-2">
        <input type="text" class="unit_input border rounded px-2 py-1 text-sm bg-gray-100" readonly />
      </td>
      <td class="px-2 py-2">
        <input type="date" class="date_input border rounded px-2 py-1 text-sm" min="${minDate}" max="${maxDate}" value="${dateVal}" />
      </td>
      <td class="px-2 py-2">
        <button class="remove_row_btn px-2 py-1 bg-red-100 text-red-700 rounded text-sm">Remove</button>
      </td>
    `;

    const cropSel = tr.querySelector('.crop_sel');
    cropSel.value = cropVal;
    const unitIn = tr.querySelector('.unit_input');
    unitIn.value = (cropVal === 'buko' || cropVal === 'saba') ? 'pcs' : 'kg';
    const dateIn = tr.querySelector('.date_input');
    dateIn.setAttribute('max', maxDate);
    dateIn.setAttribute('min', minDate);

    cropSel.addEventListener('change', ()=> {
      unitIn.value = (cropSel.value === 'buko' || cropSel.value === 'saba') ? 'pcs' : 'kg';
    });

    tr.querySelector('.remove_row_btn').addEventListener('click', (e)=>{
      e.preventDefault();
      tr.remove();
      refreshNumbers();
    });

    tbody.appendChild(tr);
    refreshNumbers();
    return tr;
  }

  function refreshNumbers(){
    const rows = tbody.querySelectorAll('tr');
    let i = 1;
    rows.forEach(r => {
      r.querySelector('td').textContent = i;
      i++;
    });
    counter = rows.length;
  }

  addRowBtn.addEventListener('click', (e) => { e.preventDefault(); createRow(); });

  clearBtn.addEventListener('click', (e)=> { e.preventDefault(); tbody.innerHTML=''; counter=0; });

  // Preview: validate client-side and show results in preview table
// Preview: validate client-side and group errors
previewBtn.addEventListener('click', (e) => {
  e.preventDefault();
  const rows = [];
  const errorGroups = {}; // { 'Missing quantity': [1,4,5], 'Invalid date': [2], ... }
  const trs = tbody.querySelectorAll('tr');
  const curYear = new Date().getFullYear();

  trs.forEach((tr, idx) => {
    const rn = idx + 1;
    const crop = tr.querySelector('.crop_sel').value;
    const qty = tr.querySelector('.qty_input').value;
    const unit = tr.querySelector('.unit_input').value;
    const date = tr.querySelector('.date_input').value;

    // Helper to record grouped errors
    function addError(type) {
      if (!errorGroups[type]) errorGroups[type] = [];
      errorGroups[type].push(rn);
    }

    if (!allowedCrops.includes(crop)) addError('Invalid crop');
    if (Number(qty) <= 0 || qty === '') addError('Missing or invalid quantity');
    if (!date.match(/^\d{4}-\d{2}-\d{2}$/)) {
      addError('Missing or invalid date');
    } else {
      const year = parseInt(date.slice(0,4),10);
      if (year < 2016 || year > curYear) addError(`Date must be between 2016 and ${curYear}`);
    }

    rows.push({ crop, quantity: parseFloat(qty || 0), unit, recorded_at: date });
  });

  previewTableBody.innerHTML = '';
  previewErrors.innerHTML = '';

  const hasErrors = Object.keys(errorGroups).length > 0;

  if (hasErrors) {
    let errorHTML = `<div class="p-3 rounded bg-red-50 text-red-700 border border-red-100 mb-3"><strong>Fix the following issues:</strong><ul class="mt-2 list-disc pl-5">`;
    
    Object.entries(errorGroups).forEach(([type, rowNumbers]) => {
      const shown = rowNumbers.slice(0,5).join(', ');
      const extra = rowNumbers.length > 5 ? `... + ${rowNumbers.length - 5} more` : '';
      errorHTML += `<li>${type} in rows ${shown}${extra}</li>`;
    });

    errorHTML += `</ul></div>`;
    previewErrors.innerHTML = errorHTML;
    // Make errors dismissible by adding close button
previewErrors.querySelector('div').insertAdjacentHTML(
  'afterbegin',
  `<button id="dismissErrorBtn" class="float-right text-red-500 hover:text-red-700">✖</button>`
);

document.getElementById('dismissErrorBtn').addEventListener('click', () => {
  previewErrors.innerHTML = '';
});

// Auto-clear errors when user edits any field
tbody.addEventListener('input', () => {
  previewErrors.innerHTML = '';
});

previewSection.classList.remove('hidden');

// If errors exist, fully hide the preview table rows
if (hasErrors) {
  previewTableBody.innerHTML = ""; // Clear table rows
  previewTableBody.parentElement.parentElement.classList.add("hidden"); // Hide entire preview table container
}
  } else {
    rows.forEach((r,i)=> {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td class="px-2 py-1">${i+1}</td><td class="px-2 py-1">${r.crop}</td><td class="px-2 py-1 text-right">${r.quantity}</td><td class="px-2 py-1">${r.unit}</td><td class="px-2 py-1">${r.recorded_at}</td><td class="px-2 py-1 text-green-700">OK</td>`;
      previewTableBody.appendChild(tr);
    });
    bulkDataInput.value = JSON.stringify(rows);
previewSection.classList.remove('hidden');

// If errors exist, fully hide the preview table rows
if (hasErrors) {
  previewTableBody.innerHTML = ""; // Clear table rows
  previewTableBody.parentElement.parentElement.classList.add("hidden"); // Hide entire preview table container
}
  }

  window.scrollTo({ top: 0, behavior: 'smooth' });
});

  // ensure the confirm form will have data
  const confirmForm = document.getElementById('bulkConfirmForm');
  confirmForm.addEventListener('submit', (e) => {
    if (!bulkDataInput.value) {
      e.preventDefault();
      alert('No valid data to import. Please preview and fix errors first.');
    }
  });

})(); // IIFE end

</script>


<!--rawr-->

      </div> <!-- end of tab-yield -->

      <!--FOR FORECASTING MISMO below-->
      <div id="tab-forecast" class="hidden"><!--hidden dapat to-->
        <!-- Forecast results will go here in Step 2 -->
        <p class="text-gray-500 text-center py-10">No forecast generated yet. Click "Forecast Results" later to view.</p>
      </div>


<script>
  const tabYieldBtn = document.getElementById('tabYieldBtn');
  const tabForecastBtn = document.getElementById('tabForecastBtn');
  const tabYield = document.getElementById('tab-yield');
  const tabForecast = document.getElementById('tab-forecast');

  tabYieldBtn.addEventListener('click', () => {
    tabYield.classList.remove('hidden');
    tabForecast.classList.add('hidden');
    tabYieldBtn.classList.add('bg-emerald-600','text-white');
    tabYieldBtn.classList.remove('bg-gray-200','text-gray-800');
    tabForecastBtn.classList.remove('bg-emerald-600','text-white');
    tabForecastBtn.classList.add('bg-gray-200','text-gray-800');
  });

  tabForecastBtn.addEventListener('click', () => {
    tabForecast.classList.remove('hidden');
    tabYield.classList.add('hidden');
    tabForecastBtn.classList.add('bg-emerald-600','text-white');
    tabForecastBtn.classList.remove('bg-gray-200','text-gray-800');
    tabYieldBtn.classList.remove('bg-emerald-600','text-white');
    tabYieldBtn.classList.add('bg-gray-200','text-gray-800');
  });
</script>

      
      <div class="flex items-center justify-center">
<!--WELCOME [USER] chuchuchu-->
          <div id="bar" class="flex w-full justify-between items-center  mb-10  rounded-full">
            <h2 class="text-2xl lg:text-4xl font-semibold text-emerald-800">Welcome,
              <?= ucfirst(htmlspecialchars($_SESSION["user_name"])) ?>!
            </h2>

<!--rawr-->










            <div class="flex items-center gap-5">

              <div class="relative">
                <div
                  class="rounded-full p-2 flex items-center justify-center hover:bg-emerald-900 hover:text-white transition duration-300 ease-in-out">
                  <?php include '../includes/notification_ui.php'; ?>
                </div>
              </div>

              <!-- Small screen -->
              <div class="block lg:hidden">
                <div class="drawer">
                  <input id="my-drawer" type="checkbox" class="drawer-toggle" />
                  <div class="drawer-content">
                    <!-- Page content here -->
                    <label for="my-drawer" class=" drawer-button"><i data-lucide="menu" class="w-5 h-5"></i></label>

                  </div>
                  <div class="drawer-side ">
                    <label for="my-drawer" aria-label="close sidebar" class="drawer-overlay"></label>


                    <ul class="menu  bg-[#ECF5E9] text-base-content min-h-full w-80 p-4 gap-3">
                      <li>
                        <div class="p-4 text-xl font-bold  text-[#28453E]">
                          AniHanda
                        </div>
                      </li>
                      <!-- Sidebar content here -->
                      <li><a href="dashboard.php" class="flex items-center gap-3 active:bg-[#BFF49B]  text-[#28453E]">
                          <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                          <span>Dashboard</span>
                        </a></li>
                      <hr class="border-gray-300">

                      <div>
                        <button onclick="toggleDropdownSmall('cropsDropdownSmall', 'chevronIconSmall')"
                          class=" w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-[#BFF49B] text-[#28453E]">
                          <span class="flex items-center gap-3"> <i data-lucide="wheat" class="w-5 h-5"></i>
                            <span>Crops</span>
                          </span> <i id="chevronIconSmall" data-lucide="chevron-down"
                            class="w-5 h-5 transition-transform duration-300"></i>
                        </button> <!-- Dropdown links -->
                        <div id="cropsDropdownSmall" class="hidden ml-5  border-l border-gray-300">
                          <div class="ml-3 mt-2 space-y-2">

                            <a href="verify_crops.php"
                              class="block px-4 py-2 text-sm rounded-lg active:bg-[#BFF49B]  text-[#28453E]  flex items-center gap-2">
                              <span>Crop Submission</span>
                            </a>
                            <a href="verified_crops.php"
                              class="block px-4 py-2 text-sm  rounded-lg active:bg-[#BFF49B]  text-[#28453E]  flex items-center gap-2">
                              <span>Verified Crops</span>
                            </a>
                          </div>

                        </div>
                      </div>



                      <hr class="border-gray-300">

                      <li><a href="confirm_payments.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                          <i data-lucide="credit-card" class="w-5 h-5"></i>
                          <span>Payments</span>
                        </a></li>
                      <hr class="border-gray-300">

                      <li><a href="bid_cancellations.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                          <i data-lucide="ban" class="w-5 h-5"></i>
                          <span>Cancellations</span>
                        </a></li>
                      <hr class="border-gray-300">

                      <li><a href="forecasting.php" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                          <i data-lucide="trending-up-down" class="w-5 h-5"></i>
                          <span>Forecasting</span>
                        </a></li>
                      <hr class="border-gray-300">

                      <li><a onclick="logoutModal.showModal()" class="flex active:bg-[#BFF49B] items-center gap-3 text-[#28453E]">
                          <i data-lucide="log-out" class="w-5 h-5"></i>
                          <span>Logout</span>
                        </a></li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>


<script src="https://unpkg.com/lucide@latest"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <script>
      lucide.createIcons();
      // anime({
      //   targets: '#bar',
      //   width: ['60%', '100%'],
      //   duration: 1500,
      //   easing: 'easeInExpo'
      // });
    </script>
</body>
</html>

