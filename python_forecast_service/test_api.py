import json
import urllib.request
from datetime import date, timedelta

BASE = "http://127.0.0.1:5000"


def post_json(path, payload, timeout=60):
    body = json.dumps(payload).encode("utf-8")
    request = urllib.request.Request(
        f"{BASE}{path}",
        data=body,
        headers={"Content-Type": "application/json", "Accept": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return response.getcode(), json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as error:
        return error.code, json.loads(error.read().decode("utf-8"))


start = date.today() - timedelta(days=89)
sales = []
for i in range(90):
    day = start + timedelta(days=i)
    weekday_boost = 250 if day.weekday() in (4, 5) else 0
    amount = 900 + (i * 2.5) + weekday_boost + ((i % 7) * 15)
    sales.append({"date": day.isoformat(), "amount": round(amount, 2)})

print("=== POST /forecast/sales ===")
status, sales_result = post_json("/forecast/sales", sales)
print("Status:", status)
print(json.dumps(sales_result, indent=2))

items = []
for item_id in (1, 2, 3):
    daily_series = []
    for i in range(90):
        day = start + timedelta(days=i)
        quantity = max(0, 5 + item_id + (day.weekday() * 0.8) + ((i + item_id) % 4))
        daily_series.append({"date": day.isoformat(), "quantity": round(quantity, 2)})
    items.append(
        {
            "item_id": item_id,
            "name": f"Menu Item {item_id}",
            "daily_series": daily_series,
            "hourly_distribution": {"8": 4, "9": 12, "12": 18, "15": 9},
        }
    )

print("\n=== POST /forecast/demand ===")
status, demand_result = post_json("/forecast/demand", {"items": items})
print("Status:", status)
print(json.dumps(demand_result, indent=2))

print("\n=== Insufficient data test ===")
status, short_result = post_json("/forecast/sales", sales[:10])
print("Status:", status)
print(json.dumps(short_result, indent=2))
