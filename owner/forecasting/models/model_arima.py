"""
SARIMA Dynamic Expanding Window Forecast Model

Automatically generates forecasts using expanding windows:
- Detects latest available data
- Uses ALL historical data for training (expanding window)
- Forecasts next 12 months into the future
- Evaluates past forecasts when actual data becomes available

Usage:
python model_arima.py buko
"""

import pandas as pd
import numpy as np
import mysql.connector
from statsmodels.tsa.statespace.sarimax import SARIMAX
from datetime import datetime
from dateutil.relativedelta import relativedelta
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
    'database': 'ahv2_db',
}

# ============================================
# SARIMA CONFIGURATION
# ============================================
ARIMA_ORDER = (1, 1, 1)
SEASONAL_ORDER = (1, 1, 1, 12)

# ============================================
# DYNAMIC WINDOW CONFIGURATION
# ============================================
FORECAST_MONTHS = 12  # How many months to forecast ahead
MIN_TRAINING_POINTS = 12  # Minimum data points needed (2 years)

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
# DYNAMIC WINDOW GENERATION
# ============================================
def generate_expanding_windows(all_data, current_date):
    """
    Generate expanding windows dynamically based on available data.
    
    Returns list of windows, each containing:
    - version: identifier (e.g., 'v2021', 'v2022')
    - train_end: last date to use for training
    - forecast_start: first month to forecast
    - forecast_end: last month to forecast
    """
    if all_data.empty:
        return []
    
    # Get earliest and latest data points
    earliest_date = all_data.index.min()
    latest_date = all_data.index.max()
    
    print(f"📅 Data range: {earliest_date.date()} to {latest_date.date()}")
    print(f"📅 Current date: {current_date.date()}")
    
    windows = []
    
    # Generate windows: start from earliest year + 2 years of data
    start_year = earliest_date.year + 1
    current_year = current_date.year
    
    # Create historical windows (for evaluation)
    for year in range(start_year, current_year + 1):
        train_end = pd.Timestamp(f"{year-1}-12-31")
        
        # Only create window if we have enough training data
        train_data = all_data[all_data.index <= train_end]
        if len(train_data) < MIN_TRAINING_POINTS:
            continue
        
        forecast_start = pd.Timestamp(f"{year}-01-01")
        forecast_end = pd.Timestamp(f"{year}-12-31")
        
        windows.append({
            'version': f'v{year}',
            'train_end': train_end,
            'forecast_start': forecast_start,
            'forecast_end': forecast_end,
            'forecast_year': year,
            'is_future': year > latest_date.year
        })
    
    # Add current/future forecast window
    if latest_date < current_date:
        # We have recent data, forecast from latest_date + 1 month
        train_end = latest_date
        forecast_start = latest_date + relativedelta(months=1)
        forecast_end = forecast_start + relativedelta(months=FORECAST_MONTHS-1)
        
        train_data = all_data[all_data.index <= train_end]
        if len(train_data) >= MIN_TRAINING_POINTS:
            windows.append({
                'version': 'v_current',
                'train_end': train_end,
                'forecast_start': forecast_start,
                'forecast_end': forecast_end,
                'forecast_year': forecast_start.year,
                'is_future': True
            })
    
    return windows

