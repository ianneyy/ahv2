"""
Seasonal Naive Baseline - Dynamic Expanding Window

Automatically generates baseline forecasts:
- Predicts this month = same month last year
- Detects latest available data
- Forecasts next 12 months into the future
- Evaluates past forecasts when actual data becomes available

Usage:
python model_baseline.py buko
"""

import pandas as pd
import numpy as np
import mysql.connector
from datetime import datetime
from dateutil.relativedelta import relativedelta
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
# DYNAMIC WINDOW CONFIGURATION
# ============================================
FORECAST_MONTHS = 12  # How many months to forecast ahead
MIN_TRAINING_POINTS = 12  # Need at least 1 year for seasonal baseline

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
    """
    if all_data.empty:
        return []
    
    earliest_date = all_data.index.min()
    latest_date = all_data.index.max()
    
    print(f"📅 Data range: {earliest_date.date()} to {latest_date.date()}")
    print(f"📅 Current date: {current_date.date()}")
    
    windows = []
    
    # Generate windows: start from earliest year + 1 year of data
    start_year = earliest_date.year + 1
    current_year = current_date.year
    
    # Create historical windows (for evaluation)
    for year in range(start_year, current_year + 1):
        train_end = pd.Timestamp(f"{year-1}-12-31")
        
        # Check if we have data from the reference year
        reference_year = year - 1
        reference_data = all_data[all_data.index.year == reference_year]
        
        if len(reference_data) < MIN_TRAINING_POINTS:
            continue
        
        forecast_start = pd.Timestamp(f"{year}-01-01")
        forecast_end = pd.Timestamp(f"{year}-12-31")
        
        windows.append({
            'version': f'v{year}',
            'train_end': train_end,
            'forecast_start': forecast_start,
            'forecast_end': forecast_end,
            'forecast_year': year,
            'reference_year': reference_year,
            'is_future': year > latest_date.year
        })
    
    # Add current/future forecast window
    if latest_date < current_date:
        # For baseline, we need last year's data
        reference_year = latest_date.year
        reference_data = all_data[all_data.index.year == reference_year]
        
        if len(reference_data) >= MIN_TRAINING_POINTS:
            train_end = latest_date
            forecast_start = latest_date + relativedelta(months=1)
            forecast_end = forecast_start + relativedelta(months=FORECAST_MONTHS-1)
            
            windows.append({
                'version': 'v_current',
                'train_end': train_end,
                'forecast_start': forecast_start,
                'forecast_end': forecast_end,
                'forecast_year': forecast_start.year,
                'reference_year': reference_year,
                'is_future': True
            })
    
    return windows

# ============================================
# MAIN FUNCTION
# ============================================
def generate_dynamic_forecast(crop_type):
    """
    Generate Seasonal Naive baseline forecasts dynamically.
    """
    
    db = mysql.connector.connect(**DB_CONFIG)
    cursor = db.cursor()
    
    current_date = pd.Timestamp.now()
    
    print(f"\n{'='*70}")
    print(f"📊 BASELINE DYNAMIC EXPANDING WINDOW FORECAST: {crop_type.upper()}")
    print(f"{'='*70}\n")
    print(f"🔧 Strategy: Predict current month = same month last year")
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
        reference_year = window['reference_year']
        is_future = window['is_future']
        
        print(f"{'─'*70}")
        print(f"📈 {version}: Using {reference_year} data → Forecast {forecast_start.date()} to {forecast_end.date()}")
        if is_future:
            print(f"   ⚡ FUTURE FORECAST (no actual data yet)")
        print(f"{'─'*70}")
        
        # Get reference year data
        reference_data = all_data[all_data.index.year == reference_year].copy()
        
        if len(reference_data) == 0:
            print(f"⚠️  No reference data for {reference_year}. Skipping.\n")
            continue
        
        print(f"   • Using {reference_year} as baseline reference")
        print(f"   • Reference data points: {len(reference_data)}")
        
        # --------------------------------------------
        # Log model version
        # --------------------------------------------
        training_start = reference_data.index.min().strftime('%Y-%m-%d')
        training_end = reference_data.index.max().strftime('%Y-%m-%d')
        forecast_start_date = forecast_start.strftime('%Y-%m-%d')
        forecast_end_date = forecast_end.strftime('%Y-%m-%d')
        
        cursor.execute("""
            SELECT model_id FROM forecast_models
            WHERE crop_type = %s AND model_version = %s AND method = 'Baseline'
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
            """, (version, crop_type, 'Baseline', training_start, training_end,
                  forecast_start_date, forecast_end_date))
        
        db.commit()
        
        # --------------------------------------------
        # Generate predictions (copy last year's values)
        # --------------------------------------------
        predictions_saved = 0
        predictions = []
        
        # Generate forecast dates
        current = forecast_start
        while current <= forecast_end:
            # Find matching month in reference year
            ref_month = current.month
            matching_ref = reference_data[reference_data.index.month == ref_month]
            
            if len(matching_ref) > 0:
                month_str = current.strftime('%Y-%m')
                predicted = round(matching_ref['total_quantity'].values[0], 2)
                
                # Baseline doesn't have confidence intervals, use ±20%
                lower = round(predicted * 0.8, 2)
                upper = round(predicted * 1.2, 2)
                
                cursor.execute("""
                    DELETE FROM yield_predictions
                    WHERE crop_type = %s AND predicted_month = %s 
                    AND model_version = %s AND method = 'Baseline'
                """, (crop_type, month_str, version))
                
                cursor.execute("""
                    INSERT INTO yield_predictions
                    (crop_type, predicted_month, predicted_quantity, confidence_lower, 
                     confidence_upper, model_version, method)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                """, (crop_type, month_str, predicted, lower, upper, version, 'Baseline'))
                
                predictions.append({
                    'month_date': current,
                    'predicted': predicted
                })
                predictions_saved += 1
            
            current += relativedelta(months=1)
        
        db.commit()
        print(f"   ✓ Saved {predictions_saved} baseline predictions")
        
        # --------------------------------------------
        # EVALUATE: Only if actual data exists
        # --------------------------------------------
        if not is_future:
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
            
            # Create predictions dataframe
            pred_df = pd.DataFrame(predictions)
            
            # Merge with actuals
            comparison = pd.merge(
                actual_data[['month_date', 'total_quantity']],
                pred_df[['month_date', 'predicted']],
                on='month_date',
                how='inner'
            )
            
            if len(comparison) == 0:
                print(f"   ⚠️  Could not match predictions with actual data\n")
                continue
            
            # Calculate metrics
            actual_values = comparison['total_quantity'].values
            predicted_values = comparison['predicted'].values
            
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
                pred = row['predicted']
                error = abs(actual - pred)
                error_pct = (error / actual * 100) if actual > 0 else 0
                print(f"      {month}: Actual={actual:.2f}, Predicted={pred:.2f}, Error={error:.2f} ({error_pct:.1f}%)")
            
            # Save evaluation metrics
            forecast_year = window['forecast_year']
            
            cursor.execute("""
                DELETE FROM model_evaluation
                WHERE crop_type = %s AND model_version = %s AND method = 'Baseline'
            """, (crop_type, version))
            
            cursor.execute("""
                INSERT INTO model_evaluation
                (model_version, crop_type, method, forecast_year, mape, rmse, mae, data_points_compared)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (version, crop_type, 'Baseline', forecast_year, mape, rmse, mae, len(comparison)))
            
            db.commit()
            print(f"   ✓ Evaluation metrics saved\n")
        else:
            print(f"   ⚡ Future forecast saved - will evaluate when actual data arrives\n")
    
    # --------------------------------------------
    # Summary
    # --------------------------------------------
    print(f"{'='*70}")
    print(f"✅ BASELINE DYNAMIC FORECAST COMPLETE FOR {crop_type.upper()}")
    print(f"{'='*70}\n")
    
    cursor.execute("""
        SELECT model_version, forecast_year, mape, rmse, mae, data_points_compared
        FROM model_evaluation
        WHERE crop_type = %s AND method = 'Baseline'
        ORDER BY model_version
    """, (crop_type,))
    
    eval_results = cursor.fetchall()
    
    if eval_results:
        print("📊 BASELINE PERFORMANCE SUMMARY:")
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
        print("Usage: python model_baseline.py <crop_type>")
        print("Example: python model_baseline.py buko")
        exit()
    
    crop = sys.argv[1]
    generate_dynamic_forecast(crop)