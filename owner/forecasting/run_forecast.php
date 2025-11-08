<?php
require_once '../../includes/db.php';

// ================================
// Step 1: Run Python models (Prophet AND ARIMA)
// ================================
$models = [
    // Prophet models
    ['script' => 'C:/xampp/htdocs/AHV2.2/ahv2/owner/forecasting/models/model_prophet.py', 'crop' => 'buko', 'method' => 'Prophet'],
    ['script' => 'C:/xampp/htdocs/AHV2.2/ahv2/owner/forecasting/models/model_prophet.py', 'crop' => 'saba', 'method' => 'Prophet'],
    // ARIMA models
    ['script' => 'C:/xampp/htdocs/AHV2.2/ahv2/owner/forecasting/models/model_arima.py', 'crop' => 'buko', 'method' => 'SARIMA'],
    ['script' => 'C:/xampp/htdocs/AHV2.2/ahv2/owner/forecasting/models/model_arima.py', 'crop' => 'saba', 'method' => 'SARIMA'],
    // Baseline models
    ['script' => 'C:/xampp/htdocs/AHV2.2/ahv2/owner/forecasting/models/model_baseline.py', 'crop' => 'buko', 'method' => 'Baseline'],
    ['script' => 'C:/xampp/htdocs/AHV2.2/ahv2/owner/forecasting/models/model_baseline.py', 'crop' => 'saba', 'method' => 'Baseline'],
];

echo "<h2>🔄 Running Forecasts with Evaluation (Prophet & ARIMA)...</h2>";

