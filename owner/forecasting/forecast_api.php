<?php
header('Content-Type: application/json');
require_once '../../includes/db.php';

$crop = $_GET['crop'] ?? 'buko';
$view = $_GET['view'] ?? 'best'; // 'best' or 'all'

// Get last 12 months + next 12 months range
$start_date = date('Y-m-01', strtotime('-12 months'));
$end_date = date('Y-m-01', strtotime('+12 months'));

// Fetch actual historical data (last 12 months)
$actual_query = "
    SELECT DATE_FORMAT(recorded_at, '%Y-%m') as month, SUM(quantity) as total
    FROM yield_records
    WHERE crop_type = ?
    AND recorded_at >= ?
    AND recorded_at <= NOW()
    GROUP BY DATE_FORMAT(recorded_at, '%Y-%m')
    ORDER BY month
";
$stmt = $conn->prepare($actual_query);
$stmt->bind_param('ss', $crop, $start_date);
$stmt->execute();
$actual_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Determine best model
$best_model_query = "
    SELECT method
    FROM model_evaluation
    WHERE crop_type = ?
    GROUP BY method
    ORDER BY AVG(mape) ASC
    LIMIT 1
";
$stmt = $conn->prepare($best_model_query);
$stmt->bind_param('s', $crop);
$stmt->execute();
$best_model_result = $stmt->get_result()->fetch_assoc();
$best_model = $best_model_result['method'] ?? 'Prophet';

// Fetch forecast data (next 12 months)
if ($view === 'best') {
    // Only fetch best model's latest version
    $forecast_query = "
        SELECT yp.predicted_month, yp.predicted_quantity, yp.confidence_lower, 
               yp.confidence_upper, yp.method, yp.model_version
        FROM yield_predictions yp
        INNER JOIN (
            SELECT method, MAX(model_version) as max_version
            FROM yield_predictions
            WHERE crop_type = ? AND method = ?
            GROUP BY method
        ) latest ON yp.method = latest.method AND yp.model_version = latest.max_version
        WHERE yp.crop_type = ?
        AND yp.predicted_month >= DATE_FORMAT(NOW(), '%Y-%m')
        AND yp.predicted_month <= ?
        ORDER BY yp.predicted_month
    ";
    $stmt = $conn->prepare($forecast_query);
    $stmt->bind_param('ssss', $crop, $best_model, $crop, $end_date);
} else {
    // Fetch all models' latest versions
    $forecast_query = "
        SELECT yp.predicted_month, yp.predicted_quantity, yp.confidence_lower, 
               yp.confidence_upper, yp.method, yp.model_version
        FROM yield_predictions yp
        INNER JOIN (
            SELECT method, MAX(model_version) as max_version
            FROM yield_predictions
            WHERE crop_type = ?
            GROUP BY method
        ) latest ON yp.method = latest.method AND yp.model_version = latest.max_version
        WHERE yp.crop_type = ?
        AND yp.predicted_month >= DATE_FORMAT(NOW(), '%Y-%m')
        AND yp.predicted_month <= ?
        ORDER BY yp.method, yp.predicted_month
    ";
    $stmt = $conn->prepare($forecast_query);
    $stmt->bind_param('sss', $crop, $crop, $end_date);
}

$stmt->execute();
$forecast_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate month labels (last 12 + next 12 months)
$labels = [];
$current = strtotime($start_date);
$end = strtotime($end_date);
while ($current <= $end) {
    $labels[] = date('M Y', $current);
    $current = strtotime('+1 month', $current);
}

// Prepare actual data series
$actual_values = [];
$actual_by_month = [];
foreach ($actual_data as $row) {
    $actual_by_month[$row['month']] = (float)$row['total'];
}

// Fill actual values array (null for future months)
$current = strtotime($start_date);
$now = strtotime(date('Y-m-01'));
while ($current <= $end) {
    $month_key = date('Y-m', $current);
    if ($current <= $now) {
        $actual_values[] = $actual_by_month[$month_key] ?? null;
    } else {
        $actual_values[] = null; // Future months
    }
    $current = strtotime('+1 month', $current);
}

