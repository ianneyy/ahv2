<?php
require_once '../includes/session.php';

require_once '../includes/db.php';

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
// $latest_actual_month = $stmt->get_result()->fetch_assoc();

// Get next month forecast (latest version, best model)
// $next_month = date('Y-m', strtotime('+2 month'));
$next_month = '2024-12';
$forecast_query = "
    SELECT yp.predicted_quantity, yp.confidence_lower, yp.confidence_upper, 
           yp.method, me.mape, yp.predicted_month
    FROM yield_predictions yp
    LEFT JOIN model_evaluation me ON yp.crop_type = me.crop_type 
        AND yp.model_version = me.model_version 
        AND yp.method = me.method
    WHERE yp.crop_type = ? 
    AND yp.predicted_month = ?
    ORDER BY me.mape ASC
    LIMIT 1
";
// $forecast_query = "
//     SELECT *
//     FROM yield_predictions
//     WHERE crop_type = ? 
//     AND predicted_month = ?
// ";
$stmt = $conn->prepare($forecast_query);
$stmt->bind_param('ss', $selected_crop, $next_month);
$stmt->execute();
$next_forecast = $stmt->get_result()->fetch_assoc();
// echo "Crop: $selected_crop<br>";
// echo "Next Month: $next_month<br>";
// echo "<pre>";
// var_dump($next_forecast);
// echo "</pre>";
// exit;
// $current_month = date('Y-m');


// // Fetch forecast for current month
// $forecast_query = $conn->query("
//     SELECT predicted_quantity 
//     FROM yield_predictions
//     WHERE crop_type = '$selected_crop' 
//       AND predicted_month = '$current_month'
//     LIMIT 1
// ");

// $next_forecast = $forecast_query->fetch_assoc();
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




$months = [];
for ($i = 11; $i >= 0; $i--) {
    $months[] = date("Y-m", strtotime("-$i month"));
}


$crop_type = 'buko';
$actual_data = [];
$result = $conn->query("
    SELECT DATE_FORMAT(recorded_at, '%Y-%m') AS month, SUM(quantity) AS total
    FROM yield_records
    WHERE crop_type = '$crop_type'
    GROUP BY month
");

while ($row = $result->fetch_assoc()) {
    $actual_data[$row['month']] = (float) $row['total'];
}

$forecast_data = [];
$result = $conn->query("
    SELECT predicted_month, predicted_quantity
    FROM yield_predictions
    WHERE crop_type = '$crop_type'
");

while ($row = $result->fetch_assoc()) {
    $forecast_data[$row['predicted_month']] = (float) $row['predicted_quantity'];
}

$actual_chart = [];
$forecast_chart = [];
foreach ($months as $month) {
    $actual_chart[] = $actual_data[$month] ?? 0;
    $forecast_chart[] = $forecast_data[$month] ?? 0;
}
?>

<?php
require_once '../includes/header.php';
?>
<div class="flex min-h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">
        <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold ">Forecast Dashboard</h2>
                    <span class="text-lg text-gray-600 ">Your overview of upcoming yield predictions.</span>
                </div>

                <?php include 'includes/sm-sidebar.php'; ?>

            </div>
            <section>
                <div class="flex justify-end ">

                    <div class="w-32">
                        <select class="select select-ghost cursor-pointer text-lg">
                            <option selected>2024</option>
                            <option>2023</option>
                            <option>2022</option>
                            <option>2021</option>
                        </select>
                    </div>
                </div>

            </section>

            <section class="flex flex-col lg:flex-row gap-5 mt-5">
                <div class="w-full flex flex-col  gap-3 ">
                    <div class="w-full flex flex-col lg:flex-row gap-5">

                        <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl ">
                            <div class="flex item-center justify-between px-5 lg:px-10 pt-6">
                                <div>

                                    <span class="text-slate-600 font-semibold">Actual Yield </span>
                                    <span class="text-sm text-slate-600">|
                                        <?= $latest_actual ? date('M Y', strtotime($latest_actual['recorded_at'])) : 'N/A' ?></span>
                                </div>

                                <div class="p-3">

                                    <i data-lucide="tally-5" class="w-6 h-6 text-emerald-700"></i>
                                </div>

                            </div>
                            <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                                <span class="text-3xl font-semibold text-gray-100">
                                    <?= $latest_actual ? number_format($latest_actual['quantity']) : 'N/A' ?>
                                </span>
                            </div>
                        </div>
                        <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
                            <div class="flex item-center justify-between px-5 lg:px-10 pt-6">
                                <div>

                                    <span class="text-slate-600 font-semibold">Forecast Yield</span>
                                    <span class="text-sm text-slate-600"> | <?= $next_forecast ?  date('M Y', strtotime($next_forecast['predicted_month'])) : 'N/A' ?></span>
                                </div>

                                <div class="p-3">

                                    <i data-lucide="chart-column-increasing" class="w-6 h-6 text-emerald-700"></i>

                                </div>
                            </div>
                            <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                                <span class="text-3xl font-semibold text-gray-100">
                                    <?= $next_forecast ? number_format($next_forecast['predicted_quantity']) : 'N/A' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-5 flex-col lg:flex-row">
                        <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
                            <div class="flex item-center justify-between px-5 lg:px-10 pt-6">

                                <span class="text-slate-600 font-semibold">Model Accuracy</span>
                                <div class="p-3">

                                    <i data-lucide="chart-column-increasing" class="w-6 h-6 text-emerald-700"></i>

                                </div>
                            </div>
                            <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                                <span class="text-3xl font-semibold text-gray-100">
                                    <?= $best_model ? number_format($best_model['avg_mape'], 1) . '%' : 'N/A' ?>
                                </span>
                            </div>
                        </div>
                        <div class="bg-gray-100 w-full border border-slate-300  flex flex-col rounded-xl">
                            <div class="flex item-center justify-between px-5 lg:px-10 pt-6">

                                <span class="text-slate-600 font-semibold ">Best Model</span>
                                <div class="p-3">

                                    <i data-lucide="bot" class="w-6 h-6 text-emerald-700"></i>

                                </div>
                            </div>
                            <div class="bg-emerald-900 w-2/8 text-center py-2 rounded-tr-xl rounded-bl-xl">

                                <span class="text-3xl font-semibold text-gray-100">
                                    <?= $best_model ? $best_model['method'] : 'N/A' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <section class="">
                <div class="mt-10 bg-gray-100 p-5 rounded-xl border border-slate-300">
                    <div class="flex justify-between items-center mb-5">
                        <h3 class="text-xl font-semibold text-emerald-900">Yield Forecast Chart</h3>

                    </div>
                    <canvas id="forecastChart" height="100"></canvas>
                </div>
            </section>

        </div>
    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    lucide.createIcons();

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


    // const response = await fetch(`forecasting/forecast_api.php?crop=${selectedCrop}&view=${currentModel}`);
    // const data = await response.json();

    // if (currentChart) {
    //     currentChart.destroy();
    // }

    const ctx = document.getElementById('forecastChart').getContext('2d');
    currentChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Actual Yield',
                data: <?php echo json_encode($actual_chart); ?>,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                tension: 0.2
            },
            {
                label: 'Forecasted Yield',
                data: <?php echo json_encode($forecast_chart); ?>,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                borderDash: [5, 5],
                tension: 0.2
            }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,

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

        }
    });

    // Load chart on page load
    loadChartData();
</script>
</body>

</html>