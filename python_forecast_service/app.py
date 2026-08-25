"""
Kin Café Forecasting Microservice
Provides ARIMA(1,1,1) sales forecasting and SARIMA demand forecasting via HTTP API.
"""

from __future__ import annotations

import sys
import traceback
import warnings
from datetime import datetime
from typing import Any

import numpy as np
import pandas as pd
from flask import Flask, jsonify, request
from statsmodels.tsa.arima.model import ARIMA
from statsmodels.tsa.statespace.sarimax import SARIMAX

warnings.filterwarnings("ignore")

app = Flask(__name__)

MIN_STABLE_DAYS = 14
MIN_PREFERRED_DAYS = 90
FORECAST_STEPS = 7
WEEKDAY_NAMES = [
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
    "Sunday",
]


def log_model_summary(label: str, fitted_model: Any) -> None:
    """Print statsmodels summary to console for defense evidence."""
    print("\n" + "=" * 72, flush=True)
    print(f"[{datetime.now().isoformat(timespec='seconds')}] {label}", flush=True)
    print("=" * 72, flush=True)
    try:
        print(fitted_model.summary(), flush=True)
    except Exception as exc:
        print(f"Could not print model summary: {exc}", flush=True)
    print("=" * 72 + "\n", flush=True)


def validate_sales_payload(payload: Any) -> tuple[list[dict[str, Any]] | None, str | None]:
    if not isinstance(payload, list):
        return None, "Expected a JSON array of daily sales records."

    if not payload:
        return None, "Expected a non-empty JSON array of daily sales records."

    cleaned: list[dict[str, Any]] = []
    for index, row in enumerate(payload):
        if not isinstance(row, dict):
            return None, f"Record at index {index} must be an object."
        if "date" not in row or "amount" not in row:
            return None, f"Record at index {index} must include 'date' and 'amount'."
        try:
            amount = float(row["amount"])
        except (TypeError, ValueError):
            return None, f"Record at index {index} has an invalid 'amount'."
        if amount < 0:
            return None, f"Record at index {index} has a negative 'amount'."
        cleaned.append({"date": str(row["date"]), "amount": amount})

    return cleaned, None


def validate_demand_payload(payload: Any) -> tuple[dict[str, Any] | None, str | None]:
    if not isinstance(payload, dict):
        return None, "Expected a JSON object with an 'items' array."

    items = payload.get("items")
    if not isinstance(items, list) or not items:
        return None, "Expected a non-empty 'items' array."

    cleaned_items: list[dict[str, Any]] = []
    for index, item in enumerate(items):
        if not isinstance(item, dict):
            return None, f"Item at index {index} must be an object."
        if "item_id" not in item:
            return None, f"Item at index {index} must include 'item_id'."

        daily_series = item.get("daily_series", [])
        if not isinstance(daily_series, list):
            return None, f"Item at index {index} must include a 'daily_series' array."

        cleaned_daily: list[dict[str, Any]] = []
        for day_index, day_row in enumerate(daily_series):
            if not isinstance(day_row, dict):
                return None, f"Item {index} daily_series[{day_index}] must be an object."
            if "date" not in day_row or "quantity" not in day_row:
                return None, (
                    f"Item {index} daily_series[{day_index}] must include 'date' and 'quantity'."
                )
            try:
                quantity = float(day_row["quantity"])
            except (TypeError, ValueError):
                return None, f"Item {index} daily_series[{day_index}] has an invalid 'quantity'."
            if quantity < 0:
                return None, f"Item {index} daily_series[{day_index}] has a negative 'quantity'."
            cleaned_daily.append({"date": str(day_row["date"]), "quantity": quantity})

        hourly_distribution = item.get("hourly_distribution", {})
        if hourly_distribution is None:
            hourly_distribution = {}
        if not isinstance(hourly_distribution, dict):
            return None, f"Item at index {index} has an invalid 'hourly_distribution'."

        cleaned_hourly: dict[str, float] = {}
        for hour_key, count in hourly_distribution.items():
            try:
                cleaned_hourly[str(hour_key)] = max(0.0, float(count))
            except (TypeError, ValueError):
                return None, f"Item at index {index} has invalid hourly_distribution values."

        cleaned_items.append(
            {
                "item_id": item["item_id"],
                "name": str(item.get("name", "")),
                "daily_series": cleaned_daily,
                "hourly_distribution": cleaned_hourly,
            }
        )

    return {"items": cleaned_items}, None


def build_daily_series(rows: list[dict[str, Any]], value_key: str) -> pd.Series:
    frame = pd.DataFrame(rows)
    frame["date"] = pd.to_datetime(frame["date"])
    frame = frame.sort_values("date")
    frame = frame.groupby("date", as_index=True)[value_key].sum()
    full_index = pd.date_range(frame.index.min(), frame.index.max(), freq="D")
    series = frame.reindex(full_index, fill_value=0.0).astype(float)
    series.index.freq = "D"
    return series