# ============================================
# MAIN FUNCTION
# ============================================
def generate_dynamic_forecast(crop_type):
    """
    Generate SARIMA forecasts with dynamic expanding windows.
    """
    
    db = mysql.connector.connect(**DB_CONFIG)
    cursor = db.cursor()
    
    current_date = pd.Timestamp.now()
    
    print(f"\n{'='*70}")
    print(f"🌾 SARIMA DYNAMIC EXPANDING WINDOW FORECAST: {crop_type.upper()}")
    print(f"{'='*70}\n")
    print(f"🔧 Mode: EXPANDING WINDOW (uses all historical data)")
    print(f"📊 SARIMA Order: {ARIMA_ORDER}, Seasonal: {SEASONAL_ORDER}")
    print(f"📅 Forecast horizon: {FORECAST_MONTHS} months\n")
    
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
    
    # --------------------------------------------
    # Generate dynamic windows
    # --------------------------------------------
    windows = generate_expanding_windows(all_data, current_date)
    
    if not windows:
        print(f"✗ Insufficient data to generate forecasts (need at least {MIN_TRAINING_POINTS} months)")
        db.close()
        return
    
    print(f"✅ Generated {len(windows)} expanding windows\n")
    
    # --------------------------------------------
    # Process each window
    # --------------------------------------------
    for window in windows:
        version = window['version']
        train_end = window['train_end']
        forecast_start = window['forecast_start']
        forecast_end = window['forecast_end']
        is_future = window['is_future']
        
        print(f"{'─'*70}")
        print(f"📈 {version}: Train until {train_end.date()} → Forecast {forecast_start.date()} to {forecast_end.date()}")
        if is_future:
            print(f"   ⚡ FUTURE FORECAST (no actual data yet)")
        print(f"{'─'*70}")
        
        # Filter training data (EXPANDING: use ALL data up to train_end)
        train_data = all_data[all_data.index <= train_end].copy()
        
        if len(train_data) < MIN_TRAINING_POINTS:
            print(f"⚠️  Insufficient training data for {version} (only {len(train_data)} points). Skipping.\n")
            continue
        
        print(f"   • Training data points: {len(train_data)} (EXPANDING)")
        print(f"   • Training period: {train_data.index.min().date()} to {train_data.index.max().date()}")
        
        # --------------------------------------------
        # Train SARIMA model
        # --------------------------------------------
        try:
            model = SARIMAX(
                train_data['total_quantity'],
                order=ARIMA_ORDER,
                seasonal_order=SEASONAL_ORDER,
                enforce_stationarity=False,
                enforce_invertibility=False
            )
            fitted_model = model.fit(disp=False)
            
            # Calculate number of months to forecast
            months_ahead = (forecast_end.year - forecast_start.year) * 12 + \
                          (forecast_end.month - forecast_start.month) + 1
            
            # Generate forecast
            forecast_result = fitted_model.get_forecast(steps=months_ahead)
            forecast_mean = forecast_result.predicted_mean
            conf_int = forecast_result.conf_int()
            
            # Create forecast dates
            last_date = train_data.index.max()
            forecast_dates = pd.date_range(
                start=last_date + pd.DateOffset(months=1),
                periods=months_ahead,
                freq='MS'
            )
            
            forecast_df = pd.DataFrame({
                'ds': forecast_dates,
                'yhat': forecast_mean.values,
                'yhat_lower': conf_int.iloc[:, 0].values,
                'yhat_upper': conf_int.iloc[:, 1].values
            })
            
            # Filter to forecast period
            future_forecast = forecast_df[
                (forecast_df['ds'] >= forecast_start) & 
                (forecast_df['ds'] <= forecast_end)
            ]
            
            print(f"   • Generated {len(future_forecast)} monthly predictions")
            
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
            # Save predictions
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
            # EVALUATE: Only if actual data exists
            # --------------------------------------------
            if not is_future:
                forecast_year = window['forecast_year']
                
                cursor.execute("""
                    SELECT 
                        DATE_FORMAT(recorded_at, '%Y-%m-01') as month_date,
                        SUM(quantity) as total_quantity
                    FROM yield_records
                    WHERE crop_type = %s 
                    AND recorded_at >= %s
                    AND recorded_at <= %s
                    AND quantity > 0
                    GROUP BY DATE_FORMAT(recorded_at, '%Y-%m')
                    ORDER BY month_date
                """, (crop_type, forecast_start.strftime('%Y-%m-%d'), 
                      forecast_end.strftime('%Y-%m-%d')))
                
                actual_rows = cursor.fetchall()
                actual_data = pd.DataFrame(actual_rows, columns=['month_date', 'total_quantity'])
                
                if actual_data.empty:
                    print(f"   ⚠️  No actual data available for evaluation period\n")
                    continue
                
                actual_data['month_date'] = pd.to_datetime(actual_data['month_date'])
                actual_data['total_quantity'] = actual_data['total_quantity'].astype(float)
                
                # Merge predictions with actuals
                future_forecast['month_date'] = future_forecast['ds']
                comparison = pd.merge(
                    actual_data[['month_date', 'total_quantity']],
                    future_forecast[['month_date', 'yhat']],
                    on='month_date',
                    how='inner'
                )
                
                if len(comparison) == 0:
                    print(f"   ⚠️  Could not match predictions with actual data\n")
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
                print(f"   ✓ Evaluation metrics saved\n")
            else:
                print(f"   ⚡ Future forecast saved - will evaluate when actual data arrives\n")
            
        except Exception as e:
            print(f"   ✗ Error in {version}: {str(e)}\n")
            continue
    
    # --------------------------------------------
    # Summary
    # --------------------------------------------
    print(f"{'='*70}")
    print(f"✅ SARIMA DYNAMIC FORECAST COMPLETE FOR {crop_type.upper()}")
    print(f"{'='*70}\n")
    
    cursor.execute("""
        SELECT model_version, forecast_year, mape, rmse, mae, data_points_compared
        FROM model_evaluation
        WHERE crop_type = %s AND method = 'SARIMA'
        ORDER BY model_version
    """, (crop_type,))
    
    eval_results = cursor.fetchall()
    
    if eval_results:
        print("📊 SARIMA PERFORMANCE SUMMARY:")
        print(f"{'Version':<15} {'Year':<8} {'MAPE':<10} {'RMSE':<12} {'MAE':<12} {'Months'}")
        print("─" * 70)
        for row in eval_results:
            mae_str = f"{row[4]}" if row[4] is not None else "N/A"
            print(f"{row[0]:<15} {row[1]:<8} {row[2]}%{' ':<6} {row[3]:<12} {mae_str:<12} {row[5]}")
        
        best = min(eval_results, key=lambda x: x[2] if x[2] is not None else float('inf'))
        print(f"\n🏆 Best performing: {best[0]} (MAPE: {best[2]}%)")
    
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
    generate_dynamic_forecast(crop)