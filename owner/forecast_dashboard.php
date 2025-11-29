<?php
require_once '../includes/session.php';

require_once '../includes/db.php';

// Get selected crop (default to buko)
$selected_crop = $_GET['crop'] ?? 'buko';
$selected_model = $_GET['model'] ?? 'SARIMA';
$selected_model_evaluation = $_GET['modeleval'] ?? 'SARIMA';
$version = $_GET['version'] ?? null;

$selected_year = $_GET['year'] ?? null;

$crop_types = ['buko', 'saba'];
$forecast_data = [];
$actual_data = [];


// Initialize arrays
foreach ($crop_types as $crop) {
    $actual_data[$crop] = [];
    $forecast_data[$crop] = [];
}



$months = [];
for ($i = 78; $i >= 0; $i--) {
    $months[] = date("Y-m", strtotime("-$i month"));
}


// $crop_type = 'buko';
$result = $conn->query("
    SELECT crop_type, DATE_FORMAT(recorded_at, '%Y-%m') AS month, SUM(quantity) AS total
    FROM yield_records
    WHERE crop_type IN ('buko', 'saba')
    GROUP BY crop_type, month
");

while ($row = $result->fetch_assoc()) {
    $actual_data[$row['crop_type']][$row['month']] = (float) $row['total'];
}

// Query Forecast Yield per crop_type
$result = $conn->query("
    SELECT crop_type, predicted_month, predicted_quantity
    FROM yield_predictions
    WHERE method = '$selected_model'
    AND model_version = '$version'
    AND crop_type IN ('buko', 'saba')
");

while ($row = $result->fetch_assoc()) {
    $forecast_data[$row['crop_type']][$row['predicted_month']] = (float) $row['predicted_quantity'];
}

$chart_actual = [];
$chart_forecast = [];

foreach ($crop_types as $crop) {
    foreach ($months as $month) {
        $chart_actual[$crop][] = $actual_data[$crop][$month] ?? 0;
        $chart_forecast[$crop][] = $forecast_data[$crop][$month] ?? 0;
    }
}



$evaluation = [];

$years_result = $conn->query("
    SELECT DISTINCT forecast_year
    FROM model_evaluation
    ORDER BY forecast_year ASC
");

$years = [];
if ($years_result) {
    while ($row = $years_result->fetch_assoc()) {
        $years[] = $row['forecast_year'];
    }
}




$result = $conn->query("
    SELECT forecast_year, mape, rmse, mae
    FROM model_evaluation
    WHERE method = '$selected_model_evaluation'
     " . ($selected_year ? " AND forecast_year = " . (int) $selected_year : "") . "
    ORDER BY forecast_year ASC
");
// var_dump($selected_model_evaluation);

while ($row = $result->fetch_assoc()) {
    $evaluation['years'][] = (int) $row['forecast_year'];
    $evaluation['mape'][] = (float) $row['mape'];
    $evaluation['rmse'][] = (float) $row['rmse'];
    $evaluation['mae'][] = (float) $row['mae'];
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
                <div class="flex items-center justify-between">
                    <div>

                        <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold ">Yield Forecast Dashboard</h2>
                        <span class="text-lg text-gray-600 ">Your overview of upcoming yield predictions.</span>
                    </div>


                </div>

                <?php include 'includes/sm-sidebar.php'; ?>

            </div>
            <section>

            </section>


            <form method="GET">

                <section class="mt-10 ">
                    <div class="bg-gray-50 p-5 rounded-3xl border-2 border-gray-200">
                        <div class="flex justify-between items-center mb-5">
                            <div>

                                <h3 class="text-xl font-semibold text-emerald-900">Yield Forecast Chart</h3>
                            </div>
                            <div class="flex gap-3">

                            <div class="w-32">
                                <select id="modelSelector" name="model"
                                    class="select px-2 bg-gray-50 border border-gray-200 rounded-lg text-emerald-900 text-sm"
                                    onchange="this.form.submit()">

                                    <option value="SARIMA" <?= ($_GET['model'] ?? '') === 'SARIMA' ? 'selected' : '' ?>>
                                        SARIMA
                                    </option>
                                    <option value="baseline" <?= ($_GET['model'] ?? '') === 'baseline' ? 'selected' : '' ?>>Baseline</option>
                                    <option value="prophet" <?= ($_GET['model'] ?? '') === 'prophet' ? 'selected' : '' ?>>
                                        Prophet</option>
                            
                                </select>
                            </div>
                            <?php
                            // Only show version select if the selected model has versions
                            $versions = [];
                            if ($selected_model) {
                                $stmt = $conn->prepare("
                                    SELECT DISTINCT model_version 
                                    FROM yield_predictions 
                                    WHERE method = ?
                                    ORDER BY model_version DESC
                                ");
                                $stmt->bind_param('s', $selected_model);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                while ($row = $result->fetch_assoc()) {
                                    $versions[] = $row['model_version'];
                                }
                            }
                            ?>

                            <?php if (!empty($versions)): ?>
                                <div class="w-32">
                                    <select id="modelVersion" name="version"
                                        class="select px-2 bg-gray-50 border border-gray-200 text-sm rounded-lg text-emerald-900" onchange="this.form.submit()">
                                        <?php foreach ($versions as $version): ?>
                                            <option value="<?= $version ?>" <?= ($_GET['version'] ?? '') === $version ? 'selected' : '' ?>>
                                                <?= $version ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            </div>

                        </div>
                        <canvas id="forecastChart" height="100"></canvas>
                    </div>
                </section>

                <section class="mt-10 ">
                    <div class="bg-gray-50 p-5 rounded-3xl border-2 border-gray-200">
                        <div class="flex justify-between items-center mb-5">
                            <div>

                                <h3 class="text-xl font-semibold text-emerald-900">Model Performance Comparison</h3>
                            </div>
                            <div class="flex items-center gap-10">

                                <div class="w-48">

                                    <select id="modelEvaluateSelector" name="modeleval"
                                        class="select px-2 bg-gray-50 border border-gray-200 rounded-lg text-emerald-900 text-sm"
                                        onchange="this.form.submit()">

                                        <option value="SARIMA" <?= ($_GET['modeleval'] ?? '') === 'SARIMA' ? 'selected' : '' ?>>
                                            SARIMA
                                        </option>
                                        <option value="baseline" <?= ($_GET['modeleval'] ?? '') === 'baseline' ? 'selected' : '' ?>>
                                            Baseline</option>
                                        <option value="prophet" <?= ($_GET['modeleval'] ?? '') === 'prophet' ? 'selected' : '' ?>>
                                            Prophet</option>

                                    </select>
                                </div>

                            </div>






                        </div>
                        <div class="w-full mb-10">
                            <input type="range" min="<?= $years[0] ?>"   max="<?= end($years) ?>"    value="<?= $selected_year ?>"
                                class="range range-sm w-full" step="1" id="yearRange"
                                oninput="updateYearLabel(this.value)" />
                            <div class="flex justify-between px-2.5 mt-2 text-xs">
                                <?php foreach ($years as $y): ?>
                                    <span>|</span>
                                <?php endforeach; ?>
                            </div>
                            <!-- Tick marks -->
                            <div class="flex justify-between mt-2 text-xs">
                                <?php foreach ($years as $y): ?>
                                    <span><?= $y ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-1 text-sm text-gray-600 font-semibold mt-3">
                                Selected year: <span id="selectedYear"><?= $selected_year ?></span>
                            </div>
                        </div>
                        <canvas id="evalChart" height="100"></canvas>
                    </div>
                </section>
            </form>

        </div>
    </main>
</div>
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    lucide.createIcons();
    function updateYearLabel(val) {
        document.getElementById('selectedYear').innerText = val;
    }
    document.getElementById('yearRange').addEventListener('change', function () {
        const year = this.value;
        const url = new URL(window.location.href);
        url.searchParams.set('year', year);
        window.location.href = url.toString();
    });
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
            datasets: [
                {
                    label: 'Buko - Actual',
                    data: <?= json_encode($chart_actual['buko']); ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.2
                },
                {
                    label: 'Buko - Forecast',
                    data: <?= json_encode($chart_forecast['buko']); ?>,
                    borderColor: 'rgba(54, 162, 235, 1)',
                    backgroundColor: 'rgba(54, 162, 235, 0)',
                    borderDash: [5, 5],
                    tension: 0.2
                },
                {
                    label: 'Saba - Actual',
                    data: <?= json_encode($chart_actual['saba']); ?>,
                    borderColor: 'rgba(255, 159, 64, 1)',
                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                    tension: 0.2
                },
                {
                    label: 'Saba - Forecast',
                    data: <?= json_encode($chart_forecast['saba']); ?>,
                    borderColor: 'rgba(255, 159, 64, 1)',
                    backgroundColor: 'rgba(255, 159, 64, 0)',
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


    const evalYears = <?= json_encode($evaluation['years']) ?>;
    const evalMAPE = <?= json_encode($evaluation['mape']) ?>;
    const evalRMSE = <?= json_encode($evaluation['rmse']) ?>;
    const evalMAE = <?= json_encode($evaluation['mae']) ?>;

    const evalCtx = document.getElementById('evalChart').getContext('2d');
    new Chart(evalCtx, {
        type: 'bar',
        data: {
            labels: evalYears,
            datasets: [
                {
                    label: 'MAPE',
                    data: evalMAPE,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                },
                {
                    label: 'RMSE',
                    data: evalRMSE,
                    backgroundColor: 'rgba(255, 99, 132, 0.6)'
                },
                {
                    label: 'MAE',
                    data: evalMAE,
                    backgroundColor: 'rgba(255, 206, 86, 0.6)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' },
                title: {
                    display: true,
                    text: 'Model Evaluation Metrics Per Year'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                }
            }
        }
    });
    // Load chart on page load
    loadChartData();
</script>
</body>

</html>