def peak_day_from_forecast(series: pd.Series, forecast_values: np.ndarray) -> str:
    if len(forecast_values) == 0:
        return "Unknown"

    last_date = series.index[-1]
    weekday_totals: dict[int, float] = {}
    weekday_counts: dict[int, int] = {}

    for offset, value in enumerate(forecast_values, start=1):
        weekday = (last_date + pd.Timedelta(days=offset)).weekday()
        weekday_totals[weekday] = weekday_totals.get(weekday, 0.0) + float(value)
        weekday_counts[weekday] = weekday_counts.get(weekday, 0) + 1

    weekday_averages = {
        weekday: weekday_totals[weekday] / weekday_counts[weekday]
        for weekday in weekday_totals
    }
    best_weekday = max(weekday_averages, key=weekday_averages.get)
    return WEEKDAY_NAMES[best_weekday]


def peak_hour_from_distribution(hourly_distribution: dict[str, float]) -> int | None:
    if not hourly_distribution:
        return None
    best_hour = max(hourly_distribution, key=lambda key: hourly_distribution[key])
    try:
        return int(best_hour)
    except (TypeError, ValueError):
        return None


def fit_arima(series: pd.Series, label: str) -> tuple[Any | None, str | None]:
    try:
        model = ARIMA(series, order=(1, 1, 1))
        fitted = model.fit()
        log_model_summary(label, fitted)
        return fitted, None
    except Exception as exc:
        print(f"[ARIMA] Fit failed for {label}: {exc}", flush=True)
        traceback.print_exc()
        return None, str(exc)


def fit_sarima(series: pd.Series, label: str) -> tuple[Any | None, str | None]:
    try:
        model = SARIMAX(
            series,
            order=(1, 1, 1),
            seasonal_order=(1, 1, 1, 7),
            enforce_stationarity=False,
            enforce_invertibility=False,
        )
        fitted = model.fit(disp=False, maxiter=200)
        log_model_summary(label, fitted)
        return fitted, None
    except Exception as exc:
        print(f"[SARIMA] Fit failed for {label}: {exc}", flush=True)
        traceback.print_exc()
        return None, str(exc)


def forecast_from_fitted(fitted_model: Any, steps: int = FORECAST_STEPS) -> dict[str, Any]:
    forecast_values = fitted_model.forecast(steps=steps)
    forecast_array = np.asarray(forecast_values, dtype=float)
    forecast_array = np.maximum(forecast_array, 0.0)

    confidence_interval: dict[str, list[float]] = {"lower": [], "upper": []}
    try:
        prediction = fitted_model.get_forecast(steps=steps)
        conf_int = prediction.conf_int(alpha=0.05)
        confidence_interval = {
            "lower": [round(max(0.0, float(value)), 2) for value in conf_int.iloc[:, 0]],
            "upper": [round(max(0.0, float(value)), 2) for value in conf_int.iloc[:, 1]],
        }
    except Exception as exc:
        print(f"[Forecast] Confidence interval unavailable: {exc}", flush=True)

    return {
        "forecast_values": [round(float(value), 2) for value in forecast_array],
        "confidence_interval": confidence_interval,
    }


def insufficient_response(context: str, extra: dict[str, Any] | None = None) -> dict[str, Any]:
    payload = {
        "insufficient_data": True,
        "fallback_method": "moving_average",
        "model_used": "moving_average",
        "message": context,
    }
    if extra:
        payload.update(extra)
    return payload


@app.get("/")
def index() -> Any:
    return (
        "<div style='font-family:sans-serif;padding:40px;max-width:600px;margin:0 auto;line-height:1.6;color:#35251d;'>"
        "<h2 style='color:#5b3f30;'>Kin Café Forecasting Microservice</h2>"
        "<p>This backend Flask service powers the ARIMA(1,1,1) and SARIMA forecasting features for Kin Café.</p>"
        "<ul style='padding-left:20px;'>"
        "<li style='margin-bottom:8px;'><strong>Main Web App:</strong> <a href='http://localhost/Kin_Cafe/'>http://localhost/Kin_Cafe/</a></li>"
        "<li style='margin-bottom:8px;'><strong>Service Health:</strong> <a href='/health'>/health</a></li>"
        "<li style='margin-bottom:8px;'><strong>Sales Forecast Endpoint:</strong> <code>POST /forecast/sales</code></li>"
        "<li style='margin-bottom:8px;'><strong>Demand Forecast Endpoint:</strong> <code>POST /forecast/demand</code></li>"
        "</ul>"
        "<p style='color:#8e684d;font-size:0.9rem;margin-top:20px;'>Status: <strong style='color:#1f6b45;'>Online &amp; Ready</strong></p>"
        "</div>"
    )


@app.get("/health")
def health() -> Any:
    return jsonify(
        {
            "status": "ok",
            "service": "kin-cafe-forecast",
            "models": {
                "sales": "ARIMA(1,1,1)",
                "demand": "SARIMA(1,1,1)(1,1,1,7)",
            },
            "min_stable_days": MIN_STABLE_DAYS,
        }
    )