// Prepare forecast data series
$forecasts_by_model = [];
foreach ($forecast_data as $row) {
    $method = $row['method'];
    if (!isset($forecasts_by_model[$method])) {
        $forecasts_by_model[$method] = [
            'values' => [],
            'lower' => [],
            'upper' => []
        ];
    }
    $forecasts_by_model[$method]['values'][$row['predicted_month']] = (float)$row['predicted_quantity'];
    $forecasts_by_model[$method]['lower'][$row['predicted_month']] = (float)$row['confidence_lower'];
    $forecasts_by_model[$method]['upper'][$row['predicted_month']] = (float)$row['confidence_upper'];
}

// Build datasets
$datasets = [];

// Actual data (solid black line)
$datasets[] = [
    'label' => 'Actual',
    'data' => $actual_values,
    'borderColor' => 'rgb(0, 0, 0)',
    'backgroundColor' => 'rgba(0, 0, 0, 0.1)',
    'borderWidth' => 3,
    'pointRadius' => 4,
    'pointHoverRadius' => 6,
    'tension' => 0.1,
    'fill' => false
];

// Forecast datasets (dashed colored lines)
$colors = [
    'Prophet' => ['rgb(76, 175, 80)', 'rgba(76, 175, 80, 0.1)'],
    'SARIMA' => ['rgb(33, 150, 243)', 'rgba(33, 150, 243, 0.1)'],
    'Baseline' => ['rgb(255, 152, 0)', 'rgba(255, 152, 0, 0.1)']
];

foreach ($forecasts_by_model as $method => $data) {
    $forecast_values = [];
    $lower_values = [];
    $upper_values = [];
    
    // Fill forecast values (null for historical months)
    $current = strtotime($start_date);
    $now = strtotime(date('Y-m-01'));
    while ($current <= $end) {
        $month_key = date('Y-m', $current);
        if ($current > $now) {
            $forecast_values[] = $data['values'][$month_key] ?? null;
            $lower_values[] = $data['lower'][$month_key] ?? null;
            $upper_values[] = $data['upper'][$month_key] ?? null;
        } else {
            $forecast_values[] = null; // Historical months
            $lower_values[] = null;
            $upper_values[] = null;
        }
        $current = strtotime('+1 month', $current);
    }
    
    $color = $colors[$method] ?? ['rgb(128, 128, 128)', 'rgba(128, 128, 128, 0.1)'];
    
    // Main forecast line
    $datasets[] = [
        'label' => $method . ' Forecast',
        'data' => $forecast_values,
        'borderColor' => $color[0],
        'backgroundColor' => $color[1],
        'borderWidth' => 2,
        'borderDash' => [5, 5],
        'pointRadius' => 3,
        'pointHoverRadius' => 5,
        'tension' => 0.1,
        'fill' => false
    ];
    
    // Confidence interval (only for best model in 'best' view)
    if ($view === 'best' && $method === $best_model) {
        $datasets[] = [
            'label' => 'Confidence Range',
            'data' => $upper_values,
            'borderColor' => 'rgba(0, 0, 0, 0)',
            'backgroundColor' => $color[1],
            'fill' => '+1',
            'pointRadius' => 0,
            'borderWidth' => 0,
            'tension' => 0.1
        ];
        $datasets[] = [
            'label' => 'Lower Bound',
            'data' => $lower_values,
            'borderColor' => 'rgba(0, 0, 0, 0)',
            'backgroundColor' => $color[1],
            'fill' => false,
            'pointRadius' => 0,
            'borderWidth' => 0,
            'tension' => 0.1
        ];
    }
}

// Return JSON
echo json_encode([
    'labels' => $labels,
    'datasets' => $datasets,
    'best_model' => $best_model
]);