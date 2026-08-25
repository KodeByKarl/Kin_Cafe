# ISO/IEC 25010 Evaluation Matrix — Kin Café Management System

**Project:** Kin Café Management System  
**Standard:** ISO/IEC 25010 (Product Quality Model)  
**Purpose:** Objective 3 evidence — map quality characteristics to verifiable tests and system features  
**Last updated:** August 23, 2026 (verification run completed)

**Verification runner:** `c:\xampp\php\php.exe tools\verify_system.php`

**Full report:** [verification-report.md](./verification-report.md)

---

## How to Use This Matrix

1. Run each listed test script or manual check.
2. Record **Pass / Fail / Partial** in the Result column during verification.
3. Attach screenshots or console output for capstone defense evidence.

**Test runner (PHP CLI from project root):**

```bat
c:\xampp\php\php.exe tools\verify_system.php
c:\xampp\php\php.exe includes\test_inventory.php
c:\xampp\php\php.exe includes\test_menu_workflow.php
c:\xampp\php\php.exe includes\test_orders_history.php
c:\xampp\php\php.exe includes\benchmark_analytics.php
c:\xampp\php\php.exe python_forecast_service\test_forecast_integration.php
```

---

## Quality Characteristic Mapping

| ISO 25010 Characteristic | Sub-characteristic | System Evidence | Test / Verification Method | Expected Result | Result |
|--------------------------|-------------------|-----------------|----------------------------|-----------------|--------|
| **Functional Suitability** | Functional completeness | POS, Dashboard, Analytics, Inventory, AI modules | Manual walkthrough: Login → Dashboard → POS → Analytics → Inventory → AI Suite | All documented modules accessible and wired | **Pass** |
| **Functional Suitability** | Functional correctness | Order processing, stock deduction, refunds | `includes/test_orders_history.php`; complete POS order with ingredient recipe | Order saved; stock adjusted; history retrievable | **Pass** (automated); POS manual pending |
| **Functional Suitability** | Functional appropriateness | ARIMA sales forecast, SARIMA demand, POS recommendations | Start `start_forecast_service.bat`; open `ai_sales_forecasting.php`, `ai_demand_prediction.php`, `pos.php` | Statistical models labeled when service + data available; POS shows recommendations | **Partial** — service OK; DB has 0 history days → MA fallback |
| **Performance Efficiency** | Time behaviour | Analytics queries | `includes/benchmark_analytics.php` | KPI/series queries complete in acceptable time on local XAMPP | **Pass** (all &lt; 1 ms) |
| **Performance Efficiency** | Resource utilization | Python forecast microservice | `python_forecast_service/test_api.py` or integration script | Service responds on port 5000 without excessive memory use | **Pass** |
| **Compatibility** | Co-existence | PHP + MySQL + Python sidecar | Run XAMPP + forecast service concurrently | Both stacks operate without port conflict | **Pass** |
| **Compatibility** | Interoperability | PHP ↔ Python forecast API | `python_forecast_service/test_forecast_integration.php` | JSON forecast returned; PHP fallback when offline | **Pass** |
| **Usability** | User interface aesthetics | Modern UI (`assets/css/ui-modern.css`, `pos.css`) | Manual review of Dashboard, POS, Analytics | Consistent layout, readable KPI cards, tab navigation | **Pass** (code review) |
| **Usability** | Operability | Role permissions, CSRF on login | `index.php` login; restricted pages without session | Unauthorized users redirected; inactive accounts blocked | **Pass** (code review) |
| **Usability** | User error protection | POS validation, unavailable item checks | Add unavailable item on POS; incomplete checkout | Warning shown; checkout blocked for invalid cart | **Pass** (code review) |
| **Reliability** | Maturity | Graceful forecast fallback | Stop Python service; reload dashboard/analytics | Moving-average fallback with transparent method label | **Pass** — verified with 0-day DB |
| **Reliability** | Availability | Local deployment on XAMPP | Start Apache/MySQL; browse all main modules | System usable offline on localhost | **Pass** |
| **Reliability** | Fault tolerance | DB transaction rollback on order errors | `includes/test_menu_workflow.php`; invalid category parent | Invalid operations rejected safely | **Pass** |
| **Security** | Confidentiality | Password hashing, session auth | `index.php` uses `password_verify()`; session gate on pages | Plaintext passwords not stored; pages require login | **Pass** (code review) |
| **Security** | Integrity | CSRF token on login | Inspect login POST handling in `index.php` | Token validated before authentication | **Pass** (code review) |
| **Security** | Accountability | Audit logging | `logAuditEvent()` on login, orders, inventory, settings | Login, order, inventory, and admin events recorded | **Pass** |
| **Maintainability** | Modularity | Split AI services | Review `includes/ai_services.php`, `includes/forecast_client.php` | Each AI page calls dedicated getter | **Pass** |
| **Maintainability** | Reusability | Shared helpers in `includes/functions.php` | Code review of forecast/analytics helpers | Common logic centralized | **Pass** |
| **Maintainability** | Analysability | Method labels on AI modules | Open AI Suite and module pages | Statistical vs rule-based methods clearly labeled | **Pass** |
| **Portability** | Adaptability | XAMPP stack, configurable AI endpoint | `user_settings.php` AI provider settings | Runs on Windows/XAMPP; external AI optional | **Pass** |
| **Portability** | Installability | README + `start_forecast_service.bat` | Follow README setup steps | Fresh install can start PHP app and forecast service | **Pass** |

