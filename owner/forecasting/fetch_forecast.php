<?php

header('Content-Type: application/json');
require_once '../../includes/db.php';

$crop = $_GET['crop'] ?? '';
if (!$crop) {
    echo json_encode(["status" => "error", "message" => "Crop not specified"]);
    exit;
}

// Fetch forecasts for this crop + PY_ARIMA
$stmt = $conn->prepare("
    SELECT forecast_date, forecast_value
    FROM forecast_results
    WHERE crop_type=? AND model_type='PY_ARIMA'
    ORDER BY forecast_date ASC
");
$stmt->bind_param("s", $crop);
$stmt->execute();
$result = $stmt->get_result();

$forecasts = [];
while ($row = $result->fetch_assoc()) {
    $forecasts[] = [
        "date" => $row['forecast_date'],
        "value" => (float)$row['forecast_value']
    ];
}

$stmt->close();

// Return JSON
echo json_encode([
    "status" => "success",
    "crop" => $crop,
    "forecasts" => $forecasts
]);
exit;

