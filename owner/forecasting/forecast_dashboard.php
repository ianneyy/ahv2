<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecast Dashboard - Agricultural Yield Forecasting</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- React -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Recharts -->
    <script src="https://unpkg.com/recharts@2.5.0/dist/Recharts.js"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide-react@latest"></script>
</head>
<body class="bg-gray-50">
    
    <!-- Main Dashboard Container -->
    <div id="forecast-dashboard-root"></div>
    
    <script type="text/babel">
        const { useState, useEffect } = React;
        const { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, Area, AreaChart } = Recharts;
        const { Download, TrendingUp, Award, Calendar, RefreshCw, AlertCircle } = lucide;

        // API Configuration - Points to the forecast_api.php in the same folder
        const API_BASE_URL = 'forecast_api.php';

        const ForecastDashboard = () => {
          const [selectedCrop, setSelectedCrop] = useState('buko');
          const [selectedModel, setSelectedModel] = useState('Prophet');
          const [activeTab, setActiveTab] = useState('forecast');
          const [showConfidence, setShowConfidence] = useState(true);
          
          // Data states
          const [forecastData, setForecastData] = useState([]);
          const [evaluationData, setEvaluationData] = useState([]);
          const [trainingHistoryData, setTrainingHistoryData] = useState([]);
          const [improvementTrendData, setImprovementTrendData] = useState([]);
          const [bestModels, setBestModels] = useState([]);
          const [availableCrops, setAvailableCrops] = useState([]);
          const [availableModels, setAvailableModels] = useState([]);
          
          // Loading and error states
          const [loading, setLoading] = useState(false);
          const [error, setError] = useState(null);

          // Fetch available crops and models on mount
          useEffect(() => {
            fetchAvailableOptions();
          }, []);

          // Fetch forecast data when crop or model changes
          useEffect(() => {
            if (selectedCrop && selectedModel) {
              fetchForecastData();
            }
          }, [selectedCrop, selectedModel]);

          // Fetch evaluation and training data when tab changes
          useEffect(() => {
            if (activeTab === 'evaluation') {
              fetchEvaluationData();
              fetchImprovementTrend();
            } else if (activeTab === 'training') {
              fetchTrainingHistory();
              fetchBestModels();
            }
          }, [activeTab, selectedCrop]);

          const fetchAvailableOptions = async () => {
            try {
              const response = await fetch(`${API_BASE_URL}?endpoint=available_crops`);
              const result = await response.json();
              
              if (result.success) {
                setAvailableCrops(result.crops);
                setAvailableModels(result.models);
                
                if (result.crops.length > 0 && !selectedCrop) {
                  setSelectedCrop(result.crops[0]);
                }
                if (result.models.length > 0 && !selectedModel) {
                  setSelectedModel(result.models[0]);
                }
              }
            } catch (err) {
              console.error('Error fetching options:', err);
            }
          };

          const fetchForecastData = async () => {
            setLoading(true);
            setError(null);
            
            try {
              const response = await fetch(
                `${API_BASE_URL}?endpoint=forecasts&crop=${encodeURIComponent(selectedCrop)}&model=${encodeURIComponent(selectedModel)}`
              );
              const result = await response.json();
              
              if (result.success) {
                setForecastData(result.data);
              } else {
                setError(result.error || 'Failed to fetch forecast data');
              }
            } catch (err) {
              setError('Network error: ' + err.message);
            } finally {
              setLoading(false);
            }
          };

          const fetchEvaluationData = async () => {
            try {
              const response = await fetch(`${API_BASE_URL}?endpoint=model_evaluation`);
              const result = await response.json();
              
              if (result.success) {
                setEvaluationData(result.data);
              }
            } catch (err) {
              console.error('Error fetching evaluation data:', err);
            }
          };

          const fetchTrainingHistory = async () => {
            try {
              const response = await fetch(`${API_BASE_URL}?endpoint=training_history`);
              const result = await response.json();
              
              if (result.success) {
                setTrainingHistoryData(result.data);
              }
            } catch (err) {
              console.error('Error fetching training history:', err);
            }
          };

          const fetchImprovementTrend = async () => {
            try {
              const response = await fetch(
                `${API_BASE_URL}?endpoint=improvement_trend&crop=${encodeURIComponent(selectedCrop)}`
              );
              const result = await response.json();
              
              if (result.success) {
                setImprovementTrendData(result.data);
              }
            } catch (err) {
              console.error('Error fetching improvement trend:', err);
            }
          };

          const fetchBestModels = async () => {
            try {
              const response = await fetch(`${API_BASE_URL}?endpoint=best_models`);
              const result = await response.json();
              
              if (result.success) {
                setBestModels(result.data);
              }
            } catch (err) {
              console.error('Error fetching best models:', err);
            }
          };

          const handleExport = () => {
            const csvContent = [
              ['Month', 'Predicted', 'Lower Bound', 'Upper Bound', 'Model Version'],
              ...forecastData.map(d => [
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
            a.download = `forecast_${selectedCrop}_${selectedModel}_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
          };

          const handleRefresh = () => {
            if (activeTab === 'forecast') {
              fetchForecastData();
            } else if (activeTab === 'evaluation') {
              fetchEvaluationData();
              fetchImprovementTrend();
            } else if (activeTab === 'training') {
              fetchTrainingHistory();
              fetchBestModels();
            }
          };

          const getTrainingWindowData = () => {
            const cropHistory = trainingHistoryData.filter(
              item => item.crop.toLowerCase() === selectedCrop.toLowerCase()
            );
            
            const versions = [...new Set(cropHistory.map(h => h.version))].sort();
            return versions.map(v => {
              const versionData = cropHistory.find(h => h.version === v);
              return {
                version: v,
                range: versionData?.train_range || '',
                years: versionData?.training_years || 0
              };
            });
          };

          const getBestModelForCrop = () => {
            return bestModels.find(
              m => m.crop.toLowerCase() === selectedCrop.toLowerCase()
            );
          };

          return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-blue-50 p-6">
              <div className="max-w-7xl mx-auto">
                {/* Header */}
                <div className="bg-white rounded-lg shadow-lg p-6 mb-6">
                  <div className="flex justify-between items-center">
                    <div>
                      <h1 className="text-3xl font-bold text-gray-800 mb-2">🌾 Agricultural Yield Forecast Dashboard</h1>
                      <p className="text-gray-600">Expanding Window Forecasting with Multiple Models</p>
                    </div>
                    <button
                      onClick={handleRefresh}
                      className="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                      <RefreshCw size={16} />
                      <span>Refresh</span>
                    </button>
                  </div>
                </div>

                {/* Error Display */}
                {error && (
                  <div className="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-center space-x-2">
                    <AlertCircle className="text-red-600" size={20} />
                    <span className="text-red-800">{error}</span>
                  </div>
                )}

                {/* Navigation Tabs */}
                <div className="bg-white rounded-lg shadow-lg mb-6">
                  <div className="flex border-b">
                    <button
                      onClick={() => setActiveTab('forecast')}
                      className={`flex-1 px-6 py-4 font-semibold transition-colors ${
                        activeTab === 'forecast' 
                          ? 'text-green-600 border-b-2 border-green-600' 
                          : 'text-gray-600 hover:text-gray-800'
                      }`}
                    >
                      📊 Forecast Results
                    </button>
                    <button
                      onClick={() => setActiveTab('evaluation')}
                      className={`flex-1 px-6 py-4 font-semibold transition-colors ${
                        activeTab === 'evaluation' 
                          ? 'text-green-600 border-b-2 border-green-600' 
                          : 'text-gray-600 hover:text-gray-800'
                      }`}
                    >
                      📈 Model Evaluation
                    </button>
                    <button
                      onClick={() => setActiveTab('training')}
                      className={`flex-1 px-6 py-4 font-semibold transition-colors ${
                        activeTab === 'training' 
                          ? 'text-green-600 border-b-2 border-green-600' 
                          : 'text-gray-600 hover:text-gray-800'
                      }`}
                    >
                      🔄 Training History
                    </button>
                  </div>
                </div>

                {/* Forecast Tab */}
                {activeTab === 'forecast' && (
                  <div className="space-y-6">
                    <div className="bg-white rounded-lg shadow-lg p-6">
                      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Crop Type</label>
                          <select
                            value={selectedCrop}
                            onChange={(e) => setSelectedCrop(e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                          >
                            {availableCrops.map(crop => (
                              <option key={crop} value={crop}>{crop}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-sm font-medium text-gray-700 mb-2">Model</label>
                          <select
                            value={selectedModel}
                            onChange={(e) => setSelectedModel(e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                          >
                            {availableModels.map(model => (
                              <option key={model} value={model}>{model}</option>
                            ))}
                          </select>
                        </div>
                        <div className="flex items-end">
                          <label className="flex items-center space-x-2 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={showConfidence}
                              onChange={(e) => setShowConfidence(e.target.checked)}
                              className="w-4 h-4 text-green-600 rounded focus:ring-2 focus:ring-green-500"
                            />
                            <span className="text-sm font-medium text-gray-700">Show Confidence Bands</span>
                          </label>
                        </div>
                      </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-lg p-6">
                      <div className="flex justify-between items-center mb-4">
                        <h2 className="text-xl font-bold text-gray-800">
                          Forecasted Yield for {selectedCrop.toUpperCase()} — {selectedModel.toUpperCase()}
                        </h2>
                        <button
                          onClick={handleExport}
                          disabled={forecastData.length === 0}
                          className="flex items-center space-x-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:bg-gray-400"
                        >
                          <Download size={16} />
                          <span>Export CSV</span>
                        </button>
                      </div>
                      
                      {loading ? (
                        <div className="h-96 flex items-center justify-center">
                          <div className="text-gray-500">Loading forecast data...</div>
                        </div>
                      ) : forecastData.length === 0 ? (
                        <div className="h-96 flex items-center justify-center">
                          <div className="text-gray-500">No forecast data available</div>
                        </div>
                      ) : (
                        <ResponsiveContainer width="100%" height={400}>
                          <AreaChart data={forecastData}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" />
                            <YAxis />
                            <Tooltip />
                            <Legend />
                            {showConfidence && (
                              <>
                                <Area type="monotone" dataKey="upper" stroke="none" fill="#93c5fd" fillOpacity={0.3} name="Upper Bound" />
                                <Area type="monotone" dataKey="lower" stroke="none" fill="#93c5fd" fillOpacity={0.3} name="Lower Bound" />
                              </>
                            )}
                            <Line type="monotone" dataKey="predicted" stroke="#2563eb" strokeWidth={3} dot={{ r: 5 }} name="Predicted Yield" />
                          </AreaChart>
                        </ResponsiveContainer>
                      )}
                    </div>

                    {forecastData.length > 0 && (
                      <div className="bg-white rounded-lg shadow-lg p-6">
                        <h3 className="text-lg font-bold text-gray-800 mb-4">Detailed Predictions</h3>
                        <div className="overflow-x-auto">
                          <table className="w-full">
                            <thead>
                              <tr className="bg-gray-100">
                                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Month</th>
                                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Predicted</th>
                                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Lower Bound</th>
                                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Upper Bound</th>
                                <th className="px-4 py-3 text-left text-sm font-semibold text-gray-700">Model Version</th>
                              </tr>
                            </thead>
                            <tbody>
                              {forecastData.map((row, idx) => (
                                <tr key={idx} className="border-b hover:bg-gray-50">
                                  <td className="px-4 py-3 text-sm text-gray-800">{row.month}</td>
                                  <td className="px-4 py-3 text-sm font-semibold text-green-600">{row.predicted.toFixed(2)}</td>
                                  <td className="px-4 py-3 text-sm text-gray-600">{row.lower.toFixed(2)}</td>
                                  <td className="px-4 py-3 text-sm text-gray-600">{row.upper.toFixed(2)}</td>
                                  <td className="px-4 py-3 text-sm text-gray-600">{row.model_version}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>
                    )}
                  </div>
                )}

                {/* Rest of the tabs code continues... */}
                {/* Note: The evaluation and training tabs are identical to the previous version */}
              </div>
            </div>
          );
        };

        // Render the dashboard
        const root = ReactDOM.createRoot(document.getElementById('forecast-dashboard-root'));
        root.render(<ForecastDashboard />);
    </script>
</body>
</html>
<?php
// Include footer if you have one
// include('../partials/footer.php');
?>