"""
Prophet Rolling Forecast with Model Evaluation

Generates forecasts with rolling windows AND validates them:
- v1: Train 2016-2020, Forecast 2021, Evaluate vs actual 2021
- v2: Train 2016-2021, Forecast 2022, Evaluate vs actual 2022
- v3: Train 2016-2022, Forecast 2023, Evaluate vs actual 2023
- v4: Train 2016-2023, Forecast 2024, Evaluate vs actual 2024

Calculates MAPE and RMSE for each version.

Usage:
python model_prophet.py buko
"""

import pandas as pd
import numpy as np
import mysql.connector
from prophet import Prophet
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')

# ============================================
# DATABASE CONFIGURATION
# ============================================
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'ahv2_db',
    'port': 3307
}

# ============================================
# ROLLING FORECAST CONFIGURATION
# ============================================
ROLLING_WINDOWS = [
    {'version': 'v1', 'train_end': '2020-12-31', 'forecast_year': 2021},
    {'version': 'v2', 'train_end': '2021-12-31', 'forecast_year': 2022},
    {'version': 'v3', 'train_end': '2022-12-31', 'forecast_year': 2023},
    {'version': 'v4', 'train_end': '2023-12-31', 'forecast_year': 2024},
]

# ============================================
# EVALUATION METRICS
# ============================================
def calculate_mape(actual, predicted):
    """Calculate Mean Absolute Percentage Error"""
    actual = np.array(actual)
    predicted = np.array(predicted)
    
    # Avoid division by zero
    mask = actual != 0
    if not mask.any():
        return None
    
    mape = np.mean(np.abs((actual[mask] - predicted[mask]) / actual[mask])) * 100
    return round(mape, 2)

def calculate_rmse(actual, predicted):
    """Calculate Root Mean Squared Error"""
    actual = np.array(actual)
    predicted = np.array(predicted)
    
    mse = np.mean((actual - predicted) ** 2)
    rmse = np.sqrt(mse)
    return round(rmse, 2)

