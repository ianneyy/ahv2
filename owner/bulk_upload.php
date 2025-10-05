<?php
// owner/bulk_upload.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';

// auth guard (owner only)
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'businessOwner') {
  header("Location: ../auth/login.php");
  exit();
}

// Simple flash holder (will show in HTML)
$flash = ['success' => null, 'error' => null];

// Whitelist
$allowed_crops = ['buko', 'saba', 'rambutan', 'lanzones'];
$allowed_units = ['pcs', 'kg'];

// Handler: Confirm bulk import (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_bulk') {
    // Expecting JSON payload in hidden input 'bulk_data'
    $bulk_json = isset($_POST['bulk_data']) ? trim($_POST['bulk_data']) : '';
    if (empty($bulk_json)) {
        $flash['error'] = "No bulk data received.";
    } else {
        $rows = json_decode($bulk_json, true);
        if (!is_array($rows)) {
            $flash['error'] = "Invalid data format.";
        } else {
            $inserted = 0;
            $errors = [];
            foreach ($rows as $i => $r) {
                // Basic sanitization / casting
                $crop = isset($r['crop']) ? trim($r['crop']) : '';
                $qty  = isset($r['quantity']) ? floatval($r['quantity']) : 0;
                $date = isset($r['recorded_at']) ? trim($r['recorded_at']) : '';

                // derive unit from crop
                $unit = in_array($crop, ['buko','saba']) ? 'pcs' : 'kg';

                // validate
                if (!in_array($crop, $allowed_crops)) {
                    $errors[] = "Row " . ($i+1) . ": Invalid crop '{$crop}'.";
                    continue;
                }
                if ($qty <= 0) {
                    $errors[] = "Row " . ($i+1) . ": Quantity must be > 0.";
                    continue;
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $errors[] = "Row " . ($i+1) . ": Invalid date format.";
                    continue;
                }
                $year = (int)substr($date,0,4);
                $curY = (int)date('Y');
                if ($year < 2016 || $year > $curY) {
                    $errors[] = "Row " . ($i+1) . ": Date year must be between 2016 and {$curY}.";
                    continue;
                }

                // Insert row as manual
                // Note: kept simple like your other handlers. Escape crop and date strings.
                $crop_esc = $conn->real_escape_string($crop);
                $date_esc = $conn->real_escape_string($date);

                $sql = "
                  INSERT INTO yield_records (crop_type, quantity, unit, source, recorded_at)
                  VALUES ('{$crop_esc}', {$qty}, '{$unit}', 'manual', '{$date_esc}')
                ";
                if ($conn->query($sql) === TRUE) {
                    $inserted++;
                } else {
                    $errors[] = "Row " . ($i+1) . ": DB error - " . $conn->error;
                }
            } // foreach rows

            if ($inserted > 0) {
                $flash['success'] = "{$inserted} record(s) imported successfully.";
            }
            if (!empty($errors)) {
                // join errors to flash (you may also log)
                $flash['error'] = implode(' ', $errors);
            }
        }
    }

    // redirect to avoid resubmission
    // store flash in session for display after redirect
    $_SESSION['bulk_flash'] = $flash;
    header("Location: bulk_upload.php");
    exit();
}

// On page load, show any flash stored in session
if (isset($_SESSION['bulk_flash'])) {
    $flash = $_SESSION['bulk_flash'];
    unset($_SESSION['bulk_flash']);
}

