<?php
require_once '../../includes/db.php'; // Adjust path if needed

// 1️⃣ Generate CSV files
function generateCsv($conn, $cropType, $filePath) {
$sql = "SELECT DATE_FORMAT(recorded_at, '%Y-%m-01') AS date,
               SUM(quantity) AS total_quantity
        FROM yield_records
        WHERE crop_type = ?
          AND unit = 'pcs'
        GROUP BY DATE_FORMAT(recorded_at, '%Y-%m-01')
        ORDER BY date ASC";


    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $cropType);
    $stmt->execute();
    $result = $stmt->get_result();

    $file = fopen($filePath, "w");
    fputcsv($file, ["date", "total_quantity"]); // header

    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
    }

    fclose($file);
}

$dataDir = __DIR__ . "/data/";
generateCsv($conn, "buko", $dataDir . "data_buko_monthly.csv");
generateCsv($conn, "saba", $dataDir . "data_saba_monthly.csv");

// 2️⃣ Run ARIMA forecasting for BUKO and SABA
$crops = ["buko", "saba"];
$output = [];
$return_code = 0;

foreach ($crops as $crop) {
    $cmd = "python " . __DIR__ . "/arima_forecasting.py --crop $crop --months 12 2>&1";
    exec($cmd, $output, $return_code);
}

// 3️⃣ Show output for debugging
echo "<pre>";
print_r($output);
echo "</pre>";
echo "Return Code: " . $return_code;

exit;

?>