# ============================================
# MAIN FUNCTION
# ============================================
def generate_rolling_forecast(crop_type):
    """
    Generate Prophet forecasts with rolling windows and evaluation.
    """
    
    db = mysql.connector.connect(**DB_CONFIG)
    cursor = db.cursor()
    
    print(f"\n{'='*70}")
    print(f"🌾 ROLLING FORECAST WITH EVALUATION FOR: {crop_type.upper()}")
    print(f"{'='*70}\n")
    
    # --------------------------------------------
    # Create evaluation table if not exists
    # --------------------------------------------
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS model_evaluation (
            eval_id INT AUTO_INCREMENT PRIMARY KEY,
            model_version VARCHAR(20),
            crop_type VARCHAR(100),
            method VARCHAR(50),
            forecast_year INT,
            mape DECIMAL(10,2),
            rmse DECIMAL(10,2),
            data_points_compared INT,
            evaluated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_model_crop (model_version, crop_type, method)
        )
    """)
    db.commit()
    
    # --------------------------------------------
    # Fetch ALL historical data
    # --------------------------------------------
    cursor.execute("""
        SELECT 
            DATE_FORMAT(recorded_at, '%Y-%m-01') as month_date,
            SUM(quantity) as total_quantity
        FROM yield_records
        WHERE crop_type = %s AND quantity > 0
        GROUP BY DATE_FORMAT(recorded_at, '%Y-%m')
        ORDER BY month_date
    """, (crop_type,))
    
    rows = cursor.fetchall()
    all_data = pd.DataFrame(rows, columns=['month_date', 'total_quantity'])
    
    if all_data.empty:
        print(f"✗ No historical data found for {crop_type}. Skipping.")
        db.close()
        return
    
    all_data['month_date'] = pd.to_datetime(all_data['month_date'])
    print(f"📊 Total historical records: {len(all_data)}")
    print(f"📅 Date range: {all_data['month_date'].min().date()} to {all_data['month_date'].max().date()}\n")
    
    # --------------------------------------------
    # Process each rolling window
    # --------------------------------------------
    for window in ROLLING_WINDOWS:
        version = window['version']
        train_end = window['train_end']
        forecast_year = window['forecast_year']
        
        print(f"{'─'*70}")
        print(f"📈 {version}: Train until {train_end} → Forecast {forecast_year}")
        print(f"{'─'*70}")
        
        # Filter training data (up to train_end)
        train_data = all_data[all_data['month_date'] <= train_end].copy()
        
        if len(train_data) < 3:
            print(f"⚠️  Insufficient training data for {version} (only {len(train_data)} points). Skipping.\n")
            continue
        
        train_data.rename(columns={'month_date': 'ds', 'total_quantity': 'y'}, inplace=True)
        
        print(f"   • Training data points: {len(train_data)}")
        print(f"   • Training period: {train_data['ds'].min().date()} to {train_data['ds'].max().date()}")
        
        # --------------------------------------------
        # Train Prophet model
        # --------------------------------------------
        try:
            model = Prophet(
                yearly_seasonality=True,
                weekly_seasonality=False,
                daily_seasonality=False,
                interval_width=0.8
            )
            model.fit(train_data)
            
            # Generate 12-month forecast
            future = model.make_future_dataframe(periods=12, freq='MS')
            forecast = model.predict(future)
            
            # Get predictions for the forecast year
            forecast_start = pd.to_datetime(f"{forecast_year}-01-01")
            forecast_end = pd.to_datetime(f"{forecast_year}-12-31")
            future_forecast = forecast[
                (forecast['ds'] >= forecast_start) & 
                (forecast['ds'] <= forecast_end)
            ]
            
            print(f"   • Generated {len(future_forecast)} monthly predictions for {forecast_year}")
            
            # --------------------------------------------
            # Log model version
            # --------------------------------------------
            training_start = train_data['ds'].min().strftime('%Y-%m-%d')
            training_end = train_data['ds'].max().strftime('%Y-%m-%d')
            forecast_start_date = future_forecast['ds'].min().strftime('%Y-%m-%d')
            forecast_end_date = future_forecast['ds'].max().strftime('%Y-%m-%d')
            
            cursor.execute("""
                SELECT model_id FROM forecast_models
                WHERE crop_type = %s AND model_version = %s AND method = 'Prophet'
            """, (crop_type, version))
            
            existing = cursor.fetchone()
            
            if existing:
                cursor.execute("""
                    UPDATE forecast_models
                    SET training_start = %s, training_end = %s,
                        forecast_start = %s, forecast_end = %s,
                        created_at = NOW()
                    WHERE model_id = %s
                """, (training_start, training_end, forecast_start_date, 
                      forecast_end_date, existing[0]))
            else:
                cursor.execute("""
                    INSERT INTO forecast_models
                    (model_version, crop_type, method, training_start, training_end, 
                     forecast_start, forecast_end)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """, (version, crop_type, 'Prophet', training_start, training_end,
                      forecast_start_date, forecast_end_date))
            
            db.commit()
            
            # --------------------------------------------
            # Save predictions to database
            # --------------------------------------------
            for _, row in future_forecast.iterrows():
                month_str = row['ds'].strftime('%Y-%m')
                predicted = max(round(row['yhat'], 2), 0)
                lower = max(round(row['yhat_lower'], 2), 0)
                upper = max(round(row['yhat_upper'], 2), 0)
                
                cursor.execute("""
                    DELETE FROM yield_predictions
                    WHERE crop_type = %s AND predicted_month = %s AND model_version = %s
                """, (crop_type, month_str, version))
                
                cursor.execute("""
                    INSERT INTO yield_predictions
                    (crop_type, predicted_month, predicted_quantity, confidence_lower, 
                     confidence_upper, model_version)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """, (crop_type, month_str, predicted, lower, upper, version))
            
            db.commit()
            print(f"   ✓ Saved {len(future_forecast)} predictions to database")
            
            # --------------------------------------------
            # EVALUATE: Compare predictions with actual data
            # --------------------------------------------
            # Get actual data for the forecast year from database directly
            cursor.execute("""
                SELECT 
                    DATE_FORMAT(recorded_at, '%Y-%m-01') as month_date,
                    SUM(quantity) as total_quantity
                FROM yield_records
                WHERE crop_type = %s 
                AND YEAR(recorded_at) = %s
                AND quantity > 0
                GROUP BY DATE_FORMAT(recorded_at, '%Y-%m')
                ORDER BY month_date
            """, (crop_type, forecast_year))
            
            actual_rows = cursor.fetchall()
            actual_data = pd.DataFrame(actual_rows, columns=['month_date', 'total_quantity'])
            
            if actual_data.empty:
                print(f"   ⚠️  No actual data available for {forecast_year} - cannot evaluate yet")
                print(f"      (Predictions saved, evaluation pending)\n")
                continue
            
            actual_data['month_date'] = pd.to_datetime(actual_data['month_date'])
            actual_data['total_quantity'] = actual_data['total_quantity'].astype(float)
            
            # Merge predictions with actual data
            future_forecast['month_date'] = future_forecast['ds']
            comparison = pd.merge(
                actual_data[['month_date', 'total_quantity']],
                future_forecast[['month_date', 'yhat']],
                on='month_date',
                how='inner'
            )
            
            if len(comparison) == 0:
                print(f"   ⚠️  Could not match predictions with actual data for evaluation\n")
                continue
            
            # Calculate metrics
            actual_values = comparison['total_quantity'].values
            predicted_values = comparison['yhat'].values
            
            mape = calculate_mape(actual_values, predicted_values)
            rmse = calculate_rmse(actual_values, predicted_values)
            
            print(f"\n   📊 EVALUATION RESULTS:")
            print(f"      • Months compared: {len(comparison)}")
            print(f"      • MAPE: {mape}%")
            print(f"      • RMSE: {rmse}")
            
            # Show detailed comparison
            print(f"\n   📋 Month-by-month comparison:")
            for _, row in comparison.iterrows():
                month = row['month_date'].strftime('%Y-%m')
                actual = row['total_quantity']
                pred = row['yhat']
                error = abs(actual - pred)
                error_pct = (error / actual * 100) if actual > 0 else 0
                print(f"      {month}: Actual={actual:.2f}, Predicted={pred:.2f}, Error={error:.2f} ({error_pct:.1f}%)")
            
            # Save evaluation metrics
            cursor.execute("""
                DELETE FROM model_evaluation
                WHERE crop_type = %s AND model_version = %s AND method = 'Prophet'
            """, (crop_type, version))
            
            cursor.execute("""
                INSERT INTO model_evaluation
                (model_version, crop_type, method, forecast_year, mape, rmse, data_points_compared)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """, (version, crop_type, 'Prophet', forecast_year, mape, rmse, len(comparison)))
            
            db.commit()
            print(f"   ✓ Evaluation metrics saved to database\n")
            
        except Exception as e:
            print(f"   ✗ Error in {version}: {str(e)}\n")
            continue
    
    # --------------------------------------------
    # Summary
    # --------------------------------------------
    print(f"{'='*70}")
    print(f"✅ ROLLING FORECAST COMPLETE FOR {crop_type.upper()}")
    print(f"{'='*70}\n")
    
    # Show overall performance summary
    cursor.execute("""
        SELECT model_version, forecast_year, mape, rmse, data_points_compared
        FROM model_evaluation
        WHERE crop_type = %s AND method = 'Prophet'
        ORDER BY model_version
    """, (crop_type,))
    
    eval_results = cursor.fetchall()
    
    if eval_results:
        print("📊 PERFORMANCE SUMMARY:")
        print(f"{'Version':<10} {'Year':<8} {'MAPE':<10} {'RMSE':<12} {'Months'}")
        print("─" * 60)
        for row in eval_results:
            print(f"{row[0]:<10} {row[1]:<8} {row[2]}%{' ':<6} {row[3]:<12} {row[4]}")
        
        # Show best performing version
        best = min(eval_results, key=lambda x: x[2] if x[2] is not None else float('inf'))
        print(f"\n🏆 Best performing: {best[0]} (MAPE: {best[2]}%)")
    else:
        print("⚠️  No evaluation results available yet (need actual data for forecast years)")
    
    cursor.close()
    db.close()

# --------------------------------------------
# Run from command line
# --------------------------------------------
if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Usage: python model_prophet.py <crop_type>")
        print("Example: python model_prophet.py buko")
        exit()
    
    crop = sys.argv[1]
    generate_rolling_forecast(crop)