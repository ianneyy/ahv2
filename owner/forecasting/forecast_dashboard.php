<?php
require_once '../../includes/db.php';

// Get selected crop (default to buko)
$selected_crop = $_GET['crop'] ?? 'buko';

// Get latest actual yield
$latest_actual_query = "
    SELECT recorded_at, quantity 
    FROM yield_records 
    WHERE crop_type = ? 
    ORDER BY recorded_at DESC 
    LIMIT 1
";
$stmt = $conn->prepare($latest_actual_query);
$stmt->bind_param('s', $selected_crop);
$stmt->execute();
$latest_actual = $stmt->get_result()->fetch_assoc();

// Get next month forecast (latest version, best model)
$next_month = date('Y-m', strtotime('+1 month'));
$forecast_query = "
    SELECT yp.predicted_quantity, yp.confidence_lower, yp.confidence_upper, 
           yp.method, me.mape
    FROM yield_predictions yp
    LEFT JOIN model_evaluation me ON yp.crop_type = me.crop_type 
        AND yp.model_version = me.model_version 
        AND yp.method = me.method
    WHERE yp.crop_type = ? 
    AND yp.predicted_month = ?
    ORDER BY me.mape ASC
    LIMIT 1
";
$stmt = $conn->prepare($forecast_query);
$stmt->bind_param('ss', $selected_crop, $next_month);
$stmt->execute();
$next_forecast = $stmt->get_result()->fetch_assoc();

// Get best model overall
$best_model_query = "
    SELECT method, AVG(mape) as avg_mape, AVG(rmse) as avg_rmse, AVG(mae) as avg_mae
    FROM model_evaluation
    WHERE crop_type = ?
    GROUP BY method
    ORDER BY avg_mape ASC
    LIMIT 1
";
$stmt = $conn->prepare($best_model_query);
$stmt->bind_param('s', $selected_crop);
$stmt->execute();
$best_model = $stmt->get_result()->fetch_assoc();

// Get all models for comparison
$comparison_query = "
    SELECT method, model_version, forecast_year, mape, rmse, mae, data_points_compared
    FROM model_evaluation
    WHERE crop_type = ?
    ORDER BY forecast_year DESC, mape ASC
