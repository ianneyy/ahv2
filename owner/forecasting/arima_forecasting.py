#!/usr/bin/env python3
"""
ARIMA Forecasting Script
Automatically loads monthly CSVs from:
forecasting/data/data_{crop}_monthly.csv
"""

import argparse, json, sys, os
import pandas as pd
import numpy as np
from statsmodels.tsa.arima.model import ARIMA

def load_monthly_series_from_csv(crop):
    base_dir = os.path.dirname(os.path.abspath(__file__))
    data_path = os.path.join(base_dir, "data", f"data_{crop}_monthly.csv")

    try:
        df = pd.read_csv(data_path)

        # ✅ Convert to datetime & sort
        df['date'] = pd.to_datetime(df['date'])
        df = df.sort_values('date')

        # ✅ Remove future dates
        today = pd.Timestamp.today().normalize()
        df = df[df['date'] <= today]

        # ✅ Group by month and sum duplicates
        df = df.groupby(df['date'].dt.to_period('M')).agg({"total_quantity": "sum"})

        # ✅ Convert index back to Timestamp
        df.index = df.index.to_timestamp()

        # ✅ Ensure continuous monthly frequency
        df = df.asfreq('MS', fill_value=0)

        # ✅ Return cleaned series
        return df['total_quantity'].astype(float)

    except Exception as e:
        print(f"ERROR: could not load data for {crop}: {e}", file=sys.stderr)
        sys.exit(2)


def arima_forecast(series, months):
    model = ARIMA(series, order=(1,1,1))
    fitted = model.fit()
    fc = fitted.get_forecast(steps=months)
    return fc.predicted_mean


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--crop', required=True)
    parser.add_argument('--months', type=int, default=12)
    args = parser.parse_args()

    crop = args.crop
    months = args.months

    series = load_monthly_series_from_csv(crop)

    # ✅ Must have 12+ months of history
    if len(series) < 12:
        print(json.dumps({"error": "insufficient_history", "months": len(series)}))
        sys.exit(3)

    try:
        fc_vals = arima_forecast(series, months)
    except Exception as e:
        print(f"ERROR: arima failed: {e}", file=sys.stderr)
        sys.exit(4)

    last_dt = series.index.max()
    forecasts = []
    for i, v in enumerate(fc_vals, start=1):
        dt = (last_dt + pd.DateOffset(months=i)).strftime('%Y-%m-01')
        forecasts.append({"date": dt, "value": float(round(v, 2))})

    out = {"forecasts": forecasts}
    print(json.dumps(out))
    sys.exit(0)


if __name__ == '__main__':
    main()
