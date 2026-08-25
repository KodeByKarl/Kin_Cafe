# Kin Café Forecasting Microservice

Python HTTP service that provides **ARIMA(1,1,1)** sales forecasting and **SARIMA(1,1,1)(1,1,1,7)** per-item demand forecasting for the Kin Café Management System.

The PHP application calls this service locally. If the service is unavailable or data is insufficient, PHP falls back to the existing moving-average logic.

## Requirements

- Python 3.10 or newer
- pip

## Install

From this folder:

```bat
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
```

## Start the service

### Option A: Batch script (recommended on Windows/XAMPP)

From the project root:

```bat
start_forecast_service.bat
```

### Option B: Manual start

```bat
cd python_forecast_service
venv\Scripts\activate
python app.py
```

The service listens on **http://127.0.0.1:5000** by default. This port does not conflict with XAMPP Apache (80/443) or MySQL (3306).

To use a different port:

```bat
python app.py 5001
```

Then set the PHP environment variable `KIN_CAFE_FORECAST_SERVICE_URL=http://127.0.0.1:5001`.

## Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/health` | Service status check |
| POST | `/forecast/sales` | 7-day sales forecast from daily totals |
| POST | `/forecast/demand` | Per-item demand forecast from daily quantities |

### Sales request example

```json
[
  {"date": "2025-05-01", "amount": 1250.50},
  {"date": "2025-05-02", "amount": 980.00}
]
```

### Demand request example

```json
{
  "items": [
    {
      "item_id": 3,
      "name": "Iced Latte",
      "daily_series": [
        {"date": "2025-05-01", "quantity": 12},
        {"date": "2025-05-02", "quantity": 9}
      ],
      "hourly_distribution": {"8": 4, "9": 10, "14": 7}
    }
  ]
}
```

## Model evidence

When a model fits successfully, **statsmodels summary output** (AIC, coefficients, etc.) is printed to the terminal. Screenshot this console output during defense to show a real ARIMA/SARIMA model is running.

## Fallback behavior

If fewer than **14 days** of history are available, or fitting fails to converge, the service returns:

```json
{
  "insufficient_data": true,
  "fallback_method": "moving_average"
}
```

PHP then uses its existing moving-average calculation and labels the result accordingly.