";
$stmt = $conn->prepare($comparison_query);
$stmt->bind_param('s', $selected_crop);
$stmt->execute();
$all_models = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Determine accuracy level
$accuracy_level = 'Unknown';
$accuracy_color = 'gray';
$accuracy_icon = '❓';
if ($best_model && $best_model['avg_mape']) {
    $mape = $best_model['avg_mape'];
    if ($mape < 15) {
        $accuracy_level = 'High';
        $accuracy_color = 'green';
        $accuracy_icon = '✅';
    } elseif ($mape < 25) {
        $accuracy_level = 'Medium';
        $accuracy_color = 'yellow';
        $accuracy_icon = '⚠️';
    } else {
        $accuracy_level = 'Low';
        $accuracy_color = 'red';
        $accuracy_icon = '❌';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast Dashboard - <?= ucfirst($selected_crop) ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #2c5530;
            font-size: 28px;
        }
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .filters select, .filters button {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filters select {
            background: white;
        }
        .filters button {
            background: #4CAF50;
            color: white;
            border: none;
        }
        .filters button:hover {
            background: #45a049;
        }
        
        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .kpi-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .kpi-label {
            color: #666;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }
        .kpi-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c5530;
            margin-bottom: 5px;
        }
        .kpi-subtext {
            font-size: 13px;
            color: #888;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        .badge-green { background: #e8f5e9; color: #2e7d32; }
        .badge-yellow { background: #fff3e0; color: #f57c00; }
        .badge-red { background: #ffebee; color: #c62828; }
        .badge-gray { background: #f5f5f5; color: #666; }
        
        /* Chart Section */
        .chart-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .chart-header h2 {
            color: #2c5530;
            font-size: 20px;
        }
        .model-toggles {
            display: flex;
            gap: 10px;
        }
        .model-toggle {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s;
        }
        .model-toggle:hover {
            border-color: #4CAF50;
        }
        .model-toggle.active {
            background: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        
        /* Recommendations Box */
        .recommendations {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .recommendations h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }
        .recommendation-item {
            background: rgba(255,255,255,0.15);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            backdrop-filter: blur(10px);
        }
        .recommendation-item:last-child {
            margin-bottom: 0;
        }
        
        /* Table */
        .table-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .table-section h2 {
            color: #2c5530;
            font-size: 20px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f5f7fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c5530;
            font-size: 13px;
            border-bottom: 2px solid #e0e0e0;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .winner-row {
            background: #e8f5e9 !important;
        }
        .winner-badge {
            background: #4CAF50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📊 Forecast Dashboard</h1>
            <div class="filters">
                <select id="cropSelector" onchange="changeCrop()">
                    <option value="buko" <?= $selected_crop === 'buko' ? 'selected' : '' ?>>🥥 Buko</option>
                    <option value="saba" <?= $selected_crop === 'saba' ? 'selected' : '' ?>>🍌 Saba</option>
                </select>
                <button onclick="location.href='run_forecast.php'">🔄 Refresh Forecasts</button>
                <button onclick="location.href='/AHV2.2/ahv2/owner/forecasting.php'" style="background: #666;">← Back</button>

            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <!-- Latest Actual -->
            <div class="kpi-card">
                <div class="kpi-label">Latest Actual Yield</div>
                <div class="kpi-value"><?= $latest_actual ? number_format($latest_actual['quantity']) : 'N/A' ?></div>
                <div class="kpi-subtext">
                    <?= $latest_actual ? date('F Y', strtotime($latest_actual['recorded_at'])) : 'No data' ?>
                </div>
            </div>

            <!-- Next Month Forecast -->
            <div class="kpi-card">
                <div class="kpi-label">Next Month Forecast</div>
                <div class="kpi-value">
                    <?= $next_forecast ? number_format($next_forecast['predicted_quantity']) : 'N/A' ?>
                </div>
                <div class="kpi-subtext">
                    <?php if ($next_forecast): ?>
                        <?= date('F Y', strtotime($next_month . '-01')) ?><br>
                        Range: <?= number_format($next_forecast['confidence_lower']) ?> - <?= number_format($next_forecast['confidence_upper']) ?>
                    <?php else: ?>
                        No forecast available
                    <?php endif; ?>
                </div>
            </div>

            <!-- Model Accuracy -->
            <div class="kpi-card">
                <div class="kpi-label">Model Accuracy</div>
                <div class="kpi-value">
                    <?= $best_model ? number_format($best_model['avg_mape'], 1) . '%' : 'N/A' ?>
                </div>
                <div class="kpi-subtext">
                    MAPE (lower is better)<br>
                    <span class="badge badge-<?= $accuracy_color ?>">
                        <?= $accuracy_icon ?> <?= $accuracy_level ?> Confidence
                    </span>
                </div>
            </div>

            <!-- Best Model -->
            <div class="kpi-card">
                <div class="kpi-label">Best Model</div>
                <div class="kpi-value" style="font-size: 24px;">
                    <?= $best_model ? $best_model['method'] : 'N/A' ?>
                </div>
                <div class="kpi-subtext">
                    <?php if ($best_model): ?>
                        RMSE: <?= number_format($best_model['avg_rmse'], 1) ?><br>
                        MAE: <?= number_format($best_model['avg_mae'], 1) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <div class="recommendations">
            <h3>📋 Recommendations</h3>
            <?php if ($best_model): ?>
                <div class="recommendation-item">
                    <strong>✅ Use <?= $best_model['method'] ?> for <?= ucfirst($selected_crop) ?> forecasting</strong><br>
                    Average error: <?= number_format($best_model['avg_mape'], 1) ?>% MAPE
                    <?php if ($best_model['avg_mape'] < 15): ?>
                        - Production ready!
                    <?php elseif ($best_model['avg_mape'] < 25): ?>
                        - Good for planning purposes
                    <?php else: ?>
                        - Use with caution, consider collecting more data
                    <?php endif; ?>
                </div>
                
                <?php if ($next_forecast && $latest_actual): ?>
                    <?php 
                    $change_pct = (($next_forecast['predicted_quantity'] - $latest_actual['quantity']) / $latest_actual['quantity']) * 100;
                    ?>
                    <div class="recommendation-item">
                        <strong><?= $change_pct > 0 ? '📈' : '📉' ?> Forecast vs Latest Actual</strong><br>
                        <?= $change_pct > 0 ? 'Increase' : 'Decrease' ?> of <?= abs(round($change_pct, 1)) ?>% expected next month
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="recommendation-item">
                    ⚠️ No model evaluation data available. Run forecasts first.
                </div>
            <?php endif; ?>
        </div>

        <!-- Chart -->
        <div class="chart-section">
            <div class="chart-header">
                <h2>📈 Actual vs Forecast</h2>
                <div class="model-toggles">
                    <button class="model-toggle active" data-model="best" onclick="toggleModel('best')">
                        Best Model
                    </button>
                    <button class="model-toggle" data-model="all" onclick="toggleModel('all')">
                        Compare All
                    </button>
                </div>
            </div>
            <canvas id="forecastChart" height="80"></canvas>
        </div>

        <!-- Model Comparison Table -->
        <div class="table-section">
            <h2>🔍 Model Comparison</h2>
            <?php if (!empty($all_models)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Version</th>
                            <th>Forecast Year</th>
                            <th>MAPE (%)</th>
                            <th>RMSE</th>
                            <th>MAE</th>
                            <th>Months</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $best_mape = min(array_column($all_models, 'mape'));
                        foreach ($all_models as $model): 
                            $is_best = ($model['mape'] == $best_mape);
                        ?>
                            <tr class="<?= $is_best ? 'winner-row' : '' ?>">
                                <td><strong><?= htmlspecialchars($model['method']) ?></strong></td>
                                <td><?= htmlspecialchars($model['model_version']) ?></td>
                                <td><?= htmlspecialchars($model['forecast_year']) ?></td>
                                <td><?= number_format($model['mape'], 2) ?>%</td>
                                <td><?= number_format($model['rmse'], 2) ?></td>
                                <td><?= number_format($model['mae'], 2) ?></td>
                                <td><?= $model['data_points_compared'] ?></td>
                                <td>
                                    <?php if ($is_best): ?>
                                        <span class="winner-badge">🏆 BEST</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px; color: #666;">
                    No model comparison data available. Run forecasts first.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const selectedCrop = '<?= $selected_crop ?>';
        let currentChart = null;
        let currentModel = 'best';

        function changeCrop() {
            const crop = document.getElementById('cropSelector').value;
            window.location.href = '?crop=' + crop;
        }

        function toggleModel(model) {
            currentModel = model;
            document.querySelectorAll('.model-toggle').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-model="${model}"]`).classList.add('active');
            loadChartData();
        }

        async function loadChartData() {
            const response = await fetch(`forecast_api.php?crop=${selectedCrop}&view=${currentModel}`);
            const data = await response.json();
            
            if (currentChart) {
                currentChart.destroy();
            }

            const ctx = document.getElementById('forecastChart').getContext('2d');
            currentChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: data.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Yield (pieces)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        }

        // Load chart on page load
        loadChartData();
    </script>
</body>
</html>