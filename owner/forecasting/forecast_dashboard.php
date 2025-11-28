<?php
// owner/forecasting/forecast_dashboard.php
session_start();

// Add your authentication check here if needed
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../login.php');
//     exit;
// }

// Include header if you have one
// include('../partials/header.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast Dashboard - Agricultural Yield Forecasting</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        .tab-active {
            color: #16a34a;
            border-bottom: 2px solid #16a34a;
        }
        .tab-inactive {
            color: #4b5563;
        }
        .tab-inactive:hover {
            color: #1f2937;
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Main Dashboard Container -->
    <div class="min-h-screen bg-gradient-to-br from-green-50 to-blue-50 p-6">
        <div class="max-w-7xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">🌾 Agricultural Yield Forecast Dashboard</h1>
                        <p class="text-gray-600">Expanding Window Forecasting with Multiple Models</p>
                    </div>
                    <button
                        onclick="handleRefresh()"
                        class="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                        </svg>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <!-- Error Display -->
            <div id="error-message" class="hidden bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center space-x-2">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-red-600">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span id="error-text" class="text-red-800"></span>
            </div>

            <!-- Navigation Tabs -->
            <div class="bg-white rounded-lg shadow-lg mb-6">
                <div class="flex border-b">
                    <button
                        onclick="setActiveTab('forecast')"
                        id="tab-forecast"
                        class="flex-1 px-6 py-4 font-semibold transition-colors tab-active"
                    >
                        📊 Forecast Results
                    </button>
                    <button
                        onclick="setActiveTab('evaluation')"
                        id="tab-evaluation"
                        class="flex-1 px-6 py-4 font-semibold transition-colors tab-inactive"
                    >
                        📈 Model Evaluation
                    </button>
                    <button
                        onclick="setActiveTab('training')"
                        id="tab-training"
                        class="flex-1 px-6 py-4 font-semibold transition-colors tab-inactive"
                    >
                        🔄 Training History
                    </button>
                </div>
            </div>

            <!-- Forecast Tab -->
            <div id="tab-content-forecast" class="space-y-6">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Crop Type</label>
                            <select
                                id="crop-select"
                                onchange="handleCropChange()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            >
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Model</label>
                            <select
                                id="model-select"
                                onchange="handleModelChange()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                            >
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    id="show-confidence"
                                    checked
                                    onchange="handleConfidenceToggle()"
                                    class="w-4 h-4 text-green-600 rounded focus:ring-2 focus:ring-green-500"
                                />
                                <span class="text-sm font-medium text-gray-700">Show Confidence Bands</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 id="chart-title" class="text-xl font-bold text-gray-800">
                            Forecasted Yield
                        </h2>
                        <button
                            onclick="handleExport()"
                            id="export-btn"
                            class="flex items-center space-x-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:bg-gray-400"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            <span>Export CSV</span>
                        </button>
                    </div>
                    
                    <div id="loading-indicator" class="h-96 flex items-center justify-center">
                        <div class="text-gray-500">Loading forecast data...</div>
                    </div>
                    
                    <div id="no-data-indicator" class="h-96 flex items-center justify-center hidden">
                        <div class="text-gray-500">No forecast data available</div>
                    </div>
                    
                    <div id="forecast-chart-container" class="hidden" style="height: 400px; position: relative;">
                        <canvas id="forecast-chart"></canvas>
                    </div>
                </div>

                <div id="forecast-table-container" class="bg-white rounded-lg shadow-lg p-6 hidden">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Detailed Predictions</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Month</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Predicted</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Lower Bound</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Upper Bound</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Model Version</th>
                                </tr>
                            </thead>
                            <tbody id="forecast-table-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Evaluation Tab (Hidden by default) -->
            <div id="tab-content-evaluation" class="space-y-6 hidden">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Model Performance Comparison</h2>
                    <div style="height: 400px; position: relative;">
                        <canvas id="evaluation-chart"></canvas>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Improvement Trend for <span id="improvement-crop"></span></h2>
                    <div style="height: 300px; position: relative;">
                        <canvas id="improvement-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Training Tab (Hidden by default) -->
            <div id="tab-content-training" class="space-y-6 hidden">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Best Performing Models by Crop</h2>
                    <div id="best-models-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Training Window History for <span id="training-crop"></span></h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Version</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Training Period</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Years of Data</th>
                                </tr>
                            </thead>
                            <tbody id="training-table-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global state
        const state = {
            selectedCrop: 'buko',
            selectedModel: 'Prophet',
            activeTab: 'forecast',
            showConfidence: true,
            forecastData: [],
            evaluationData: [],
            trainingHistoryData: [],
            improvementTrendData: [],
            bestModels: [],
            availableCrops: [],
            availableModels: [],
            charts: {}
        };

        const API_BASE_URL = 'forecast_api.php';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetchAvailableOptions();
        });

        // Fetch available crops and models
        async function fetchAvailableOptions() {
            try {
                const response = await fetch(`${API_BASE_URL}?endpoint=available_crops`);
                const result = await response.json();
                
                if (result.success) {
                    state.availableCrops = result.crops;
                    state.availableModels = result.models;
                    
                    populateDropdowns();
                    
                    if (result.crops.length > 0) {
                        state.selectedCrop = result.crops[0];
                    }
                    if (result.models.length > 0) {
                        state.selectedModel = result.models[0];
                    }
                    
                    fetchForecastData();
                }
            } catch (err) {
                console.error('Error fetching options:', err);
                showError('Failed to load options: ' + err.message);
            }
        }

        function populateDropdowns() {
            const cropSelect = document.getElementById('crop-select');
            const modelSelect = document.getElementById('model-select');
            
            cropSelect.innerHTML = state.availableCrops.map(crop => 
                `<option value="${crop}">${crop}</option>`
            ).join('');
            
            modelSelect.innerHTML = state.availableModels.map(model => 
                `<option value="${model}">${model}</option>`
            ).join('');
        }

        // Fetch forecast data
        async function fetchForecastData() {
            showLoading(true);
            hideError();
            
            try {
                const response = await fetch(
                    `${API_BASE_URL}?endpoint=forecasts&crop=${encodeURIComponent(state.selectedCrop)}&model=${encodeURIComponent(state.selectedModel)}`
                );
                const result = await response.json();
                
                if (result.success) {
                    state.forecastData = result.data;
                    renderForecastChart();
                    renderForecastTable();
                } else {
                    showError(result.error || 'Failed to fetch forecast data');
                    showNoData(true);
                }
            } catch (err) {
                showError('Network error: ' + err.message);
                showNoData(true);
            } finally {
                showLoading(false);
            }
        }

        // Fetch evaluation data
        async function fetchEvaluationData() {
            try {
                const response = await fetch(`${API_BASE_URL}?endpoint=model_evaluation`);
                const result = await response.json();
                
                if (result.success) {
                    state.evaluationData = result.data;
                    renderEvaluationChart();
                }
            } catch (err) {
                console.error('Error fetching evaluation data:', err);
            }
        }

        // Fetch improvement trend
        async function fetchImprovementTrend() {
            try {
                const response = await fetch(
                    `${API_BASE_URL}?endpoint=improvement_trend&crop=${encodeURIComponent(state.selectedCrop)}`
                );
                const result = await response.json();
                
                if (result.success) {
                    state.improvementTrendData = result.data;
                    renderImprovementChart();
                }
            } catch (err) {
                console.error('Error fetching improvement trend:', err);
            }
        }

        // Fetch training history
        async function fetchTrainingHistory() {
            try {
                const response = await fetch(`${API_BASE_URL}?endpoint=training_history`);
                const result = await response.json();
                
                if (result.success) {
                    state.trainingHistoryData = result.data;
                    renderTrainingTable();
                }
            } catch (err) {
                console.error('Error fetching training history:', err);
            }
        }

        // Fetch best models
        async function fetchBestModels() {
            try {
                const response = await fetch(`${API_BASE_URL}?endpoint=best_models`);
                const result = await response.json();
                
                if (result.success) {
                    state.bestModels = result.data;
                    renderBestModels();
                }
            } catch (err) {
                console.error('Error fetching best models:', err);
            }
        }

        // Render forecast chart
        function renderForecastChart() {
            const container = document.getElementById('forecast-chart-container');
            const canvas = document.getElementById('forecast-chart');
            const ctx = canvas.getContext('2d');
            
            // Destroy existing chart
            if (state.charts.forecast) {
                state.charts.forecast.destroy();
            }
            
            if (state.forecastData.length === 0) {
                showNoData(true);
                return;
            }
            
            showNoData(false);
            container.classList.remove('hidden');
            
            const datasets = [{
                label: 'Predicted Yield',
                data: state.forecastData.map(d => d.predicted),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                borderWidth: 3,
                fill: false,
                tension: 0.4
            }];
            
            if (state.showConfidence) {
                datasets.push({
                    label: 'Upper Bound',
                    data: state.forecastData.map(d => d.upper),
                    borderColor: '#93c5fd',
                    backgroundColor: 'rgba(147, 197, 253, 0.2)',
                    borderWidth: 1,
                    fill: '+1',
                    tension: 0.4
                });
                
                datasets.push({
                    label: 'Lower Bound',
                    data: state.forecastData.map(d => d.lower),
                    borderColor: '#93c5fd',
                    backgroundColor: 'rgba(147, 197, 253, 0.2)',
                    borderWidth: 1,
                    fill: false,
                    tension: 0.4
                });
            }
            
            state.charts.forecast = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: state.forecastData.map(d => d.month),
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
            
            document.getElementById('chart-title').textContent = 
                `Forecasted Yield for ${state.selectedCrop.toUpperCase()} — ${state.selectedModel.toUpperCase()}`;
        }

        // Render forecast table
        function renderForecastTable() {
            const tbody = document.getElementById('forecast-table-body');
            const container = document.getElementById('forecast-table-container');
            
            if (state.forecastData.length === 0) {
                container.classList.add('hidden');
                return;
            }
            
            container.classList.remove('hidden');
            
            tbody.innerHTML = state.forecastData.map(row => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-800">${row.month}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">${row.predicted.toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${row.lower.toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${row.upper.toFixed(2)}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${row.model_version}</td>
                </tr>
            `).join('');
        }

        // Render evaluation chart
        function renderEvaluationChart() {
            const canvas = document.getElementById('evaluation-chart');
            const ctx = canvas.getContext('2d');
            
            if (state.charts.evaluation) {
                state.charts.evaluation.destroy();
            }
            
            if (state.evaluationData.length === 0) return;
            
            state.charts.evaluation = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: state.evaluationData.map(d => `${d.crop} - ${d.model}`),
                    datasets: [{
                        label: 'RMSE',
                        data: state.evaluationData.map(d => d.rmse),
                        backgroundColor: 'rgba(59, 130, 246, 0.7)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }

        // Render improvement chart
        function renderImprovementChart() {
            const canvas = document.getElementById('improvement-chart');
            const ctx = canvas.getContext('2d');
            
            if (state.charts.improvement) {
                state.charts.improvement.destroy();
            }
            
            document.getElementById('improvement-crop').textContent = state.selectedCrop.toUpperCase();
            
            if (state.improvementTrendData.length === 0) return;
            
            state.charts.improvement = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: state.improvementTrendData.map(d => `V${d.version}`),
                    datasets: [{
                        label: 'RMSE',
                        data: state.improvementTrendData.map(d => d.rmse),
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
        }

        // Render best models
        function renderBestModels() {
            const container = document.getElementById('best-models-container');
            
            container.innerHTML = state.bestModels.map(model => `
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border border-green-200">
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="text-lg font-bold text-gray-800">${model.crop.toUpperCase()}</h3>
                        <span class="text-2xl">🏆</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-1"><strong>Best Model:</strong> ${model.best_model}</p>
                    <p class="text-sm text-gray-600"><strong>RMSE:</strong> ${model.best_rmse.toFixed(4)}</p>
                </div>
            `).join('');
        }

        // Render training table
        function renderTrainingTable() {
            const tbody = document.getElementById('training-table-body');
            document.getElementById('training-crop').textContent = state.selectedCrop.toUpperCase();
            
            const cropHistory = state.trainingHistoryData.filter(
                item => item.crop.toLowerCase() === state.selectedCrop.toLowerCase()
            );
            
            tbody.innerHTML = cropHistory.map(row => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm text-gray-800">Version ${row.version}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${row.train_range}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">${row.training_years} years</td>
                </tr>
            `).join('');
        }

        // Event handlers
        function handleCropChange() {
            state.selectedCrop = document.getElementById('crop-select').value;
            if (state.activeTab === 'forecast') {
                fetchForecastData();
            } else if (state.activeTab === 'evaluation') {
                fetchImprovementTrend();
            } else if (state.activeTab === 'training') {
                renderTrainingTable();
            }
        }

        function handleModelChange() {
            state.selectedModel = document.getElementById('model-select').value;
            fetchForecastData();
        }

        function handleConfidenceToggle() {
            state.showConfidence = document.getElementById('show-confidence').checked;
            renderForecastChart();
        }

        function setActiveTab(tabName) {
            state.activeTab = tabName;
            
            // Update tab buttons
            ['forecast', 'evaluation', 'training'].forEach(tab => {
                const btn = document.getElementById(`tab-${tab}`);
                const content = document.getElementById(`tab-content-${tab}`);
                
                if (tab === tabName) {
                    btn.classList.remove('tab-inactive');
                    btn.classList.add('tab-active');
                    content.classList.remove('hidden');
                } else {
                    btn.classList.remove('tab-active');
                    btn.classList.add('tab-inactive');
                    content.classList.add('hidden');
                }
            });
            
            // Load data for the active tab
            if (tabName === 'evaluation') {
                fetchEvaluationData();
                fetchImprovementTrend();
            } else if (tabName === 'training') {
                fetchTrainingHistory();
                fetchBestModels();
            }
        }

        function handleRefresh() {
            if (state.activeTab === 'forecast') {
                fetchForecastData();
            } else if (state.activeTab === 'evaluation') {
                fetchEvaluationData();
                fetchImprovementTrend();
            } else if (state.activeTab === 'training') {
                fetchTrainingHistory();
                fetchBestModels();
            }
        }

        function handleExport() {
            if (state.forecastData.length === 0) return;
            
            const csvContent = [
                ['Month', 'Predicted', 'Lower Bound', 'Upper Bound', 'Model Version'],
                ...state.forecastData.map(d => [
                    d.month, 
                    d.predicted, 
                    d.lower, 
                    d.upper,
                    d.model_version
                ])
            ].map(row => row.join(',')).join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `forecast_${state.selectedCrop}_${state.selectedModel}_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // UI helper functions
        function showLoading(show) {
            document.getElementById('loading-indicator').classList.toggle('hidden', !show);
        }

        function showNoData(show) {
            document.getElementById('no-data-indicator').classList.toggle('hidden', !show);
            document.getElementById('forecast-chart-container').classList.toggle('hidden', show);
        }

        function showError(message) {
            document.getElementById('error-message').classList.remove('hidden');
            document.getElementById('error-text').textContent = message;
        }

        function hideError() {
            document.getElementById('error-message').classList.add('hidden');
        }
    </script>
</body>
</html>
<?php
// Include footer if you have one
// include('../partials/footer.php');
?>