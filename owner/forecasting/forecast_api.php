<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Include session and database connection
require_once(__DIR__ . '/../../includes/session.php');
require_once(__DIR__ . '/../../includes/db.php');

// Now your PDO connection inside db.php will be available



$host = '127.0.0.1';
$dbname = 'ahv2_db';
$username = 'root';
$password = '';
$port = 3307;

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}


// Get endpoint from query parameter
$endpoint = $_GET['endpoint'] ?? '';

switch ($endpoint) {
    case 'forecasts':
        getForecasts($pdo);
        break;
    case 'model_evaluation':
        getModelEvaluation($pdo);
        break;
    case 'training_history':
        getTrainingHistory($pdo);
        break;
    case 'improvement_trend':
        getImprovementTrend($pdo);
        break;
    case 'best_models':
        getBestModels($pdo);
        break;
    case 'available_crops':
        getAvailableCrops($pdo);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid endpoint']);
        break;
}

// 1. GET FORECASTS BY CROP AND MODEL
function getForecasts($pdo) {
    $crop = $_GET['crop'] ?? '';
    $model = $_GET['model'] ?? '';
    
    if (empty($crop) || empty($model)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing crop or model parameter']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                predicted_month as month,
                predicted_quantity as predicted,
                confidence_lower as lower,
                confidence_upper as upper,
                model_version,
                method,
                generated_at
            FROM yield_predictions
            WHERE crop_type = :crop 
            AND method = :method
            ORDER BY predicted_month ASC
        ");
        
        $stmt->execute([
            'crop' => $crop,
            'method' => $model
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert string values to float for charting
        foreach ($results as &$row) {
            $row['predicted'] = (float)$row['predicted'];
            $row['lower'] = (float)$row['lower'];
            $row['upper'] = (float)$row['upper'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
    }
}

// 2. GET MODEL EVALUATION METRICS
function getModelEvaluation($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                crop_type as crop,
                method as model,
                AVG(mape) as avg_mape,
                AVG(rmse) as avg_rmse,
                AVG(mae) as avg_mae,
                COUNT(*) as evaluation_count
            FROM model_evaluation
            GROUP BY crop_type, method
            ORDER BY crop_type, avg_mape ASC
        ");
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert to float and add ranking
        $cropRanks = [];
        foreach ($results as &$row) {
            $row['avg_mape'] = (float)$row['avg_mape'];
            $row['avg_rmse'] = (float)$row['avg_rmse'];
            $row['avg_mae'] = (float)$row['avg_mae'];
            
            // Calculate rank per crop
            $crop = $row['crop'];
            if (!isset($cropRanks[$crop])) {
                $cropRanks[$crop] = 1;
            }
            $row['rank'] = $cropRanks[$crop]++;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
    }
}

// 3. GET TRAINING HISTORY (EXPANDING WINDOW EVIDENCE)
function getTrainingHistory($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                model_version as version,
                crop_type as crop,
                method as model,
                DATE_FORMAT(training_start, '%Y') as train_start_year,
                DATE_FORMAT(training_end, '%Y') as train_end_year,
                DATE_FORMAT(forecast_start, '%Y') as forecast_year,
                CONCAT(DATE_FORMAT(training_start, '%Y'), '-', 
                       DATE_FORMAT(training_end, '%Y')) as train_range,
                DATE_FORMAT(forecast_start, '%Y') as test_range,
                accuracy_mape as mape,
                accuracy_rmse as rmse,
                TIMESTAMPDIFF(YEAR, training_start, training_end) as training_years
            FROM forecast_models
            ORDER BY crop_type, method, model_version ASC
        ");
        
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert numeric values
        foreach ($results as &$row) {
            $row['mape'] = (float)$row['mape'];
            $row['rmse'] = (float)$row['rmse'];
            $row['training_years'] = (int)$row['training_years'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
    }
}

// 4. GET IMPROVEMENT TREND ACROSS VERSIONS
function getImprovementTrend($pdo) {
    $crop = $_GET['crop'] ?? 'buko';
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                model_version as version,
                method,
                accuracy_mape as mape
            FROM forecast_models
            WHERE crop_type = :crop
            ORDER BY model_version ASC, method
        ");
        
        $stmt->execute(['crop' => $crop]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pivot data for charting (version as key, methods as columns)
        $pivoted = [];
        foreach ($results as $row) {
            $version = $row['version'];
            if (!isset($pivoted[$version])) {
                $pivoted[$version] = ['version' => $version];
            }
            $method = strtolower($row['method']);
            $pivoted[$version][$method] = (float)$row['mape'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => array_values($pivoted)
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
    }
}

// 5. GET BEST MODELS PER CROP
function getBestModels($pdo) {
    try {
        // First, get average metrics for all models
        $stmt = $pdo->prepare("
            SELECT 
                crop_type as crop,
                method as model,
                AVG(mape) as avg_mape,
                AVG(rmse) as avg_rmse
            FROM model_evaluation
            GROUP BY crop_type, method
        ");
        
        $stmt->execute();
        $allModels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find the best model per crop (lowest MAPE)
        $bestModels = [];
        $cropGroups = [];
        
        // Group by crop
        foreach ($allModels as $model) {
            $crop = $model['crop'];
            if (!isset($cropGroups[$crop])) {
                $cropGroups[$crop] = [];
            }
            $cropGroups[$crop][] = $model;
        }
        
        // Find best model for each crop
        foreach ($cropGroups as $crop => $models) {
            $bestModel = null;
            $lowestMape = PHP_FLOAT_MAX;
            
            foreach ($models as $model) {
                $mape = (float)$model['avg_mape'];
                if ($mape < $lowestMape) {
                    $lowestMape = $mape;
                    $bestModel = $model;
                }
            }
            
            if ($bestModel) {
                $bestModel['avg_mape'] = (float)$bestModel['avg_mape'];
                $bestModel['avg_rmse'] = (float)$bestModel['avg_rmse'];
                $bestModels[] = $bestModel;
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $bestModels
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
    }
}

// 6. GET AVAILABLE CROPS AND MODELS
function getAvailableCrops($pdo) {
    try {
        $cropStmt = $pdo->query("
            SELECT DISTINCT crop_type 
            FROM yield_predictions 
            ORDER BY crop_type
        ");
        $crops = $cropStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $modelStmt = $pdo->query("
            SELECT DISTINCT method 
            FROM yield_predictions 
            ORDER BY method
        ");
        $models = $modelStmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode([
            'success' => true,
            'crops' => $crops,
            'models' => $models
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Query failed: ' . $e->getMessage()]);
    }
}
?>