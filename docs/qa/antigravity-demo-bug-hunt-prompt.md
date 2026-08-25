# AntiGravity Prompt — Kin Café Demo & Bug Hunt

Copy everything inside the fenced block below and paste it as your **first message** to AntiGravity.

---

```
You are a QA engineer and capstone defense presenter testing the Kin Café Management System on a local Windows/XAMPP machine. Your job is to (1) run a full demo walkthrough as if presenting to panelists, and (2) actively try to break the app and report bugs.

## Project

- **Name:** Kin Café Management System (PHP + MySQL + Python Flask forecast microservice)
- **Workspace:** c:\xampp\htdocs\Kin_Cafe
- **Base URL:** http://localhost/Kin_Cafe/
- **Login:** username `admin` / password `admin123`
- **Stack:** PHP (PDO), MySQL, Bootstrap 4, Chart.js, Flask on port 5000 for ARIMA/SARIMA

## Phase 0 — Environment (do this first)

1. Confirm XAMPP Apache + MySQL are running.
2. In the project folder, start the forecast service:
   - `cd c:\xampp\htdocs\Kin_Cafe`
   - Run `start_forecast_service.bat` (or start Flask on port 5000 manually)
3. Seed demo data if needed:
   - `c:\xampp\php\php.exe tools\seed_demo_data.php`
   - Use `--force` only if you need to reset demo orders (`RCPT-DEMO-*` receipts)
4. Run automated checks and capture output:
   - `c:\xampp\php\php.exe tools\verify_system.php` → expect **14/14 PASS**
   - `tools\run_all_tests.bat` → all PHP regression tests pass
   - Open http://127.0.0.1:5000/health → expect `"status":"ok"` and ARIMA references
5. If forecast service is offline or history has <14 days of completed orders, note that ARIMA/SARIMA labels may fall back to Moving Average — that is expected degraded behavior, not necessarily a bug.

## Phase 1 — Demo Walkthrough (presenter mode)

Walk through every section below in the browser. For each step, note PASS/FAIL and take a screenshot when something looks wrong or impressive (POS recommendations, ARIMA labels, low-stock alerts).

### A. Authentication & Dashboard
- Login at `index.php`
- Open `dashboard.php`
- Confirm KPIs load: orders, sales, net sales, active users
- Change sales chart range to **7 / 14 / 30 days** — chart and labels must update
- Active Users trend badge should show a computed % (not a hardcoded placeholder)

### B. POS & Checkout (cash-only by design)
- Open `pos.php`
- Add **Latte** (or any coffee item) to cart
- Confirm **Recommended Add-ons** panel updates (expect Chaofan or pairing suggestions from demo data)
- Open **Virtual Assistant** slide-out → ask: "What are today's top sellers?" → expect a coherent answer
- Complete a **cash** order: payment ≥ total, verify change due, receipt, order saved
- Try product code lookup: valid `ITM000x` code adds item; invalid code shows explicit error
- Read `docs/payment-policy.md` — card/digital payments are intentionally disabled; do NOT file that as a bug

### C. Analytics & AI Suite
- Open `analytics.php` — KPI trend badges should update on refresh
- Switch in-page tabs: Overview / Sales / Demand / Preferences / Anomaly
- Click **Advanced Analytics** hero button → `advanced_analytics.php` loads
- Open AI hub `ai_insights.php` and visit:
  - `ai_sales_forecasting.php` → **Ingredient Stock-Up Guidance** section visible; method label **ARIMA(1,1,1)** when service + data OK
  - `ai_demand_prediction.php` → per-item **SARIMA** labels when data sufficient
  - `ai_recommendation_system.php` → pairing / co-purchase data
  - `ai_customer_preferences.php` → **Profiled repeat customers** with repeat buyer counts
  - `ai_anomaly_detection.php` → at least context for sales anomalies when seeded
  - `ai_inventory_optimization.php` → consumption-based labels (not time-series ML)
  - `ai_smart_reordering.php` → reorder guidance for low-stock ingredients
  - `ai_waste_reduction.php` → expiring / slow-moving highlights

### D. Inventory
- Open `inventory.php`
- Test tabs: Stock / Optimization / Reordering / Waste
- Deep link: `inventory.php?tab=reordering&filter=low` — should open Reordering with low-stock filter
- Confirm Fresh Milk / Espresso Beans show low-stock from demo seed
- Adjust stock (+/-) and verify log entry appears

### E. Notifications & Settings
- `user_settings.php` → Notifications tab
- Click low-stock notification link → should land on filtered inventory
- Click expiring-stock link → should open waste tab with expiring filter

### F. Orders & Audit
- `orders_history.php` → open a recent order detail
- Filter transaction logs by event type (checkout, inventory, etc.)
- Confirm demo orders use receipt prefix `RCPT-DEMO-*` when seeded

### G. Role-based access (if time permits)
- Create a **cashier** user in user settings
- Log in as cashier: POS yes; inventory/analytics should be restricted
- Log back in as admin

## Phase 2 — Bug Hunt (adversarial mode)

After the demo pass, deliberately stress the system. Try every category below and record exact steps to reproduce.

### Checkout & validation
- Empty cart checkout → must reject
- Cash payment **below** order total → must reject with clear message
- Apply promo code: valid, expired, below minimum order, over max uses
- Add unavailable/deleted menu item (second session if needed) → checkout must reject
- Rapid double-click Pay / duplicate submit → no duplicate orders or double stock deduction
- Very large cart (10+ line items, qty 99) → totals correct, no overflow/display bugs
- Special characters in customer name/phone → sanitized, no SQL/XSS in UI

### POS recommendations & assistant
- Empty cart → recommendations panel sensible (empty state or general picks, not broken)
- Add/remove items rapidly → recommendations stay in sync
- Assistant: nonsense question, empty input, very long prompt → graceful handling, no PHP errors

### Forecast & AI edge cases
- Stop forecast service → pages should degrade to Moving Average fallback, not white-screen
- Restart service → ARIMA/SARIMA labels return when ≥14 days history exists
- Refresh AI pages repeatedly → no stale cache showing wrong method labels
- Compare dashboard forecast number vs sales forecasting page — should be consistent source

### Inventory
- Adjust stock to **zero** or **negative attempt** → negative must be rejected
- Zero-quantity adjustment → rejected
- Complete POS sale → ingredient/menu stock decreases with log entry
- Delete ingredient in use → archive/block behavior, no orphaned UI

### Analytics & charts
- Switch date ranges quickly on dashboard — no Chart.js errors in console
- Analytics auto-refresh (if enabled) — no duplicate listeners or memory leak symptoms
- Empty date range / no orders period — graceful empty states

### Security & session
- Access `dashboard.php`, `pos.php`, `inventory.php` without login → redirect to login
- CSRF: note if state-changing POSTs lack tokens (file as severity-appropriate finding)
- Logout → back button should not expose protected pages without re-auth

### UI/UX regressions
- Mobile-width viewport (~375px) — POS and sidebar usable
- Broken images, missing icons, overlapping modals
- Console JavaScript errors on every main page visited
- PHP notices/warnings visible in HTML (always a bug)

## Phase 3 — Automated test gap analysis

Run any tests not already run and note failures:
- `c:\xampp\php\php.exe includes\test_orders_history.php`
- `c:\xampp\php\php.exe includes\test_menu_workflow.php`
- `c:\xampp\php\php.exe includes\test_inventory.php`
- `c:\xampp\php\php.exe python_forecast_service\test_forecast_integration.php`
- In `python_forecast_service\venv`: `Scripts\python.exe test_api.py`

If you can edit code, do NOT fix bugs silently — report first. Only fix if explicitly asked.

## Deliverable — Bug & Demo Report

Produce a structured report in markdown:

### 1. Environment summary
- PHP/MySQL/Flask status, seed state, verify_system output (14/14 or not)

### 2. Demo checklist scorecard
Table: Section | Step | Result (PASS/FAIL/SKIP) | Notes

### 3. Bugs found
For each bug:
- **ID:** BUG-001, BUG-002, …
- **Severity:** Critical / High / Medium / Low
- **Area:** POS, Analytics, Forecast, Inventory, Auth, etc.
- **Steps to reproduce**
- **Expected vs actual**
- **Evidence:** screenshot path, console error, or HTTP response snippet
- **Suggested fix** (file/function hint if known)

### 4. Not bugs (by design)
List items tested but excluded per policy (e.g. cash-only POS, consumption-based inventory optimization labels).

### 5. Demo readiness verdict
One of: **Ready for defense** / **Ready with minor issues** / **Not ready** — with 2–3 sentence justification.

### 6. Top 3 polish items
Optional quick wins before presentation.

Start with Phase 0 now. Work autonomously through all phases. Be skeptical — assume something is broken until you verify it in the browser and terminal.
```

---

## Quick reference

| Item | Value |
|------|-------|
| Demo checklist | [demo-checklist.md](./demo-checklist.md) |
| QA index | [test-index.md](./test-index.md) |
| Payment policy | [payment-policy.md](../payment-policy.md) |
| ISO 25010 matrix | [iso25010-matrix.md](./iso25010-matrix.md) |
| Seed script | `tools/seed_demo_data.php` |
| Verify script | `tools/verify_system.php` |

## Tips for AntiGravity

- Enable **browser / computer use** if available — this task requires real UI clicks, not code review alone.
- Point AntiGravity at workspace `c:\xampp\htdocs\Kin_Cafe` so it can run `.bat` and PHP scripts.
- If ARIMA labels missing, check Flask on port 5000 before filing forecast bugs.
- Demo data receipts are prefixed `RCPT-DEMO-*` — safe to reset with `seed_demo_data.php --force`.