// For convenience show a small history preview (latest 10 manual rows)
$preview_sql = "SELECT * FROM yield_records WHERE source = 'manual' ORDER BY recorded_at DESC, created_at DESC LIMIT 10";
$preview_rs = $conn->query($preview_sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Bulk Upload — AniHanda</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="../assets/style.css" />
</head>
<body class="bg-[#ECF5E9]">
  <?php include 'owner_nav_stub.php'; // OPTIONAL: if you have a header include; otherwise your existing owner layout can wrap this ?> 

  <main class="p-6 lg:max-w-6xl mx-auto">
    <h1 class="text-2xl font-semibold text-emerald-800 mb-4">Bulk Upload — Manual Yield Entries</h1>

    <!-- Flash -->
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

    <!-- Bulk Upload UI -->
    <section class="bg-white p-6 rounded shadow mb-6">
      <p class="text-sm text-gray-600 mb-4">
        Use the form below to add multiple manual yield records at once. Each row will be inserted with <code>source='manual'</code>.
      </p>

      <!-- Dynamic rows table -->
      <div class="overflow-x-auto">
        <table id="bulkTable" class="w-full table-auto border-collapse">
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
          <tbody id="bulkTbody">
            <!-- JS will inject rows here -->
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex gap-2">
        <button id="addRowBtn" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">+ Add Row</button>
        <button id="previewBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Preview</button>
        <button id="clearAllBtn" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Clear All</button>
      </div>

      <!-- Small preview of recent manual rows -->
      <div class="mt-6">
        <h4 class="font-medium text-gray-700 mb-2">Recent Manual Records (latest 10)</h4>
        <div class="overflow-x-auto bg-gray-50 p-3 rounded">
          <?php if ($preview_rs && $preview_rs->num_rows > 0): ?>
            <table class="w-full text-sm">
              <thead><tr><th class="px-2 py-1">Crop</th><th class="px-2 py-1 text-right">Qty</th><th class="px-2 py-1">Unit</th><th class="px-2 py-1">Date</th></tr></thead>
              <tbody>
                <?php while ($pr = $preview_rs->fetch_assoc()): ?>
                  <tr>
                    <td class="px-2 py-1"><?= htmlspecialchars(ucfirst($pr['crop_type'])) ?></td>
                    <td class="px-2 py-1 text-right"><?= htmlspecialchars((string)$pr['quantity']) ?></td>
                    <td class="px-2 py-1"><?= htmlspecialchars($pr['unit']) ?></td>
                    <td class="px-2 py-1"><?= htmlspecialchars($pr['recorded_at']) ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <div class="text-sm text-gray-500">No manual records yet.</div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Preview Modal -->
    <div id="previewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
      <div class="bg-white rounded-lg w-full max-w-4xl p-6 shadow-lg">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">Preview Bulk Import</h3>
          <button id="closePreview" class="text-gray-500 hover:text-gray-800">✖</button>
        </div>

        <div id="previewErrors" class="mb-3"></div>

        <div class="overflow-x-auto max-h-72">
          <table id="previewTable" class="w-full table-auto text-sm">
            <thead class="bg-emerald-100"><tr><th class="px-2 py-1">#</th><th class="px-2 py-1">Crop</th><th class="px-2 py-1 text-right">Qty</th><th class="px-2 py-1">Unit</th><th class="px-2 py-1">Date</th><th class="px-2 py-1">Status</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>

        <div class="mt-4 flex justify-end gap-2">
          <button id="cancelPreview" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200">Cancel</button>

          <!-- Confirm form: will include hidden JSON payload -->
          <form method="POST" id="confirmForm" class="inline">
            <input type="hidden" name="action" value="confirm_bulk">
            <input type="hidden" name="bulk_data" id="bulk_data" value="">
            <button id="confirmImportBtn" type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded hover:bg-emerald-700">Confirm & Import</button>
          </form>
        </div>
      </div>
    </div>

  </main>

  <script>
    // Constants
    const allowedCrops = ['buko', 'saba', 'rambutan', 'lanzones'];
    const minDate = '2016-01-01';
    const maxDate = (new Date()).toISOString().slice(0,10); // today
    let rowCount = 0;

    // Elements
    const bulkTbody = document.getElementById('bulkTbody');
    const addRowBtn = document.getElementById('addRowBtn');
    const previewBtn = document.getElementById('previewBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const previewModal = document.getElementById('previewModal');
    const previewTableBody = document.querySelector('#previewTable tbody');
    const previewErrors = document.getElementById('previewErrors');
    const closePreview = document.getElementById('closePreview');
    const cancelPreview = document.getElementById('cancelPreview');
    const bulkDataInput = document.getElementById('bulk_data');

    // Helper: create row
    function createRow(data = {}) {
      rowCount++;
      const tr = document.createElement('tr');
      tr.dataset.row = rowCount;

      const cropVal = data.crop || 'buko';
      const qtyVal = (data.quantity !== undefined) ? data.quantity : '';
      const dateVal = data.recorded_at || '';

      tr.innerHTML = `
        <td class="px-2 py-2 text-sm">${rowCount}</td>
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

      // set selected crop
      const cropSel = tr.querySelector('.crop_sel');
      cropSel.value = cropVal;

      // set unit based on crop
      const unitInput = tr.querySelector('.unit_input');
      unitInput.value = (cropVal === 'buko' || cropVal === 'saba') ? 'pcs' : 'kg';

      // if provided dateVal set else leave blank
      const dateInput = tr.querySelector('.date_input');
      dateInput.setAttribute('max', maxDate);
      dateInput.setAttribute('min', minDate);

      // event: on crop change auto-set unit
      cropSel.addEventListener('change', () => {
        const c = cropSel.value;
        unitInput.value = (c === 'buko' || c === 'saba') ? 'pcs' : 'kg';
      });

      // remove handler
      tr.querySelector('.remove_row_btn').addEventListener('click', (e) => {
        e.preventDefault();
        tr.remove();
        refreshRowNumbers();
      });

      bulkTbody.appendChild(tr);
      return tr;
    }

    function refreshRowNumbers() {
      const rows = bulkTbody.querySelectorAll('tr');
      let idx = 1;
      rows.forEach(r => {
        r.querySelector('td').textContent = idx;
        idx++;
      });
      rowCount = rows.length;
    }

    addRowBtn.addEventListener('click', (e) => {
      e.preventDefault();
      createRow();
      // scroll to last
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    });

    clearAllBtn.addEventListener('click', (e) => {
      e.preventDefault();
      bulkTbody.innerHTML = '';
      rowCount = 0;
    });

    // initial one row
    createRow();

    // Preview handler: validate client-side and show modal
    previewBtn.addEventListener('click', (e) => {
      e.preventDefault();
      // build rows data
      const rows = [];
      const errors = [];
      const trs = bulkTbody.querySelectorAll('tr');
      trs.forEach((tr, idx) => {
        const crop = tr.querySelector('.crop_sel').value;
        const qty = tr.querySelector('.qty_input').value;
        const unit = tr.querySelector('.unit_input').value;
        const date = tr.querySelector('.date_input').value;

        // validations
        const rowNum = idx + 1;
        if (!allowedCrops.includes(crop)) {
          errors.push(`Row ${rowNum}: Invalid crop`);
        }
        if (Number(qty) <= 0 || qty === '') {
          errors.push(`Row ${rowNum}: Quantity must be > 0`);
        }
        if (!date.match(/^\d{4}-\d{2}-\d{2}$/)) {
          errors.push(`Row ${rowNum}: Date is required`);
        } else {
          const year = parseInt(date.slice(0,4), 10);
          const cur = new Date().getFullYear();
          if (year < 2016 || year > cur) {
            errors.push(`Row ${rowNum}: Date year must be between 2016 and ${cur}`);
          }
        }

        rows.push({ crop, quantity: parseFloat(qty), unit, recorded_at: date });
      });

      // show preview
      previewTableBody.innerHTML = '';
      previewErrors.innerHTML = '';
      if (errors.length > 0) {
        previewErrors.innerHTML = `<div class="p-3 rounded bg-red-50 text-red-700 border border-red-100 mb-3"><strong>Fix these errors before importing:</strong><ul class="mt-2 list-disc pl-5">${errors.map(e => `<li>${e}</li>`).join('')}</ul></div>`;
        // also populate table with statuses
        rows.forEach((r, i) => {
          const tr = document.createElement('tr');
          const ok = !errors.some(err => err.startsWith(`Row ${i+1}:`));
          tr.innerHTML = `<td class="px-2 py-1">${i+1}</td><td class="px-2 py-1">${r.crop}</td><td class="px-2 py-1 text-right">${r.quantity}</td><td class="px-2 py-1">${r.unit}</td><td class="px-2 py-1">${r.recorded_at}</td><td class="px-2 py-1 ${ok ? 'text-green-700' : 'text-red-700'}">${ok ? 'OK' : 'Error'}</td>`;
          previewTableBody.appendChild(tr);
        });
      } else {
        // No errors -> preview table all OK
        rows.forEach((r, i) => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td class="px-2 py-1">${i+1}</td><td class="px-2 py-1">${r.crop}</td><td class="px-2 py-1 text-right">${r.quantity}</td><td class="px-2 py-1">${r.unit}</td><td class="px-2 py-1">${r.recorded_at}</td><td class="px-2 py-1 text-green-700">OK</td>`;
          previewTableBody.appendChild(tr);
        });

        // set hidden input for server submission
        bulkDataInput.value = JSON.stringify(rows);
      }

      // show modal
      previewModal.classList.remove('hidden');
      previewModal.classList.add('flex');
      // scroll to top so modal visible on small screens
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    // Close preview modal
    closePreview.addEventListener('click', () => {
      previewModal.classList.add('hidden');
      previewModal.classList.remove('flex');
      previewTableBody.innerHTML = '';
      previewErrors.innerHTML = '';
    });
    cancelPreview.addEventListener('click', () => {
      previewModal.classList.add('hidden');
      previewModal.classList.remove('flex');
      previewTableBody.innerHTML = '';
      previewErrors.innerHTML = '';
    });

    // Confirm import: make sure there is bulk_data
    const confirmForm = document.getElementById('confirmForm');
    confirmForm.addEventListener('submit', (e) => {
      if (!bulkDataInput.value) {
        e.preventDefault();
        alert('No valid data to import. Please fix errors in preview first.');
      } else {
        // allow normal submit to server
      }
    });
  </script>
</body>
</html>