---

## Feature-Specific Evidence (Capstone AI Section)

| Feature | Algorithm / Method | Code Location | Verification |
|---------|-------------------|---------------|--------------|
| Sales Forecasting | ARIMA(1,1,1) via statsmodels | `python_forecast_service/app.py`; `includes/functions.php` | **Pass** — test_api.py AIC 1113.34, total ₱8,450.81 |
| Demand Prediction | SARIMA(1,1,1)(1,1,1,7) | `python_forecast_service/app.py`; `includes/ai_services.php` | **Pass** — 3 items forecasted in test_api.py |
| Recommendation System | SQL order-pair co-occurrence | `includes/ai_services.php`; `pos.php` | **Pass** — panel wired; 0 pairs until order history exists |
| Sales → Stock-Up Link | Forecast uplift + demand → recipes | `aiBuildForecastStockUpGuidance()`; `ai_sales_forecasting.php` | **Pass** — section present; empty until data exists |
| Inventory Optimization | 14-day consumption rate | `includes/ai_services.php` | **Pass** — consumption-based label verified |
| Customer Profiling | Per-customer SQL (2+ orders) | `aiGetCustomerProfiles()` | **Pass** — logic verified; 0 profiles until repeat orders |
| Anomaly Detection | Z-score + IQR | `aiDetectSalesAnomalies()` | **Pass** — function wired; 0 flags with sparse data |

---

## Manual Demo Script (Defense Day)

1. Log in as staff → confirm dashboard KPIs load.
2. Open **POS** → add a menu item → confirm **Recommended Add-ons** panel updates.
3. Start forecast service → open **Sales Forecasting** → confirm ARIMA label and **Ingredient Stock-Up Guidance** section.
4. Open **Demand Prediction** → confirm SARIMA labels (requires ≥14 days data).
5. Open **Customer Preferences** → confirm **Profiled customers** count matches named profiles list.
6. Open **Inventory Optimization** → confirm consumption-based method label.
7. Run PHP test scripts above and save terminal output as annex evidence.

---

## Verification Sign-Off

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Developer | | | |
| Tester | | | |
| Adviser | | | |

---

## Notes for Paper Alignment

- Describe **Inventory Optimization** as *consumption-based analytics* (14-day usage rate), not time-series ML.
- Describe **Recommendation System** as *order-pair co-occurrence analytics* surfaced live on POS.
- Describe **Sales Forecasting → Stock-Up** as an integrated planning workflow linking ARIMA/SARIMA outputs to ingredient replenishment.
- **Profiled customers** refers to repeat customers with ≥2 completed orders in 90 days, not aggregate trend rows.
