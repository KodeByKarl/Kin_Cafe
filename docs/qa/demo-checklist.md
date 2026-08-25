# Defense Demo Checklist — Kin Café Management System

Use this checklist on demo/defense day. Run automated tests first, then complete the browser walkthrough.

---

## Before You Start (5 minutes)

```bat
REM 0. Seed demo data (first run or reset)
c:\xampp\php\php.exe tools\seed_demo_data.php

REM 1. Start XAMPP (Apache + MySQL)
REM 2. Start forecast microservice
cd c:\xampp\htdocs\Kin_Cafe
start_forecast_service.bat

REM 3. Run automated verification
c:\xampp\php\php.exe tools\verify_system.php
tools\run_all_tests.bat
```

- [ ] Forecast health check returns `"status":"ok"` at http://127.0.0.1:5000/health
- [ ] `verify_system.php` shows 14/14 PASS
- [ ] Database has **≥14 days** of completed orders (otherwise UI shows Moving Average fallback)

---

## Browser Walkthrough

### Authentication & Dashboard
- [ ] Login as staff (`index.php`)
- [ ] Dashboard KPIs load (orders, sales, net sales, active users)
- [ ] Change sales chart range to **14 days** and **30 days**
- [ ] Active Users trend badge shows computed % (not hardcoded)

### POS & Service
- [ ] Open **POS** → add menu item to cart
- [ ] **Recommended Add-ons** panel updates
- [ ] Open **Virtual Assistant** slide-out → ask a prompt → receive answer
- [ ] Complete a cash order (see [payment-policy.md](../payment-policy.md))

### Analytics & AI
- [ ] **Analytics** → KPI trend badges update on refresh
- [ ] Switch tabs: Overview / Sales / Demand / Preferences / Anomaly
- [ ] Open **Advanced Analytics** from Analytics hero button
- [ ] **AI Suite** → open Sales Forecasting, Demand Prediction, Recommendation System
- [ ] Sales Forecasting → **Ingredient Stock-Up Guidance** section visible
- [ ] Customer Preferences → profiled repeat customers + repeat buyer counts on trends

### Inventory
- [ ] **Inventory** tabs: Stock / Optimization / Reordering / Waste
- [ ] Deep link works: `inventory.php?tab=reordering&filter=low`
- [ ] AI Suite → Inventory Health Snapshot shows score

### Notifications
- [ ] **User Settings → Notifications** → low-stock link opens filtered inventory
- [ ] Expiring-stock link opens waste tab with expiring filter

### Orders
- [ ] **Orders History** → open order detail → filter transaction logs by event type

---

## Statistical Model Labels (if ≥14 days data)

- [ ] Dashboard / Analytics show **ARIMA(1,1,1)** for sales forecast
- [ ] Demand Prediction shows **SARIMA** per-item labels
- [ ] Method labels visible on heuristic modules (rule-based vs statistical)

---

## Evidence to Capture

| Item | How |
|------|-----|
| ISO 25010 matrix | Screenshot or PDF of [iso25010-matrix.md](./iso25010-matrix.md) |
| Test output | Save `run_all_tests.bat` terminal log |
| Benchmark | Save [performance-benchmark.md](./performance-benchmark.md) output |
| ARIMA console | Flask terminal showing model summary / AIC |
| POS recommendations | Screenshot with cart + add-ons panel |

---

## If Something Fails

| Symptom | Fix |
|---------|-----|
| Moving Average instead of ARIMA | Start forecast service + add ≥14 days order history |
| 0 recommendations / 0 profiles | Process more orders with repeat customers |
| Forecast service offline | Run `start_forecast_service.bat`, kill stale port 5000 processes |
| Empty stock-up guidance | Add menu recipes + ingredient usage history |

---

## Sign-off

| Role | Name | Date |
|------|------|------|
| Presenter | | |
| Adviser | | |
