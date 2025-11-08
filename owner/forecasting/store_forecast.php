<?php
header('Content-Type: application/json');

// Include DB connection (adjust path if needed)
require_once __DIR__ . '/../../includes/db.php';

// Check DB connection
if (!isset($conn)) {
    echo json_encode(['status'=>'error','message'=>'Database connection failed']);
    exit;
}

// Get crop from POST or GET
$crop = $_POST['crop'] ?? $_GET['crop'] ?? '';
if (!$crop) {
    echo json_encode(['status'=>'error','message'=>'No crop specified']);
    exit;
}

// Run Python ARIMA script
$pythonScript = __DIR__ . '/arima_forecasting.py';
$cropArg = escapeshellarg($crop);
$cmd = "python \"$pythonScript\" --crop=$cropArg --months=12 2>&1"; // capture errors

$output = shell_exec($cmd);

// Decode Python JSON output
$result = json_decode($output, true);
if (!$result || !isset($result['forecasts'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Python forecast failed',
        'output' => $output
    ]);
    exit;
}

// Insert forecast into DB
$rowsInserted = 0;
$stmt = $conn->prepare(
    "INSERT INTO forecast_results (crop_type, forecast_date, forecast_value, model_type) VALUES (?, ?, ?, 'PY_ARIMA')"
);

foreach ($result['forecasts'] as $f) {
    $date = $f['date'];
    $value = $f['value'];

    $stmt->bind_param("ssd", $crop, $date, $value);
    if ($stmt->execute()) {
        $rowsInserted++;
    }
}

// Return JSON response
echo json_encode([
    'status' => 'success',
    'crop' => $crop,
    'rows_inserted' => $rowsInserted
]);