@app.post("/forecast/sales")
def forecast_sales() -> Any:
    payload = request.get_json(silent=True)
    rows, error = validate_sales_payload(payload)
    if error:
        return jsonify({"error": error}), 400

    assert rows is not None
    non_zero_days = sum(1 for row in rows if float(row["amount"]) > 0)
    if len(rows) < MIN_STABLE_DAYS or non_zero_days < MIN_STABLE_DAYS:
        return jsonify(
            insufficient_response(
                f"Need at least {MIN_STABLE_DAYS} days with sales history for a stable ARIMA fit.",
                {"days_received": len(rows), "non_zero_days": non_zero_days},
            )
        )

    series = build_daily_series(rows, "amount")
    if len(series) < MIN_STABLE_DAYS:
        return jsonify(
            insufficient_response(
                "Expanded daily series is too short for ARIMA.",
                {"days_in_series": len(series)},
            )
        )

    fitted, fit_error = fit_arima(series, "Sales ARIMA(1,1,1)")
    if fitted is None:
        return jsonify(
            insufficient_response(
                "ARIMA(1,1,1) failed to converge.",
                {"fit_error": fit_error},
            )
        )

    forecast_payload = forecast_from_fitted(fitted, FORECAST_STEPS)
    forecast_values = forecast_payload["forecast_values"]
    forecast_total = round(float(np.sum(forecast_values)), 2)
    peak_day = peak_day_from_forecast(series, np.asarray(forecast_values, dtype=float))

    try:
        aic = round(float(fitted.aic), 2)
    except Exception:
        aic = None

    return jsonify(
        {
            "insufficient_data": False,
            "model_used": "ARIMA(1,1,1)",
            "forecast_7day": forecast_values,
            "forecast_7day_total": forecast_total,
            "peak_day": peak_day,
            "confidence_interval": forecast_payload["confidence_interval"],
            "days_used": len(series),
            "aic": aic,
        }
    )


@app.post("/forecast/demand")
def forecast_demand() -> Any:
    payload = request.get_json(silent=True)
    cleaned_payload, error = validate_demand_payload(payload)
    if error:
        return jsonify({"error": error}), 400

    assert cleaned_payload is not None
    item_results: list[dict[str, Any]] = []
    models_used: set[str] = set()

    for item in cleaned_payload["items"]:
        item_id = item["item_id"]
        item_name = item.get("name") or f"Item {item_id}"
        daily_rows = item["daily_series"]
        hourly_distribution = item["hourly_distribution"]
        peak_hour = peak_hour_from_distribution(hourly_distribution)

        base_result = {
            "item_id": item_id,
            "name": item_name,
            "peak_service_window": peak_hour,
        }

        if len(daily_rows) < MIN_STABLE_DAYS:
            item_results.append(
                {
                    **base_result,
                    **insufficient_response(
                        f"Item {item_id} has fewer than {MIN_STABLE_DAYS} daily records."
                    ),
                    "days_received": len(daily_rows),
                }
            )
            continue

        series = build_daily_series(daily_rows, "quantity")
        if len(series) < MIN_STABLE_DAYS:
            item_results.append(
                {
                    **base_result,
                    **insufficient_response(
                        f"Item {item_id} expanded series is too short for SARIMA."
                    ),
                    "days_in_series": len(series),
                }
            )
            continue

        fitted, _ = fit_sarima(series, f"Demand SARIMA item {item_id} ({item_name})")
        model_used = "SARIMA(1,1,1)(1,1,1,7)"
        if fitted is None:
            fitted, _ = fit_arima(series, f"Demand ARIMA fallback item {item_id} ({item_name})")
            model_used = "ARIMA(1,1,1) fallback"
            if fitted is None:
                item_results.append(
                    {
                        **base_result,
                        **insufficient_response(
                            f"SARIMA and ARIMA both failed for item {item_id}."
                        ),
                    }
                )
                continue

        models_used.add(model_used)
        forecast_payload = forecast_from_fitted(fitted, FORECAST_STEPS)
        forecast_values = forecast_payload["forecast_values"]
        next_day = forecast_values[0] if forecast_values else 0.0

        item_results.append(
            {
                **base_result,
                "insufficient_data": False,
                "model_used": model_used,
                "next_day_forecast": next_day,
                "7day_forecast": forecast_values,
                "7day_forecast_total": round(float(np.sum(forecast_values)), 2),
                "confidence_interval": forecast_payload["confidence_interval"],
                "days_used": len(series),
            }
        )

    overall_model = "mixed"
    if len(models_used) == 1:
        overall_model = next(iter(models_used))
    elif not models_used:
        overall_model = "moving_average"

    return jsonify(
        {
            "items": item_results,
            "model_used": overall_model,
            "items_forecasted": sum(1 for row in item_results if not row.get("insufficient_data")),
            "items_fallback": sum(1 for row in item_results if row.get("insufficient_data")),
        }
    )


if __name__ == "__main__":
    port = 5000
    if len(sys.argv) > 1:
        try:
            port = int(sys.argv[1])
        except ValueError:
            print(f"Invalid port '{sys.argv[1]}', using default 5000.", flush=True)

    print(f"Kin Café Forecast Service starting on http://127.0.0.1:{port}", flush=True)
    print("Endpoints: GET /health, POST /forecast/sales, POST /forecast/demand", flush=True)
    app.run(host="127.0.0.1", port=port, debug=False, threaded=True)
