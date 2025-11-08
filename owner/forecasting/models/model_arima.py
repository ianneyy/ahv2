"""
SARIMA Rolling Forecast Model

Generates forecasts with rolling windows AND validates them.
SARIMA = Seasonal ARIMA, captures yearly patterns in monthly data.

Usage:
python model_arima.py buko
"""

import pandas as pd
import numpy as np
import mysql.connector
from statsmodels.tsa.statespace.sarimax import SARIMAX
from datetime import datetime
import warnings
warnings.filterwarnings('ignore')
import sys
sys.stdout.reconfigure(encoding='utf-8')
# ============================================
# DATABASE CONFIGURATION
# ============================================
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'ahv2_db'
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
# SARIMA CONFIGURATION
# ============================================
# Non-seasonal ARIMA(p, d, q)
ARIMA_ORDER = (1, 1, 1)
# Seasonal order = (P, D, Q, S)
# S=12 → monthly data with yearly seasonality
SEASONAL_ORDER = (1, 1, 1, 12)

# ============================================
# EVALUATION METRICS
# ============================================
def calculate_mape(actual, predicted):
    """Calculate Mean Absolute Percentage Error"""
    actual = np.array(actual)
    predicted = np.array(predicted)
    
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

def calculate_mae(actual, predicted):
    """Calculate Mean Absolute Error"""
    actual = np.array(actual)
    predicted = np.array(predicted)
    
    mae = np.mean(np.abs(actual - predicted))
    return round(mae, 2)