foreach($models as $m){
    $command = "python \"{$m['script']}\" \"{$m['crop']}\" 2>&1";
    exec($command, $output, $return_var);

    echo "<div style='background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid " . 
         ($return_var === 0 ? "#4CAF50" : "#f44336") . ";'>";
    echo "<strong>{$m['method']}: " . htmlspecialchars($m['crop']) . "</strong><br>";
    echo "<small>Return code: $return_var</small>";
    echo "<pre style='margin-top: 10px; font-size: 12px; max-height: 400px; overflow-y: auto;'>" . 
         implode("\n", $output) . "</pre>";
    echo "</div>";
    
    $output = []; // Clear for next iteration
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Yield Forecast Results with Evaluation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f9f9f9;
        }
        h2 {
            color: #2c5530;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
            margin-top: 40px;
        }
        h3 {
            color: #2c5530;
            margin-top: 30px;
        }
        .crop-section {
            background: white;
            margin: 20px 0;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .method-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .method-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }
        .method-card.winner {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        .method-card h4 {
            margin: 0 0 15px 0;
            color: #2c5530;
            font-size: 18px;
        }
        .winner-badge {
            background: #4CAF50;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background: #2c5530;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            font-size: 13px;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            background: #fff3cd;
            border-radius: 4px;
        }
        .good-accuracy {
            color: #4CAF50;
            font-weight: bold;
        }
        .medium-accuracy {
            color: #ff9800;
            font-weight: bold;
        }
        .poor-accuracy {
            color: #f44336;
            font-weight: bold;
        }
        .metric-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .metric-label {
            font-weight: 600;
            color: #666;
        }
        .metric-value {
            font-weight: bold;
            color: #2c5530;
        }
        .summary-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .summary-box h4 {
            margin: 0 0 10px 0;
            color: #1976D2;
        }
    </style>
</head>
<body>

<?php
// Fetch all evaluation data
$eval_query = "SELECT model_version, crop_type, method, forecast_year, 
                      mape, rmse, data_points_compared
               FROM model_evaluation
               ORDER BY crop_type, forecast_year, method";
$eval_result = $conn->query($eval_query);

if($eval_result && $eval_result->num_rows > 0) {
    // Group by crop, then year, then method
    $data = [];
    while($row = $eval_result->fetch_assoc()) {
        $crop = $row['crop_type'];
        $year = $row['forecast_year'];
        $method = $row['method'];
        $data[$crop][$year][$method] = $row;
    }
    
    // Display for each crop
    foreach($data as $crop => $years) {
        echo "<h2>🌾 " . ucfirst($crop) . " - Model Performance Comparison</h2>";
        echo "<div class='crop-section'>";
        
        // Calculate overall best method
        $prophet_total = 0;
        $arima_total = 0;
        $prophet_count = 0;
        $arima_count = 0;
        
        foreach($years as $year => $methods) {
            if(isset($methods['Prophet'])) {
                $prophet_total += $methods['Prophet']['mape'];
                $prophet_count++;
            }
            if(isset($methods['ARIMA'])) {
                $arima_total += $methods['ARIMA']['mape'];
                $arima_count++;
            }
        }
        
        $prophet_avg = $prophet_count > 0 ? $prophet_total / $prophet_count : 999;
        $arima_avg = $arima_count > 0 ? $arima_total / $arima_count : 999;
        $overall_winner = $prophet_avg < $arima_avg ? 'Prophet' : 'ARIMA';
        
        // Summary box
        echo "<div class='summary-box'>";
        echo "<h4>📊 Overall Performance (Average MAPE)</h4>";
        echo "<div class='method-comparison'>";
        
        echo "<div style='text-align: center;'>";
        echo "<strong>Prophet</strong>";
        if($overall_winner == 'Prophet') echo " <span class='winner-badge'>WINNER</span>";
        echo "<div style='font-size: 32px; color: " . ($prophet_avg < 20 ? "#4CAF50" : "#ff9800") . "; margin: 10px 0;'>";
        echo number_format($prophet_avg, 2) . "%";
        echo "</div>";
        echo "<small>Average across {$prophet_count} years</small>";
        echo "</div>";
        
        echo "<div style='text-align: center;'>";
        echo "<strong>ARIMA</strong>";
        if($overall_winner == 'ARIMA') echo " <span class='winner-badge'>WINNER</span>";
        echo "<div style='font-size: 32px; color: " . ($arima_avg < 20 ? "#4CAF50" : "#ff9800") . "; margin: 10px 0;'>";
        echo number_format($arima_avg, 2) . "%";
        echo "</div>";
        echo "<small>Average across {$arima_count} years</small>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
        
        // Year by year comparison
        foreach($years as $year => $methods) {
            echo "<h3>Year: {$year}</h3>";
            
            // Determine winner for this year
            $prophet_mape = isset($methods['Prophet']) ? $methods['Prophet']['mape'] : 999;
            $arima_mape = isset($methods['ARIMA']) ? $methods['ARIMA']['mape'] : 999;
            $year_winner = $prophet_mape < $arima_mape ? 'Prophet' : 'ARIMA';
            
            echo "<div class='method-comparison'>";
            
            // Prophet card
            if(isset($methods['Prophet'])) {
                $m = $methods['Prophet'];
                $is_winner = ($year_winner == 'Prophet');
                $card_class = $is_winner ? 'method-card winner' : 'method-card';
                
                echo "<div class='$card_class'>";
                echo "<h4>Prophet {$m['model_version']}";
                if($is_winner) echo "<span class='winner-badge'>BEST FOR {$year}</span>";
                echo "</h4>";
                
                $mape_class = 'good-accuracy';
                if($m['mape'] > 20) $mape_class = 'poor-accuracy';
                elseif($m['mape'] > 10) $mape_class = 'medium-accuracy';
                
                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>MAPE:</span>";
                echo "<span class='metric-value $mape_class'>{$m['mape']}%</span>";
                echo "</div>";
                
                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>RMSE:</span>";
                echo "<span class='metric-value'>{$m['rmse']} pieces</span>";
                echo "</div>";
                
                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>Months Compared:</span>";
                echo "<span class='metric-value'>{$m['data_points_compared']}</span>";
                echo "</div>";
                
                echo "</div>";
            }
            
            // ARIMA card
            if(isset($methods['ARIMA'])) {
                $m = $methods['ARIMA'];
                $is_winner = ($year_winner == 'ARIMA');
                $card_class = $is_winner ? 'method-card winner' : 'method-card';
                
                echo "<div class='$card_class'>";
                echo "<h4>ARIMA {$m['model_version']}";
                if($is_winner) echo "<span class='winner-badge'>BEST FOR {$year}</span>";
                echo "</h4>";
                
                $mape_class = 'good-accuracy';
                if($m['mape'] > 20) $mape_class = 'poor-accuracy';
                elseif($m['mape'] > 10) $mape_class = 'medium-accuracy';
                
                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>MAPE:</span>";
                echo "<span class='metric-value $mape_class'>{$m['mape']}%</span>";
                echo "</div>";
                
                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>RMSE:</span>";
                echo "<span class='metric-value'>{$m['rmse']} pieces</span>";
                echo "</div>";
                
                echo "<div class='metric-row'>";
                echo "<span class='metric-label'>Months Compared:</span>";
                echo "<span class='metric-value'>{$m['data_points_compared']}</span>";
                echo "</div>";
                
                echo "</div>";
            }
            
            echo "</div>"; // end method-comparison
        }
        
        // Detailed table
        echo "<h3>Complete Comparison Table</h3>";
        echo "<table>";
        echo "<thead><tr>";
        echo "<th>Year</th><th>Method</th><th>Version</th><th>MAPE</th><th>RMSE</th><th>Months</th><th>Winner</th>";
        echo "</tr></thead><tbody>";
        
        foreach($years as $year => $methods) {
            $prophet_mape = isset($methods['Prophet']) ? $methods['Prophet']['mape'] : 999;
            $arima_mape = isset($methods['ARIMA']) ? $methods['ARIMA']['mape'] : 999;
            $year_winner = $prophet_mape < $arima_mape ? 'Prophet' : 'ARIMA';
            
            foreach($methods as $method => $m) {
                $is_winner = ($method == $year_winner);
                $mape_class = 'good-accuracy';
                if($m['mape'] > 20) $mape_class = 'poor-accuracy';
                elseif($m['mape'] > 10) $mape_class = 'medium-accuracy';
                
                echo "<tr" . ($is_winner ? " style='background: #e8f5e9;'" : "") . ">";
                echo "<td><strong>{$year}</strong></td>";
                echo "<td><strong>{$method}</strong></td>";
                echo "<td>{$m['model_version']}</td>";
                echo "<td class='$mape_class'>{$m['mape']}%</td>";
                echo "<td>{$m['rmse']} pieces</td>";
                echo "<td>{$m['data_points_compared']}</td>";
                echo "<td>" . ($is_winner ? "🏆" : "-") . "</td>";
                echo "</tr>";
            }
        }
        
        echo "</tbody></table>";
        echo "</div>"; // end crop-section
    }
    
} else {
    echo "<div class='no-data'>";
    echo "<strong>No evaluation results yet</strong><br>";
    echo "Models need actual data for the forecast years to calculate accuracy metrics.";
    echo "</div>";
}
?>

<!-- Interpretation Guide -->
<div style='background: #e8f5e9; padding: 20px; border-left: 4px solid #4CAF50; margin-top: 40px; border-radius: 4px;'>
    <h3>📖 Understanding the Metrics</h3>
    <p><strong>MAPE (Mean Absolute Percentage Error):</strong> Lower is better</p>
    <ul>
        <li><span class='good-accuracy'>0-10%</span> = Excellent accuracy</li>
        <li><span class='medium-accuracy'>10-20%</span> = Good accuracy</li>
        <li><span class='poor-accuracy'>&gt;20%</span> = Needs improvement</li>
    </ul>
    <p><strong>RMSE (Root Mean Squared Error):</strong> Lower is better. Measures average prediction error in pieces.</p>
    <p><strong>🏆 Winner:</strong> The method with the lowest MAPE for each year/crop.</p>
</div>

</body>
</html>