"""
Seasonal Naive Baseline Model

Simple baseline: Predicts this month = same month last year
Example: Jan 2024 prediction = Actual Jan 2023

This is the MINIMUM your ML models should beat!

Usage:
python model_baseline.py buko
"""

import pandas as pd
import numpy as np
import mysql.connector
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
def generate_baseline_forecast(crop_type):
    """
    Generate Seasonal Naive baseline forecasts.
    Prediction = Same month last year's actual value
    """
    
    db = mysql.connector.connect(**DB_CONFIG)
    cursor = db.cursor()
    
    print(f"\n{'='*70}")
    print(f"📊 SEASONAL NAIVE BASELINE FOR: {crop_type.upper()}")
    print(f"{'='*70}\n")
    print("Strategy: Predict current month = same month last year\n")
    
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
    print(f"📅 Date range: {all_data.index.min().date()} to {all_data.index.max().date()}\n")
    
    # --------------------------------------------
    # Process each rolling window
    # --------------------------------------------
    for window in ROLLING_WINDOWS:
        version = window['version']
        train_end = window['train_end']
        forecast_year = window['forecast_year']
        
        print(f"{'─'*70}")
        print(f"📈 {version}: Using data up to {train_end} → Forecast {forecast_year}")
        print(f"{'─'*70}")
        
        # For baseline, we only need data from the year before forecast year
        reference_year = forecast_year - 1
        
        # Get reference year data (last year's values)
        reference_data = all_data[
            (all_data.index.year == reference_year)
        ].copy()
        
        if len(reference_data) == 0:
            print(f"⚠️  No reference data for {reference_year}. Skipping.\n")
            continue
        
        print(f"   • Using {reference_year} as reference (baseline)")
        print(f"   • Reference data points: {len(reference_data)}")
        
        # --------------------------------------------
        # Log model version
        # --------------------------------------------
        training_start = reference_data.index.min().strftime('%Y-%m-%d')
        training_end = reference_data.index.max().strftime('%Y-%m-%d')
        forecast_start_date = f"{forecast_year}-01-01"
        forecast_end_date = f"{forecast_year}-12-31"
        
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
        # Generate predictions (just copy last year's values)
        # --------------------------------------------
        predictions_saved = 0
        
        for _, row in reference_data.iterrows():
            # Get month from reference year
            ref_month = row.name.month
            
            # Predict for same month in forecast year
            forecast_date = pd.Timestamp(year=forecast_year, month=ref_month, day=1)
            month_str = forecast_date.strftime('%Y-%m')
            predicted = round(row['total_quantity'], 2)
            
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
            
            predictions_saved += 1
        
        db.commit()
        print(f"   ✓ Saved {predictions_saved} baseline predictions")
        
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
            print(f"   ⚠️  No actual data available for {forecast_year} - cannot evaluate yet\n")
            continue
        
        actual_data['month_date'] = pd.to_datetime(actual_data['month_date'])
        actual_data['total_quantity'] = actual_data['total_quantity'].astype(float)
        
        # Create predictions dataframe
        predictions = []
        for _, row in reference_data.iterrows():
            ref_month = row.name.month
            forecast_date = pd.Timestamp(year=forecast_year, month=ref_month, day=1)
            predictions.append({
                'month_date': forecast_date,
                'predicted': row['total_quantity']
            })
        
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
    
    # --------------------------------------------
    # Summary
    # --------------------------------------------
    print(f"{'='*70}")
    print(f"✅ BASELINE FORECAST COMPLETE FOR {crop_type.upper()}")
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
        print(f"{'Version':<10} {'Year':<8} {'MAPE':<10} {'RMSE':<12} {'MAE':<12} {'Months'}")
        print("─" * 70)
        for row in eval_results:
            print(f"{row[0]:<10} {row[1]:<8} {row[2]}%{' ':<6} {row[3]:<12} {row[4]:<12} {row[5]}")
    
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
    generate_baseline_forecast(crop)