# ============================================
# MAIN FUNCTION
# ============================================
def generate_rolling_forecast(crop_type):
    """
    Generate ARIMA forecasts with rolling windows and evaluation.
    """
    
    db = mysql.connector.connect(**DB_CONFIG)
    cursor = db.cursor()
    
    print(f"\n{'='*70}")
    print(f"🌾 SARIMA ROLLING FORECAST WITH EVALUATION FOR: {crop_type.upper()}")
    print(f"{'='*70}\n")
    
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
    all_data['total_quantity'] = all_data['total_quantity'].astype(float)
    all_data.set_index('month_date', inplace=True)
    
    print(f"📊 Total historical records: {len(all_data)}")
    print(f"📅 Date range: {all_data.index.min().date()} to {all_data.index.max().date()}")
    print(f"🔧 SARIMA Order: {ARIMA_ORDER}, Seasonal: {SEASONAL_ORDER}\n")
    
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
        
        # Filter training data
        train_data = all_data[all_data.index <= train_end].copy()
        
        if len(train_data) < 12:  # Need at least 12 months for ARIMA
            print(f"⚠️  Insufficient training data for {version} (only {len(train_data)} points). Skipping.\n")
            continue
        
        print(f"   • Training data points: {len(train_data)}")
        print(f"   • Training period: {train_data.index.min().date()} to {train_data.index.max().date()}")
        
        # --------------------------------------------
        # Train ARIMA model
        # --------------------------------------------
        try:
            # Fit SARIMA model
            model = SARIMAX(
                train_data['total_quantity'],
                order=ARIMA_ORDER,
                seasonal_order=SEASONAL_ORDER,
                enforce_stationarity=False,
                enforce_invertibility=False
            )
            fitted_model = model.fit(disp=False)
            
            # Generate 12-month forecast with confidence intervals
            forecast_result = fitted_model.get_forecast(steps=12)
            forecast_mean = forecast_result.predicted_mean
            conf_int = forecast_result.conf_int()
            
            # Create forecast dataframe
            last_date = train_data.index.max()
            forecast_dates = pd.date_range(
                start=last_date + pd.DateOffset(months=1),
                periods=12,
                freq='MS'
            )
            
            forecast_df = pd.DataFrame({
                'ds': forecast_dates,
                'yhat': forecast_mean.values,
                'yhat_lower': conf_int.iloc[:, 0].values,
                'yhat_upper': conf_int.iloc[:, 1].values
            })
            
            # Filter only forecast year
            forecast_start = pd.to_datetime(f"{forecast_year}-01-01")
            forecast_end = pd.to_datetime(f"{forecast_year}-12-31")
            future_forecast = forecast_df[
                (forecast_df['ds'] >= forecast_start) & 
                (forecast_df['ds'] <= forecast_end)
            ]
            
            print(f"   • Generated {len(future_forecast)} monthly predictions for {forecast_year}")
            
            # --------------------------------------------
            # Log model version
            # --------------------------------------------
            training_start = train_data.index.min().strftime('%Y-%m-%d')
            training_end = train_data.index.max().strftime('%Y-%m-%d')
            forecast_start_date = future_forecast['ds'].min().strftime('%Y-%m-%d')
            forecast_end_date = future_forecast['ds'].max().strftime('%Y-%m-%d')
            
            cursor.execute("""
                SELECT model_id FROM forecast_models
                WHERE crop_type = %s AND model_version = %s AND method = 'SARIMA'
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
                """, (version, crop_type, 'SARIMA', training_start, training_end,
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
                    WHERE crop_type = %s AND predicted_month = %s 
                    AND model_version = %s AND method = 'SARIMA'
                """, (crop_type, month_str, version))
                
                cursor.execute("""
                    INSERT INTO yield_predictions
                    (crop_type, predicted_month, predicted_quantity, confidence_lower, 
                     confidence_upper, model_version, method)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """, (crop_type, month_str, predicted, lower, upper, version, 'SARIMA'))
            
            db.commit()
            print(f"   ✓ Saved {len(future_forecast)} predictions to database")
            
            # --------------------------------------------
            # EVALUATE: Compare predictions with actual data
            # --------------------------------------------
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
            mae = calculate_mae(actual_values, predicted_values)
            
            print(f"\n   📊 EVALUATION RESULTS:")
            print(f"      • Months compared: {len(comparison)}")
            print(f"      • MAPE: {mape}%")
            print(f"      • RMSE: {rmse} pieces")
            print(f"      • MAE: {mae} pieces")
            
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
                WHERE crop_type = %s AND model_version = %s AND method = 'SARIMA'
            """, (crop_type, version))
            
            cursor.execute("""
                INSERT INTO model_evaluation
                (model_version, crop_type, method, forecast_year, mape, rmse, mae, data_points_compared)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (version, crop_type, 'SARIMA', forecast_year, mape, rmse, mae, len(comparison)))
            
            db.commit()
            print(f"   ✓ Evaluation metrics saved to database\n")
            
        except Exception as e:
            print(f"   ✗ Error in {version}: {str(e)}\n")
            continue
    
    # --------------------------------------------
    # Summary
    # --------------------------------------------
    print(f"{'='*70}")
    print(f"✅ SARIMA ROLLING FORECAST COMPLETE FOR {crop_type.upper()}")
    print(f"{'='*70}\n")
    
    # Show overall performance summary
    cursor.execute("""
        SELECT model_version, forecast_year, mape, rmse, mae, data_points_compared
        FROM model_evaluation
        WHERE crop_type = %s AND method = 'SARIMA'
        ORDER BY model_version
    """, (crop_type,))
    
    eval_results = cursor.fetchall()
    
    if eval_results:
        print("📊 SARIMA PERFORMANCE SUMMARY:")
        print(f"{'Version':<10} {'Year':<8} {'MAPE':<10} {'RMSE':<12} {'MAE':<12} {'Months'}")
        print("─" * 70)
        for row in eval_results:
            mae_str = f"{row[4]}" if row[4] is not None else "N/A"
            print(f"{row[0]:<10} {row[1]:<8} {row[2]}%{' ':<6} {row[3]:<12} {mae_str:<12} {row[5]}")
        
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
        print("Usage: python model_arima.py <crop_type>")
        print("Example: python model_arima.py buko")
        exit()
    
    crop = sys.argv[1]
    generate_rolling_forecast(crop)